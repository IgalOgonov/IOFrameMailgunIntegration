<?php
if(!defined('IOFrameManagersMailManager'))
    require __DIR__.'/../../../../../IOFrame/Managers/MailManager.php';
if(!defined('IOFrameManagersIntegrationsEmailMailgun'))
    require __DIR__.'/../../../../../IOFrame/Managers/Integrations/Email/Mailgun.php';
if(!defined('IOFrameUtilCLICommonMailQueueFunctions'))
    require __DIR__.'/../../../../../IOFrame/Util/CLI/CommonMailQueueFunctions.php';

/* All errors and results similar to send_mail_default_queue.php $_handleQueue function. */
$_handleQueue = function(&$parameters,&$errors,&$opt){

    $result = ['exit'=>true,'result'=>false];

    //Main difference - we are not using the default SMTP email provider, so it will not fail even if no mail settings defined
    \IOFrame\Util\CLI\CommonMailQueueFunctions::setEmailDefaults($parameters,['provider'=>'mailgun','batchSize'=>6000]);

    if(!\IOFrame\Util\CLI\CommonMailQueueFunctions::tryToCreateMailManager($parameters, $errors, $opt))
        return $result;

    $remainingTime = \IOFrame\Util\CLI\CommonJobRuntimeFunctions::getRemainingRuntime($parameters,$opt) - $parameters['email']['runtimeSafetyMargin'];
    if($remainingTime <= 0)
        return $result;

    $taskResult = $opt['concurrencyHandler']->listenToQueues(
        $parameters['_queue']['listenTo'],
        $parameters['email']['returnToQueue'],
        ['timeout'=>$remainingTime,'queuePrefix'=>$parameters['_queue']['prefix']]
    );

    //Replacing this one function is as close as we can get to DRY, even if it's still similar in some ways
    return \IOFrame\Util\CLI\CommonMailQueueFunctions::handleMailTaskResult($parameters, $errors, $opt, $taskResult,[
        'success'=> function(array &$parameters, array &$errors, array &$opt, array &$taskResult){

            $test = $taskResult['task']['test'] ?? false;
            $silent = $parameters['silent'] || ($taskResult['task']['silent'] ?? false);
            $verbose = !$silent && ($parameters['verbose'] ?? $test);

            $Mailgun = new \IOFrame\Managers\Integrations\Email\Mailgun($parameters['defaultParams']['localSettings'],$parameters['email']['MailManager']);

            if(!\IOFrame\Util\CLI\CommonMailQueueFunctions::parseEmailTask($parameters,$errors,$opt,$taskResult, [], $verbose)){
                return ['exit'=>false,'result'=>false];
            }

            try{
                if(!empty($taskResult['task']['data']['body'])){
                    $sent = $Mailgun->sendMail(
                        $taskResult['task']['data']['to'],
                        $taskResult['task']['data']['subject'],
                        $taskResult['task']['data']['body'],
                        [
                            'from'=> $taskResult['task']['data']['from']??null,
                            'text'=> $taskResult['task']['data']['text']??null,
                            'attachment'=> $taskResult['task']['data']['attachment']??null,
                            'cc'=> $taskResult['task']['data']['cc']??null,
                            'bcc'=> $taskResult['task']['data']['bcc']??null,
                            'replies'=> $taskResult['task']['data']['replies']??null,
                            'recipient-variables'=>$taskResult['task']['data']['tag']??null,
                        ],
                        [
                            'test'=>$test,
                            'verbose'=>$verbose,
                            'tracking'=>$taskResult['task']['data']['tracking']??null,
                            'skip-verification'=>$taskResult['task']['data']['skip-verification']??null,
                            'require-tls'=>$taskResult['task']['data']['require-tls']??null,
                            'deliverytime'=>$taskResult['task']['data']['deliverytime']??null,
                            'tag'=>$taskResult['task']['data']['tag']??null,
                        ]
                    );
                }
                else{
                    $sent = $Mailgun->sendMailTemplate(
                        $taskResult['task']['data']['to'],
                        $taskResult['task']['data']['subject'],
                        $taskResult['task']['data']['template'],
                        [
                            'from'=> $taskResult['task']['data']['from']??null,
                            'text'=> $taskResult['task']['data']['text']??null,
                            'attachment'=> $taskResult['task']['data']['attachment']??null,
                            'inline'=> $taskResult['task']['data']['inline']??null,
                            'cc'=> $taskResult['task']['data']['cc']??null,
                            'bcc'=> $taskResult['task']['data']['bcc']??null,
                            'replies'=> $taskResult['task']['data']['replies']??null,
                            'recipient-variables'=>$taskResult['task']['data']['recipient-variables']??null,
                            'varArray'=> $taskResult['task']['data']['varArray']??[],
                        ],
                        [
                            'test'=>$test,
                            'verbose'=>$verbose,
                            'tracking'=>$taskResult['task']['data']['tracking']??null,
                            'skip-verification'=>$taskResult['task']['data']['skip-verification']??null,
                            'require-tls'=>$taskResult['task']['data']['require-tls']??null,
                            'deliverytime'=>$taskResult['task']['data']['deliverytime']??null,
                            'tag'=>$taskResult['task']['data']['tag']??null,
                        ]
                    );
                }

                if(!$sent){
                    throw new \Exception('Mailgun message '.(empty($taskResult['task']['data']['body'])?'from template '.$taskResult['task']['data']['template']:'without template').' not sent');
                }

                $sendResult = \IOFrame\Util\CLI\CommonMailQueueFunctions::handleDefaultMailQueues($parameters, $opt, $taskResult, 'success', $verbose);
                $outcomeDetails = ['id'=>$taskResult['task']['id']??'-', 'queue'=>$taskResult['queue'], 'outcome'=>'success', 'time'=>time(),'nextQueues'=>$sendResult??null];
                if($verbose)
                    echo json_encode($outcomeDetails,JSON_PRETTY_PRINT).EOL;
                \IOFrame\Util\PureUtilFunctions::createPathInObject($parameters, ['_queue','_results'], $outcomeDetails,true);
                //TODO Log success
                return ['exit'=>($parameters['email']['batchSize']-- <= 0),'result'=>false];
            }
            catch (\Exception $e){
                $sendResult = \IOFrame\Util\CLI\CommonMailQueueFunctions::handleDefaultMailQueues($parameters, $opt, $taskResult, 'failure', $verbose);

                $outcomeDetails = ['id'=>$taskResult['task']['id']??'-', 'queue'=>$taskResult['queue'], 'exception'=>$e->getMessage(),'nextQueues'=>$sendResult];
                if($verbose)
                    echo json_encode($outcomeDetails,JSON_PRETTY_PRINT).EOL;
                \IOFrame\Util\PureUtilFunctions::createPathInObject($errors, ['mail-queue-failed-to-send',$opt['id']], $outcomeDetails, true);
                //TODO Log failure
                return ['exit'=>false,'result'=>false];
            }
        }
    ]);
};