## MODIFIED Requirements

### Requirement: Agent Enable / Disable Lifecycle
The platform registry SHALL support explicit three-step lifecycle operations per agent: install (provision), enable/disable (traffic), and uninstall (deprovision).

#### Scenario: Install provisions resources but does not enable traffic
- **WHEN** an admin calls `POST /api/v1/internal/agents/{name}/install`
- **THEN** the platform provisions storage declared in manifest `storage`
- **AND** marks `installed_at = now()`
- **AND** keeps `enabled = false`

#### Scenario: Enable requires prior install
- **WHEN** an admin calls `POST /api/v1/internal/agents/{name}/enable` for an agent with `installed_at = null`
- **THEN** the platform returns `409 Conflict` with actionable error
- **AND** does not set `enabled = true`

#### Scenario: Delete performs full deprovision
- **WHEN** an admin calls `DELETE /api/v1/internal/agents/{name}` for a disabled installed agent
- **THEN** the platform deprovisions declared storage resources
- **AND** clears `installed_at`
- **AND** keeps the registry row for future install

#### Scenario: Postgres install creates main and E2E databases
- **WHEN** an agent Agent Card includes `storage.postgres`
- **THEN** install provisions both primary DB and E2E DB (`test_db_name` or `<db_name>_test` by convention)

#### Scenario: Postgres Agent Card declares startup migration contract
- **WHEN** an agent Agent Card includes `storage.postgres`
- **THEN** it declares `storage.postgres.startup_migration` with `enabled = true`, `mode = "best_effort"`, and a non-empty `command`
- **AND** convention audit marks the agent as `error` if this contract is missing

#### Scenario: Postgres uninstall removes both databases and role
- **WHEN** uninstall runs for an agent with postgres storage
- **THEN** the platform drops primary DB, E2E DB, and role owned by the agent

#### Scenario: Restart applies startup migrations after code update
- **WHEN** operators pull updated agent code and restart the agent container
- **THEN** the agent startup script attempts migrations automatically
- **AND** container startup continues even if migration command fails (`best_effort`)

#### Scenario: Redis and OpenSearch uninstall cleanup
- **WHEN** uninstall runs for an agent with redis/opensearch storage
- **THEN** the platform flushes the configured Redis DB
- **AND** deletes managed OpenSearch indices derived from agent name + collection

### Requirement: Registry Audit Log
All install/enable/disable/uninstall lifecycle changes SHALL be recorded in `agent_registry_audit`.

#### Scenario: Install/uninstall actions are audited
- **WHEN** an admin installs or uninstalls an agent
- **THEN** `agent_registry_audit` contains action (`installed` or `uninstalled`), actor, timestamp, and performed actions payload
