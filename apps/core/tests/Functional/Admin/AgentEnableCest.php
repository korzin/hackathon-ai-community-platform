<?php

declare(strict_types=1);

namespace App\Tests\Functional\Admin;

final class AgentEnableCest
{
    private function login(\FunctionalTester $I): void
    {
        $I->sendPost('/admin/login', [
            '_username' => 'admin',
            '_password' => 'test-password',
        ]);
        $I->seeResponseCodeIs(200);
    }

    public function enableEndpointRequiresAuth(\FunctionalTester $I): void
    {
        $I->sendPost('/api/v1/internal/agents/hello-agent/enable');
        $I->seeResponseContains('_username');
    }

    public function enableAgentReturnsEnabledStatus(\FunctionalTester $I): void
    {
        $this->login($I);

        $I->sendPost('/api/v1/internal/agents/hello-agent/install');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'installed', 'name' => 'hello-agent']);

        $I->sendPost('/api/v1/internal/agents/hello-agent/enable');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'enabled', 'name' => 'hello-agent']);
    }

    public function enableNonExistentAgentReturns404(\FunctionalTester $I): void
    {
        $this->login($I);

        $I->sendPost('/api/v1/internal/agents/non-existent-agent/enable');
        $I->seeResponseCodeIs(404);
        $I->seeResponseIsJson();
    }
}
