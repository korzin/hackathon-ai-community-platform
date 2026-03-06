# Design: Traefik Access Log Pipeline & Grouped Log View

## Architecture Overview

```
Traefik (stdout JSON) ──► Fluent Bit (sidecar) ──► OpenSearch (_bulk API)
                                                         │
                                                         ▼
                                              platform_logs_YYYY_MM_DD
                                                         │
                                                         ▼
                                              Core Admin /admin/logs
                                              (flat list OR grouped view)
```

---

## Part 1: Fluent Bit Log Pipeline

### Why Fluent Bit over alternatives

| Option | Image Size | Memory | Config | Verdict |
|--------|-----------|--------|--------|---------|
| **Fluent Bit** | ~35 MB | ~15 MB | INI/YAML, native OpenSearch output | **Chosen** — lightest, built-in OpenSearch plugin |
| Fluentd | ~180 MB | ~80 MB | Ruby DSL, plugin needed | Heavier, overkill for single-source collection |
| Filebeat | ~60 MB | ~40 MB | YAML, ES-compatible | Needs file-based input; no native Docker log driver |
| Vector | ~90 MB | ~30 MB | TOML, flexible | Good but Fluent Bit is smaller and sufficient |

### How it works

1. **Input**: Fluent Bit uses the `forward` input or Docker `fluentd` log driver.
   Simpler approach: Traefik writes access logs to a shared volume file, and
   Fluent Bit tails that file.

   Updated plan: Traefik writes access logs to `/var/log/traefik/access.log`
   (JSON, one line per request). Fluent Bit mounts the same volume and tails the
   file via the `tail` input plugin.

2. **Parser**: Traefik already outputs JSON — Fluent Bit uses `json` parser
   (built-in, zero config).

3. **Enrichment** (Fluent Bit `modify` filter):
   - Add `app_name` = `traefik`
   - Rename Traefik fields to match `platform_logs` schema:
     - `time` → `@timestamp`
     - `OriginStatus` → `status` (HTTP status code as string)
     - `RequestMethod` → `request_method`
     - `RequestPath` → `request_uri`
     - `ClientAddr` → `client_ip` (strip port)
     - `Duration` → `duration_ms` (nanoseconds → milliseconds)
     - `ServiceName` → `target_app` (downstream service)
     - `request_X-Trace-Id` → `trace_id`
     - `request_X-Request-Id` → `request_id`
     - `request_X-Service-Name` → `source_app`
   - Set `level_name` = `INFO` (all access logs are informational)
   - Set `channel` = `access`
   - Compose `message` = `"{RequestMethod} {RequestPath} → {OriginStatus} ({duration_ms}ms)"`

4. **Output**: `opensearch` plugin, pointed at `http://opensearch:9200`,
   index pattern `platform_logs_*` with daily suffix (`%Y_%m_%d`).

### Traefik config change

```yaml
# docker/traefik/traefik.yml — update accessLog
accessLog:
  filePath: "/var/log/traefik/access.log"
  format: json
  bufferingSize: 100
  fields:
    defaultMode: keep
    headers:
      defaultMode: drop
      names:
        X-Request-Id: keep
        X-Service-Name: keep
        X-Agent-Name: keep
        X-Feature-Name: keep
        X-Trace-Id: keep
        Content-Type: keep
        Authorization: redact
```

### Docker Compose additions

```yaml
# compose.yaml — new service
fluent-bit:
  image: fluent/fluent-bit:3.2
  volumes:
    - traefik-logs:/var/log/traefik:ro
    - ./docker/fluent-bit/fluent-bit.conf:/fluent-bit/etc/fluent-bit.conf:ro
    - ./docker/fluent-bit/parsers.conf:/fluent-bit/etc/parsers.conf:ro
  depends_on:
    - opensearch
    - traefik
  networks:
    - dev-edge

# Update traefik service volumes:
traefik:
  volumes:
    - ./docker/traefik/traefik.yml:/etc/traefik/traefik.yml:ro
    - /var/run/docker.sock:/var/run/docker.sock:ro
    - traefik-logs:/var/log/traefik

# Add volume:
volumes:
  traefik-logs:
```

### Fluent Bit config files

**`docker/fluent-bit/fluent-bit.conf`**:
```ini
[SERVICE]
    flush        5
    daemon       Off
    log_level    info
    parsers_file parsers.conf

[INPUT]
    name         tail
    path         /var/log/traefik/access.log
    parser       json
    tag          traefik.access
    refresh_interval 5
    mem_buf_limit 5MB

[FILTER]
    name         modify
    match        traefik.access
    add          app_name traefik
    add          level_name INFO
    add          channel access

[OUTPUT]
    name         opensearch
    match        traefik.access
    host         opensearch
    port         9200
    index        platform_logs
    type         _doc
    logstash_format on
    logstash_prefix platform_logs
    logstash_dateformat %Y_%m_%d
    suppress_type_name on
    tls          off
    trace_error  on
```

