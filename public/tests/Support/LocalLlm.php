<?php

namespace App\Tests\Support;

/**
 * Helpers for opt-in integration tests against a real local Ollama
 * (compose profile `llm`).
 */
final class LocalLlm
{
    public static function baseUrl(): string
    {
        $fromEnv = getenv('OLLAMA_BASE_URL') ?: ($_ENV['OLLAMA_BASE_URL'] ?? '');
        if (is_string($fromEnv) && $fromEnv !== '') {
            return rtrim($fromEnv, '/');
        }

        // Inside Docker compose network
        return 'http://ollama:11434';
    }

    public static function model(): string
    {
        $fromEnv = getenv('OLLAMA_MODEL')
            ?: getenv('OPENAI_MODEL')
            ?: ($_ENV['OLLAMA_MODEL'] ?? $_ENV['OPENAI_MODEL'] ?? '');

        return is_string($fromEnv) && $fromEnv !== '' ? $fromEnv : 'llama3.2:3b';
    }

    public static function openAiBaseUrl(): string
    {
        return self::baseUrl() . '/v1';
    }

    /**
     * @return array{ok: bool, reason?: string, models?: list<string>}
     */
    public static function probe(): array
    {
        $base = self::baseUrl();
        $ctx = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 2,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($base . '/api/tags', false, $ctx);
        if ($raw === false) {
            return [
                'ok' => false,
                'reason' => sprintf('Ollama not reachable at %s (start with: docker compose --profile llm up -d)', $base),
            ];
        }

        /** @var array{models?: list<array{name?: string}>}|null $json */
        $json = json_decode($raw, true);
        $names = [];
        foreach ($json['models'] ?? [] as $model) {
            if (isset($model['name']) && is_string($model['name'])) {
                $names[] = $model['name'];
            }
        }

        $wanted = self::model();
        $found = false;
        foreach ($names as $name) {
            if ($name === $wanted || str_starts_with($name, $wanted)) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            return [
                'ok' => false,
                'reason' => sprintf(
                    'Model "%s" not pulled in Ollama (have: %s). Run ollama-init or: ollama pull %s',
                    $wanted,
                    $names === [] ? 'none' : implode(', ', $names),
                    $wanted
                ),
                'models' => $names,
            ];
        }

        return ['ok' => true, 'models' => $names];
    }

    /**
     * Point Symfony OPENAI_* env at local Ollama for the current process.
     * Call before booting the kernel / createClient().
     */
    public static function enableEnvForSymfony(): void
    {
        $vars = [
            'OPENAI_BASE_URL' => self::openAiBaseUrl(),
            'OPENAI_API_KEY' => 'ollama',
            'OPENAI_MODEL' => self::model(),
        ];

        foreach ($vars as $key => $value) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }
    }
}
