# Agent Convention Test Cases

Every agent MUST pass all test cases defined here. Tests run via Codecept.js + Playwright
and are executed with `make conventions-test` against the running Docker stack.

Tests live in `tests/agent-conventions/` and target each agent via its Traefik-exposed port.

---

## Test Suite Structure

```
tests/agent-conventions/
├── package.json
├── codecept.conf.js
└── tests/
    ├── manifest_test.js
    ├── health_test.js
    └── a2a_observability_test.js
```

Run: `make conventions-test` (executes inside Docker network or via published ports)

---

## TC-01: Manifest Endpoint

**File**: `tests/agent-conventions/tests/manifest_test.js`

| ID | Test Case | Expected |
|---|---|---|
| TC-01-01 | `GET /api/v1/manifest` returns HTTP 200 | Status 200 |
| TC-01-02 | Response is valid JSON | No parse error |
| TC-01-03 | `name` field exists and is a non-empty string | `typeof name === 'string' && name.length > 0` |
| TC-01-04 | `version` field exists and matches semver `X.Y.Z` | `/^\d+\.\d+\.\d+$/.test(version)` |
| TC-01-05 | `skills` field exists and is an array | `Array.isArray(skills)` |
| TC-01-06 | If `skills` non-empty, `url` or legacy `a2a_endpoint` is present and is a URL | URL parse succeeds |
| TC-01-07 | `Content-Type` header contains `application/json` | Header assertion |
| TC-01-08 | Response time under 3000ms | Playwright timeout |
| TC-01-09 | If `storage.postgres` exists, `storage.postgres.startup_migration` is declared | `enabled === true`, `mode === "best_effort"`, `command` is non-empty string |

```javascript
// Example: tests/agent-conventions/tests/manifest_test.js
Feature('Manifest Convention');

Scenario('manifest endpoint returns valid schema', async ({ I }) => {
  const res = await I.sendGetRequest('/api/v1/manifest');
  I.assertEqual(res.status, 200);
  const body = res.data;
  I.assertTrue(typeof body.name === 'string' && body.name.length > 0, 'name required');
  I.assertTrue(/^\d+\.\d+\.\d+$/.test(body.version), 'version must be semver');
  I.assertTrue(Array.isArray(body.skills), 'skills must be array');
  if (body.skills.length > 0) {
    I.assertTruthy(body.url || body.a2a_endpoint, 'url or a2a_endpoint required when skills declared');
  }
});
```

---

## TC-02: Health Endpoint

**File**: `tests/agent-conventions/tests/health_test.js`

| ID | Test Case | Expected |
|---|---|---|
| TC-02-01 | `GET /health` returns HTTP 200 | Status 200 |
| TC-02-02 | Response body contains `{"status": "ok"}` | JSON assertion |
| TC-02-03 | `Content-Type` contains `application/json` | Header assertion |
| TC-02-04 | Response time under 1000ms | Fast health check |

---

## TC-03: A2A Endpoint + Correlation (conditional — only if skills declared)

Current status: baseline convention checks are implemented in
`tests/agent-conventions/tests/a2a_observability_test.js` for the hello-agent
intent contract and correlation IDs.

| ID | Test Case | Expected |
|---|---|---|
| TC-03-01 | `POST /api/v1/a2a` with valid envelope returns HTTP 200 | Status 200 |
| TC-03-02 | Response contains `status` field | `completed`, `failed`, or `needs_clarification` |
| TC-03-03 | Unknown intent/tool returns structured error | `status: "failed"`, `error` non-null |
| TC-03-04 | Missing `intent`/tool field returns 400/422 | Status 4xx |
| TC-03-05 | Provided `request_id` is preserved in response | Correlation continuity |
| TC-03-06 | Missing `request_id` still yields generated `request_id` | Non-empty string |
| TC-03-07 | OTel-compatible `trace_id` (32 hex) is accepted | Status 200 |
| TC-03-08 | Response remains JSON envelope (no plain-text primary body) | JSON assertion |

Minimum valid request payload for hello-agent TC-03:
```json
{
  "intent":     "hello.greet",
  "payload":    { "name": "AuditUser" },
  "trace_id":   "00000000000000000000000000000001",
  "request_id": "00000000-0000-7000-8000-000000000001"
}
```

---

## TC-04: No-Auth Endpoints

| ID | Test Case | Expected |
|---|---|---|
| TC-04-01 | `GET /api/v1/manifest` requires no `Authorization` header | No 401/403 |
| TC-04-02 | `GET /health` requires no `Authorization` header | No 401/403 |

---

## TC-05: Admin Lifecycle (Install / Enable / Delete)

| ID | Test Case | Expected |
|---|---|---|
| TC-05-01 | Install endpoint provisions agent without enabling | `POST /api/v1/internal/agents/{name}/install` returns `200`, agent stays disabled |
| TC-05-02 | Enable before install is rejected | `POST /api/v1/internal/agents/{name}/enable` returns `409` with actionable message |
| TC-05-03 | Installed disabled agent shows delete action in Installed tab | Admin UI shows `Видалити` in `Встановлені` |
| TC-05-04 | Delete performs deprovision and moves agent to Marketplace | Agent disappears from `Встановлені`, appears in `Маркетплейс` with `Встановити` |
| TC-05-05 | Settings action appears only after enable | `Налаштування` button visible only for enabled installed agents |

---

## Running Tests

### All agents (auto-discovered):
```bash
make conventions-test
```

### Single agent:
```bash
AGENT_URL=http://localhost:8083 make conventions-test
```

### Inside Docker:
```bash
docker compose run --rm test-runner npx codeceptjs run --steps
```

### CI:
Tests run as part of the pipeline after `docker compose up`. Failures block merge.

---

## Adding Tests for a New Convention

1. Add the test case table to this document
2. Implement or update the test in `tests/agent-conventions/tests/`
3. Add assertion to `AgentConventionVerifier` in core (for runtime checks)
4. Update `make conventions-test` if new dependencies required
