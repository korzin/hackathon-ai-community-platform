<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging;

use App\Logging\PayloadSanitizer;
use Codeception\Test\Unit;

final class PayloadSanitizerTest extends Unit
{
    public function testSanitizeRedactsSensitiveFieldsAndPreservesStructure(): void
    {
        $sanitizer = new PayloadSanitizer();

        $result = $sanitizer->sanitize([
            'token' => 'secret-token',
            'nested' => [
                'authorization' => 'Bearer 123',
                'payload' => ['name' => 'Dima'],
            ],
        ]);

        $this->assertSame('[REDACTED]', $result['data']['token']);
        $this->assertSame('[REDACTED]', $result['data']['nested']['authorization']);
        $this->assertSame('Dima', $result['data']['nested']['payload']['name']);
        $this->assertGreaterThanOrEqual(2, $result['capture_meta']['redacted_fields_count']);
    }

    public function testSanitizeTruncatesLongStringsWithMetadata(): void
    {
        $sanitizer = new PayloadSanitizer();
        $long = str_repeat('a', 2000);

        $result = $sanitizer->sanitize([
            'message' => $long,
        ]);

        $this->assertStringContainsString('[truncated]', $result['data']['message']);
        $this->assertGreaterThanOrEqual(1, $result['capture_meta']['truncated_values_count']);
    }
}
