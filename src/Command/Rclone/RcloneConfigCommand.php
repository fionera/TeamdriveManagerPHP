<?php


namespace TeamdriveManager\Command\Teamdrive;


use Google_Service_Drive_TeamDrive;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TeamdriveManager\Service\GoogleDriveService;
use TeamdriveManager\Service\TeamDriveService;

class RcloneConfigCommand extends Command
{
    protected static $defaultName = 'rclone:config';

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;
    /**
     * @var TeamDriveService
     */
    private $teamDriveService;
    /**
     * @var array
     */
    private $config;

    public function __construct(GoogleDriveService $googleDriveService, TeamDriveService $teamDriveService, array $config)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveService;
        $this->teamDriveService = $teamDriveService;
        $this->config = $config;
    }


    protected function configure()
    {
        $this
            ->addOption('name', '-n', InputOption::VALUE_REQUIRED, 'The name for the Teamdrive');
    }


    public function run(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

//        if ($argc === 3) {
//            $serviceAccountFileName = $argv[2];
//        } else {
//            $serviceAccountFileName = $config['serviceAccountFile'];
//        }
//
//        $googleRequestQueue->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) use ($teamDriveNameBegin) {
//            return strpos($teamDrive->getName(), $teamDriveNameBegin) === 0;
//        })->then(function (array $teamDriveArray) use ($serviceAccountFileName, $cloneConfigManager) {
//            $configFileString = $cloneConfigManager->createRcloneEntriesForTeamDriveList($teamDriveArray, $serviceAccountFileName);
//
//            file_put_contents('rclone.conf', $configFileString);
//        });
    }
}