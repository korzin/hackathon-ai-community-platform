<?php

declare(strict_types=1);

namespace App\Tests\Functional;

final class ManifestCest
{
    public function testManifestEndpointReturnsValidJson(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/manifest');
        $I->seeResponseCodeIs(200);
        $I->seeResponseIsJson();
        $I->seeResponseContainsJson([
            'name' => 'dev-reporter-agent',
            'version' => '1.0.0',
        ]);
        $I->seeResponseContainsJson(['url' => 'http://dev-reporter-agent/api/v1/a2a']);
        $response = json_decode($I->grabResponse(), true);
        \PHPUnit\Framework\Assert::assertIsArray($response['skills']);
        \PHPUnit\Framework\Assert::assertSame('devreporter.ingest', $response['skills'][0]['id']);
        \PHPUnit\Framework\Assert::assertSame('devreporter.status', $response['skills'][1]['id']);
        \PHPUnit\Framework\Assert::assertSame('devreporter.notify', $response['skills'][2]['id']);
    }

    public function testManifestContainsHealthUrl(\FunctionalTester $I): void
    {
        $I->sendGet('/api/v1/manifest');
        $I->seeResponseContainsJson(['health_url' => 'http://dev-reporter-agent/health']);
    }
}
