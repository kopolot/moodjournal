<?php

namespace App\Scheduler\Handler;

use App\Repository\UserRepository;
use App\Scheduler\Message\DeleteDisabledUser;
use App\Service\UserService;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler( fromTransport:'async')]
final class DeleteDisabledUserHandler
{
    public function __construct(
        protected UserRepository $userRepository,
        protected UserService $userService,
    ) {}

    public function __invoke( DeleteDisabledUser $message)
    {
        $user = $this->userRepository->findOneById($message->getUserId());
        if ($user) {
            $this->userService->deleteDisabledUser($user);
        }
    }
}