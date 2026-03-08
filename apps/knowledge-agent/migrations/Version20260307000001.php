<?php

declare(strict_types=1);

namespace App\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260307000001 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create knowledge_source_messages table for raw message ingestion metadata';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE IF NOT EXISTS knowledge_source_messages (
                id                   UUID PRIMARY KEY DEFAULT gen_random_uuid(),
                source_platform      VARCHAR(32) NOT NULL DEFAULT 'telegram',
                event_type           VARCHAR(64) NOT NULL DEFAULT 'message_created',
                chat_id              VARCHAR(128) DEFAULT NULL,
                chat_title           VARCHAR(255) DEFAULT NULL,
                chat_type            VARCHAR(64) DEFAULT NULL,
                channel              VARCHAR(128) DEFAULT NULL,
                message_id           VARCHAR(128) DEFAULT NULL,
                thread_id            VARCHAR(128) DEFAULT NULL,
                sender_id            VARCHAR(128) DEFAULT NULL,
                sender_username      VARCHAR(255) DEFAULT NULL,
                sender_display_name  VARCHAR(255) DEFAULT NULL,
                message_text         TEXT DEFAULT NULL,
                message_timestamp    TIMESTAMPTZ DEFAULT NULL,
                trace_id             VARCHAR(128) DEFAULT NULL,
                request_id           VARCHAR(128) DEFAULT NULL,
                metadata             JSONB NOT NULL DEFAULT '{}'::jsonb,
                raw_payload          JSONB NOT NULL,
                created_at           TIMESTAMPTZ NOT NULL DEFAULT now(),
                CONSTRAINT uq_knowledge_source_message UNIQUE (source_platform, chat_id, message_id)
            )
        SQL);

        $this->addSql('CREATE INDEX IF NOT EXISTS idx_knowledge_source_messages_created_at ON knowledge_source_messages (created_at DESC)');
        $this->addSql('CREATE INDEX IF NOT EXISTS idx_knowledge_source_messages_chat ON knowledge_source_messages (chat_id, message_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE IF EXISTS knowledge_source_messages');
    }
}
