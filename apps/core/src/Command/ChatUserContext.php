<?php

declare(strict_types=1);

namespace App\Command;

/**
 * Session-level user context collected at CLI startup.
 *
 * Mirrors the context that OpenClaw naturally provides from the platform
 * (author, language, platform) so that CLI tool invocations carry
 * equivalent metadata.
 */
final readonly class ChatUserContext
{
    public function __construct(
        public string $username,
        public string $language,
        public string $platform = 'cli',
    ) {
    }
}
