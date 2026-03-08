# Tasks: Add Dev Reporter Agent

## 1. Scaffold Agent

- [x] Create `apps/dev-reporter-agent/` following hello-agent structure
- [x] Set up `composer.json` with Symfony 7, Doctrine DBAL, PHPStan, PHP CS Fixer
- [x] Create `Dockerfile` in `docker/dev-reporter-agent/`
- [x] Create `compose.agent-dev-reporter.yaml` with Traefik labels
- [x] Add health endpoint (`GET /health → {"status": "ok"}`)
- [x] Add manifest endpoint (`GET /api/v1/manifest`) with 3 skills declared

## 2. Database & Migration

- [x] Create Doctrine migration for `pipeline_runs` table
- [x] Create `PipelineRunRepository` with DBAL insert/query methods
- [x] Add indexes on `status` and `created_at`

## 3. A2A Handler & Skills

- [x] Create `DevReporterA2AHandler` with intent routing
- [x] Implement `devreporter.ingest` — validate payload, store to DB, trigger notification
- [x] Implement `devreporter.status` — query pipeline_runs, aggregate stats
- [x] Implement `devreporter.notify` — format and send message via Core A2A → OpenClaw
- [x] Create `A2AController` (POST `/api/v1/a2a`)

## 4. Pipeline Integration

- [x] Add `send_report_to_agent()` function in `scripts/pipeline.sh`
- [x] Call it at pipeline completion (both success and failure paths)
- [x] Use `curl` to POST report via Core's `/api/v1/a2a/send-message`
- [x] Graceful fallback — if agent is unreachable, log warning and continue

## 5. Admin Panel

- [x] Create admin controller with pipeline runs list view
- [x] Show: date, task, branch, status, duration, agent breakdown
- [x] Filter by status (all/passed/failed)
- [x] Use iframe-compatible admin template (harmonized with Core admin)

## 6. Makefile & Config

- [x] Add `dev-reporter-setup`, `dev-reporter-install` targets
- [x] Add `dev-reporter-test`, `dev-reporter-analyse`, `dev-reporter-cs-check`, `dev-reporter-cs-fix`
- [x] Add `dev-reporter-migrate` target
- [x] Update `bootstrap.sh` to include dev-reporter in setup flow (via `make setup` → `dev-reporter-setup`)

## 7. Tests

- [x] Unit tests for `DevReporterA2AHandler` (ingest, status, notify intents)
- [x] Unit tests for `PipelineRunRepository`
- [x] Functional tests for A2A controller (POST with valid/invalid payloads)
- [x] Functional tests for manifest endpoint

## 8. Documentation

- [x] Create `docs/agents/ua/dev-reporter-agent.md`
- [x] Create `docs/agents/en/dev-reporter-agent.md`
- [x] Update `docs/local-dev.md` with dev-reporter setup instructions

## 9. Quality Checks

- [ ] `make dev-reporter-analyse` — zero PHPStan errors
- [ ] `make dev-reporter-cs-check` — no CS violations
- [ ] `make dev-reporter-test` — all tests pass
- [ ] `make conventions-test` — agent compliance tests pass
