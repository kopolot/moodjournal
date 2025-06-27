<?php

use App\Entity\User;
use Symfony\Contracts\EventDispatcher\Event;

final class UserDisabledEvent extends Event
{

    public function __construct(
        private User $user
    )
    {}

    public function getUser(): User
    {
        return $this->user;
    }

    private function setUser(User $user): void
    {
        $this->user = $user;
    }
}