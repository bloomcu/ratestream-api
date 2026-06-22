# Frontend Guide: RateStream Sync Key

This guide covers the frontend work for organization-level RateStream sync keys. The key is used by the WordPress site/plugin to verify webhook sync requests from the API.

## User Flow

- Add a RateStream website sync section in organization/settings UI for admin users.
- Show the current organization `rates_domain` near the key controls so admins understand which website will receive sync requests.
- Provide a "Reveal sync key" or "Get sync key" action.
- Provide a copy-to-clipboard action after the key is loaded.
- Provide a "Rotate key" action with a confirmation step. Rotation immediately invalidates the old key and the WordPress/plugin setting must be updated.
- Do not show this section to non-admin users. If the backend still returns `403`, show a standard permission message.

## Permissions

The endpoints require:

- authenticated Sanctum user
- organization access
- role `admin` or `super_admin`

Editors receive `403`. Admin users from other organizations receive `403`.

## API Endpoints

Base path uses the organization slug:

```text
/api/{organizationSlug}/rates/sync-key
```

### Get Or Create Key

```http
GET /api/{organizationSlug}/rates/sync-key
Authorization: Bearer {token}
```

Response when a key already exists:

```json
{
  "data": {
    "key": "existing-sync-key",
    "created": false
  }
}
```

Response when no key existed and the API created one:

```json
{
  "data": {
    "key": "64-character-generated-key",
    "created": true
  }
}
```

Frontend behavior:

- Treat this endpoint as safe to call when opening the settings panel.
- If `created` is `true`, show copy/setup UI because this is the first generated key.
- The key is retrievable later, so the UI does not need a one-time-only warning.

### Rotate Key

```http
POST /api/{organizationSlug}/rates/sync-key/rotate
Authorization: Bearer {token}
```

Response:

```json
{
  "message": "Rates sync key rotated.",
  "data": {
    "key": "new-64-character-generated-key"
  }
}
```

Frontend behavior:

- Require confirmation before calling this endpoint.
- Confirmation copy should state that the existing WordPress/plugin key will stop working immediately.
- After success, replace the displayed key with the returned key and prompt the admin to update the WordPress/plugin setting.

## Suggested UI States

- Loading: key request is in flight.
- Ready: key is loaded and copy button is available.
- Missing website domain: still allow key retrieval, but show that sync jobs also require a valid `rates_domain`.
- Forbidden: hide section when role is known; otherwise show a permission error if API returns `403`.
- Rotation pending: disable rotate and copy buttons during request.
- Rotation success: show the new key and a short reminder to update WordPress/plugin.
- Error: preserve existing key in UI and show retry action.

## Security Notes

- Do not store the key in browser local storage.
- Keep the key in component/page state only.
- Do not log the key to analytics, error trackers, or console output.
- Do not include the key in normal organization settings saves.
- Treat rotation as a sensitive action and require an explicit confirmation.

## Acceptance Checklist

- Admin can load an existing key.
- Admin can generate a key for an organization that does not have one.
- Admin can copy the key.
- Admin can rotate the key after confirming.
- Editor cannot access key controls.
- API `403` is handled cleanly.
- The organization settings page does not accidentally submit or overwrite `rates_sync_key`.
