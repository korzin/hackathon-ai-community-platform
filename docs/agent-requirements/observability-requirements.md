# Agent Observability Requirements

This document defines the minimum observability contract for all agents and orchestrators in the platform, including multi-stack A2A flows (`OpenClaw` -> `core` -> `PHP/Python/TypeScript` agents).

## 1. Scope

These requirements apply to:

- `OpenClaw` (entrypoint orchestrator)
- `core` (A2A Gateway — routing and bridging)
- all specialized agents exposing A2A endpoints

## 2. Required Trace Context Propagation

Every inter-service HTTP call MUST propagate W3C trace context headers:

- `traceparent`
- `tracestate` (if present)
- `baggage` (if present)

Platform correlation headers MUST also be propagated:

- `x-request-id`
- `x-conversation-id` (if available)
- `x-agent-run-id`
- `x-a2a-hop`

Rules:

- services MUST forward these headers unchanged unless explicitly rotating `x-agent-run-id` for a new local run
- `x-a2a-hop` MUST increment by 1 on every A2A hop
- missing `traceparent` at entrypoint is allowed only for external requests; entrypoint MUST create a new root trace

## 3. Required A2A Metadata

A2A request envelopes MUST contain correlation metadata:

```json
{
  "request_id": "uuid-or-stable-id",
  "trace_id": "otel-trace-id-or-alias",
  "conversation_id": "optional-conversation-id",
  "agent_run_id": "current-agent-run-id",
  "parent_agent_run_id": "optional-parent-run-id",
  "hop": 2
}
```

Rules:

- `request_id` MUST be stable for retries/idempotency
- `hop` MUST match `x-a2a-hop`
- agents MUST return `request_id` in the response envelope

## 4. Required LLM Call Tagging

Every outbound LLM API call (chat completions, embeddings) MUST include:

### `tags` field (top-level array in request body)

```json
"tags": ["agent:<agent-name>", "method:<feature-name>"]
```

- `agent:<agent-name>` — stable service identifier (e.g. `agent:hello-agent`, `agent:core`)
- `method:<feature-name>` — skill or operation being performed (e.g. `method:a2a.hello.greet`, `method:knowledge.embedding`)

### `metadata` field (top-level object in request body)

```json
"metadata": {
  "request_id": "...",
  "trace_id": "...",
  "trace_name": "...",
  "session_id": "...",
  "generation_name": "...",
  "trace_metadata": {
    "request_id": "...",
    "session_id": "...",
    "agent_name": "...",
    "feature_name": "..."
  }
}
```

- `request_id` and `trace_id` are REQUIRED
- `session_id` is REQUIRED for grouping and SHOULD be stable across all agent calls for one client message
- `trace_name`, `generation_name`, `trace_metadata` are RECOMMENDED for Langfuse readability and filtering

Tags are used for filtering and grouping in LiteLLM spend logs and dashboard.

## 5. Required Span Model

Each service MUST create these spans where applicable:

1. `agent.request` or `orchestrator.request` for inbound request handling
2. `a2a.call` for outbound A2A HTTP request
3. `llm.inference` for each model call
4. `tool.call` for each local tool execution
5. `storage.query` for DB/search/vector operations that materially affect latency or output

## 6. Required Span Attributes

Minimum attributes for `llm.inference` spans:

- `agent.name`
- `agent.version`
- `llm.provider`
- `llm.model`
- `llm.usage.input_tokens`
- `llm.usage.output_tokens`
- `llm.cost.usd` (if available)

Minimum attributes for `a2a.call` spans:

- `a2a.target_agent`
- `a2a.intent` or `a2a.tool`
- `http.method`
- `http.status_code`
- `x-request-id`
- `x-agent-run-id`

## 7. Logging Requirements

Structured logs MUST include:

- `timestamp`
- `level`
- `service`
- `trace_id`
- `span_id` (if available)
- `request_id`
- `agent_run_id`
- `event`
- `error.code` / `error.message` (for failures)

For step-level A2A/OpenClaw traces, logs MUST additionally include:

- `event_name`
- `step`
- `source_app`
- `target_app` (when applicable)
- `status`
- `sequence_order`
- `duration_ms` (for terminal events)
- sanitized step context fields: `step_input`, `step_output`, `capture_meta`

Plain text logs without correlation fields are NOT sufficient.

## 8. Prompt and Data Safety

- production logs/traces MUST not store raw secrets, API keys, or access tokens
- sensitive user data MUST be redacted or hashed before persistence
- full prompt/response body logging MUST be configurable per environment

## 9. OpenClaw Entry Point Rules

`OpenClaw` MUST:

- create the root trace for external user requests
- assign initial `x-request-id`, `x-agent-run-id`, and `x-a2a-hop=0`
- propagate trace/correlation headers to `core`
- emit spans for routing decisions and downstream tool/agent calls

## 10. Compliance Gate

A service is considered observability-compliant only if:

1. it propagates required headers
2. it includes `tags` and `metadata` in all LLM API calls
3. it emits required spans with required attributes
4. it returns correlated A2A responses
5. it uses structured logs with trace correlation
