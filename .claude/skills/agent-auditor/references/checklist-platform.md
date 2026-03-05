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
| P-09 | Agent manifest schema exists | Glob `apps/core/config/agent-manifest.schema.json` | Exists | — | Missing |
| P-10 | All agents listed in index.md | Cross-ref apps/ dirs with index.md | All listed | Some missing | Most missing |
| P-11 | Langfuse service in compose | Grep compose.yaml for langfuse | Present | — | Missing |
| P-12 | Launch instructions exist | Glob `docs/local-dev.md` or `LOCAL_DEV.md` | Exists | — | Missing |

## N: Network & Traefik

Checks that every agent service is properly wired into the Traefik reverse proxy
and Docker network topology.

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| N-01 | Agent on `dev-edge` network | Grep compose.yaml for `networks:` under agent service | `dev-edge` listed | — | Missing or wrong network |
| N-02 | `traefik.enable=true` label | Grep compose.yaml for agent's traefik.enable label | Present | — | Missing |
| N-03 | Traefik router defined | Grep for `traefik.http.routers.<agent>.rule` | Present with `PathPrefix` | — | Missing |
| N-04 | Dedicated entrypoint assigned | Grep for `traefik.http.routers.<agent>.entrypoints` | Maps to a unique entrypoint | — | Missing or shares entrypoint with another agent |
| N-05 | Entrypoint defined in `traefik.yml` | Read `docker/traefik/traefik.yml`, check entrypoint used by agent is defined | Defined | — | Missing from static config |
| N-06 | Entrypoint port exposed on Traefik | Grep compose.yaml `traefik` service `ports:` for the entrypoint port | Port mapped | — | Port not mapped |
| N-07 | `edge-auth@docker` middleware applied | Grep for `traefik.http.routers.<agent>.middlewares=edge-auth@docker` | Present | — | Missing (user-facing agent exposed without auth) |
| N-08 | Service port label matches container | Compare `traefik.http.services.<agent>.loadbalancer.server.port` with Dockerfile EXPOSE or known port | Matches | — | Mismatch or missing |
| N-09 | Agent-to-Core env vars present | For agents that register with Core: check `PLATFORM_CORE_URL` and `APP_INTERNAL_TOKEN` in compose env | Both present | One missing | Both missing |
| N-10 | No duplicate entrypoint ports | Cross-check all entrypoint port numbers in `traefik.yml` | All unique | — | Duplicate port found |
