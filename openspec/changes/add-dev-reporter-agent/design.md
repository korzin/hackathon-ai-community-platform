# Design: Dev Reporter Agent

## Context

The AI Community Platform uses a multi-agent pipeline for overnight development. The pipeline produces local Markdown reports and optionally sends Telegram messages via `--telegram` flag. However, this is a one-way "fire-and-forget" notification with no persistence, no history, and no ability to query results interactively.

The dev-reporter agent bridges this gap by becoming the platform's development observability layer.

## Goals

1. **Persist pipeline results** in a structured database for trend tracking
2. **Deliver rich Telegram notifications** via the existing OpenClaw bot
3. **Support interactive queries** ("what was done?", "show failures", "status") through A2A skills
4. **Minimal footprint** — PHP agent following hello-agent patterns, no new infrastructure

## Architecture

```
pipeline.sh ──(A2A)──→ Core ──→ dev-reporter-agent
                                     │
                                     ├──→ Postgres (pipeline_runs table)
                                     │
                                     └──(A2A)──→ Core ──→ OpenClaw ──→ Telegram
```

### Data Flow

1. **Ingest**: `pipeline.sh` sends a POST to Core's `/api/v1/a2a/send-message` with:
   - `agent: dev-reporter-agent`
   - `intent: devreporter.ingest`
   - `payload: { task, branch, status, duration, agents: [...], report_path }`

2. **Store**: Agent persists to `pipeline_runs` table via Doctrine DBAL

3. **Notify**: After storing, agent calls Core's A2A to invoke OpenClaw's message delivery:
   - Formats an HTML message with pipeline summary
   - Sends via `openclaw.send_message` skill (or direct Telegram Bot API as fallback)

4. **Query**: Users ask OpenClaw questions → routed to `devreporter.status` skill:
   - "What was done last night?" → returns last N pipeline runs
   - "Show failed pipelines" → filters by status=failed
   - "Development status" → aggregate stats (pass rate, avg duration, most active branches)

## Data Model

### Table: `pipeline_runs`

| Column | Type | Description |
|--------|------|-------------|
| id | SERIAL PRIMARY KEY | Auto-increment |
| pipeline_id | VARCHAR(20) | Timestamp-based ID from pipeline.sh |
| task | TEXT | Task description |
| branch | VARCHAR(100) | Git branch name |
| status | VARCHAR(20) | completed, failed |
| failed_agent | VARCHAR(50) NULL | Agent that caused failure |
| duration_seconds | INTEGER | Total pipeline duration |
| agent_results | JSONB | Per-agent status and duration |
| report_content | TEXT NULL | Full Markdown report content |
| created_at | TIMESTAMP | When the pipeline finished |

### Indexes

- `idx_pipeline_runs_status` on `(status)`
- `idx_pipeline_runs_created_at` on `(created_at DESC)`

## Telegram Message Format

### Pipeline Completed
```
🟢 Pipeline COMPLETED

📋 Add streaming support to A2A
🌿 Branch: pipeline/add-streaming-support
⏱ Duration: 45 min

✅ architect — 8 min
✅ coder — 22 min
✅ validator — 5 min
✅ tester — 7 min
✅ documenter — 3 min
```

### Pipeline Failed
```
🔴 Pipeline FAILED at validator

📋 Add streaming support to A2A
🌿 Branch: pipeline/add-streaming-support
⏱ Duration: 35 min

✅ architect — 8 min
✅ coder — 22 min
❌ validator — 5 min (PHPStan: 3 errors)

🔄 Resume: ./scripts/pipeline.sh --from validator --branch pipeline/add-streaming-support "..."
```

### Status Query Response
```
📊 Development Status (last 7 days)

Runs: 12 total (10 passed, 2 failed)
Pass rate: 83%
Avg duration: 42 min

Recent:
✅ Add A2A streaming — 45 min
✅ Fix knowledge search — 28 min
❌ Add marketplace — failed at tester
✅ Update hello-agent docs — 15 min
```

## Skill Contracts

### `devreporter.ingest`

**Input**:
```json
{
  "pipeline_id": "20260307_213045",
  "task": "Add streaming support",
  "branch": "pipeline/add-streaming-support",
  "status": "completed",
  "failed_agent": null,
  "duration_seconds": 2700,
  "agent_results": [
    {"agent": "architect", "status": "pass", "duration": 480},
    {"agent": "coder", "status": "pass", "duration": 1320}
  ],
  "report_content": "# Pipeline Report..."
}
```

**Output**: `{ "status": "completed", "run_id": 42 }`

### `devreporter.status`

**Input**:
```json
{
  "query": "recent",
  "days": 7,
  "limit": 10,
  "status_filter": null
}
```

**Output**: `{ "status": "completed", "result": { "runs": [...], "stats": {...} } }`

### `devreporter.notify`

**Input**:
```json
{
  "message": "Custom notification text",
  "format": "html"
}
```

**Output**: `{ "status": "completed" }`

## Trade-offs

| Decision | Alternative | Reasoning |
|----------|-------------|-----------|
| PHP agent (Symfony) | Python (FastAPI) | Consistency with hello/knowledge agents, reuse DBAL patterns |
| Store in own DB schema | Use Core's DB | Agent isolation principle — each agent owns its data |
| Notify via A2A → OpenClaw | Direct Telegram Bot API | Uses existing infrastructure, respects platform boundaries |
| JSONB for agent_results | Separate table | Simple, queryable, no joins needed for typical queries |

## Security

- No user-facing endpoints — all interaction via A2A (authenticated through Core)
- No secrets stored — Telegram delivery delegated to OpenClaw
- Admin panel behind Core's auth middleware (iframe integration)
