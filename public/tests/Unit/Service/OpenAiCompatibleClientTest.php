<?php

namespace App\Tests\Unit\Service;

use App\Service\OpenAiCompatibleClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

final class OpenAiCompatibleClientTest extends TestCase
{
    public function testDisabledWhenNoKeyAndNoBaseUrl(): void
    {
        $client = new OpenAiCompatibleClient(
            new MockHttpClient(),
            new NullLogger(),
            '',
            '',
            'gpt-4o-mini',
        );

        $this->assertFalse($client->isEnabled());
        $this->assertNull($client->chatJson([['role' => 'user', 'content' => 'hi']]));
    }

    public function testEnabledWithLocalOllamaBaseUrlOnly(): void
    {
        $client = new OpenAiCompatibleClient(
            new MockHttpClient(),
            new NullLogger(),
            '',
            'http://127.0.0.1:11434/v1',
            'llama3.1:8b',
        );

        $this->assertTrue($client->isEnabled());
    }
}
