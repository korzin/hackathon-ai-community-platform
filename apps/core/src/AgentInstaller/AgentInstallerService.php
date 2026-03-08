<?php

declare(strict_types=1);

namespace App\AgentInstaller;

use App\AgentInstaller\Strategy\InstallStrategyInterface;

final class AgentInstallerService
{
    public function __construct(
        private readonly InstallStrategyInterface $postgres,
        private readonly InstallStrategyInterface $redis,
        private readonly InstallStrategyInterface $opensearch,
    ) {
    }

    /**
     * Provision storage resources declared in the agent manifest.
     *
     * @param array<string, mixed> $manifest
     *
     * @return list<string> all actions performed
     *
     * @throws AgentInstallException on failure
     */
    public function install(array $manifest): array
    {
        $storage = $manifest['storage'] ?? null;

        if (!is_array($storage)) {
            return [];
        }

        $agentName = (string) ($manifest['name'] ?? 'unknown');
        $actions = [];

        if (isset($storage['postgres']) && is_array($storage['postgres'])) {
            $actions = array_merge($actions, $this->postgres->provision($storage['postgres'], $agentName));
        }

        if (isset($storage['redis']) && is_array($storage['redis'])) {
            $actions = array_merge($actions, $this->redis->provision($storage['redis'], $agentName));
        }

        if (isset($storage['opensearch']) && is_array($storage['opensearch'])) {
            $actions = array_merge($actions, $this->opensearch->provision($storage['opensearch'], $agentName));
        }

        return $actions;
    }

    /**
     * Deprovision storage resources declared in the agent manifest.
     *
     * @param array<string, mixed> $manifest
     *
     * @return list<string> all actions performed
     *
     * @throws AgentInstallException on failure
     */
    public function uninstall(array $manifest): array
    {
        $storage = $manifest['storage'] ?? null;

        if (!is_array($storage)) {
            return [];
        }

        $agentName = (string) ($manifest['name'] ?? 'unknown');
        $actions = [];

        if (isset($storage['opensearch']) && is_array($storage['opensearch'])) {
            $actions = array_merge($actions, $this->opensearch->deprovision($storage['opensearch'], $agentName));
        }

        if (isset($storage['redis']) && is_array($storage['redis'])) {
            $actions = array_merge($actions, $this->redis->deprovision($storage['redis'], $agentName));
        }

        if (isset($storage['postgres']) && is_array($storage['postgres'])) {
            $actions = array_merge($actions, $this->postgres->deprovision($storage['postgres'], $agentName));
        }

        return $actions;
    }
}
