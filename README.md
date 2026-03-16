# Multi-Branch Inventory & Order Management System (CodeIgniter 4)

A CodeIgniter 4 + MySQL inventory and order system designed for multi-branch operations. It exposes a versioned JSON API for branch/product/inventory/order/transfer workflows, with role-based access control and database-level safeguards to prevent negative inventory.

## 1) Project Overview

This project models a typical retail/warehouse setup:
- Multiple **branches**
- A centralized **product** catalog (SKU, pricing, tax %)
- Per-branch **inventory**
- **Orders** that decrement inventory
- **Stock transfers** between branches

The API is designed to be used by a web UI or external clients. Authentication is currently session-based (login establishes a server session).

## 2) Features

- **Role-based access control (RBAC)**: `SUPER_ADMIN`, `BRANCH_MANAGER`, `SALES`
  - Backward compatibility: existing `ADMIN` role is still accepted (tests/data), and `SUPER_ADMIN` is treated as admin-equivalent for access checks.
- **Branch management** (create/update/list)
- **Product catalog** (CRUD)
- **Inventory viewing** per branch and per product
- **Inventory adjustments** (add / adjust delta)
- **Inventory movement history** (append-only ledger view)
- **Orders** with subtotal/tax/grand total calculation
- **Stock transfers** between branches
- **Negative inventory prevention**
	- table CHECK constraints (where supported)
	- triggers (`BEFORE INSERT`/`BEFORE UPDATE`) to enforce non-negative stock
- **Concurrency-safe stock mutations** via MySQL stored procedures with row locks (`SELECT ... FOR UPDATE`)
- **Basic rate limiting** (per IP/user) on write endpoints
- **Server-side pagination + search** for branches/products/inventory/movements

## 3) Tech Stack

- **Backend**: PHP 8.2+, CodeIgniter 4
- **Database**: MySQL 8 / MariaDB (InnoDB, triggers, stored procedures)
- **Testing**: PHPUnit (CI4 test utilities)
- **Auth**: Server sessions (login stores `user_id`, `role`, `branch_id` in session)

## 4) Architecture Explanation

At a high level:

1. **Routes** define `/api/v1/*` endpoints with filters for authentication and roles.
2. **API Controllers** validate inputs and delegate business logic to services.
3. **Services** orchestrate transactions and call inventory/order logic.
4. **Database layer** enforces safety:
	 - Triggers prevent negative inventory
	 - Stored procedures (`sp_inventory_increase`, `sp_inventory_decrease`) perform row-level locking and write to an inventory movement ledger

Key files to review:
- `app/Config/Routes.php` (API routes)
- `app/Filters/ApiAuthFilter.php` and `app/Filters/RoleFilter.php` (access control)
- `app/Database/Migrations/*CreateImsSchema.php` (tables, triggers, stored procedures)
- `docs/architecture.md` (design notes)

## 5) Database Structure

The schema is created by migrations in `app/Database/Migrations/`.

### Core tables

| Table | Purpose |
|------|---------|
| `users` | Users with role (`ADMIN`, `BRANCH_MANAGER`, `SALES`) and status |
| `branches` | Branches; optional `manager_id` (FK to `users`) |
| `products` | Product catalog (SKU unique, pricing, tax % per product) |
| `inventory` | Current on-hand stock per branch/product (composite PK `(branch_id, product_id)`) |
| `inventory_movements` | Append-only stock ledger (delta, before/after, ref type/id, actor, note) |
| `orders` | Order header (branch, user, subtotal, tax, grand total, status) |
| `order_items` | Order lines (unique per `(order_id, product_id)`) |
| `stock_transfers` | Transfer records (from/to branch, product, quantity, status) |

### Important relationships

- `branches.manager_id → users.id` (nullable)
- `users.branch_id → branches.id` (nullable; used for direct branch assignment, e.g. SALES)
- `inventory.branch_id → branches.id`, `inventory.product_id → products.id`
- `orders.branch_id → branches.id`, `orders.user_id → users.id`
- `order_items.order_id → orders.id` (cascade delete), `order_items.product_id → products.id`
- `inventory_movements` references branch/product/user (actor) + business ref (order/transfer/adjustment)

### Data integrity & concurrency

- **Non-negative inventory** is enforced with:
	- `CHECK (quantity >= 0)` on `inventory` (DB-dependent)
	- `trg_inventory_bi_non_negative` and `trg_inventory_bu_non_negative` triggers
- **Safe increments/decrements** are implemented via stored procedures:
	- `sp_inventory_increase(...)`
	- `sp_inventory_decrease(...)` (signals `Insufficient stock` when underflow would occur)

## 6) Installation Steps

### Prerequisites

- PHP 8.2+
- Composer
- MySQL 8+ or MariaDB with InnoDB enabled
- Required PHP extensions: `intl`, `mbstring`, `json`, and MySQL driver (`mysqli`/`mysqlnd`)

### Install

1) Install dependencies:

```bash
composer install
```

2) Configure environment:

- Copy `env` → `.env`
- Set at least:
	- `CI_ENVIRONMENT = development`
	- `app.baseURL`
	- database settings (host/user/pass/db)

3) Create your database and run migrations:

```bash
php spark migrate
```

## 7) Running the Project

### Local dev server

```bash
php spark serve
```

By default this serves the app from the `public/` directory.

### Web server

For Apache/Nginx/IIS, point the document root to `public/` (do not serve from the project root).

