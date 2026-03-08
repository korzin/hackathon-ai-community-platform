<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

final class A2AControllerCest
{
    private function setAuthHeaders(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', 'dev-internal-token');
    }

    public function a2aWithoutTokenReturns401(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'devreporter.status',
            'payload' => [],
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(401);
        $I->seeResponseIsJson();
        $I->seeResponseContains('Unauthorized');
    }

    public function a2aWithIngestIntentReturnsCompleted(\FunctionalTester $I): void
    {
        $this->setAuthHeaders($I);
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'devreporter.ingest',
            'payload' => [
                'pipeline_id' => '20260308_120000',
                'task' => 'Add streaming support',
                'branch' => 'pipeline/add-streaming',
                'status' => 'completed',
                'duration_seconds' => 2700,
                'agent_results' => [],
            ],
            'request_id' => 'test-req-001',
            'trace_id' => 'test-trace-001',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'completed']);
        $I->seeResponseContains('"run_id"');
    }

    public function a2aWithIngestMissingTaskReturnsFailed(\FunctionalTester $I): void
    {
        $this->setAuthHeaders($I);
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'devreporter.ingest',
            'payload' => [
                'status' => 'completed',
            ],
            'request_id' => 'test-req-002',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'failed']);
        $I->seeResponseContains('task');
    }

    public function a2aWithStatusIntentReturnsRuns(\FunctionalTester $I): void
    {
        $this->setAuthHeaders($I);
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'devreporter.status',
            'payload' => [
                'limit' => 5,
            ],
            'request_id' => 'test-req-003',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'completed']);
        $I->seeResponseContains('"runs"');
        $I->seeResponseContains('"stats"');
    }

    public function a2aWithNotifyIntentReturnsCompleted(\FunctionalTester $I): void
    {
        $this->setAuthHeaders($I);
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'devreporter.notify',
            'payload' => [
                'message' => 'Test notification',
            ],
            'request_id' => 'test-req-004',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'completed']);
    }

    public function a2aWithInvalidJsonReturns422(\FunctionalTester $I): void
    {
        $this->setAuthHeaders($I);
        $I->sendPost('/api/v1/a2a', '{not-valid-json}');

        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
        $I->seeResponseContains('intent is required');
    }

    public function a2aWithoutIntentReturns422(\FunctionalTester $I): void
    {
        $this->setAuthHeaders($I);
        $I->sendPost('/api/v1/a2a', json_encode([
            'payload' => ['task' => 'Test'],
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(422);
        $I->seeResponseIsJson();
        $I->seeResponseContains('intent is required');
    }

    public function a2aWithUnknownIntentReturnsFailed(\FunctionalTester $I): void
    {
        $this->setAuthHeaders($I);
        $I->sendPost('/api/v1/a2a', json_encode([
            'intent' => 'unknown.action',
            'request_id' => 'test-req-005',
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'failed']);
        $I->seeResponseContains('Unknown intent');
    }
}
