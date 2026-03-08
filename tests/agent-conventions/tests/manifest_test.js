// TC-01: Agent Manifest Endpoint
// Validates that /api/v1/manifest returns a compliant response.
// Run with: AGENT_URL=http://knowledge-agent:80 npx codeceptjs run

const assert = require('node:assert/strict');

Feature('TC-01 Agent Manifest Endpoint');

Scenario('GET /api/v1/manifest returns HTTP 200', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    assert.strictEqual(res.status, 200, `Expected 200, got ${res.status}`);
});

Scenario('manifest Content-Type is application/json', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    assert.ok(
        (res.headers['content-type'] || '').includes('application/json'),
        `Expected JSON content-type, got "${res.headers['content-type']}"`,
    );
});

Scenario('manifest has required field: name (non-empty string)', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    assert.strictEqual(res.status, 200);
    const body = res.data;
    assert.ok(body.name, 'field "name" must be present and non-empty');
    assert.strictEqual(typeof body.name, 'string', 'field "name" must be a string');
});

Scenario('manifest field name follows kebab-case convention', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const { name } = res.data;
    const isKebab = /^[a-z][a-z0-9-]*$/.test(name);
    assert.ok(isKebab, `name "${name}" must be kebab-case (e.g. my-agent)`);
});

Scenario('manifest has required field: version (non-empty string)', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const body = res.data;
    assert.ok(body.version, 'field "version" must be present and non-empty');
    assert.strictEqual(typeof body.version, 'string', 'field "version" must be a string');
});

Scenario('manifest version follows semver X.Y.Z format', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const { version } = res.data;
    const isSemver = /^\d+\.\d+\.\d+$/.test(version);
    assert.ok(isSemver, `version "${version}" must follow semver X.Y.Z`);
});

Scenario('manifest has skills field as array', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const body = res.data;
    if (body.skills !== undefined) {
        assert.ok(Array.isArray(body.skills), 'skills must be an array');
    }
});

Scenario('manifest has a2a_endpoint when skills is non-empty', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const body = res.data;
    if (Array.isArray(body.skills) && body.skills.length > 0) {
        const endpoint = body.url || body.a2a_endpoint;
        assert.ok(endpoint, 'url or a2a_endpoint is required when skills is non-empty');
        assert.strictEqual(typeof endpoint, 'string');
    }
});

Scenario('postgres storage declares startup migration contract', async ({ I }) => {
    const res = await I.sendGetRequest('/api/v1/manifest');
    const body = res.data;
    const postgres = body?.storage?.postgres;

    if (postgres && typeof postgres === 'object') {
        assert.ok(
            postgres.startup_migration && typeof postgres.startup_migration === 'object',
            'storage.postgres.startup_migration is required for Postgres-backed agents',
        );

        assert.strictEqual(
            postgres.startup_migration.enabled,
            true,
            'storage.postgres.startup_migration.enabled must be true',
        );

        assert.ok(
            typeof postgres.startup_migration.command === 'string' && postgres.startup_migration.command.trim().length > 0,
            'storage.postgres.startup_migration.command must be a non-empty string',
        );

        assert.strictEqual(
            postgres.startup_migration.mode,
            'best_effort',
            'storage.postgres.startup_migration.mode must be "best_effort"',
        );
    }
});
