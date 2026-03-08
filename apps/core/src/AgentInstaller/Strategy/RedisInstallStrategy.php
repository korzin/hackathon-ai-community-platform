<?php

declare(strict_types=1);

namespace App\AgentInstaller\Strategy;

use App\AgentInstaller\AgentInstallException;

final class RedisInstallStrategy implements InstallStrategyInterface
{
    public function __construct(
        private readonly string $redisUrl,
    ) {
    }

    public function provision(array $storageConfig, string $agentName): array
    {
        $dbNumber = $storageConfig['db_number'] ?? null;

        if (!is_int($dbNumber) || $dbNumber < 0 || $dbNumber > 15) {
            throw new AgentInstallException(sprintf('Invalid Redis db_number: %s', var_export($dbNumber, true)));
        }

        $redis = $this->createRedisConnection();

        try {
            $result = $redis->select($dbNumber);
            if (false === $result) {
                throw new AgentInstallException(sprintf('Redis SELECT %d failed', $dbNumber));
            }
        } finally {
            $redis->close();
        }

        return [sprintf('verified_redis_db:%d', $dbNumber)];
    }

    public function deprovision(array $storageConfig, string $agentName): array
    {
        $dbNumber = $storageConfig['db_number'] ?? null;

        if (!is_int($dbNumber) || $dbNumber < 0 || $dbNumber > 15) {
            throw new AgentInstallException(sprintf('Invalid Redis db_number: %s', var_export($dbNumber, true)));
        }

        $redis = $this->createRedisConnection();

        try {
            $selected = $redis->select($dbNumber);
            if (false === $selected) {
                throw new AgentInstallException(sprintf('Redis SELECT %d failed', $dbNumber));
            }

            $flushed = $redis->flushDB();
            if (false === $flushed) {
                throw new AgentInstallException(sprintf('Redis FLUSHDB %d failed', $dbNumber));
            }
        } finally {
            $redis->close();
        }

        return [sprintf('cleared_redis_db:%d', $dbNumber)];
    }

    public function isProvisioned(array $storageConfig): bool
    {
        $dbNumber = $storageConfig['db_number'] ?? null;

        return is_int($dbNumber) && $dbNumber >= 0 && $dbNumber <= 15;
    }

    private function createRedisConnection(): \Redis
    {
        $parsed = parse_url($this->redisUrl);
        $host = $parsed['host'] ?? '127.0.0.1';
        $port = $parsed['port'] ?? 6379;

        $redis = new \Redis();

        if (!$redis->connect($host, (int) $port, 5.0)) {
            throw new AgentInstallException(sprintf('Cannot connect to Redis at %s:%d', $host, $port));
        }

        return $redis;
    }
}
