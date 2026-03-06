<?php

declare(strict_types=1);

namespace App\Tests\Unit\Logging;

use App\Logging\TraceSequenceProjector;
use Codeception\Test\Unit;

final class TraceSequenceProjectorTest extends Unit
{
    public function testProjectBuildsSequenceEventsAndParticipants(): void
    {
        $projector = new TraceSequenceProjector();

        $projection = $projector->project([
            [
                '@timestamp' => '2026-03-06T10:00:00Z',
                'event_name' => 'core.a2a.outbound.started',
                'step' => 'a2a_outbound',
                'source_app' => 'core',
                'target_app' => 'hello-agent',
                'tool' => 'hello.greet',
                'status' => 'started',
                'trace_id' => 'trace-1',
                'request_id' => 'req-1',
                'context' => [
                    'step_input' => ['name' => 'Dima'],
                ],
            ],
            [
                '@timestamp' => '2026-03-06T10:00:01Z',
                'message' => 'Non-structured log',
            ],
        ]);

        $this->assertCount(1, $projection['events']);
        $this->assertSame('core', $projection['events'][0]['from']);
        $this->assertSame('hello-agent', $projection['events'][0]['to']);
        $this->assertSame('hello.greet', $projection['events'][0]['operation']);
        $this->assertSame(['core', 'hello-agent'], $projection['participants']);
    }
}
