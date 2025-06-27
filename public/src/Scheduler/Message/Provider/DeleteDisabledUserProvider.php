<?php

namespace App\Scheduler\Message\Provider;

use App\Repository\UserRepository;
use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Messenger\Message\RedispatchMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;

final class DeleteDisabledUserProvider implements \Symfony\Component\Scheduler\Trigger\MessageProviderInterface
{

    public function __construct(
        private int $daysToDeleteDisabledUser,
        private UserRepository $userRepository, // Assuming you have a UserRepository to fetch users      
    ) {}

    public function getMessages( MessageContext $context): iterable
    {
        $dateNow = $context->triggeredAt;
        $users = $this->userRepository->createQueryBuilder( 'u')
            ->where( 'u.isActive = false')
            ->andWhere( 'u.disabledAt < :date')
            ->setParameter('date', $dateNow->modify("-{$this->daysToDeleteDisabledUser} days"))
            ->getQuery()
            ->getResult();
        foreach ($users as $user) {
            yield new RedispatchMessage(
                new \App\Scheduler\Message\DeleteDisabledUser($user->getId()),
                'async'
            );
        }
    }

    public function getId(): string
    {
        return 'delete_disabled_user_provider';
    }
}