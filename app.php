<?php

require_once 'vendor/autoload.php';
$config = include 'config.php';
putenv('GOOGLE_APPLICATION_CREDENTIALS=' . $config['serviceAccountFile']);

$users = $config['users'];

$client = new Google_Client();
$client->useApplicationDefaultCredentials();
$client->setSubject($config['subject']);
$client->setScopes([Google_Service_Drive::DRIVE]);

$driveService = new Google_Service_Drive($client);

$teamDriveList = $driveService->teamdrives->listTeamdrives([
    'pageSize' => 100
]);

/** @var Google_Service_Drive_TeamDrive $teamDrive */
foreach ($teamDriveList as $teamDrive) {
    $name = $teamDrive->getName();
    $id = $teamDrive->getId();
    if (strpos($name, $config['teamdriveNameBegin']) === 0) {

        $permissions = $driveService->permissions->listPermissions($id, [
            'supportsTeamDrives' => true,
        ]);

        echo 'Getting Permissions for ' . $name . "\n";
        $client->setUseBatch(true);
        $permissionBatch = new Google_Http_Batch($client);
        /** @var Google_Service_Drive_Permission $permission */
        foreach ($permissions as $permission) {
            /** @noinspection PhpParamsInspection */
            $permissionBatch->add($driveService->permissions->get($id, $permission->getId(), [
                'supportsTeamDrives' => true,
                'fields' => 'kind,id,emailAddress,domain,role,allowFileDiscovery,displayName,photoLink,expirationTime,teamDrivePermissionDetails,deleted'
            ]));
        }
        /** @noinspection AlterInForeachInspection */
        unset($permission);
        $permissions = $permissionBatch->execute();
        $client->setUseBatch(false);

        foreach ($users as $mail => $role) {
            foreach ($permissions as $permission) {
                if ($permission->getEmailAddress() === $mail) {
                    if ($permission->getTeamDrivePermissionDetails()[0]->getRole() !== $role) {
                        echo 'Updating ' . $mail . ' for Teamdrive ' . $name . "\n";
                        $permission->getTeamDrivePermissionDetails()[0]->setRole($role);
                        $driveService->permissions->update($id, $permission->getId(),
                            $permission, [
                                'supportsTeamDrives' => true,
                            ]);
                    }

                    continue 2;
                }
            }

            echo 'Creating ' . $mail . ' for Teamdrive ' . $name . "\n";
            $driveService->permissions->create($id,
                new Google_Service_Drive_Permission([
                    'type' => 'user',
                    'role' => $role,
                    'emailAddress' => $mail
                ]), [
                    'supportsTeamDrives' => true,
                    'sendNotificationEmail' => false,
                ]);
        }

        foreach ($permissions as $permission) {
            if (!array_key_exists($permission->getEmailAddress(), $users)) {
                echo 'Deleting ' . $permission->getEmailAddress() . ' for Teamdrive ' . $name . "\n";
                $driveService->permissions->delete($id, $permission->getId(), [
                    'supportsTeamDrives' => true,
                ]);
            }
        }
    }
}

