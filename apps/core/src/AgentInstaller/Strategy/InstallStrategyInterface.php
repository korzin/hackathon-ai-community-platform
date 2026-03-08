<?php

declare(strict_types=1);

namespace App\AgentInstaller\Strategy;

interface InstallStrategyInterface
{
    /**
     * Provision storage resources for an agent. Must be idempotent.
     *
     * @param array<string, mixed> $storageConfig the agent's storage sub-section for this strategy
     * @param string               $agentName     the agent name for logging/audit
     *
     * @return list<string> list of actions performed (for audit logging)
     */
    public function provision(array $storageConfig, string $agentName): array;

    /**
     * Deprovision storage resources for an agent. Must be idempotent.
     *
     * @param array<string, mixed> $storageConfig the agent's storage sub-section for this strategy
     * @param string               $agentName     the agent name for logging/audit
     *
     * @return list<string> list of actions performed (for audit logging)
     */
    public function deprovision(array $storageConfig, string $agentName): array;

    /**
     * Check whether provisioning is already complete.
     *
     * @param array<string, mixed> $storageConfig
     */
    public function isProvisioned(array $storageConfig): bool;
}
