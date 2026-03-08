# TOOLS.md - Tool Invocation Contract

## Canonical Endpoints

- Discovery: `GET /api/v1/a2a/discovery`
- Invoke: `POST /api/v1/a2a/send-message`

Use only these endpoints for routing and invocation.

## Invoke Payload

```json
{
  "tool": "knowledge.store_message",
  "input": {
    "message": {
      "platform": "telegram",
      "chat_id": "-100123",
      "message_id": "42",
      "text": "Useful chat message",
      "author": {
        "id": "123",
        "username": "john_doe"
      },
      "sent_at": "2026-03-07T12:00:00Z"
    },
    "metadata": {
      "channel": "telegram.main"
    }
  },
  "trace_id": "trace_...",
  "request_id": "req_..."
}
```

## Expected Response

```json
{
  "status": "completed",
  "result": {
    "stored": true,
    "id": "uuid"
  },
  "agent": "knowledge-agent",
  "tool": "knowledge.store_message",
  "duration_ms": 142,
  "trace_id": "trace_...",
  "request_id": "req_..."
}
```

## Runtime Naming

1. OpenClaw may expose tools with underscore ids (example: `hello_greet`).
2. Use the exact runtime name for invocation.
3. Never assume dotted ids if runtime exposes underscored ids.

## Status Handling

- `status=completed`: summarize and answer user.
- `status=input_required`: ask targeted clarification.
- `status=failed` + `reason=unknown_tool`: capability unavailable.
- `status=failed` + `reason=agent_disabled`: temporary unavailability.
- transport timeout/error: single retry with same `request_id`, then fail.

## Idempotency Rules

1. Reuse the same `request_id` for retried send of the same user turn.
2. Do not issue a second semantic call if the first one is still pending.
3. Keep `trace_id` stable within one interaction chain.

## Security Rules

1. Send `Authorization: Bearer <OPENCLAW_GATEWAY_TOKEN>`.
2. Never print secrets in logs or user-visible output.
3. Never include raw headers in final user responses.
