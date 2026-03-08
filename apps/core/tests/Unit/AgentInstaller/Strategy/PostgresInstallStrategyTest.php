<?php

declare(strict_types=1);

namespace App\Tests\Unit\AgentInstaller\Strategy;

use App\AgentInstaller\AgentInstallException;
use App\AgentInstaller\Strategy\PostgresInstallStrategy;
use Codeception\Test\Unit;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\MockObject\MockObject;

final class PostgresInstallStrategyTest extends Unit
{
    private Connection&MockObject $connection;
    private PostgresInstallStrategy $strategy;

    protected function setUp(): void
    {
        $this->connection = $this->createMock(Connection::class);
        $this->strategy = new PostgresInstallStrategy($this->connection);
    }

    public function testProvisionCreatesUserAndDatabase(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(false);

        $this->connection->expects($this->exactly(5))
            ->method('executeStatement');

        $this->connection->method('quote')
            ->willReturnCallback(static fn (string $v): string => sprintf("'%s'", $v));

        $actions = $this->strategy->provision(
            ['db_name' => 'test_db', 'user' => 'test_user', 'password' => 'test_pass'],
            'test-agent',
        );

        $this->assertContains('created_user:test_user', $actions);
        $this->assertContains('created_database:test_db', $actions);
        $this->assertContains('created_database:test_db_test', $actions);
    }

    public function testProvisionSkipsExistingUser(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnCallback(static function (string $sql): int|false {
                if (str_contains($sql, 'pg_roles')) {
                    return 1;
                }

                return false;
            });

        $this->connection->method('quote')
            ->willReturnCallback(static fn (string $v): string => sprintf("'%s'", $v));

        $actions = $this->strategy->provision(
            ['db_name' => 'test_db', 'user' => 'test_user', 'password' => 'test_pass'],
            'test-agent',
        );

        $this->assertNotContains('created_user:test_user', $actions);
        $this->assertContains('created_database:test_db', $actions);
        $this->assertContains('created_database:test_db_test', $actions);
    }

    public function testProvisionSkipsExistingDatabase(): void
    {
        $this->connection->method('fetchOne')
            ->willReturnCallback(static function (string $sql): int|false {
                if (str_contains($sql, 'pg_database')) {
                    return 1;
                }

                return false;
            });

        $this->connection->method('quote')
            ->willReturnCallback(static fn (string $v): string => sprintf("'%s'", $v));

        $actions = $this->strategy->provision(
            ['db_name' => 'test_db', 'user' => 'test_user', 'password' => 'test_pass'],
            'test-agent',
        );

        $this->assertContains('created_user:test_user', $actions);
        $this->assertNotContains('created_database:test_db', $actions);
        $this->assertNotContains('created_database:test_db_test', $actions);
    }

    public function testProvisionIsIdempotentWhenBothExist(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(1);

        $actions = $this->strategy->provision(
            ['db_name' => 'test_db', 'user' => 'test_user', 'password' => 'test_pass'],
            'test-agent',
        );

        $this->assertNotContains('created_user:test_user', $actions);
        $this->assertNotContains('created_database:test_db', $actions);
    }

    public function testInvalidIdentifierThrowsException(): void
    {
        $this->expectException(AgentInstallException::class);

        $this->strategy->provision(
            ['db_name' => 'INVALID!', 'user' => 'test_user', 'password' => 'test_pass'],
            'test-agent',
        );
    }

    public function testIsProvisionedReturnsTrueWhenBothExist(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(1);

        $this->assertTrue($this->strategy->isProvisioned(['db_name' => 'test_db', 'user' => 'test_user']));
    }

    public function testIsProvisionedReturnsFalseWhenMissing(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(false);

        $this->assertFalse($this->strategy->isProvisioned(['db_name' => 'test_db', 'user' => 'test_user']));
    }

    public function testDeprovisionDropsDatabasesAndRole(): void
    {
        $this->connection->method('fetchOne')
            ->willReturn(1);

        $this->connection->expects($this->exactly(6))
            ->method('executeStatement');

        $actions = $this->strategy->deprovision(
            ['db_name' => 'test_db', 'user' => 'test_user', 'password' => 'test_pass'],
            'test-agent',
        );

        $this->assertContains('dropped_database:test_db_test', $actions);
        $this->assertContains('dropped_database:test_db', $actions);
        $this->assertContains('dropped_user:test_user', $actions);
    }
}