### Field mapping (Traefik JSON → platform_logs schema)

| Traefik Field | OpenSearch Field | Transform |
|---------------|-----------------|-----------|
| `StartUTC` | `@timestamp` | Direct (ISO 8601) |
| `OriginStatus` | `status` | Cast to string |
| `RequestMethod` | `request_method` | Direct |
| `RequestPath` | `request_uri` | Direct |
| `ClientAddr` | `client_ip` | Strip `:port` suffix |
| `Duration` | `duration_ms` | Nanoseconds ÷ 1,000,000 |
| `ServiceName` | `target_app` | Strip `@docker` suffix |
| `request_X-Trace-Id` | `trace_id` | Direct |
| `request_X-Request-Id` | `request_id` | Direct |
| `request_X-Service-Name` | `source_app` | Direct |
| `request_X-Agent-Name` | — | Stored in context |
| `request_X-Feature-Name` | `event_name` | Direct |
| (composed) | `message` | `"{method} {path} → {status} ({duration}ms)"` |
| (static) | `app_name` | `traefik` |
| (static) | `level_name` | `INFO` |
| (static) | `channel` | `access` |

The exact Lua/modify filter to do the field renaming and composition will be
implemented during apply. The Fluent Bit `lua` filter provides the flexibility
for conditional logic (strip port, convert ns→ms, compose message).

---

## Part 2: Grouped Log View in Admin

### UI Design

The logs page (`/admin/logs`) gains a **"Групування"** (Grouping) select in the
filter form with three options:

1. **Без групування** (None) — current flat list, default
2. **За trace_id** — group logs by `trace_id`
3. **За request_id** — group logs by `request_id`

When grouping is active, the results area changes from a flat table to a list of
collapsible `<details>` cards:

```
┌──────────────────────────────────────────────────────────────┐
│ ▶ trace_id: a1b2c3d4…  │ 12 logs │ 3 apps │ 14:32 – 14:33  │
├──────────────────────────────────────────────────────────────┤
│ ▼ trace_id: e5f6g7h8…  │  8 logs │ 2 apps │ 14:30 – 14:31  │
│  ┌────────────────────────────────────────────────────────┐  │
│  │ 14:30:12 │ INFO  │ traefik │ GET /api/v1/a2a → 200   │  │
│  │ 14:30:12 │ INFO  │ hello   │ A2A request received     │  │
│  │ 14:30:13 │ INFO  │ hello   │ LLM call completed       │  │
│  │ …                                                      │  │
│  └────────────────────────────────────────────────────────┘  │
└──────────────────────────────────────────────────────────────┘
```

### Backend: Aggregation Query

When `group_by` is set, the controller builds a `composite` aggregation:

```json
{
  "size": 0,
  "query": { "bool": { ... existing filters ... } },
  "aggs": {
    "groups": {
      "composite": {
        "size": 20,
        "sources": [
          { "group_key": { "terms": { "field": "trace_id" } } }
        ]
      },
      "aggs": {
        "latest": { "max": { "field": "@timestamp" } },
        "earliest": { "min": { "field": "@timestamp" } },
        "apps": { "terms": { "field": "app_name", "size": 20 } },
        "sample_hits": {
          "top_hits": {
            "size": 50,
            "sort": [{ "@timestamp": "asc" }],
            "_source": true
          }
        }
      }
    }
  }
}
```

- Returns up to **20 groups per page** (configurable).
- Each group includes up to **50 log entries** (sorted oldest-first).
- Pagination uses `after_key` from composite aggregation response.
- Groups sorted by `latest` timestamp descending (newest groups first).

### Controller changes

`LogsController` additions:
- New query param: `group_by` (`''` | `'trace_id'` | `'request_id'`)
- New query param: `after` (JSON-encoded composite after_key for pagination)
- When `group_by` is set, switch to aggregation query builder
- Return `groups` array to template instead of flat `hits`

### Template changes

`logs.html.twig` additions:
- Add grouping select to filter form
- Conditional rendering: flat table when no grouping, `<details>` cards when grouped
- Each group card shows: group key (first 8 chars + link), log count, app badges, time range
- Expanded card shows logs table identical to the flat view format

### App list update

`LogsController::APPS` must include `'traefik'` so the app filter dropdown
offers it as an option.

---

## Trade-offs

| Decision | Alternative | Rationale |
|----------|------------|-----------|
| File-based log transfer (shared volume) | Docker fluentd log driver | Simpler, no Docker daemon config changes needed |
| Fluent Bit Lua filter for field mapping | Pre-process in Traefik or use Logstash | Lua is lightweight; keeps Traefik config clean |
| Composite aggregation for grouping | Client-side grouping in PHP | Server-side grouping handles large datasets; pagination via after_key |
| 20 groups per page | Load all groups | Bounded memory; fast response times |
| 50 logs per group | Unlimited | Covers typical request flows; link to trace page for full view |
