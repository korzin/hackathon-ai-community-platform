# Dev Reporter Agent

## Purpose
Dev Reporter Agent receives pipeline execution results via A2A, persists them to PostgreSQL, and delivers Telegram notifications via the OpenClaw bot. It provides an admin panel for browsing pipeline run history with status filtering.

## Features
- `POST /api/v1/a2a` — A2A endpoint accepting 3 skills
- `GET /health` — standard health check (`{"status": "ok", "service": "dev-reporter-agent"}`)
- `GET /api/v1/manifest` — Agent Card per platform conventions
- `GET /admin/pipeline` — admin panel with pipeline run history and status filter

## Skills

| Skill ID | Description | Key Inputs |
|---|---|---|
| `devreporter.ingest` | Store a pipeline run result to DB and trigger Telegram notification | `task`, `status`, `branch`, `pipeline_id`, `duration_seconds`, `agent_results` |
| `devreporter.status` | Query recent pipeline runs and aggregate stats | `limit`, `days`, `status_filter` |
| `devreporter.notify` | Send a custom message via Core A2A → OpenClaw Telegram | `message` |

### `devreporter.ingest` Payload

```json
{
  "intent": "devreporter.ingest",
  "payload": {
    "pipeline_id": "20260308_120000",
    "task": "Add streaming support",
    "branch": "pipeline/add-streaming",
    "status": "completed",
    "duration_seconds": 2700,
    "failed_agent": null,
    "agent_results": [
      { "agent": "Coder", "status": "pass", "duration": 900 },
      { "agent": "Validator", "status": "pass", "duration": 600 }
    ],
    "report_content": "Optional full report text"
  }
}
```

`status` must be `"completed"` or `"failed"`. `task` is required.

### `devreporter.status` Response

```json
{
  "status": "completed",
  "result": {
    "runs": [...],
    "stats": {
      "total": 42,
      "passed": 38,
      "failed": 4,
      "pass_rate": 90.5,
      "avg_duration": 1800.0
    }
  }
}
```

## Database

Table: `pipeline_runs`

| Column | Type | Notes |
|---|---|---|
| `id` | SERIAL PK | Auto-increment |
| `pipeline_id` | VARCHAR(100) | Pipeline run identifier |
| `task` | TEXT | Task description |
| `branch` | VARCHAR(255) | Git branch |
| `status` | VARCHAR(20) | `completed` or `failed` |
| `failed_agent` | VARCHAR(100) | Nullable — which agent failed |
| `duration_seconds` | INTEGER | Total pipeline duration |
| `agent_results` | JSONB | Per-agent results array |
| `report_content` | TEXT | Optional full report |
| `created_at` | TIMESTAMPTZ | Auto-set on insert |

Indexes: `idx_pipeline_runs_status`, `idx_pipeline_runs_created_at`

## Tech Stack
- PHP 8.5 + Symfony 7
- Doctrine DBAL 4 (PostgreSQL)
- Apache (Docker)
- Traefik routing on port **8087**

## Telegram Notifications
On `devreporter.ingest`, the agent formats a message and dispatches it to Core's A2A endpoint (`openclaw.send_message`). This is best-effort and non-blocking — if Core is unreachable, a warning is logged and the ingest still succeeds.

## Makefile Commands
- `make dev-reporter-setup` — build container and install dependencies
- `make dev-reporter-install` — install PHP dependencies
- `make dev-reporter-migrate` — run Doctrine migrations
- `make dev-reporter-test` — run Codeception tests
- `make dev-reporter-analyse` — PHPStan analysis (level 8)
- `make dev-reporter-cs-check` / `make dev-reporter-cs-fix` — check/fix code style

## Admin Panel
Available at `http://localhost:8087/admin/pipeline` (or via Traefik at the configured admin entrypoint).

Shows:
- Stats row: total runs, passed, failed, pass rate, avg duration (last 7 days)
- Filterable table: All / Passed / Failed
- Per-row: date, task, branch, status badge, duration, agent count
