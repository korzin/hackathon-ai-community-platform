<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentInstaller\Strategy;

use App\AgentInstaller\AgentInstallException;
use App\AgentInstaller\Strategy\OpenSearchInstallStrategy;
use Codeception\Test\Unit;

final class OpenSearchInstallStrategyTest extends Unit
{
    public function testProvisionThrowsOnEmptyCollections(): void
    {
        $strategy = new OpenSearchInstallStrategy('http://localhost:9200');

        $this->expectException(AgentInstallException::class);

        $strategy->provision(['collections' => []], 'test-agent');
    }

    public function testProvisionThrowsOnMissingCollections(): void
    {
        $strategy = new OpenSearchInstallStrategy('http://localhost:9200');

        $this->expectException(AgentInstallException::class);

        $strategy->provision([], 'test-agent');
    }

    public function testIsProvisionedReturnsTrueWhenNoCollections(): void
    {
        $strategy = new OpenSearchInstallStrategy('http://localhost:9200');

        $this->assertTrue($strategy->isProvisioned(['collections' => []]));
    }

    public function testDeprovisionReturnsEmptyActionsWhenNoCollections(): void
    {
        $strategy = new OpenSearchInstallStrategy('http://localhost:9200');

        $this->assertSame([], $strategy->deprovision(['collections' => []], 'test-agent'));
    }
}
