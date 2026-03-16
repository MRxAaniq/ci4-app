# Postman Test Cases (IMS API)

Files:
- `IMS_API_v1.postman_collection.json` – collection with requests + automated tests
- `IMS_Local.postman_environment.json` – environment variables (baseUrl + credentials)

## Import

1) Open Postman
2) Import both JSON files
3) Select the **IMS Local** environment

## How to run

- Use **Collection Runner** and run folders in order:
  1) `0. Pre-auth`
  2) `1. Happy path (Admin)`
  3) `2. Negative & RBAC`

## Notes / prerequisites

- The API uses **session cookies** (login establishes a session). Postman must have the cookie jar enabled.
- The RBAC folder assumes the test credentials exist in your database:
  - `admin@example.com` / `password`
  - `sales.a@example.com` / `password`
  - `manager.a@example.com` / `password`

If these users do not exist in your dev database, either:
- create them in `users` (password stored as `password_hash`), or
- adjust the environment variables to match your local accounts.
