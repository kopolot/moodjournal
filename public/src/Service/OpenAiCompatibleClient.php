<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Thin OpenAI-compatible chat client (OpenAI / Groq / OpenRouter / Ollama).
 * Disabled when neither API key nor base URL is configured.
 */
class OpenAiCompatibleClient
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $apiKey,
        private string $baseUrl,
        private string $model,
        private float $timeoutSeconds = 20.0,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->apiKey !== '' || $this->baseUrl !== '';
    }

    /**
     * @param list<array{role: string, content: string}> $messages
     *
     * @return array<string, mixed>|null decoded JSON object from the model, or null on failure
     */
    public function chatJson(array $messages): ?array
    {
        if (!$this->isEnabled()) {
            return null;
        }

        $base = rtrim($this->baseUrl !== '' ? $this->baseUrl : 'https://api.openai.com/v1', '/');
        $headers = [
            'Content-Type' => 'application/json',
        ];
        if ($this->apiKey !== '') {
            $headers['Authorization'] = 'Bearer ' . $this->apiKey;
        }

        try {
            $response = $this->httpClient->request('POST', $base . '/chat/completions', [
                'headers' => $headers,
                'timeout' => $this->timeoutSeconds,
                'json' => [
                    'model' => $this->model !== '' ? $this->model : 'gpt-4o-mini',
                    'temperature' => 0.4,
                    'response_format' => ['type' => 'json_object'],
                    'messages' => $messages,
                ],
            ]);

            $status = $response->getStatusCode();
            if ($status < 200 || $status >= 300) {
                $this->logger->warning('LLM chat failed with HTTP {status}', ['status' => $status]);

                return null;
            }

            /** @var array<string, mixed> $payload */
            $payload = $response->toArray(false);
            $content = $payload['choices'][0]['message']['content'] ?? null;
            if (!is_string($content) || trim($content) === '') {
                return null;
            }

            $decoded = json_decode($content, true);
            if (!\is_array($decoded)) {
                return null;
            }

            /** @var array<string, mixed> $decoded */
            return $decoded;
        } catch (\Throwable $e) {
            $this->logger->warning('LLM chat exception: {message}', ['message' => $e->getMessage()]);

            return null;
        }
    }
}
