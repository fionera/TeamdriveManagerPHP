<?php

require_once 'vendor/autoload.php';
require_once 'User.php';
require_once 'GoogleRequestQueue.php';
require_once 'TeamDriveManager.php';
require_once 'RcloneConfigManager.php';

$config = include 'config.php';
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $config['serviceAccountFile']);

$loop = React\EventLoop\Factory::create();

/** @var User[] $users */
$users = User::fromConfig($config);
$teamDriveNameBegin = $config['teamDriveNameBegin'];

$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->setSubject($config['subject']);
$client->setScopes([Google_Service_Drive::DRIVE]);
$client->setDefer(true);

$driveService = new Google_Service_Drive($client);

$googleRequestQueue = new GoogleRequestQueue($driveService);
$teamDriveManager = new TeamDriveManager($googleRequestQueue, $users);
$cloneConfigManager = new RcloneConfigManager();

if ($argc > 1) {

    if ($argv[1] === 'rclone') {
        if ($argc === 3) {
            $serviceAccountFileName = $argv[2];
        } else {
            $serviceAccountFileName = $config['serviceAccountFile'];
        }

        $googleRequestQueue->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) use ($teamDriveNameBegin) {
            return strpos($teamDrive->getName(), $teamDriveNameBegin) === 0;
        })->then(function (array $teamDriveArray) use ($serviceAccountFileName, $cloneConfigManager) {
            $configFileString = $cloneConfigManager->createRcloneEntriesForTeamDriveList($teamDriveArray, $serviceAccountFileName);

            file_put_contents('rclone.conf', $configFileString);
        });
    }

    if ($argv[1] === 'create') {
        $name = $argv[2];

        $googleRequestQueue->createTeamDrive($name)->then(function () use ($name, $googleRequestQueue, $teamDriveManager) {
            $googleRequestQueue->getTeamDriveList(function ($teamDriveName) use ($name) {
                return $teamDriveName === $name;
            })->then(function (array $teamDriveArray) use ($teamDriveManager) {
                foreach ($teamDriveArray as $teamDrive) {
                    $teamDriveManager->checkPermissionsForTeamDrive($teamDrive);
                }
            });
        });
    }
} else {
    $googleRequestQueue->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) use ($teamDriveNameBegin) {
        return strpos($teamDrive->getName(), $teamDriveNameBegin) === 0;
    })->then(function (array $teamDriveArray) use ($teamDriveManager) {
        foreach ($teamDriveArray as $teamDrive) {
            $teamDriveManager->checkPermissionsForTeamDrive($teamDrive);
        }
    });
}

$loop->run();
