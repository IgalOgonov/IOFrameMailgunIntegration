<?php
namespace IOFrame\Managers\Integrations\Email{
    define('IOFrameManagersIntegrationsEmailMailgun',true);

    /** Handles mail sending via Mailgun API (for SMTP, use the regular MailManager).
     * Requires the relevant Mail Settings to be set:
     * "mailgunHost" "mailgunDomain" "mailgunAPIKey"
     * @link https://documentation.mailgun.com/en/latest/user_manual.html#sending-via-api
     * @author Igal Ogonov <igal1333@hotmail.com>
     * @license https://opensource.org/licenses/LGPL-3.0 GNU Lesser General Public License version 3
     * */
    class Mailgun extends \IOFrame\Abstract\Logger {
        /** Can optionally use this handler to extract settings, use templates, etc
         * */
        protected ?\IOFrame\Managers\MailManager $MailManager = null;
        /** Official Mailgun PHP integration client, and our system's domain
         * */
        protected array $mailgun = ['client'=>null,'domain'=>null];

        /** @throws \Exception if undefined settings
         * */
        function __construct(\IOFrame\Handlers\SettingsHandler $settings, \IOFrame\Managers\MailManager $MailManager = null, array $params = []){

            parent::__construct($settings,array_merge($params,['logChannel'=>\IOFrame\Definitions::LOG_MAILING_CHANNEL]));

            if($MailManager !== null)
                $this->MailManager = $MailManager;
            else{
                $this->MailManager = new \IOFrame\Managers\MailManager($settings,array_merge($params,['mode'=>'none']));
            }

            $mailSettings = $this->MailManager->mailSettings;

            if (!$mailSettings->getSetting('mailgunHost') || !$mailSettings->getSetting('mailgunDomain') ||
                !$mailSettings->getSetting('mailgunAPIKey')
            ){
                throw(new \Exception('Cannot update mailgun settings - missing settings or cannot read settings file.'));
            }
            $this->mailgun = [
                'client'=>\Mailgun\Mailgun::create($mailSettings->getSetting('mailgunAPIKey'), $mailSettings->getSetting('mailgunHost')),
                'domain'=>$mailSettings->getSetting('mailgunDomain')
            ];
        }

        /** Sends a mail(s) via Mailgun API
         * @param string|string[] $to address(es) to send to
         * @param string $subject Mail subject
         * @param string $body Mail body, in HTML
         * @param array $inputs
         *          {
         *              'from': see MailManager->sendMail()
         *              'text': see MailManager->sendMail() (in the mailgun API, this field is "text")
         *              'cc': see sendMail()
         *              'bcc': see sendMail()
         *              'attachment': object[] - unlike sendMail(), format is: [
         *                  {
         *                      'filePath'=><string, exact path to file>
         *                      'fileName'=><string, name ot appear in email>
         *                  }
         *              ]
         *              'inline': object, similar to attachment WITHOUT fileName
         *              'replies': see sendMail()
         *              'recipient-variables': object, see https://documentation.mailgun.com/en/latest/user_manual.html#batch-sending-1
         *          }
         * @param array $params test/verbose, as well as:
         *              'tracking':<bool, default null - disables link rewriting for messages>
         *              'deliverytime':<string, default null - delivery schedule>
         *              'tag':<string|string[], default null -  tag(s)>
         *              'require-tls':<string|string[], default null -  connection setting>
         *              'skip-verification':<string|string[], default null -  connection setting>
         * @throws \Exception If not in a valid API mode
         * @link https://documentation.mailgun.com/en/latest/user_manual.html#sending-via-api
         * @returns mixed|null API result
         *
         */
        function sendMail(array|string $to, string $subject, string $body, array $inputs = [], array $params = []): bool {
            $test = $params['test']??false;
            $verbose = $params['verbose'] ?? $test;

            $inputs['from'] = $inputs['from'] ?: $this->MailManager->mailSettings->getSetting('defaultAlias');

            if(empty($inputs['from'])){
                $this->logger->error('Tried to send Mailgun mail without from, and no default alias');
                if($verbose)
                    echo 'No from, and no default alias';
                return false;
            }

            $sendParams = [
                'from'=>gettype($inputs['from']) === 'string'? $inputs['from'] : $inputs['from'][1].' <'.$inputs['from'][0].'>',
                'to'=>$to,
                'subject'=>$subject,
                'html'=>$body,
            ];
            foreach (['text','cc','bcc','attachment','inline','replies','recipient-variables'] as $option)
                if(!empty($inputs[$option]))
                    $sendParams[$option] = $inputs[$option];
            foreach (['recipient-variables'] as $backToJson)
                if(!empty($sendParams[$backToJson]))
                    $sendParams[$backToJson] = json_encode($sendParams[$backToJson]);

            if(!empty($params['mailgun']) && is_array($params['mailgun'])){
                foreach (['tracking'=>'o:tracking','deliverytime'=>'o:deliverytime','tag'=>'o:tag','require-tls'=>'o:require-tls','testmode'=>'o:testmode',
                             'skip-verification'=>'o:skip-verification'] as $input => $option)
                    if(!empty($params['mailgun'][$input]))
                        $sendParams[$option] = $params['mailgun'][$input];
            }

            if($verbose){
                echo 'Sending mailgun mail with domain '.$this->mailgun['domain'].' and params '.EOL;
                echo htmlspecialchars(json_encode($sendParams,JSON_PRETTY_PRINT)).EOL;
            }

            $res = $test || $this->mailgun['client']->messages()->send($this->mailgun['domain'],$sendParams);
            if(!$res)
                $this->logger->error('Tried to send Mailgun mail',['to'=>$to,'parameters'=>array_merge($sendParams,['body'=>null,'text'=>null])]);
            return $res;
        }


