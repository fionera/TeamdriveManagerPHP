<?php declare(strict_types=1);

namespace TeamdriveManager\Service;

use Google_Service_Drive_TeamDrive;

class RcloneConfigService
{
    /**
     * @param Google_Service_Drive_TeamDrive[] $teamDriveArray
     * @param string                           $serviceAccountFileName
     *
     * @return string
     */
    public function createRcloneEntriesForTeamDriveList(array $teamDriveArray, string $serviceAccountFileName): string
    {
        $rcloneConfigString = '';
        foreach ($teamDriveArray as $teamDrive) {
            $rcloneConfigString .= $this->createRcloneConfig($teamDrive, $serviceAccountFileName);
        }

        return $rcloneConfigString;
    }

    public function createRcloneConfig(Google_Service_Drive_TeamDrive $teamDrive, string $serviceAccountFileName): string
    {
        $name = $this->getRcloneEntryName($teamDrive);

        return <<<EOF
[$name]
type = drive
client_id =
client_secret =
scope = drive
root_folder_id =
service_account_file = $serviceAccountFileName
team_drive = $teamDrive->id


EOF;
    }

    public function getRcloneEntryName(Google_Service_Drive_TeamDrive $teamDrive)
    {
        return str_replace([' - ', ' / ', '/', '-', ' '], ['_', '_', '', '', '-'], $teamDrive->getName());
    }
}
