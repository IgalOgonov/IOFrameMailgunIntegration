<?php
$rootFolder = $this->settings->getSetting('absPathToRoot');
$foldersToCopy = array();
array_push(
    $foldersToCopy,
    [
        $rootFolder.'plugins/IOFrameMailgunIntegration/files/cli',
        $rootFolder.'cli'
    ],
    [
        $rootFolder.'plugins/IOFrameMailgunIntegration/files/IOFrame',
        $rootFolder.'IOFrame'
    ],
    [
        $rootFolder.'plugins/IOFrameMailgunIntegration/files/test',
        $rootFolder.'test'
    ]
);

foreach($foldersToCopy as $folder) {
    if (file_exists($folder[0])){
        if (!$test)
            \IOFrame\Util\FileSystemFunctions::folder_copy($folder[0], $folder[1]);
        else
            echo 'Copying folder ' . $folder[0] . ' to ' . $folder[1] . EOL;
    }
};

/* Install via composer*/

if(!empty($options['installLibraries'])){

    if( empty(getenv('COMPOSER_HOME')) ){
        if($test)
            echo 'COMPOSER_HOME ENV not set'.EOL;
        $windows = strtoupper(substr(PHP_OS, 0, 3)) === 'WIN';
        if($windows){
            throw new \Exception('Empty COMPOSER_HOME ENV variable');
        }
        else
            putenv('COMPOSER_HOME=$HOME/.composer');
    }

    $commands = ['command' => 'require','packages'=>['mailgun/mailgun-php']];
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
        echo EOL.file_get_contents($logFile).EOL.EOL;

    fclose($stream);
    chdir($currentFolder);
}

if(!$local){
    $validSettings = ['mailgunAPIKey','mailgunDomain','mailgunHost'];
    $toSet = [];
    foreach ($validSettings as $setting)
        if(!empty($options[$setting]))
            $toSet[$setting] = $options[$setting];

    if(!empty($toSet)){
        $mailSettings = new \IOFrame\Handlers\SettingsHandler(
            $this->settings->getSetting('absPathToRoot').'localFiles/mailSettings/',
            $this->defaultSettingsParams
        );
        $mailSettings->setSettings($toSet,['test'=>$test,'createNew'=>true]);
    }
}






