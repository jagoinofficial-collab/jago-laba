# Step 13 — HTTPS, callback sandbox, and queue on staging host

## Decision

**BLOCKED for host verification.** No staging hostname/base URL or operator
access was provided in the repository/task context. Consequently no request
was sent to an external host, no provider console was changed, and no cron or
worker was installed or started. The checks below are local regression
evidence only; they are not a substitute for host-level evidence.

## Local regression evidence (2026-11-01)

- Full PHPUnit suite on PHP 8.4.27: **58 tests / 532 assertions — PASS**.
- Focused hosting contract, OAuth sandbox, and queue suite: **14 tests / 163
  assertions — PASS**.
- Hosting contract tests cover root-public callback construction and the
  legacy callback path switch. OAuth tests cover sandbox-only mode, callback
  replay, origin/session boundaries, failure/retry boundaries, multi-store,
  and foreign-company isolation. Queue tests cover bounded jobs, checkpoints,
  retry/failure handling, manual CSRF mode, scheduler registration, and atomic
  dispatch using test fixtures.
- No staging HTTPS request, TLS certificate inspection, real reverse-proxy
  header/cookie observation, external provider sandbox callback, or host cron /
  worker lifecycle was exercised.

The first PHPUnit attempt could not start because the default PHP 8.4 CLI had
no loaded `mbstring`/SQLite/OpenSSL extensions. The same installed PHP 8.4.27
binary was rerun with its matching extensions loaded explicitly; the focused
and full suites then passed. No project code or PHP configuration file was
changed for that run.

## Required operator inputs

Before executing host checks, provide/provision these through the approved
deployment or secret-management channel (never paste credentials into chat):

1. HTTPS staging origin and the intended public document-root/base path.
2. Staging operator access and a non-production test identity covering the
   required roles/workspaces/companies/stores.
3. Trusted proxy IP/CIDR list, host allowlist, and the actual web-server TLS /
   redirect configuration.
4. Provider sandbox app registration with the exact HTTPS callback URI,
   sandbox credentials, and a seller-owned sandbox account that may authorize
   the test store.
5. Staging cron/worker operator access, queue health/heartbeat path, and
   confirmation that Live provider URLs/credentials and unrelated write paths
   are disabled during the run.

## Host-level execution checklist (pending)

- Verify certificate chain/hostname, HTTP-to-HTTPS behavior, host allowlist,
  absolute URLs, and trusted-proxy handling from the real staging edge.
- Observe session cookie attributes (`Secure`, `HttpOnly`, `SameSite`) and
  validate session persistence/path across the real proxy and document root.
- Run the callback against the registered provider **sandbox** only; test
  state/replay/origin/session and multi-store/foreign-company boundaries. Do
  not use Live credentials.
- Verify exactly one `schedule:run` trigger; exercise bounded queue worker
  startup/stop, overlap prevention, timeout/restart, failed-job retry, and
  heartbeat with staging-only data and sandbox outbound endpoints.
- Save redacted request/response headers, callback IDs, cron records, worker
  logs, and before/after queue/checkpoint counts. Never save tokens or PII.

This step cannot be marked complete until the above checks run on the actual
staging host and the results are reviewed. Production cutover remains out of
scope.
