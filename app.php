<?php

use GuzzleHttp\Psr7\Response;

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

function getFileID(string $string)
{
    $parsed = parse_url($string);

    if (isset($parsed['query'])) {
        return str_replace('id=', '', $parsed['query']);
    }

    return str_replace(['/file/d/', '/view'], '', $parsed['path']);
}

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

function downloadFile(Response $response, Google_Service_Drive_DriveFile $fileInformation)
{
    echo 'Starting Download' . "\n";

    $output = new \Symfony\Component\Console\Output\ConsoleOutput();
    $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output);

    if (!is_dir('downloads') && !mkdir('downloads') && !is_dir('downloads')) {
        throw new \RuntimeException(sprintf('Directory "%s" was not created', 'downloads'));
    }

    $file = fopen('downloads/' . $fileInformation->getName(), 'xb');

    if ($file === false) {
        die('An error occurred');
    }

    $stream = $response->getBody();

    if ($stream->getSize() === null) {
        $progressBar->setMaxSteps($response->getHeader('Content-Length')[0]);
    } else {
        $progressBar->setMaxSteps($stream->getSize());
    }

    $stepSize = 8192;
    while (!$stream->eof()) {
        fwrite($file, $stream->read($stepSize));
        $progressBar->advance($stepSize);
    }

    $progressBar->finish();
    fclose($file);
}

if ($argv[1] === 'download') {
    if (!isset($argv[2])) {
        die('No fileID or Url given');
    }

    $fileID = getFileID($argv[2]);

    echo 'Requesting File Informations' . "\n";
    $googleRequestQueue->getFileInformation($fileID)->then(function (Google_Service_Drive_DriveFile $fileInformation) use ($googleRequestQueue) {
        echo 'Trying to download' . "\n";
        $googleRequestQueue->downloadFile($fileInformation->getId())->then(function (Response $response) use ($fileInformation) {
            downloadFile($response, $fileInformation);
        }, function ($e) use ($googleRequestQueue, $fileInformation) {
            if ($e instanceof Google_Service_Exception) {
                if ($e->getErrors()[0]['reason'] === 'downloadQuotaExceeded') {
                    echo 'Quota exceeded! Creating Copy!' . "\n";

                    $googleRequestQueue->createFileCopy($fileInformation->getId())->then(function (Google_Service_Drive_DriveFile $driveFile) use ($googleRequestQueue) {
                        echo 'Trying to download' . "\n";
                        $googleRequestQueue->downloadFile($driveFile->getId())->then(function (Response $response) use ($driveFile, $googleRequestQueue) {
                            downloadFile($response, $driveFile);

                            echo 'Deleting Copy!' . "\n";

                            $googleRequestQueue->deleteFile($driveFile->getId());
                        }, function ($e) {
                            var_dump($e);
                        });
                    }, function ($e) {
                        var_dump($e);
                    });
                } else {
                    echo $e->getMessage();
                }
            } else {
                var_dump($e);
            }
        });
    }, function ($e) {
        var_dump($e);
    });
}

if ($argv[1] === 'td') {
    if ($argv[2] === 'create') {
        $name = $argv[3];

        $googleRequestQueue->createTeamDrive($name)->then(function () use ($name, $googleRequestQueue, $teamDriveManager) {
            $googleRequestQueue->getTeamDriveList(function ($teamDriveName) use ($name) {
                return $teamDriveName === $name;
            })->then(function (array $teamDriveArray) use ($teamDriveManager) {
                foreach ($teamDriveArray as $teamDrive) {
                    $teamDriveManager->checkPermissionsForTeamDrive($teamDrive);
                }
            });
        });
    } else {
        $googleRequestQueue->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) use ($teamDriveNameBegin) {
            return strpos($teamDrive->getName(), $teamDriveNameBegin) === 0;
        })->then(function (array $teamDriveArray) use ($teamDriveManager) {
            foreach ($teamDriveArray as $teamDrive) {
                $teamDriveManager->checkPermissionsForTeamDrive($teamDrive);
            }
        });
    }
}

$loop->run();
