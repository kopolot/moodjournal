<?php

namespace Kopolot\Utility;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class KopolotUtility extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}