### Docker (recommended)

This repo includes a `docker-compose.yml` that runs:
- MySQL 8
- A single web container (Nginx + PHP-FPM) serving the built Vue SPA and the CI4 API at `/api/v1/*`

Start everything:

```bash
docker compose up --build
```

Then open:
- App: `http://localhost:8080`
- MySQL (optional, for local tools): `localhost:3307`

Notes:
- On startup the container runs `php spark migrate --all`.
- Set `SEED_DEV=1` (already enabled in compose) to seed demo data via `ImsDevSeeder`.
- If you do not want seeding, set `SEED_DEV=0` in `docker-compose.yml`.

## 8) API Endpoints

All endpoints are JSON and are versioned under `/api/v1`.

### Authentication

Authentication is session-based:
- `POST /api/v1/auth/login` establishes a server session
- Subsequent API requests must include the session cookie

Example with curl (cookie jar):

```bash
curl -i -c cookies.txt \
	-H "Content-Type: application/json" \
	-d '{"email":"you@example.com","password":"your-password"}' \
	http://localhost:8080/api/v1/auth/login
```

Then call protected endpoints using the cookie:

```bash
curl -i -b cookies.txt http://localhost:8080/api/v1/branches
```

### Endpoint catalog

| Method | Path | Auth | Roles |
|---|---|---|---|
| POST | `/api/v1/auth/login` | No | - |
| POST | `/api/v1/auth/logout` | Yes | Any logged-in user |
| GET | `/api/v1/branches` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER`, `SALES` |
| GET | `/api/v1/branches/{id}` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER`, `SALES` |
| POST | `/api/v1/branches` | Yes | `SUPER_ADMIN` |
| PATCH | `/api/v1/branches/{id}` | Yes | `SUPER_ADMIN` |
| GET | `/api/v1/products` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER`, `SALES` |
| GET | `/api/v1/products/{id}` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER`, `SALES` |
| POST | `/api/v1/products` | Yes | `SUPER_ADMIN` |
| PATCH | `/api/v1/products/{id}` | Yes | `SUPER_ADMIN` |
| DELETE | `/api/v1/products/{id}` | Yes | `SUPER_ADMIN` |
| GET | `/api/v1/branches/{branchId}/inventory` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER`, `SALES` |
| GET | `/api/v1/branches/{branchId}/inventory/{productId}` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER`, `SALES` |
| GET | `/api/v1/branches/{branchId}/inventory/movements` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER` |
| POST | `/api/v1/branches/{branchId}/inventory/add` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER` |
| POST | `/api/v1/branches/{branchId}/inventory/adjust` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER` |
| GET | `/api/v1/branches/{branchId}/dashboard` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER` |
| POST | `/api/v1/orders` | Yes | `BRANCH_MANAGER`, `SALES` |
| POST | `/api/v1/transfers` | Yes | `SUPER_ADMIN`, `BRANCH_MANAGER` |

### Request payloads (high level)

- Create product (`POST /api/v1/products`)
	- `name`, `sku`, `cost_price`, `sale_price`, optional `tax_percentage`, optional `status`
- Add stock (`POST /api/v1/branches/{branchId}/inventory/add`)
	- `product_id`, `quantity`, optional `note`
- Adjust stock (`POST /api/v1/branches/{branchId}/inventory/adjust`)
	- `product_id`, `delta` (positive or negative), optional `note`
- Create order (`POST /api/v1/orders`)
	- `branch_id`
	- `products`: array of `{ product_id, quantity }`
- Create transfer (`POST /api/v1/transfers`)
	- `from_branch`, `to_branch`, `product_id`, `quantity`

Response format:
- Success: `{ "data": { ... } }`
- Validation error: `{ "errors": { field: message } }` (HTTP 422)
- Other errors: `{ "message": "...", "errors": [...] }`

## 9) Test Credentials

Automated tests use a dedicated deterministic seed (`Tests\\Support\\Database\\Seeds\\ImsTestSeeder`). These credentials exist in the **test database** during test runs:

| Role | Email | Password | Notes |
|---|---|---|---|
| ADMIN | `admin@example.com` | `password` | Global access (legacy role used by tests) |
| BRANCH_MANAGER | `manager.a@example.com` | `password` | Manager of Branch A |
| SALES | `sales.a@example.com` | `password` | Assigned to Branch A (`branch_id = 10`) |

If you want similar accounts in a development database, you can insert them into `users` (password stored as `password_hash`) or build a small development seeder.

### Running tests

The test suite uses a dedicated test database (commonly `ims_test`) and runs migrations + seeds automatically.

```bash
# (optional) create the test database, if you use MySQL locally
php tools/create_test_db.php

# run the full test suite
php vendor/bin/phpunit
```

## 10) Future Improvements

- Replace session-based API auth with **token-based auth** (JWT/opaque tokens) and CSRF strategy for web
- Add sorting options for list endpoints (currently supports pagination + search)
- Expand order lifecycle: cancel/refund flows with stock restoration and audit trails
- Add multi-product stock transfers (transfer header + items) instead of single-product transfer rows
- Improve reporting: inventory valuation, low-stock alerts, sales summaries by branch
- Add OpenAPI/Swagger spec and Postman collection generation
- Improve concurrency tests by running parallel HTTP processes for deterministic race-condition coverage
- CI pipeline for linting/testing (GitHub Actions) + environment-based config validation
