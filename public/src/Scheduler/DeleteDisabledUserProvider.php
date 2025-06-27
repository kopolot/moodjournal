<?php

namespace App\Scheduler;

use App\Repository\UserRepository;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use App\Scheduler\Message\Provider\DeleteDisabledUserProvider as MessageProvider;

#[AsSchedule]
final class DeleteDisabledUserProvider implements ScheduleProviderInterface
{
    private Schedule $schedule;

    public function __construct(
        private UserRepository $userRepository,
        #[Autowire('%app.days_to_delete_disabled_user%')]
        private int $daysToDeleteDisabledUser = 30,
    ) {}

    public function getSchedule(): Schedule
    {
        return $this->schedule ??= (new Schedule())
            ->with(
                RecurringMessage::every("5 minutes", new MessageProvider(
                    $this->daysToDeleteDisabledUser,
                    $this->userRepository
                )),

            );
    }

    public function getDaysToDeleteDisabledUser(): int
    {
        return $this->daysToDeleteDisabledUser;
    }
}
