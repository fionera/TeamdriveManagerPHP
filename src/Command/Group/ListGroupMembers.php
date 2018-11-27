<?php


namespace TeamdriveManager\Command\Group;


use Google_Service_Directory_Group;
use Google_Service_Directory_Members;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TeamdriveManager\Service\GoogleGroupService;

class ListGroupMembers extends Command
{
    protected static $defaultName = 'group:members';
    /**
     * @var GoogleGroupService
     */
    private $googleGroupService;

    public function __construct(GoogleGroupService $googleGroupService)
    {
        parent::__construct();
        $this->googleGroupService = $googleGroupService;
    }

    public function run(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);

        $address = $io->ask('Group Address');

        $this->googleGroupService->getGroupByEmail($address)->then(function (Google_Service_Directory_Group $group) use ($output, $address) {
            $this->googleGroupService->getMembersForGroup($group)->then(function (Google_Service_Directory_Members $members) use ($output) {
                /** @var \Google_Service_Directory_Member $member */
                foreach ($members as $member) {
                    if ($member->getEmail() === null) {
                        continue;
                    }

                    $output->writeln($member->getEmail());
                }
            });

        });

    }
}