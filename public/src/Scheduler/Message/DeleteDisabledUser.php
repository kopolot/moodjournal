<?php

namespace App\Scheduler\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;
use Symfony\Component\Uid\Uuid;

#[AsMessage('async')]
final class DeleteDisabledUser
{
    public function __construct(
        protected Uuid $userId,
    ){}

    public function getUserId(): Uuid
    {
        return $this->userId;
    }

    public function getUserIdAsString(): string
    {
        return $this->userId->toRfc4122();
    }
}