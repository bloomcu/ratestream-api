# TODO

## Broader Test Suite Work

- Build coverage around routes that are currently registered and reachable. Do not add tests for commented-out media, organization comments, public site show, or disabled single-rate CRUD endpoints unless those routes are restored.

### Existing Active Coverage

- `tests/Unit/Domain/Organizations/OrganizationTest.php`
  - organizations generate slugs
  - organizations have many users
  - Teams are intentionally excluded from organization unit coverage until we decide whether the scaffold team routes are still active product surface.
  - Stale legacy assertions for default slug route keys and media relationships are intentionally excluded from current active coverage.

- `tests/Feature/Base/Auth/AuthControllerTest.php`
  - login succeeds with valid credentials and rejects invalid credentials
  - login revokes existing tokens before issuing a new Sanctum token
  - authenticated users can fetch `auth/me`; unauthenticated users cannot
  - logout revokes the bearer token
  - registration creates the organization and admin user, ensures a default rate group exists, and returns a usable Sanctum token
  - registration token can fetch `auth/me`
  - logout revokes a registration token
  - the user can log in again after logging out the registration token
  - the old registration token is rejected after logout
  - password-forgot returns the same success response for known and unknown valid email addresses
  - password-reset accepts a valid token, updates the password, revokes existing Sanctum tokens, and invalidates the reset token
  - password-reset rejects an invalid token
  - registration rejects existing user emails and emails with pending invitations
- `tests/Feature/Base/Invitations/InvitationControllerTest.php`
  - `GET /api/{organization:slug}/invitations/{invitation:uuid}` returns the invitation for the requested organization
  - `GET /api/{organization:slug}/invitations/{invitation:uuid}` rejects an invitation from another organization
  - `GET /api/{organization:slug}/invitations` lists only invitations for the requested organization
  - `POST /api/{organization:slug}/invitations` lets current organization users create invitations and sends the invitation email
  - `POST /api/{organization:slug}/invitations` rejects organization users creating invitations for another organization
  - `POST /api/{organization:slug}/invitations` lets super admins create invitations for any organization
  - `POST /api/{organization:slug}/invitations` rejects existing user and invitation emails
  - `DELETE /api/{organization:slug}/invitations/{invitation:uuid}` removes an invitation from the requested organization
  - `DELETE /api/{organization:slug}/invitations/{invitation:uuid}` rejects an invitation from another organization
  - `POST /api/auth/register/invitation/{invitation:uuid}` creates the user in the invitation organization and consumes the invitation
  - `POST /api/auth/register/invitation/{invitation:uuid}` uses the invitation email instead of the request-supplied email
- `tests/Feature/Base/Organizations/OrganizationControllerTest.php`
  - unauthenticated users cannot access organization routes
  - `GET /api/organizations` returns only the authenticated user's organization for organization users
  - `GET /api/organizations` returns all organizations alphabetically for super admins
  - `GET /api/organizations/{organization:slug}` returns the user's own organization by slug
  - `GET /api/organizations/{organization:slug}` rejects another organization for organization users
  - `GET /api/organizations/{organization:slug}` allows super admins to view any organization
  - `PUT /api/organizations/{organization:slug}` updates the user's own organization title and `rates_domain`, including the current local/dev HTTP allowance
  - `PUT /api/organizations/{organization:slug}` rejects another organization for organization users
  - `POST /api/organizations` creates an organization and ensures a default rate group
  - `DELETE /api/organizations/{organization:slug}` rejects another organization for organization users
  - `DELETE /api/organizations/{organization:slug}` allows super admins to delete an empty organization
- `tests/Feature/CSV/CSVControllerTest.php`
  - `GET /api/{organization:slug}/csv/{file}` parses valid CSV files into column and row previews
  - `GET /api/{organization:slug}/csv/{file}` rejects duplicate column UIDs
  - `GET /api/{organization:slug}/csv/{file}` rejects a missing `Unique ID` header cell
  - `GET /api/{organization:slug}/csv/{file}` rejects missing column UIDs
  - `GET /api/{organization:slug}/csv/{file}` rejects missing column names
  - `GET /api/{organization:slug}/csv/{file}` rejects missing row UIDs
  - `GET /api/{organization:slug}/csv/{file}` rejects duplicate row UIDs
- `tests/Feature/Rates/RateBatchControllerTest.php`
  - batch updates dispatch website sync only when updating the default published group
  - draft revision updates do not dispatch website sync
  - revision batch updates do not mutate the published group
  - revision batch responses return the requested revision data
