# A2A Protocol For Agents

## Goal

`A2A` (`agent-to-agent`) is the internal interaction contract between the core-agent, specialized agents, and other platform modules that perform autonomous tasks.

## Purpose

A2A exists so that:

- the core-agent can delegate work to other agents
- responses stay uniform regardless of the implementation of each agent
- clarification loops and multi-step orchestration run through one contract

## Core Requirements

- A2A should be the unified protocol for all agent invocations
- every agent should expose a clear discovery point or registry description
- requests and responses must use a stable structure
- call correlation must be explicit (`request_id`, `trace_id`, and optionally `conversation_id`)

## Minimum Interaction Model

- a `request` contains context, intent, payload, and metadata
- a `response` returns either a result, a clarification need, or an error
- an agent must not return an unstructured arbitrary payload without a status

## Baseline Response Statuses

- `completed` — the agent completed the task successfully
- `needs_clarification` — the agent requires additional input
- `failed` — the agent could not complete the task correctly
- `queued` — an optional future-facing status for async flows

## Clarification Loop

- when an agent returns `needs_clarification`, it must state what is missing
- the core-agent or orchestrator must be able to send a follow-up answer in the same logical chain
- clarification must not break request correlation

## Payload Requirements

- payloads must be structured
- sources, evidence, or supporting context should be passed as explicit fields rather than only plain text
- agent-specific fields are allowed if they do not break the base envelope contract

## Timeouts, Retries, Idempotency

- a repeated call with the same `request_id` must not create unpredictable duplication
- timeouts should be an explicit part of the invocation contract
- retry behavior should be controlled and must not create retry storms

## Agent Requirements

- every agent should either support A2A directly or be adapted through a platform-owned wrapper
- an agent must not break the baseline envelope contract
- errors must be returned in a structured way, not silently

## Step-Level Observability Requirements

Every critical A2A chain step should emit structured logs with:

- `event_name`
- `step`
- `source_app`
- `target_app` (when available)
- `trace_id`
- `request_id`
- `status`
- `duration_ms` (for terminal events)
- `sequence_order`

For debugging, services should capture sanitized context fields:

- `step_input`
- `step_output`
- `request_headers` (sanitized)
- `capture_meta` (`is_truncated`, `original_size_bytes`, `captured_size_bytes`, `redacted_fields_count`, `truncated_values_count`)

Secrets (`token`, `authorization`, `api_key`, `secret`, `password`, `cookie`) must be redacted before log persistence.

## Out Of Scope For MVP

- a complex streaming protocol
- inter-cluster agent federation
- arbitrary incompatible agent-specific transport models
