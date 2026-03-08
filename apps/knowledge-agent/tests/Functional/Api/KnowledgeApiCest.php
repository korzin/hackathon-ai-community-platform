<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

class KnowledgeApiCest
{
    private function internalToken(): string
    {
        $token = $_ENV['APP_INTERNAL_TOKEN'] ?? $_SERVER['APP_INTERNAL_TOKEN'] ?? 'dev-internal-token';

        return (string) $token;
    }

    public function healthEndpointReturns200(\FunctionalTester $I): void
    {
        $I->sendGet('/health');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'ok', 'service' => 'knowledge-agent']);
    }

    public function uploadRequiresToken(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/knowledge/upload', json_encode(['messages' => []], \JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(401);
    }

    public function uploadWithEmptyMessagesReturns422(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        $I->sendPost('/api/v1/knowledge/upload', json_encode(['messages' => []], \JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(422);
    }

    public function searchRequiresQueryParam(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/search');
        $I->seeResponseCodeIs(400);
        $I->seeResponseIsJson();
    }

    public function a2aRequiresToken(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->sendPost('/api/v1/knowledge/a2a', json_encode(['request' => ['intent' => 'get_tree']], \JSON_THROW_ON_ERROR));
        $I->seeResponseCodeIs(401);
    }

    public function a2aAcceptsDirectEnvelopeForStoreMessageIntent(\FunctionalTester $I): void
    {
        $I->haveHttpHeader('Content-Type', 'application/json');
        $I->haveHttpHeader('X-Platform-Internal-Token', $this->internalToken());
        $I->sendPost('/api/v1/knowledge/a2a', json_encode([
            'intent' => 'knowledge.store_message',
            'request_id' => 'req-functional-store-message',
            'payload' => [],
        ], \JSON_THROW_ON_ERROR));

        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'status' => 'failed',
            'request_id' => 'req-functional-store-message',
            'error' => 'message payload is required',
        ]);
    }

    public function entryGetReturns404ForUnknownId(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries/nonexistent-id-xyz');
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
    }

    public function treeEndpointReturnsJson(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/tree');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['tree' => []]);
    }

    public function entriesEndpointReturnsJson(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/knowledge/entries');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['entries' => [], 'count' => 0]);
    }
}
