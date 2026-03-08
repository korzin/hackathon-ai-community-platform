<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260308000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create pipeline_runs table for dev-reporter-agent';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS pipeline_runs (
                id               SERIAL PRIMARY KEY,
                pipeline_id      VARCHAR(20) NOT NULL DEFAULT '',
                task             TEXT NOT NULL,
                branch           VARCHAR(100) NOT NULL DEFAULT '',
                status           VARCHAR(20) NOT NULL DEFAULT 'completed',
                failed_agent     VARCHAR(50) DEFAULT NULL,
                duration_seconds INTEGER NOT NULL DEFAULT 0,
                agent_results    JSONB NOT NULL DEFAULT '[]'::jsonb,
                report_content   TEXT DEFAULT NULL,
                created_at       TIMESTAMPTZ NOT NULL DEFAULT now()
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pipeline_runs_status ON pipeline_runs (status)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_pipeline_runs_created_at ON pipeline_runs (created_at DESC)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS pipeline_runs');
    }
}