- `tests/Feature/Rates/RateEditingEndpointsTest.php`
  - `POST /api/{organization:slug}/rates/batch` rejects malformed payloads
  - `POST /api/{organization:slug}/rates/batch` rejects a `rate_group_id` from another organization
  - `POST /api/{organization:slug}/rates/batch` creates rates and columns for the requested rate group
  - `POST /api/{organization:slug}/rates/batch` deletes rates and columns only from the requested group
  - `POST /api/{organization:slug}/columns` creates a column for the requested organization/rate group
  - `PUT /api/{organization:slug}/columns/{column}/order` reorders columns within the organization
  - `PUT /api/{organization:slug}/rates/uid/update/{rate:uid}` updates a rate UID in the same organization
  - `PUT /api/{organization:slug}/rates/uid/update/{rate:uid}` rejects duplicate UIDs within the same organization
- `tests/Feature/Rates/RateGroupLifecycleTest.php`
  - `GET /api/{organization:slug}/rate-groups` lists only unarchived groups for the requested organization
  - `POST /api/{organization:slug}/rate-groups/{rateGroup}/clone` creates a draft revision with copied columns and rates
  - `POST /api/{organization:slug}/rate-groups/{rateGroup}/clone` rejects a group from another organization
  - `GET /api/{organization:slug}/rate-groups/{rateGroup}/revisions` lists only revisions for the requested parent group
  - `GET /api/{organization:slug}/rate-groups/{rateGroup}/revisions` rejects a parent group from another organization
  - `POST /api/{organization:slug}/rate-groups/{rateGroup}/publish` promotes a revision to the default group and dispatches website sync
  - `POST /api/{organization:slug}/rate-groups/{rateGroup}/publish` rejects non-revision groups and groups from another organization
  - `POST /api/{organization:slug}/rate-groups/{rateGroup}/schedule` stores a revision publish time in UTC
  - `POST /api/{organization:slug}/rate-groups/{rateGroup}/schedule` rejects invalid dates, non-revision groups, and groups from another organization
- `tests/Feature/Rates/PublicRateEndpointsTest.php`
  - `GET /api/{organization:slug}/rates` returns the default rate group for the organization
  - `GET /api/{organization:slug}/rates` can return an explicit in-organization `rate_group_id`
  - `GET /api/{organization:slug}/rates` rejects a `rate_group_id` from another organization
  - `GET /api/{organization:slug}/rates/export` streams CSV for the default rate group without draft-group rows
- `tests/Feature/Rates/RateSyncKeyControllerTest.php`
  - sync key show/rotate behavior for the admin-gated rates sync key endpoints
  - rotate returns the new key rather than the stale key
- `tests/Feature/Security/CrossOrganizationRouteModelAccessTest.php`
  - cross-organization protection for `PUT /api/{organization:slug}/rates/uid/update/{rate:uid}`
  - cross-organization protection for `PUT /api/{organization:slug}/columns/{column}/order`
  - cross-organization protection for `GET /api/{organization:slug}/csv/{file}`
  - legacy/commented-route regression coverage for organization comments and media delete paths
- `tests/Feature/Security/AuthOrganizationAccessBoundaryTest.php`
  - unauthenticated users cannot access `GET /api/{organization:slug}/rate-groups`
  - editors can access their own `GET /api/{organization:slug}/rate-groups`
  - admins can access their own `GET /api/{organization:slug}/rates/sync-key`
  - editors cannot access their own `GET /api/{organization:slug}/rates/sync-key`
  - users cannot access another organization's `GET /api/{organization:slug}/rate-groups`
  - admins cannot access another organization's `GET /api/{organization:slug}/rates/sync-key`
  - super admins can access cross-organization `GET /api/{organization:slug}/rate-groups`
  - super admins can access cross-organization `GET /api/{organization:slug}/rates/sync-key`
- `tests/Feature/Smoke/ActiveRouteSmokeTest.php`
  - active product route signatures are registered after route loading
  - representative active product routes resolve through routing and middleware without server errors
- `tests/Feature/Base/Users/UserControllerTest.php`
  - `GET /api/{organization:slug}/users` returns only users for the requested organization
  - `GET /api/{organization:slug}/users` returns only approved user fields
  - `GET /api/{organization:slug}/users` rejects users from another organization
  - `DELETE /api/{organization:slug}/users/{user}` is admin-gated, rejects cross-organization users, revokes tokens, and soft-deletes users
  - `DELETE /api/{organization:slug}/users/{user}` preserves owned RateStream records when soft-deleting a user
  - `PATCH /api/{organization:slug}/users/{user}/role` is admin-gated, rejects cross-organization users, validates roles, and blocks organization admins from assigning or modifying `super_admin`
  - `PATCH /api/{organization:slug}/users/{user}/role` allows super admins to update users across organizations
