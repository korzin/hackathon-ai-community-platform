// TC-02: Agent Health Endpoint
// Validates that /health returns a compliant response.

const assert = require('node:assert/strict');

Feature('TC-02 Agent Health Endpoint');

Scenario('GET /health returns HTTP 200', async ({ I }) => {
    const res = await I.sendGetRequest('/health');
    assert.strictEqual(res.status, 200, `Expected 200, got ${res.status}`);
});

Scenario('health endpoint returns JSON', async ({ I }) => {
    const res = await I.sendGetRequest('/health');
    assert.ok(
        (res.headers['content-type'] || '').includes('application/json'),
        `Expected JSON content-type, got "${res.headers['content-type']}"`,
    );
});

Scenario('health response has status field', async ({ I }) => {
    const res = await I.sendGetRequest('/health');
    assert.strictEqual(res.status, 200);
    const body = res.data;
    assert.ok(body.status !== undefined, 'health response must have a "status" field');
});

Scenario('health status is ok when service is running', async ({ I }) => {
    const res = await I.sendGetRequest('/health');
    const { status } = res.data;
    assert.strictEqual(status, 'ok', `health status must be "ok", got "${status}"`);
});
