<?php declare(strict_types=1);

namespace TeamdriveManager\Service;

use Google_Service_Directory;
use Google_Service_Directory_Group;
use Google_Service_Directory_Member;
use Google_Service_Drive;
use React\Promise\PromiseInterface;

class GoogleGroupService
{
    /**
     * @var Google_Service_Drive
     */
    private $directoryService;
    /**
     * @var RequestQueue
     */
    private $requestQueue;

    /**
     * GoogleRequestQueue constructor.
     *
     * @param Google_Service_Directory $directoryService
     * @param RequestQueue             $requestQueue
     */
    public function __construct(Google_Service_Directory $directoryService, RequestQueue $requestQueue)
    {
        $this->directoryService = $directoryService;
        $this->requestQueue = $requestQueue;
    }

    public function getGroupByEmail(string $email): PromiseInterface
    {
        echo 'Retrieving Group for Address ' . $email . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->directoryService->groups->get($email);

        return $this->requestQueue->queueRequest($request);
    }

    public function createGroup(string $name, string $email, string $description = 'Created by TeamdriveManager'): PromiseInterface
    {
        echo 'Creating Group ' . $name . ' with Address ' . $email . "\n";

        $group = new Google_Service_Directory_Group();
        $group->setName($name);
        $group->setEmail($email);
        $group->setDescription($description);

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->directoryService->groups->insert($group);

        return $this->requestQueue->queueRequest($request);
    }

    public function addMailToGroup(Google_Service_Directory_Group $group, string $mail): PromiseInterface
    {
        echo 'Adding User ' . $mail . ' to Group ' . $group->getName() . ' with Address ' . $group->getEmail() . "\n";

        $member = new Google_Service_Directory_Member();
        $member->setEmail($mail);
        $member->setRole('MEMBER');
        $member->setDeliverySettings('NONE');

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->directoryService->members->insert($group->getId(), $member);

        return $this->requestQueue->queueRequest($request);
    }

    public function removeMemberFromGroup(Google_Service_Directory_Group $group, Google_Service_Directory_Member $member): PromiseInterface
    {
        echo 'Removing User ' . $member->getEmail() . ' from Group ' . $group->getName() . ' with Address ' . $group->getEmail() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->directoryService->members->delete($group->getId(), $member->getId());

        return $this->requestQueue->queueRequest($request);
    }

    public function getMemberForGroupByMail(Google_Service_Directory_Group $group, string $mail): PromiseInterface
    {
        echo 'Retrieving Users for Mail ' . $mail . ' for Group ' . $group->getName() . ' with Address ' . $group->getEmail() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->directoryService->members->get($group->getId(), $mail);

        return $this->requestQueue->queueRequest($request);
    }

    public function getMembersForGroup(Google_Service_Directory_Group $group): PromiseInterface
    {
        echo 'Retrieving Users for Group ' . $group->getName() . ' with Address ' . $group->getEmail() . "\n";

        /** @var \GuzzleHttp\Psr7\Request $request */
        $request = $this->directoryService->members->listMembers($group->getId());

        return $this->requestQueue->queueRequest($request);
    }
}
