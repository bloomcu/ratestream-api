# Agent Guidance

## Testing Expectations

When new functionality is added, add tests for the behavior that is actually registered, reachable, and intended to be used by the current product. Do not add active coverage for scaffold routes, commented-out routes, future product decisions, or infrastructure that does not exist yet. Put those items in `TODO.md` under a future development or product review section instead.

For new or changed routes, cover the full behavioral boundary:

- the successful request for the intended user role
- unauthenticated access when the route is protected
- organization access boundaries for organization-scoped routes
- super-admin behavior when the current feature supports it
- validation failures for required fields and invalid foreign keys
- side effects in the database, storage, mail, queue, HTTP client, and logs
- token, secret, and sensitive-data handling when auth or integrations are involved

For organization-scoped resources, include same-organization and cross-organization assertions. Route model binding alone is not enough unless the route is explicitly scoped and the test proves the boundary.

For auth and user administration, include tests for token lifecycle, role boundaries, invitation lifecycle, and deletion behavior. If a hardening rule has not been implemented yet, document it in `TODO.md` as development work rather than writing a skipped or speculative active test.

## Test Naming

Use precise test names that describe exactly what is being checked. Avoid broad names such as `cannot_access_admin_routes` when the test only covers one route family. Prefer names like `editors_cannot_access_the_rates_sync_key_route` or `rate_batch_rejects_a_rate_group_from_another_organization`.

When a test intentionally documents a removed or deferred legacy behavior, keep a skipped stub only if it provides useful chain of custody. The skip message should explain why the behavior is not active coverage.

## Coverage Conventions

Feature tests should exercise HTTP endpoints through the real route and middleware stack whenever possible. Unit tests are appropriate for isolated service behavior, model behavior, or jobs that can be tested without routing.

Keep public endpoint tests separate from authenticated editing/admin tests. Public rates, authenticated rate editing, rate group lifecycle, sync key administration, CSV parsing, organization administration, invitations, users, security boundaries, and smoke coverage each have their own test classes.

Smoke tests should verify that active product route signatures are registered and that representative requests do not produce server errors. They are not a substitute for feature-specific assertions.

Use explicit database assertions for ownership and side effects. For soft-deleted records, assert soft deletion rather than only checking response status. For streamed CSV responses, inspect the streamed content and verify draft or cross-organization data is absent.

## Current Testing Patterns

Use `actingAs($user, 'sanctum')` for authenticated API requests. Public endpoints should be called without authentication unless the route is intentionally protected.

Use fakes for external side effects:

- `Mail::fake()` for invitation emails
- `Queue::fake()` when a request would dispatch website sync jobs
- `Storage::fake()` for file and CSV tests
- `Http::fake()` for webhook calls
- a local `UncompromisedVerifier` binding when password validation would otherwise call an external uncompromised-password service

When testing logs around webhooks or secrets, assert that sensitive values are not present in the logged context.

Tests run against SQLite in memory via `phpunit.xml`. Do not rely on MySQL-only behavior unless the test explicitly documents that gap.

Factories are not always available for older scaffold domains. Prefer using existing factories when present, but direct model creation is acceptable in tests when it is clearer and matches current local patterns.

## TODO Discipline

Keep `TODO.md` accurate:

- `Existing Active Coverage` should list only tests that exist and behavior they actually assert.
- `Laravel 10 Migration-Critical Remaining Coverage` should include only coverage needed to make the framework upgrade safe.
- product decisions, scaffold routes, audit logging, role-policy changes, expiration rules, and other unimplemented behavior belong in separate action-item sections.

When adding tests, update `TODO.md` in the same change so the coverage inventory stays aligned with the suite.

## Useful Commands

Run focused tests while developing:

```bash
vendor/bin/phpunit tests/Feature/Rates/RateEditingEndpointsTest.php
```

Run the full suite before migration or dependency work:

```bash
vendor/bin/phpunit
```
