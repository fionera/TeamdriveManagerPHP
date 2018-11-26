<?php declare(strict_types=1);

namespace TeamdriveManager\Service;

use Exception;
use Google_Service_Drive;
use Google_Service_Drive_DriveFile;
use Google_Service_Drive_Permission;
use Google_Service_Drive_PermissionList;
use Google_Service_Drive_TeamDrive;
use Google_Service_Drive_TeamDriveList;
use React\Promise\PromiseInterface;
use TeamdriveManager\Struct\User;

class GoogleDriveService
{

    /**
     * @var Google_Service_Drive
     */
    private $driveService;
    /**
     * @var RequestQueue
     */
    private $requestQueue;

    /**
     * GoogleRequestQueue constructor.
     * @param Google_Service_Drive $drive_service
     * @param RequestQueue $requestQueue
     */
    public function __construct(Google_Service_Drive $drive_service, RequestQueue $requestQueue)
    {
        $this->driveService = $drive_service;
        $this->requestQueue = $requestQueue;
    }

    public function updatePermission(User $user, Google_Service_Drive_TeamDrive $teamDrive, Google_Service_Drive_Permission $permission): PromiseInterface
    {
        echo 'Updating ' . $user->mail . ' for Teamdrive ' . $teamDrive->getName() . "\n";

        $newPermission = new Google_Service_Drive_Permission();
        $newPermission->setRole($user->role);

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->update($teamDrive->getId(), $permission->getId(),
            $newPermission, [
                'supportsTeamDrives' => true,
            ]);

        return $this->requestQueue->queueRequest($request);
    }

    public function createPermission(User $user, Google_Service_Drive_TeamDrive $teamDrive): PromiseInterface
    {
        echo 'Creating ' . $user->mail . ' for Teamdrive ' . $teamDrive->getName() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->create($teamDrive->getId(),
            new Google_Service_Drive_Permission([
                'type' => 'user',
                'role' => $user->role,
                'emailAddress' => $user->mail
            ]), [
                'supportsTeamDrives' => true,
                'sendNotificationEmail' => false,
            ]);

        return $this->requestQueue->queueRequest($request);
    }

    public function deletePermission(Google_Service_Drive_TeamDrive $teamDrive, Google_Service_Drive_Permission $permission): PromiseInterface
    {
        echo 'Deleting ' . $permission->getEmailAddress() . ' for Teamdrive ' . $teamDrive->getName() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->delete($teamDrive->getId(), $permission->getId(), [
            'supportsTeamDrives' => true,
        ]);

        return $this->requestQueue->queueRequest($request);

    }

    public function getPermission(Google_Service_Drive_TeamDrive $teamDrive, string $id): PromiseInterface
    {
        echo 'Getting Permission ' . $id . ' for Teamdrive ' . $teamDrive->getName() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->get($teamDrive->getId(), $id, [
            'supportsTeamDrives' => true,
            'fields' => 'kind,id,emailAddress,domain,role,allowFileDiscovery,displayName,photoLink,expirationTime,teamDrivePermissionDetails,deleted'
        ]);

        return $this->requestQueue->queueRequest($request);
    }

    public function getPermissionList(Google_Service_Drive_TeamDrive $teamDrive): PromiseInterface
    {
        echo 'Getting PermissionList for ' . $teamDrive->getName() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->permissions->listPermissions($teamDrive->getId(), [
            'supportsTeamDrives' => true,
        ]);

        return $this->requestQueue->queueRequest($request);
    }

    public function getPermissionArray(Google_Service_Drive_TeamDrive $teamDrive): PromiseInterface
    {
        echo 'Getting Permissions for ' . $teamDrive->getName() . "\n";

        return $this->getPermissionList($teamDrive)->then(function (Google_Service_Drive_PermissionList $permissionList) use ($teamDrive) {
            $permissionRequestPromises = [];

            /** @var Google_Service_Drive_Permission $permission */
            foreach ($permissionList as $permission) {
                $permissionRequestPromises[] = $this->getPermission($teamDrive, $permission->getId());
            }

            return \GuzzleHttp\Promise\all($permissionRequestPromises);
        });
    }

    public function getTeamDriveList(callable $filter, int $pageSize = 100): PromiseInterface
    {
        echo 'Getting TeamDrive List' . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->teamdrives->listTeamdrives([
            'pageSize' => $pageSize
        ]);

        return $this->requestQueue->queueRequest($request)->then(function (Google_Service_Drive_TeamDriveList $teamDriveList) use ($filter) {
            $filteredDriveList = [];
            foreach ($teamDriveList as $teamDrive) {
                if ($filter($teamDrive) === true) {
                    $filteredDriveList[] = $teamDrive;
                }
            }

            return $filteredDriveList;
        });
    }

    public function createTeamDrive(string $teamDriveName): PromiseInterface
    {
        echo 'Creating Teamdrive ' . $teamDriveName . "\n";

        $teamDrive = new Google_Service_Drive_TeamDrive();
        $teamDrive->setName($teamDriveName);
        try {
            $requestId = random_int(1, 10000000);
        } catch (Exception $e) {
            echo 'Cant get random int for id';
            die();
        }

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->teamdrives->create($requestId, $teamDrive);

        return $this->requestQueue->queueRequest($request);
    }


    public function getFileInformation(string $fileID): PromiseInterface
    {
        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->files->get($fileID, [
            'supportsTeamDrives' => true
        ]);

        return $this->requestQueue->queueRequest($request);
    }

    public function downloadFile(string $fileID): PromiseInterface
    {
        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->files->get($fileID, [
            'supportsTeamDrives' => true,
            'alt' => 'media',
        ]);

        return $this->requestQueue->queueStreamRequest($request);
    }

    public function createFileCopy(string $fileID): PromiseInterface
    {
        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->files->copy($fileID,
            new Google_Service_Drive_DriveFile(),
            [
                'supportsTeamDrives' => true,
            ]);

        return $this->requestQueue->queueRequest($request);
    }

    public function deleteFile(string $fileID): PromiseInterface
    {
        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->driveService->files->delete($fileID,
            [
                'supportsTeamDrives' => true,
            ]);

        return $this->requestQueue->queueRequest($request);
    }
}