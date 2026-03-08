<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class HealthCest
{
    public function testHealthEndpointReturnsOk(\FunctionalTester $I): void
    {
        $I->sendGet('/health');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson(['status' => 'ok', 'service' => 'dev-reporter-agent']);
    }
}
