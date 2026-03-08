# Proposal: Add Dev Reporter Agent

## Why

The multi-agent pipeline (`scripts/pipeline.sh`) runs overnight and produces reports in local files. There is no way to query pipeline results, track development trends, or get summaries without SSH-ing into the machine. The platform needs an agent that:

1. Receives pipeline results via A2A and persists them
2. Posts formatted summaries to Telegram through the existing OpenClaw bot
3. Answers interactive questions about development status (e.g., "what was done last night?", "show failed pipelines")

## What Changes

### New Capability: `dev-reporter`

A new PHP agent (`apps/dev-reporter-agent/`) following the hello-agent scaffold:

- **A2A skills**:
  - `devreporter.ingest` — receives a pipeline report JSON, stores it in Postgres
  - `devreporter.status` — returns current development status (recent runs, pass/fail stats)
  - `devreporter.notify` — sends a formatted message to Telegram via OpenClaw's bot
- **Admin panel**: simple view of pipeline history (list of runs with status, duration, branch)
- **Telegram integration**: uses Core's A2A bridge → OpenClaw to deliver messages to the existing chat

### New Spec: `dev-reporter`

Defines the A2A contract, data model, and Telegram message format.

### Modified: `scripts/pipeline.sh`

At pipeline completion, calls `devreporter.ingest` via Core's A2A endpoint to persist the report and trigger Telegram notification.

## Impact

- **New app**: `apps/dev-reporter-agent/` (PHP 8.5 + Symfony 7)
- **New compose file**: `compose.agent-dev-reporter.yaml`
- **New DB table**: `pipeline_runs` in agent-specific schema
- **Modified**: `scripts/pipeline.sh` — adds A2A call at end
- **Modified**: `Makefile` — adds dev-reporter targets
- **No breaking changes** to existing agents or Core
