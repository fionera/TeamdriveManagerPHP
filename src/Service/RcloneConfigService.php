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

    public function getRcloneEntryName(Google_Service_Drive_TeamDrive $teamDrive): string
    {
        return $this->convertTeamDriveName($teamDrive->getName());
    }

    public function convertTeamDriveName(String $string): string
    {
        /** @var array $replaceChars */
        $replaceChars = array(
            'ä' => 'ae',
            'ö' => 'oe',
            'ü' => 'ue',
            'Ä' => 'Ae',
            'Ö' => 'Oe',
            'Ü' => 'Ue',
            'ß' => 'ss',
            '.' => '-',
        );

        $string = str_replace(array_keys($replaceChars), array_values($replaceChars), $string);
        $string = preg_replace('#[^a-zA-Z0-9\-_]#', '',  $string);

        return $string;
    }
}
