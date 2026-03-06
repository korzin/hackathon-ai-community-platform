# Proposal: Add Traefik Access Log Pipeline & Grouped Log View

## Change ID
`add-traefik-log-pipeline`

## Motivation
Traefik access logs are now enabled (JSON to stdout), but they are not collected
or queryable. Operators cannot see HTTP traffic between services in the admin
panel. Additionally, the existing logs page shows a flat list — there is no way
to group related logs by `trace_id` or `request_id` to understand a full request
flow at a glance.

## Goals
1. Ship Traefik JSON access logs into the same `platform_logs_*` OpenSearch
   indices that the admin already queries.
2. Add a **grouping mode** to the admin logs page: group by `trace_id` or
   `request_id` with collapsible UI (collapsed by default, expand to see all
   related logs sorted by time).

## Scope
- **Infrastructure**: add Fluent Bit sidecar container that tails Traefik stdout,
  parses JSON, enriches with `app_name=traefik`, and bulk-inserts into OpenSearch.
- **Backend**: extend `LogsController` with a `group_by` query parameter
  (`trace_id` | `request_id`) that switches to an aggregation query and returns
  grouped results.
- **Frontend**: add a grouping toggle to the logs filter form; render grouped
  results as collapsible `<details>` elements, each showing the group key, count,
  time range, and expandable log entries sorted by `@timestamp ASC`.

## Out of Scope
- Log rotation / retention changes for Traefik-specific indices.
- Dashboards or advanced analytics (OpenSearch Dashboards, Grafana).
- Changes to how other agents ship their logs (they continue using Monolog).

## Capabilities
| Capability | Spec Delta |
|------------|-----------|
| traefik-log-pipeline | New — Fluent Bit collects Traefik access logs into OpenSearch |
| grouped-log-view | New — Admin logs page supports grouping by trace_id / request_id |

## Affected Specs
- `observability-integration` — extended with Traefik access log collection.

## Risks & Mitigations
| Risk | Mitigation |
|------|-----------|
| Fluent Bit adds memory overhead | Minimal: ~15 MB RSS; bounded buffer (5 MB). |
| High-volume access logs flood OpenSearch | Reuses existing retention & cleanup policy; Traefik logs share the daily index and the same max-size-gb setting. |
| Grouped queries are slow on large indices | Use `composite` aggregation with size limit (20 groups per page); sorted by latest timestamp descending. |
