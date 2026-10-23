# TASK-011 — Scaling and final acceptance evidence

**Дата:** 2026-10-23

## Scaling path

The 1,000,000-comment and 100,000-users/day figures are planning targets from the approved backlog, not measured capacity claims. Existing evidence is limited to the local MySQL 8.4 query plans and an invariant five SQL queries per uncached comments page in `TASK-010-quality-report.md`.

| Area | Current design and evidence | Evolution when measurements require it |
|---|---|---|
| Laravel/API | Stateless application code; session state is in Redis. Begin with one app instance and record request rate, PHP-FPM saturation, error rate and p95 latency. | Add stateless PHP-FPM instances behind a load balancer; keep shared config/secrets and deploy the same immutable image. |
| MySQL | InnoDB indexes cover root pagination, tree retrieval, ownership and guest sort support. Root pages select 25 roots and load their full subtrees. TASK-010 EXPLAIN used the intended indexes on a small local database. | Load representative data; inspect slow queries and `EXPLAIN ANALYZE`; tune the existing indexes/query shape first. Upgrade database resources before adding read replicas. Keep migrations as the schema authority. |
| Pagination and trees | Offset pages contain 25 roots, with complete subtrees. Cache miss query count stayed at five when replies grew from 10 to 18. | Measure deep offsets, large subtrees and response sizes. Consider keyset pagination or lazy branch loading only when the measured UX/query cost warrants a product/API change. |
| Redis | Stores sessions, rate-limit/cache state and shared comment-list generations. | Move to dedicated/HA Redis when memory, throughput or failover data requires it; monitor eviction, latency and cache hit rate. |
| RabbitMQ | Queued mail and `comment.created` broadcasting; worker consumes `broadcasts,mail`. Automated checks cover dispatch configuration, and the VPS recheck observed one `BroadcastCommentCreated` job complete successfully. SMTP mail delivery is not claimed as end-to-end tested. | Add workers and broker capacity based on queue age, ready/unacked depth and retry rates; evaluate HA topology and alert on dead letters/failed jobs. |
| Reverb/WebSocket | One Reverb process; browser receives created events and reconciles via REST. | Run multiple Reverb nodes with shared Redis and a load balancer configured for WebSocket upgrades; watch concurrent connections and event-loop saturation. |
| Media | Private Laravel local disk; image processing is synchronous. | Move to S3-compatible object storage through Laravel Filesystem; add CDN only for appropriate public/static delivery. Preserve private attachment authorization and plan a one-time file migration. |
| Delivery | Production Compose runs the web, PHP-FPM, Reverb, worker and stateful services as separate processes. | Build/publish immutable images through CI, deploy with an approved release step, back up MySQL/media, and run migrations once per release before scaling app replicas. |

### Capacity validation still required

- No representative load test was run for one million comments or 100,000 daily users.
- No populated-page latency/size, media throughput, cache hit rate under load or concurrent WebSocket capacity has been measured.
- TASK-010 EXPLAIN estimates came from a small local data set; mixed-author sort uses a temporary table/filesort as expected by the approved query strategy.
- Capacity claims must wait for a seeded representative data set and a documented load profile; change indexes or pagination only from that evidence.

## SOW Definition of Done audit

The completed TASK-003–010 specifications and regression tests provide evidence for the guest/registered flows, comment tree/sorting/pagination, sanitization/preview, attachment lifecycle, API/SPA, Redis/RabbitMQ/Reverb integration, Docker development environment, OpenAPI, security review and local quality checks. Detailed evidence lives in the completed task specs and TASK-010 quality report.

| Requirement | Evidence / status |
|---|---|
| Functional API/SPA, auth, comments, tree, sorting, attachments | Completed task specs TASK-003–009 and current backend test suite; browser walkthrough evidence is in TASK-009. |
| Performance/security/OpenAPI/CI | `docs/reports/TASK-010-quality-report.md`; local checks passed, hosted CI run awaits push. |
| Production build and deployment procedure | Production images built; separate Compose stack started; `/`, `/up` and `/api/v1/comments` returned HTTP 200. PHP image contains compiled Vite manifest, has no `public/hot`, and has no Node executable. |
| Public test deployment | VPS deployment is live at `[QA endpoint omitted]`; HTTPS app/health/API and Reverb WebSocket smoke checks pass. It is a temporary IP-based QA hostname, not an availability guarantee. |
| Database diagram | Workbench-importable SQL was imported into a disposable MySQL 8.4 schema. Column metadata, indexes, foreign keys and CHECK constraints matched the schema created by Laravel migrations. |
| Capacity target evidence | **Open evidence gap:** targets are documented as planning objectives; no production-scale load test was run. |
| Final repository status/history | REVIEW worktree contains only planned TASK-011 edits/artifacts; last 20 commit subjects contain `TASK-XXX`; `git diff --check` passed. No `.env.production`, generated image assets, or build outputs are included in the repository diff. |

## TASK-011 production smoke checks

- Production images `comments-app:production` and `comments-web:production` built successfully from the multi-stage Dockerfile.
- Isolated production Compose stack started with disposable test credentials; MySQL migrations completed successfully and app, MySQL, Redis, RabbitMQ, queue-worker, Reverb and Nginx containers ran.
- `GET /`, `GET /up` and `GET /api/v1/comments?page=1&sort_by=date&direction=desc` returned HTTP 200.
- Runtime image checks passed: `public/build/manifest.json` exists, `public/hot` is absent, and Node is not installed in PHP runtime.
- Workbench DDL imported successfully into an isolated MySQL schema. Column, index and constraint metadata comparisons against migration-created schema were identical.
- Full backend regression suite: 56 tests / 349 assertions passed. Pint: 77 files passed. Vite production build passed. Development and production Compose configuration validation passed.
- Local smoke credentials were placeholders and the stack was isolated; it did not validate external SMTP, production reCAPTCHA, public TLS, cloud networking, hosted CI or production-scale load.

### VPS recheck — 2026-10-23

- Public HTTP redirects to HTTPS (`308`); HTTPS `/`, `/up`, and comments API return `200` with valid certificate verification. Sanctum CSRF endpoint returns `204` and both cookies include `Secure`; Reverb WebSocket upgrade returns `101`.
- All seven Compose services are running; app, MySQL, Redis, RabbitMQ and web report healthy. The queue worker process uses the configured RabbitMQ queues. From the app container, Redis `PING` and MySQL `SELECT 1` succeed.
- During recheck, the RabbitMQ worker and Reverb were found running as root. Both Compose services now explicitly run as `www-data`; after recreation, both processes report UID `www-data` and Reverb WebSocket upgrade still returns `101`.
- Docker web remains bound to `127.0.0.1:8080`. The app storage volume is mounted at `/var/www/html/storage`; database, Redis and RabbitMQ each have persistent named volumes.
- SMTP host TCP connectivity was checked, but no email was sent. A real reCAPTCHA challenge, browser-based publication/login flow, backup restore and production load profile were not exercised.
- Current VPS snapshot was deployed over repository revision `fc5890a`; task changes are uncommitted. After commit/push, align the VPS checkout to the committed revision for reproducible release tracking.
