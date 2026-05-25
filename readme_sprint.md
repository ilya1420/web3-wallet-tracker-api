# Sprint Plan: SaaS Evolution

## Planning Horizon

- Sprint length: 2 weeks.
- Planning window: 12 weeks (6 sprints).
- Goal: production-grade multi-tenant SaaS API with predictable delivery, observability, and safe scaling.

## Sprint 1 (Weeks 1-2): Foundation Hardening

### Week 1

- Baseline architecture audit against DDD/Clean boundaries (`Domain`, `Application`, `Infrastructure`, `UI`).
- Close critical security gaps in auth flow (token TTL checks, replay protection, uniform auth errors).
- Add strict DTO validation coverage for all public auth endpoints.
- Add structured error logging with `request_id` and `use_case`.

### Week 2

- Introduce API contract tests for `/api/auth/register`, `/api/auth/login-links`, `/api/auth/confirm-token`.
- Add integration tests for `RequestLoginLinkUseCase` and `ConfirmLoginTokenUseCase`.
- Add domain unit tests for `Email`, token invariants, and security-sensitive rules.
- Update README runbook for local incident triage (auth failures, mail queue delays).

## Sprint 2 (Weeks 3-4): Tenant Safety & Access Control

### Week 3

- Audit tenant-boundaries across user and wallet read/write paths.
- Enforce tenant scoping at repository query level where missing.
- Add authorization regression tests for user/admin role separation.
- Add anti-enumeration checks in auth errors and admin lookup responses.

### Week 4

- Add API tests for forbidden cross-tenant access cases.
- Add rate-limit tests by IP/email/user with stable error contract for `429`.
- Add security logging guardrails to prevent raw token/PII leaks.
- Document access-control matrix and threat model deltas.

## Sprint 3 (Weeks 5-6): Reliability & Async Guarantees

### Week 5

- Introduce/complete outbox for critical async events (auth + registration notifications).
- Define Messenger retry strategy and DLQ policy per critical queue.
- Add idempotency keys/guards for consumers handling login-link and registration events.
- Add queue failure alert rules (retry spikes, DLQ growth).

### Week 6

- Add integration tests for outbox relay and retry behavior.
- Add serialization contract tests for Messenger messages.
- Add handler idempotency tests under duplicate-delivery simulation.
- Extend runbook: replay failed messages and DLQ recovery procedure.

## Sprint 4 (Weeks 7-8): Performance & Data Scaling

### Week 7

- Profile hot paths (`/api/me`, wallets list/detail, token confirmation).
- Eliminate N+1 and reduce over-fetching in Doctrine queries.
- Add/adjust DB indexes for token lookups, tenant filtering, and wallet access patterns.
- Review Redis key strategy (namespacing, TTL, anti key-explosion policy).

### Week 8

- Add pagination standards for heavy collection endpoints.
- Introduce caching for read-mostly projections with explicit invalidation policy.
- Add load-oriented integration checks for auth and wallet flows.
- Document SLO candidates: p95 latency, error rate, queue lag.

## Sprint 5 (Weeks 9-10): Product Features & API Maturity

### Week 9

- Introduce API versioning policy for backward-compatible evolution.
- Add feature flags for risky rollouts (registration policy and async toggles).
- Extend wallet functionality with safe contract evolution (non-breaking fields only).
- Add migration policy checks for zero-downtime schema changes.

### Week 10

- Add contract tests for backward compatibility of existing clients.
- Add admin API enhancements with strict role-based constraints and auditability.
- Add changelog/release-note process tied to API contract changes.
- Update operational docs for feature-flag rollout and rollback.

## Sprint 6 (Weeks 11-12): Operations Readiness & Release Discipline

### Week 11

- Add core dashboards (auth success/fail, registration fail reasons, queue lag, token validation failures).
- Add alerts for security and reliability incidents (brute-force anomalies, elevated 401/429, DLQ size).
- Add correlation between API errors and async failures via shared identifiers.
- Validate disaster recovery procedures for DB/Redis/RabbitMQ in staging.

### Week 12

- Run pre-release hardening checklist and close critical findings.
- Execute end-to-end smoke suite in staging with production-like settings.
- Freeze API contracts for release and confirm migration rollback steps.
- Publish release runbook: rollout sequence, monitoring checkpoints, rollback triggers.

## Definition Of Done For Each Sprint

- Code + config + migrations + tests delivered together.
- Vertical analysis documented for changed features (contract -> app -> domain -> infra -> data/ops).
- Horizontal risks documented with concrete mitigations.
- README/operations docs updated with every behavior or runbook change.
- Observability signals for critical flows are measurable before release.
