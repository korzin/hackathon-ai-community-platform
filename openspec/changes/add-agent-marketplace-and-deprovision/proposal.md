# Change: Add Agent Marketplace Tabs and Full Deprovision Flow

## Why

The current admin flow couples provisioning and enabling, and delete removes only the registry row. Operators need explicit lifecycle control:

- install (provision infrastructure)
- enable (traffic/routing)
- settings access only after enable
- full delete that deprovisions storage (Postgres, Redis, OpenSearch)

When an agent is still discoverable in Docker after deletion, it should remain visible as installable inventory in a separate marketplace view instead of disappearing.

## What Changes

- **Admin UI split**: `/admin/agents` gets two tabs: `Встановлені` and `Маркетплейс`
- **Lifecycle buttons order**:
  - marketplace row: `Встановити`
  - installed but disabled row: `Увімкнути`
  - installed and enabled row: `Налаштування` (plus disable)
- **New install API**: `POST /api/v1/internal/agents/{name}/install` performs provisioning and marks `installed_at`
- **Enable API behavior change**: `POST /api/v1/internal/agents/{name}/enable` requires prior install
- **Delete API behavior change**: `DELETE /api/v1/internal/agents/{name}` performs full deprovision (drop/cleanup storage), disables agent, clears install mark, and keeps the registry row as marketplace candidate
- **Provisioning contract extension**:
  - Postgres strategy provisions main DB and E2E DB (`<db_name>_test` by default)
  - Deprovision removes main + E2E DB and role
  - Redis deprovision flushes configured DB
  - OpenSearch deprovision removes managed indices
- **Migration continuity after code updates**:
  - Postgres-backed agents declare startup migration contract in Agent Card (`storage.postgres.startup_migration`)
  - Agent containers execute migration command on each start in `best_effort` mode
  - Operator restart command (`docker compose restart <agent-service>`) applies new migrations after pull/rebuild
- **Error surfacing**: admin JS displays backend error message instead of only `Помилка: 500`

## Impact

- Affected specs: `agent-registry`, `admin-agent-management`
- Affected code:
  - `apps/core/src/Controller/Api/Internal/*Agent*Controller.php`
  - `apps/core/src/AgentInstaller/*`
  - `apps/core/src/AgentRegistry/*`
  - `apps/core/templates/admin/agents.html.twig`
  - `apps/core/public/css/admin.css`
  - tests in `apps/core/tests/` and `tests/e2e/`
- Breaking behavior:
  - enable no longer auto-provisions
  - delete no longer removes registry row; it transitions the agent back to marketplace state
