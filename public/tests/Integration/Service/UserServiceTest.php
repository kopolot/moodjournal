<?php

namespace App\Tests\Integration\Service;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Placeholder for future UserService integration tests against the kernel/DB.
 */
class UserServiceTest extends KernelTestCase
{
    public function testKernelBoots(): void
    {
        self::bootKernel();
        $this->assertNotNull(self::$kernel);
    }
}
