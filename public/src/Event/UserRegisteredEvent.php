<?php

namespace App\Event;

use App\Entity\User;

class UserRegisteredEvent
{
    public function __construct(public User $user) {}
}