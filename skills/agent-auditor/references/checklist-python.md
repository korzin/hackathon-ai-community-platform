# Python/FastAPI Agent Checklist

Checks for agents built with Python + FastAPI.

## S: Structure & Build

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| S-01 | `app/` directory exists | Glob `apps/<agent>/app/` | Exists | — | Missing |
| S-02 | `tests/` directory exists | Glob `apps/<agent>/tests/` | Exists | — | Missing |
| S-03 | `app/main.py` exists | Glob | Exists | — | Missing |
| S-04 | Dockerfile exists | Glob `docker/<agent>/Dockerfile` | Exists | — | Missing |
| S-05 | Dockerfile uses slim Python base | Read Dockerfile FROM line | `python:3.x-slim` | Non-slim image | No Dockerfile |
| S-06 | Compose service defined | Grep compose.yaml for service name | Found | — | Missing |
| S-07 | `requirements.txt` exists | Glob `apps/<agent>/requirements.txt` | Exists | — | Missing |
| S-08 | Dependencies pinned (`==`) | Read requirements.txt, check for `==` | All pinned | Some unpinned | No pins |
| S-09 | `__init__.py` in app/ | Glob `apps/<agent>/app/__init__.py` | Exists | — | Missing |
| S-10 | `conftest.py` in tests/ | Glob `apps/<agent>/tests/conftest.py` | Exists | — | Missing |

## T: Testing

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| T-01 | Test files exist | Glob `apps/<agent>/tests/test_*.py` | >= 1 file | — | 0 files |
| T-02 | Test-to-module ratio | Count .py in app/ vs test_*.py | > 0.2 | 0.05 – 0.2 | < 0.05 |
| T-03 | pytest in requirements | Grep requirements.txt for `pytest` | Found | — | Missing |
| T-04 | httpx in requirements | Grep for `httpx` (FastAPI test client) | Found | — | Missing |
| T-05 | Makefile has test target | Grep Makefile for `<agent>-test` | Found | — | Missing |

## C: Configuration (agents only)

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| C-01 | Manifest route exists | Grep app/ for `"/api/v1/manifest"` | Found | — | Missing |
| C-02 | Manifest returns required fields | Read manifest route, check name/version/capabilities | All present | Missing optional | Missing required |
| C-03 | Compose label `ai.platform.agent=true` | Grep compose.yaml | Found | — | Missing |
| C-04 | Config module exists | Glob `apps/<agent>/app/config.py` | Exists | — | Missing |
| C-05 | Pydantic settings for config | Grep for `BaseSettings` or `pydantic-settings` | Found | — | Not found |

## X: Security

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| X-01 | No hardcoded secrets in app/ | Grep for `password=`, `secret=`, `api_key=`, `token=` literals | None found | — | Found |
| X-02 | `.gitignore` covers sensitive files | Check for `__pycache__/`, `.env`, `*.pyc` | Covered | Partial | Missing |
| X-03 | Auth for protected endpoints | Grep for token/header validation | Found | — | Missing |

## O: Observability

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| O-01 | Health endpoint exists | Grep for `"/health"` route | Found | — | Missing |
| O-02 | Logging configured | Grep for `logging` or `logger` imports | Found | — | Not found |
| O-03 | Langfuse / observability | Grep for `langfuse` or `opentelemetry` | Found | — | Not found |
| O-04 | Compose env has observability vars | Grep compose.yaml for LANGFUSE_ vars | Present | — | Missing |
| O-05 | Log messages include `trace_id` | Grep app/ for `trace_id` in logger calls; every route handler receiving trace_id must pass it to log context | All pass trace_id | Some missing | No trace_id usage |
| O-06 | Log messages include `request_id` | Grep app/ for `request_id` in logger calls | All pass request_id | Some missing | No request_id usage |
| O-07 | Error/warning paths include context | Grep app/ for `logger.warning` and `logger.error` calls; check they include relevant identifiers not just a bare string | All have context | Some bare | Majority bare |
| O-08 | LLM calls log duration and model | If agent calls external LLM, grep for `duration` and `model` near LLM call code | Both logged | One of two | Neither |
| O-09 | OpenSearch logging handler | Grep for OpenSearch or opensearch logging handler in app or config | Configured | — | Missing |

## D: Documentation

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| D-01 | Agent PRD exists | Glob `docs/agents/en/*<slug>*` | Exists | — | Missing |
| D-02 | PRD in both languages | Both ua/ and en/ | Both | One only | Neither |
| D-03 | Listed in index.md | Grep index.md | Found | — | Missing |
| D-04 | OpenAPI / API documentation | Grep for `/docs` or `/openapi.json` endpoint | Found | — | Missing |

## M: Database & Migrations

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| M-01 | `alembic/` directory exists | Glob `apps/<agent>/alembic/` | Exists | — | Missing (if has DB) |
| M-02 | `alembic.ini` exists | Glob | Exists | — | Missing (if has DB) |
| M-03 | Migration files exist | Glob `apps/<agent>/alembic/versions/*.py` | >= 1 | 0 files | No versions dir |
| M-04 | Compose env has DATABASE_URL | Grep compose.yaml | Present | — | Missing (if has DB) |
| M-05 | Makefile has migrate target | Grep Makefile | Found | — | Missing (if has DB) |

## Q: Standards

| ID | Check | How to Verify | PASS | WARN | FAIL |
|----|-------|---------------|------|------|------|
| Q-01 | Type hints on routes | Grep routers/ for `->` return type hints | Most have | Some missing | None |
| Q-02 | Pydantic models for schemas | Glob for `schemas.py` or models/ | Found | — | Missing |
| Q-03 | `.editorconfig` exists | Glob root or app dir | Exists | — | Missing |
