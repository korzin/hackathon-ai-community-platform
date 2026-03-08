<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentInstaller;

use App\AgentInstaller\AgentInstallerService;
use App\AgentInstaller\AgentInstallException;
use App\AgentInstaller\Strategy\InstallStrategyInterface;
use Codeception\Test\Unit;
use PHPUnit\Framework\MockObject\MockObject;

final class AgentInstallerServiceTest extends Unit
{
    private InstallStrategyInterface&MockObject $postgres;
    private InstallStrategyInterface&MockObject $redis;
    private InstallStrategyInterface&MockObject $opensearch;
    private AgentInstallerService $service;

    protected function setUp(): void
    {
        $this->postgres = $this->createMock(InstallStrategyInterface::class);
        $this->redis = $this->createMock(InstallStrategyInterface::class);
        $this->opensearch = $this->createMock(InstallStrategyInterface::class);
        $this->service = new AgentInstallerService($this->postgres, $this->redis, $this->opensearch);
    }

    public function testInstallWithAllStorageTypes(): void
    {
        $manifest = [
            'name' => 'test-agent',
            'storage' => [
                'postgres' => ['db_name' => 'test', 'user' => 'test', 'password' => 'test'],
                'redis' => ['db_number' => 1],
                'opensearch' => ['collections' => ['chunks']],
            ],
        ];

        $this->postgres->expects($this->once())
            ->method('provision')
            ->willReturn(['created_user:test', 'created_database:test']);

        $this->redis->expects($this->once())
            ->method('provision')
            ->willReturn(['verified_redis_db:1']);

        $this->opensearch->expects($this->once())
            ->method('provision')
            ->willReturn(['created_index:test_agent_chunks']);

        $actions = $this->service->install($manifest);

        $this->assertCount(4, $actions);
    }

    public function testInstallWithNoStorageSection(): void
    {
        $manifest = ['name' => 'stateless-agent'];

        $this->postgres->expects($this->never())->method('provision');
        $this->redis->expects($this->never())->method('provision');
        $this->opensearch->expects($this->never())->method('provision');

        $actions = $this->service->install($manifest);

        $this->assertSame([], $actions);
    }

    public function testInstallWithOnlyPostgres(): void
    {
        $manifest = [
            'name' => 'db-only-agent',
            'storage' => [
                'postgres' => ['db_name' => 'db_only', 'user' => 'db_only', 'password' => 'secret'],
            ],
        ];

        $this->postgres->expects($this->once())
            ->method('provision')
            ->willReturn(['created_database:db_only']);

        $this->redis->expects($this->never())->method('provision');
        $this->opensearch->expects($this->never())->method('provision');

        $actions = $this->service->install($manifest);

        $this->assertContains('created_database:db_only', $actions);
    }

    public function testUninstallWithAllStorageTypes(): void
    {
        $manifest = [
            'name' => 'test-agent',
            'storage' => [
                'postgres' => ['db_name' => 'test', 'user' => 'test', 'password' => 'test'],
                'redis' => ['db_number' => 1],
                'opensearch' => ['collections' => ['chunks']],
            ],
        ];

        $this->opensearch->expects($this->once())
            ->method('deprovision')
            ->willReturn(['deleted_index:test_agent_chunks']);

        $this->redis->expects($this->once())
            ->method('deprovision')
            ->willReturn(['cleared_redis_db:1']);

        $this->postgres->expects($this->once())
            ->method('deprovision')
            ->willReturn(['dropped_database:test', 'dropped_user:test']);

        $actions = $this->service->uninstall($manifest);

        $this->assertCount(4, $actions);
    }

    public function testUninstallWithNoStorageSection(): void
    {
        $manifest = ['name' => 'stateless-agent'];

        $this->postgres->expects($this->never())->method('deprovision');
        $this->redis->expects($this->never())->method('deprovision');
        $this->opensearch->expects($this->never())->method('deprovision');

        $actions = $this->service->uninstall($manifest);

        $this->assertSame([], $actions);
    }

    public function testInstallPropagatesException(): void
    {
        $manifest = [
            'name' => 'failing-agent',
            'storage' => [
                'postgres' => ['db_name' => 'fail', 'user' => 'fail', 'password' => 'fail'],
            ],
        ];

        $this->postgres->expects($this->once())
            ->method('provision')
            ->willThrowException(new AgentInstallException('Provision failed'));

        $this->expectException(AgentInstallException::class);
        $this->expectExceptionMessage('Provision failed');

        $this->service->install($manifest);
    }

    public function testUninstallPropagatesException(): void
    {
        $manifest = [
            'name' => 'failing-agent',
            'storage' => [
                'postgres' => ['db_name' => 'fail', 'user' => 'fail', 'password' => 'fail'],
            ],
        ];

        $this->postgres->expects($this->once())
            ->method('deprovision')
            ->willThrowException(new AgentInstallException('Deprovision failed'));

        $this->expectException(AgentInstallException::class);
        $this->expectExceptionMessage('Deprovision failed');

        $this->service->uninstall($manifest);
    }
}
