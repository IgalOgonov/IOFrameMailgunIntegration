<?php

$rootFolder = $this->settings->getSetting('absPathToRoot');
$filesToDelete = array();
array_push(
    $filesToDelete,
    $rootFolder.'cli/cron-management/common/email-queue-managers/integrations/send_mail_mailgun_queue.php',
    $rootFolder.'cli/config/cron-management/start-queue-mailgun-mailing-manager.json',
    $rootFolder.'IOFrame/Managers/Integrations/Email/Mailgun.php',
    $rootFolder.'test/verbose/mailgunTest.php',
);

foreach($filesToDelete as $file) {
    if (file_exists($file)){
        if (!$test)
            unlink($file);
        else
            echo 'Deleting file ' . $file . EOL;
    }
};

if(!empty($options['uninstallLibraries'])){

    if( empty(getenv('COMPOSER_HOME')) ){
        $windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if($windows){
            throw new \Exception('Empty COMPOSER_HOME ENV variable');
        }
        else
            putenv('COMPOSER_HOME=$HOME/.composer');
    }

    $commands = array('command' => 'remove','packages'=>['mailgun/mailgun-php']);
    if($test)
        $commands['--dry-run'] = true;

    $currentFolder = __DIR__;
    chdir($rootFolder);
    $logFile = $rootFolder.'localFiles/temp/mailgunPluginLogs.txt';
    $stream = fopen($logFile, 'w+');
    $output = new \Symfony\Component\Console\Output\StreamOutput($stream);
    $application = new \Composer\Console\Application();
    $application->setAutoExit(false);
    $application->run(new \Symfony\Component\Console\Input\ArrayInput($commands), $output);

    if($test)
        echo EOL.file_get_contents($logFile).EOL;

    fclose($stream);
    chdir($currentFolder);
}

if(!$local && !empty($options['removeSettings'])){

    $mailSettings = new \IOFrame\Handlers\SettingsHandler(
        $this->settings->getSetting('absPathToRoot').'localFiles/mailSettings/',
        $this->defaultSettingsParams
    );
    $mailSettings->setSettings(['mailgunAPIKey'=>null,'mailgunDomain'=>null,'mailgunHost'=>null],['test'=>$test]);
}