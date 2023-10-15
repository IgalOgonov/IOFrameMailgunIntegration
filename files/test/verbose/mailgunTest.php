<?php


//Include mail
require_once __DIR__.'/../../IOFrame/Managers/MailManager.php';
if(!defined('IOFrameUtilPureUtilFunctions'))
    require __DIR__.'/../../IOFrame/Util/PureUtilFunctions.php';
try{
    $mail = new \IOFrame\Managers\MailManager($settings,array_merge($defaultSettingsParams,['verbose'=>true]));

    if(!defined('IOFrameManagersIntegrationsEmailMailgun'))
        require_once __DIR__.'/../../IOFrame/Managers/Integrations/Email/Mailgun.php';


    $mailgun = new \IOFrame\Managers\Integrations\Email\Mailgun($settings,$mail);
    /*var_dump(
        $mailgun->sendMail(
            ['mail@a.com','mail@b.com'],
            'Test Subject %recipient.var%',
            'Test images send to %recipient.var%: <br>
                    <img src="cid:something.gif"> <img src="cid:snail.jpg">',
            [
                'from'=>'sender@alias.com',
                'text'=>'This is a simple test text %recipient.var%',
                'recipient-variables'=>[
                    'mail@a.com'=>['var'=>'A'],
                    'mail@b.com'=>['var'=>'B']
                ],
                'inline'=>[
                    ['filePath'=>__DIR__.'/exampleFiles/snail.jpg'],
                    ['filePath'=>__DIR__.'/exampleFiles/something.gif']
                ],
                'attachment'=>[
                    ['filePath'=>__DIR__.'/exampleFiles/example.txt','fileName'=>'Example']
                ]
            ],
            [
                'tracking'=>true,
                'tag'=>['test-1','test-2'],
                'skip-verification'=>false,
                'require-tls'=>true,
                'verbose'=>true,
                'test'=>false
            ]
        )
    );*/

    //The following was done after creating a template with ID '_mailgun_example', similar body to above test, with IOFrame variable %%VARIABLE%%
    /*var_dump(
        $mailgun->sendMailTemplate(
            ['mail@a.com','mail@b.com'],
            'Test Subject %recipient.var%',
            '_mailgun_example',
            [
                'varArray'=>[
                    'VARIABLE'=>'TEST VARIABLE'
                ],
                'from'=>'sender@alias.com',
                'text'=>'This is a simple test text %recipient.var%',
                'recipient-variables'=>[
                    'mail@a.com'=>['var'=>'A'],
                    'mail@b.com'=>['var'=>'B']
                ],
                'inline'=>[
                    ['filePath'=>__DIR__.'/exampleFiles/snail.jpg'],
                    ['filePath'=>__DIR__.'/exampleFiles/something.gif']
                ],
                'attachment'=>[
                    ['filePath'=>__DIR__.'/exampleFiles/example.txt','fileName'=>'Example']
                ]
            ],
            [
                'tracking'=>true,
                'tag'=>['test-1','test-2'],
                'skip-verification'=>false,
                'require-tls'=>true,
                'verbose'=>true,
                'test'=>false
            ]
        )
    );*/

    // After/Before running the following, start a mailgun mailing queue with configs with - after "cd /path/to/project/root/cli" -
    // php cron-management.php -v -a dynamic --fp cli/config/cron-management/start-queue-mailgun-mailing-manager.json
    /*var_dump(
        $mailgun->sendMailAsync(
            [
                'to'=>['mail@a.com','mail@b.com'],
                'from'=>['sender@alias.com','IOFrame Test'],
                'subject'=>'Test Subject %recipient.var%',
                'template'=>'_mailgun_example',
                'varArray'=>['VARIABLE'=>\IOFrame\Util\PureUtilFunctions::GeraHash(15)],
                'text'=>'This is a simple test text %recipient.var%',
                'recipient-variables'=>[
                    'mail@b.com'=>['var'=>'TOA'],
                    'mail@a.com'=>['var'=>'IO']
                ],
                'inline'=>[
                    ['filePath'=>__DIR__.'/exampleFiles/snail.jpg'],
                    ['filePath'=>__DIR__.'/exampleFiles/something.gif']
                ],
                'attachment'=>[
                    ['filePath'=>__DIR__.'/exampleFiles/example.txt','fileName'=>'Example']
                ]
            ],
            [
                'tracking'=>true,
                'tag'=>['test-1','test-2'],
                'skip-verification'=>false,
                'require-tls'=>true,
                'successQueue'=>true,
                'failureQueue'=>true,
                'test'=>false,
                'verbose'=>true
            ]
        )
    );*/
}
catch (\Exception $e){
    echo 'Mailgun error - '.$e->getMessage();
}