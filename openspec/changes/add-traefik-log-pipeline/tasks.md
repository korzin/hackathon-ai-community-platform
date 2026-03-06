# Tasks: add-traefik-log-pipeline

## Phase 1: Fluent Bit Pipeline (infrastructure)

- [ ] **1.1** Update `docker/traefik/traefik.yml`: change `accessLog` from stdout to
  `filePath: /var/log/traefik/access.log` with `bufferingSize: 100`.
- [ ] **1.2** Add `traefik-logs` named volume to `compose.yaml` volumes section.
- [ ] **1.3** Mount `traefik-logs` volume on the `traefik` service at `/var/log/traefik`.
- [ ] **1.4** Create `docker/fluent-bit/fluent-bit.conf` with `tail` input,
  `modify` + `lua` filters for field mapping, and `opensearch` output.
- [ ] **1.5** Create `docker/fluent-bit/parsers.conf` with JSON parser definition.
- [ ] **1.6** Create `docker/fluent-bit/transform.lua` with field renaming logic:
  `ClientAddr` port stripping, `Duration` ns→ms, `ServiceName` `@docker`
  stripping, message composition, header field extraction.
- [ ] **1.7** Add `fluent-bit` service to `compose.yaml` with volume mounts,
  `depends_on: [opensearch, traefik]`, and `dev-edge` network.
- [ ] **1.8** Verify: `make up`, send HTTP request through Traefik, confirm document
  with `app_name=traefik` appears in OpenSearch via
  `curl localhost:9200/platform_logs_*/_search?q=app_name:traefik`.

## Phase 2: Admin Backend — Grouped Query (core PHP)

- [ ] **2.1** Add `'traefik'` to `LogsController::APPS` constant.
- [ ] **2.2** Add `group_by` and `after` query parameters to `LogsController::__invoke`.
- [ ] **2.3** Implement `buildGroupedQuery()` method using composite aggregation on
  the `group_by` field, with sub-aggregations: `min(@timestamp)`,
  `max(@timestamp)`, `terms(app_name)`, `top_hits(size=50, sort=@timestamp:asc)`.
- [ ] **2.4** Parse aggregation response into `groups` array: each entry has
  `key`, `count`, `earliest`, `latest`, `apps`, `hits`, `after_key`.
- [ ] **2.5** Pass `groups`, `group_by`, `after_key` to template when grouping active;
  keep existing flat flow when `group_by` is empty.

## Phase 3: Admin Frontend — Grouped UI (Twig + CSS)

- [ ] **3.1** Add "Групування" select to `logs.html.twig` filter form with options:
  `""` (Без групування), `trace_id` (За trace_id), `request_id` (За request_id).
- [ ] **3.2** Add conditional block: when `group_by` is set, render grouped cards
  instead of the flat table. Use `<details>` elements, collapsed by default.
- [ ] **3.3** Group card summary: group key (first 8 chars + link for trace_id),
  log count, app badges, time range.
- [ ] **3.4** Group card body: log entries table matching flat view format
  (time, level, app, message, exception).
- [ ] **3.5** Add grouped pagination: "Далі" button using `after_key` for the next
  page of composite results.
- [ ] **3.6** Add CSS for `.log-group-card` styling consistent with existing
  glass-card and trace-timeline patterns.

## Phase 4: Verification

- [ ] **4.1** Start the full stack (`make up`). Send requests to different agents.
  Confirm Traefik access logs appear in admin `/admin/logs` with `app=traefik` filter.
- [ ] **4.2** Test grouping by `trace_id`: confirm related logs from multiple apps
  appear together in the same collapsed card.
- [ ] **4.3** Test grouping by `request_id`: confirm per-request grouping works.
- [ ] **4.4** Test pagination: generate enough traffic for >20 groups, verify
  "Далі" button loads next page.
- [ ] **4.5** Run `make analyse` and `make cs-check` on core — fix any issues.

## Dependencies

- Phase 2 and 3 can run **in parallel** (backend + frontend).
- Phase 1 must complete before Phase 4 verification.
- Phase 2 + 3 must complete before Phase 4 verification.
