<?php


namespace TeamdriveManager\Command\Drive;

use Google_Service_Drive_TeamDrive;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TeamdriveManager\Service\GoogleDriveService;
use TeamdriveManager\Service\TeamDriveService;

class DownloadFileCommand extends Command
{
    protected static $defaultName = 'd:download';

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;
    /**
     * @var array
     */
    private $config;

    public function __construct(GoogleDriveService $googleDriveServiceService, array $config)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveServiceService;
        $this->config = $config;
    }


    protected function configure()
    {
        $this
            ->addOption('name', '-n', InputOption::VALUE_REQUIRED, 'The name for the Teamdrive');
    }


    private function getFileID(string $string)
    {
        $parsed = parse_url($string);

        if (isset($parsed['query'])) {
            return str_replace('id=', '', $parsed['query']);
        }

        return str_replace(['/file/d/', '/view'], '', $parsed['path']);
    }

//    private function downloadFile(Response $response, Google_Service_Drive_DriveFile $fileInformation)
//    {
//        echo 'Starting Download' . "\n";
//
//        $output = new \Symfony\Component\Console\Output\ConsoleOutput();
//        $progressBar = new \Symfony\Component\Console\Helper\ProgressBar($output);
//
//        if (!is_dir('downloads') && !mkdir('downloads') && !is_dir('downloads')) {
//            throw new \RuntimeException(sprintf('Directory "%s" was not created', 'downloads'));
//        }
//
//        $file = fopen('downloads/' . $fileInformation->getName(), 'xb');
//
//        if ($file === false) {
//            die('An error occurred');
//        }
//
//        $stream = $response->getBody();
//
//        if ($stream->getSize() === null) {
//            echo 'Stream Size unknown using Content-Length' . "\n";
//            $progressBar->setMaxSteps($response->getHeader('Content-Length')[0]);
//        } else {
//            $progressBar->setMaxSteps($stream->getSize());
//        }
//
//        $stepSize = 8192;
//        while (!$stream->eof()) {
//            fwrite($file, $stream->read($stepSize));
//            $progressBar->advance($stepSize);
//        }
//
//        $progressBar->finish();
//        fclose($file);
//    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

//        $fileID = getFileID($argv[2])

//        echo 'Requesting File Informations' . "\n";
//        $googleRequestQueue->getFileInformation($fileID)->then(function (Google_Service_Drive_DriveFile $fileInformation) use ($googleRequestQueue) {
//            echo 'Trying to download' . "\n";
//            $googleRequestQueue->downloadFile($fileInformation->getId())->then(function (Response $response) use ($fileInformation) {
//                downloadFile($response, $fileInformation);
//            }, function ($e) use ($googleRequestQueue, $fileInformation) {
//                if ($e instanceof Google_Service_Exception) {
//                    if ($e->getErrors()[0]['reason'] === 'downloadQuotaExceeded') {
//                        echo 'Quota exceeded! Creating Copy!' . "\n";
//
//                        $googleRequestQueue->createFileCopy($fileInformation->getId())->then(function (Google_Service_Drive_DriveFile $driveFile) use ($googleRequestQueue) {
//                            echo 'Trying to download' . "\n";
//                            $googleRequestQueue->downloadFile($driveFile->getId())->then(function (Response $response) use ($driveFile, $googleRequestQueue) {
//                                downloadFile($response, $driveFile);
//
//                                echo 'Deleting Copy!' . "\n";
//
//                                $googleRequestQueue->deleteFile($driveFile->getId());
//                            }, function ($e) {
//                                var_dump($e);
//                            });
//                        }, function ($e) {
//                            var_dump($e);
//                        });
//                    } else {
//                        echo $e->getCode() . ': ' . $e->getMessage() . "\n";
//                    }
//                } else {
//                    var_dump($e);
//                }
//            });
//        }, function ($e) {
//            var_dump($e);
//        });
    }
}