- `tests/Unit/App/Jobs/SyncPublishedRatesToWebsiteTest.php`
  - website sync job webhook behavior
  - failed webhook responses throw and do not log the sync secret
- `tests/Unit/App/Services/RateGroups/RateGroupPublisherTest.php`
  - rate group publish service behavior

### Laravel 10 Migration-Critical Remaining Coverage

- Current migration-critical active product coverage is complete. Keep scaffold/product-decision items below separate from the Laravel 10 upgrade gate.

### Product Review Action Items

- Decide whether `GET /api/{organization:slug}/rates/export` should remain a public route long-term or become an authenticated export endpoint.

### Develop And Test User Administration Hardening

- Decide and implement invitation role restrictions; then test that unsafe roles such as `super_admin` cannot be invited by organization-scoped users.
- Decide and implement invitation expiration; then test that expired invitations cannot be accepted.
- Decide self-delete, last-admin deletion, and super-admin deletion behavior; then test those deletion boundaries.

### Develop And Test Audit Events

- Implement audit logging for invitation create/delete, invitation registration, role change, and user deletion.
- Add audit-event assertions after the audit event model/schema exists.

### Useful But Not Laravel 10-Critical Active Route Coverage

- Files:
  - Generic file routes appear to be base scaffold/upload plumbing; keep them outside Laravel 10-critical coverage unless the frontend actively depends on them.
  - public `GET /api/{organization:slug}/files` and `GET /api/{organization:slug}/files/{file}` return only files visible for that organization.
  - authenticated `POST /api/{organization:slug}/files` and `DELETE /api/{organization:slug}/files/{file}` store/delete files under the correct organization.
  - public `GET /api/files/{file}` download behavior is covered for visibility and storage failures.
- Sites and teams:
  - CRUD routes under `/{organization:slug}/sites` and `/{organization:slug}/teams` are organization-scoped.
  - slug-bound show/update/delete routes reject resources from other organizations.
- Taxonomy/scaffold routes:
  - categories, statuses, and tags CRUD routes are globally registered under `/api`.
  - Add only basic smoke coverage unless the frontend actively depends on deeper behavior.
- Subscriptions:
  - Subscription/Cashier routes appear to be base scaffold billing plumbing; keep them outside Laravel 10-critical coverage unless billing is active product surface.
  - `GET /api/{organization:slug}/subscriptions/intent`, `GET /api/{organization:slug}/subscriptions/plans`, and `GET /api/{organization:slug}/subscriptions/plans/availability` return expected shapes with Cashier/Stripe calls faked.
  - `POST /api/{organization:slug}/subscriptions/subscriptions` and `PATCH /api/{organization:slug}/subscriptions/subscriptions` call Cashier with expected inputs and reject unauthenticated or cross-organization access.

### Develop And Test Organization Settings Hardening

- Decide and implement production HTTPS enforcement for `rates_domain`; then test non-HTTPS production domain rejection separately from the current local/dev HTTP allowance.

## RateStream Sync Key Threat Model

| Risk | Importance | Current State | Mitigation Details |
| --- | --- | --- | --- |
| Sync key exfiltration through `rates_domain` redirect | High | Any user with organization update access can change their own org's `rates_domain`. Most current users are `editor`, so admin-only gating would block normal usage. | Short term: treat org editors as trusted rates operators and document this. Near term: add a specific capability such as `manage_rates_sync` or `manage_integrations`, then grant it to current operational users. Longer term: split organization settings permissions from rate editing permissions. |
| Sync key exposure in frontend/browser | High | API intentionally returns the retrievable key to authorized users. | Frontend must keep the key in page/component state only; do not store in localStorage/sessionStorage; do not send to analytics/error tracking; do not log it; do not include it in normal organization settings saves. |
| Overly narrow `admin` role blocks real users | High | Most users are currently `editor`, so strict admin-only sync-key access may be operationally unusable. | Replace role-only assumptions with a capability model. Until then, consider a rates-manager middleware that allows `admin`, `super_admin`, and `editor` if editors are trusted for rates operations. |
| Middleware misuse | Medium | `organization.admin` only checks role and assumes `verify.organization.access` ran first. | Document that role middleware must be paired with org-access middleware, or replace it with a combined middleware/policy that checks both organization access and capability in one place. |
| Key rotation outage | Medium | Rotation immediately invalidates the key configured in WordPress. | Frontend should require confirmation and clearly warn that WordPress/plugin must be updated immediately. Consider showing "last rotated at" in the future. |
| Replay / repeated sync triggering | Medium | Anyone with the key can trigger the WordPress sync endpoint. Endpoint syncs from API and does not accept arbitrary rate payloads. | Keep the key secret; consider rate limiting or request signing with timestamp if abuse becomes likely. WordPress should log failed attempts without logging secrets. |
| Non-HTTPS production domains | Medium | Local HTTP is needed for Docker (`host.docker.internal`), but production HTTP would expose the sync key in transit. | Enforce HTTPS for `rates_domain` outside local/dev environments, or warn/block non-HTTPS domains in organization settings. |
| Secrets in logs | Medium | Laravel logs webhook URL/status/body but not the `X-RateStream-Secret`. WordPress code should also avoid logging secrets. | Keep headers/key out of logs. During debugging, only temporarily log expected/provided keys in local environments and remove immediately. |
| Queue worker deploy staleness | Low | Long-running workers can continue using old code until restarted, as seen when `test-secret` remained active. | Deployment runbook must include `php artisan queue:restart` or worker/container restart after code deploys. Key rotation itself should not require restart once workers are on current code. |
| Encrypted key retrievability | Low | `rates_sync_key` is encrypted at rest, not hashed, because Laravel must send the raw key to WordPress. | Accept this tradeoff for retrievable setup credentials. Revisit if protocol changes to one-time display or asymmetric verification. |