        /** Sends mail via mailgun, using an IOFrame template
         * @param array $to Object of the form <string, address to send mail to> => <null|string, Recipient name or null>
         * @param string $subject Mail subject
         * @param string $template Template number
         * @param array $inputs See sendMail(), as well as:
         *                'varArray': <object, of the form <string, variable name> => <mixed, new value> >
         * @param array $params See sendMail()
         * @return bool
         * @throws \Exception
         */
        function sendMailTemplate(array $to, string $subject, string $template, array $inputs = [], array $params = []  ): bool {
            $test = $params['test']??false;
            $verbose = $params['verbose'] ?? $test;
            $varArray = $inputs['varArray'] ?? [];
            $setNewTemplate = $this->MailManager->setWorkingTemplate($template,['test'=>$test,'verbose'=>$verbose]);

            if($setNewTemplate !== 0){
                if($verbose)
                    echo 'Could not load template'.EOL;
                return false;
            }
            return $this->sendMail(
                $to,
                $subject,
                $this->MailManager->fillTemplate(null,$varArray),
                $inputs,
                $params
            );
        }

        /**
         * Sends a mail, or multiple mails, into the mailgun mailing queue
         * @param array $inputs
         *          {
         *              'to': see sendMail()
         *              'subject': see sendMail()
         *              'body': see sendMail()
         *              'from': see sendMail() $inputs
         *              'text': see sendMail() $inputs
         *              'attachment': see sendMail() $inputs
         *              'replies': see sendMail() $inputs
         *              'cc': see sendMail() $inputs
         *              'bcc': see sendMail() $inputs
         *              'template': see sendMail() $params
         *              'varArray': see sendMail() $params
         *              'tracking':see sendMail() $params
         *              'deliverytime':see sendMail() $params
         *              'tag':see sendMail() $params
         *              'require-tls':see sendMail() $params
         *              'skip-verification':see sendMail() $params
         *              'recipient-variables':see sendMail() $params
         *          }
         *          If both template and body are set, body takes precedence.
         * @param array $params test/verbose, as well as:
         *               'queue': <string, default 'mailgun_mailing' - queue to send mail to>
         *               'differentPrefix': <string, default null - different queue prefix>
         *               'successQueue / failureQueue': same as MailManager->sendMailAsync(), but different default queue
         *               'successQueueExp/ failureQueueExp': <int, default 300 - if successQueue / failureQueue is true, will expire after this many seconds>
         * @returns bool|int|string|array Will return false if missing any of the required inputs, or MailManager was not initiated with cache
         *                      If successQueue AND failureQueue are false, returns RedisConcurrency->pushToQueues() codes.
         *                      If successQueue OR failureQueue are true, can instead return the name of the queue on success.
         *                      If successQueue AND failureQueue are true, can instead return a an array of the form ['success'=><string,queue name>, 'failure'=><string, queue name>]
         *
         */
        function sendMailAsync( array $inputs, array $params = []){
            $test = $params['test']??false;
            $verbose = $params['verbose'] ?? $test;
            $queue = $params['queue'] ?? 'mailgun_mailing';
            $successQueue = $params['successQueue']??null;
            $failureQueue = $params['failureQueue']??null;

            if(empty($this->MailManager->defaultSettingsParams['RedisManager'])){
                if($verbose)
                    echo 'Cannot use queue without Redis'.EOL;
                return false;
            }

            if($successQueue)
                $successQueue = is_string($successQueue)?$successQueue:('mailgun_mail_sent_'.\IOFrame\Util\PureUtilFunctions::GeraHash(20));
            if($failureQueue)
                $failureQueue = is_string($failureQueue)?$failureQueue:('mailgun_mail_failed_to_send_'.\IOFrame\Util\PureUtilFunctions::GeraHash(20));
            $opt = [
                'tracking'=>false,
                'deliverytime'=>false,
                'tag'=>false,
                'require-tls'=>false,
                'skip-verification'=>false,
                'recipient-variables'=>false
            ];

            foreach ($opt as $param => $req){
                $inputs[$param] = $inputs[$param] ?? null;
            }

            return $this->MailManager->sendMailAsync($inputs,array_merge($params,['queue'=>$queue,'successQueue'=>$successQueue,'failureQueue'=>$failureQueue]));
        }

    }
}