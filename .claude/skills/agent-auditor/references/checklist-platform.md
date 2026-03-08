# Platform-Level Checklist

Cross-cutting checks that span all agents.

## P: Platform Integrity

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| P-01 | All agent dirs have Dockerfiles | Compare `apps/*-agent/` with `docker/*-agent/` | All match | — | Mismatch |
| P-02 | All agent dirs have compose services | Compare apps/ dirs with compose.yaml services | All match | — | Missing services |
| P-03 | Compose services have `ai.platform.agent=true` label | Grep compose.yaml per agent | All labeled | — | Some missing |
| P-04 | Makefile has targets for every agent | Check test/analyse/cs-check per agent | Full coverage | Partial | Missing agents |
| P-05 | Convention test suite exists | Glob `tests/agent-conventions/` | Exists with tests | — | Missing |
| P-06 | E2E test suite exists | Glob `tests/e2e/` | Exists with tests | — | Missing |
| P-07 | `sync-skills.sh` exists and is executable | Glob `scripts/sync-skills.sh` | Exists + executable | Exists, not executable | Missing |
| P-08 | `docs/agent-requirements/conventions.md` exists | Glob | Exists | — | Missing |
| P-09 | Agent Card schema exists | Glob `apps/core/config/agent-card.schema.json` | Exists | — | Missing |
| P-10 | All agents listed in index.md | Cross-ref apps/ dirs with index.md | All listed | Some missing | Most missing |
| P-11 | Langfuse service in compose | Grep compose.yaml for langfuse | Present | — | Missing |
| P-12 | Launch instructions exist | Glob `docs/local-dev.md` or `LOCAL_DEV.md` | Exists | — | Missing |
| P-13 | No direct agent-to-agent HTTP calls | Grep all `apps/*-agent/` source dirs for `http://<other-agent-service-name>` patterns. Agents must communicate only via `PLATFORM_CORE_URL` A2A gateway. | No matches across all agents | — | Direct cross-agent URL found |
