<?php declare(strict_types=1);

namespace TeamdriveManager\Command\Rclone;

use Google_Service_Drive_TeamDrive;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TeamdriveManager\Service\GoogleDriveService;
use TeamdriveManager\Service\RcloneConfigService;

class RcloneConfigCommand extends Command
{
    protected static $defaultName = 'rclone:config';

    /**
     * @var GoogleDriveService
     */
    private $googleDriveService;
    /**
     * @var array
     */
    private $config;
    /**
     * @var RcloneConfigService
     */
    private $rcloneConfigService;

    public function __construct(GoogleDriveService $googleDriveServiceService, RcloneConfigService $rcloneConfigService, array $config)
    {
        parent::__construct();
        $this->googleDriveService = $googleDriveServiceService;
        $this->config = $config;
        $this->rcloneConfigService = $rcloneConfigService;
    }


    protected function configure()
    {
        $this->setDescription('Create a Rclone Config file for all Teamdrives your User has access to');
        $this
            ->addOption('sa-folder-path','-P', InputOption::VALUE_REQUIRED, 'The service account folder path.');
    }

    public function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $path = $input->getOption('sa-folder-path');

        $this->googleDriveService->getTeamDriveList(function (Google_Service_Drive_TeamDrive $teamDrive) {
            return true;
        })->then(function (array $teamDriveArray) use ($path) {
            $configFileString = $this->rcloneConfigService->createRcloneEntriesForTeamDriveList($teamDriveArray, '', $path);
            file_put_contents('rclone.conf', $configFileString);
        });
    }
}
