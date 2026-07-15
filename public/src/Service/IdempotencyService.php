<?php

namespace App\Service;

use Predis\ClientInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Symfony\Contracts\Cache\CacheInterface;

/**
 * Redis-backed idempotency for mutating endpoints.
 *
 * Same user + scope + Idempotency-Key → one producer run, shared result (24h).
 * Replays with a different request body → 409 Conflict.
 * Concurrent first hits are serialized with a Redis NX lock.
 */
class IdempotencyService
{
    private const TTL_SECONDS = 86400;
    private const LOCK_TTL_SECONDS = 30;
    private const LOCK_WAIT_SECONDS = 10;

    public function __construct(
        private CacheInterface $idempotencyCache,
        private ClientInterface $redis,
    ) {
    }

    /**
     * @template T
     * @param callable(): T $producer
     * @return T
     */
    public function run(
        string $scope,
        ?string $idempotencyKey,
        string $userId,
        string $payloadHash,
        callable $producer,
    ): mixed {
        $key = $idempotencyKey !== null ? trim($idempotencyKey) : '';
        if ($key === '') {
            return $producer();
        }

        if (!preg_match('/^[A-Za-z0-9._-]{8,128}$/', $key)) {
            throw new BadRequestHttpException('idempotency.key.invalid');
        }

        $cacheKey = sprintf('idem.%s.%s.%s', $scope, $userId, hash('xxh128', $key));
        $lockKey = 'lock:'.$cacheKey;

        $cached = $this->readCached($cacheKey);
        if ($cached !== null) {
            return $this->assertPayloadMatches($cached, $payloadHash);
        }

        $this->acquireLock($lockKey);
        try {
            $cached = $this->readCached($cacheKey);
            if ($cached !== null) {
                return $this->assertPayloadMatches($cached, $payloadHash);
            }

            $result = $producer();

            $item = $this->idempotencyCache->getItem($cacheKey);
            $item->expiresAfter(self::TTL_SECONDS);
            $item->set([
                'hash' => $payloadHash,
                'body' => $result,
            ]);
            $this->idempotencyCache->save($item);

            return $result;
        } finally {
            $this->releaseLock($lockKey);
        }
    }

    /**
     * @return array{hash: string, body: mixed}|null
     */
    private function readCached(string $cacheKey): ?array
    {
        $item = $this->idempotencyCache->getItem($cacheKey);
        if (!$item->isHit()) {
            return null;
        }

        $value = $item->get();
        if (!\is_array($value) || !\array_key_exists('hash', $value) || !\array_key_exists('body', $value)) {
            return null;
        }

        return $value;
    }

    /**
     * @param array{hash: string, body: mixed} $cached
     */
    private function assertPayloadMatches(array $cached, string $payloadHash): mixed
    {
        if (!hash_equals((string) $cached['hash'], $payloadHash)) {
            throw new ConflictHttpException('idempotency.key.conflict');
        }

        return $cached['body'];
    }

    private function acquireLock(string $lockKey): void
    {
        $deadline = microtime(true) + self::LOCK_WAIT_SECONDS;

        while (microtime(true) < $deadline) {
            $ok = $this->redis->set($lockKey, '1', 'EX', self::LOCK_TTL_SECONDS, 'NX');
            if ($ok) {
                return;
            }
            usleep(25_000);
        }

        throw new ServiceUnavailableHttpException(retryAfter: 1, message: 'idempotency.lock.timeout');
    }

    private function releaseLock(string $lockKey): void
    {
        $this->redis->del($lockKey);
    }
}