## User Administration Threat Model

| Risk | Importance | Current State | Mitigation Details |
| --- | --- | --- | --- |
| Non-admin invitation creation | High | Invitation routes sit behind organization access, but `InvitationStoreRequest::authorize()` returns `true`. Any organization user who can reach the route may invite users. | Require `organization.admin` or a future `manage_users` capability on invitation create/list/delete. Add tests for editor denial and admin/super-admin allowance. |
| Privilege escalation through invitation role | High | `InvitationStoreRequest` accepts any `RoleEnum`, including `super_admin`, but invite registration currently hardcodes new users to `editor`, ignoring invitation role. This mismatch is confusing and dangerous if registration later starts honoring the invitation role. | Decide intended role behavior. Short term: restrict invite roles to safe org roles only, probably `editor` unless admin invites are intentionally supported. Never allow org-scoped users to invite `super_admin`. |
| Public invitation disclosure | High | Public invitation show route returns invitation loaded with organization and user. Anyone with the UUID can view invitation details. UUID is hard to guess, but invite links are shared externally. | Limit public invitation response to fields needed for registration, such as organization title/slug and invited email. Do not expose user relationships or internal role metadata unless needed by the UI. |
| Invitation replay / stale invites | Medium | Registration deletes the invitation after use, but there is no visible expiration or registered timestamp enforcement. | Add `expires_at` or created-at age checks; reject expired invites; optionally record `registered_at` before deletion if audit history is needed. |
| User deletion blast radius | High | User deletion is admin-gated and deletes tokens, but it can delete any user in the same organization. There is no visible self-delete, last-admin, or super-admin protection. | Block deleting yourself unless explicitly supported. Prevent deleting the last admin/super-admin equivalent for an organization. Prevent org admins from deleting super admins. Add tests. |
| Cross-organization user deletion | High | User delete route disables scoped bindings, then controller manually checks org ownership unless requester is `super_admin`. | Prefer scoped bindings where possible or keep the explicit check and add regression tests proving org admins receive 404/403 when deleting users outside their org. |
| User list exposure | Medium | Any organization user can list all users in their org. This may be acceptable, but exposes roles and profile data through `UserResource`. | Decide whether user listing is admin-only or available to all org members. If all members can list users, keep returned fields minimal. |
| Missing centralized user capability model | Medium | Admin checks are split across route middleware and controller logic. Invitations do not currently use the admin middleware. | Introduce explicit capabilities such as `manage_users`, `manage_integrations`, and `edit_rates`, or consolidate user-admin authorization in policies/middleware. Avoid relying only on broad roles. |
| Audit gaps | Medium | User invites, deletions, and role-bearing actions do not appear to create audit events. | Add audit logging for invitation creation/deletion, registration, user deletion, and future role changes. Include actor id, organization id, target user/email, and action. Do not log passwords or tokens. |
| Account takeover after user removal | Low | User deletion deletes Sanctum tokens before deleting the user. | Keep token revocation. If soft deletes are introduced later, verify removed users cannot authenticate and old tokens remain invalid. |

## RateStream Webhook Work

- Add a direct regression test for PublishScheduledRateGroups if we want explicit scheduled-publish coverage.
