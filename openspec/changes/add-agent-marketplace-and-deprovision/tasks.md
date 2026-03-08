# Tasks: add-agent-marketplace-and-deprovision

## 1. API and Lifecycle

- [ ] 1.1 Add `POST /api/v1/internal/agents/{name}/install` endpoint (admin auth)
- [ ] 1.2 Update `POST /api/v1/internal/agents/{name}/enable` to require installed state and return `409` when not installed
- [ ] 1.3 Update `DELETE /api/v1/internal/agents/{name}` to deprovision resources and mark agent uninstalled instead of deleting row
- [ ] 1.4 Add audit events for `installed` and `uninstalled`

## 2. Provisioning and Deprovisioning

- [ ] 2.1 Extend installer strategy interface with deprovision operation
- [ ] 2.2 Implement Postgres deprovision: drop `<db_name>`, `<db_name>_test` (or explicit `test_db_name`), and role
- [ ] 2.3 Implement Redis deprovision: `FLUSHDB` for configured DB
- [ ] 2.4 Implement OpenSearch deprovision: delete managed indices
- [ ] 2.5 Extend install flow to create E2E DB by convention (`<db_name>_test`) unless manifest provides `storage.postgres.test_db_name`
- [ ] 2.6 Require `storage.postgres.startup_migration` declaration in convention audit for Postgres-backed agents
- [ ] 2.7 Add startup migration scripts in agent containers (best-effort on every container start)

## 3. Admin UI

- [ ] 3.1 Split agent page into `Встановлені` and `Маркетплейс` tabs
- [ ] 3.2 Show lifecycle actions by state: install -> enable -> settings
- [ ] 3.3 Keep deleted-but-discoverable agents in marketplace tab
- [ ] 3.4 Improve AJAX error handling to show backend message

## 4. Tests

- [ ] 4.1 Add/adjust unit tests for installer service and strategies (install + deprovision)
- [ ] 4.2 Add functional tests for install endpoint and new enable precondition
- [ ] 4.3 Update admin delete/enable tests for marketplace transition behavior

## 5. Documentation

- [ ] 5.1 Update `docs/agent-requirements/conventions.md` with install/enable/deprovision lifecycle
- [ ] 5.2 Update `docs/agent-requirements/test-cases.md` with marketplace and deprovision coverage
- [ ] 5.3 Update `docs/agent-requirements/storage-provisioning.md` with startup migration + restart flow after code updates

## 6. Quality

- [ ] 6.1 Run targeted core tests for changed controllers/services
- [ ] 6.2 Run agent convention audit against `news-maker-agent`
