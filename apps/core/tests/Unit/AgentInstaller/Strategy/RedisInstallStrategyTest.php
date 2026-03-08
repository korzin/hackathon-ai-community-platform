<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentInstaller\Strategy;

use App\AgentInstaller\AgentInstallException;
use App\AgentInstaller\Strategy\RedisInstallStrategy;
use Codeception\Test\Unit;

final class RedisInstallStrategyTest extends Unit
{
    public function testIsProvisionedReturnsTrueForValidDbNumber(): void
    {
        $strategy = new RedisInstallStrategy('redis://localhost:6379');

        $this->assertTrue($strategy->isProvisioned(['db_number' => 0]));
        $this->assertTrue($strategy->isProvisioned(['db_number' => 15]));
    }

    public function testIsProvisionedReturnsFalseForInvalidDbNumber(): void
    {
        $strategy = new RedisInstallStrategy('redis://localhost:6379');

        $this->assertFalse($strategy->isProvisioned(['db_number' => -1]));
        $this->assertFalse($strategy->isProvisioned(['db_number' => 16]));
        $this->assertFalse($strategy->isProvisioned(['db_number' => 'not-int']));
    }

    public function testProvisionThrowsOnInvalidDbNumber(): void
    {
        $strategy = new RedisInstallStrategy('redis://localhost:6379');

        $this->expectException(AgentInstallException::class);

        $strategy->provision(['db_number' => 16], 'test-agent');
    }

    public function testDeprovisionThrowsOnInvalidDbNumber(): void
    {
        $strategy = new RedisInstallStrategy('redis://localhost:6379');

        $this->expectException(AgentInstallException::class);

        $strategy->deprovision(['db_number' => 16], 'test-agent');
    }
}
