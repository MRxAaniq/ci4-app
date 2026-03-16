# Multi-Branch Inventory & Order Management System (CI4 + MySQL)

Goal: production-ready, modular CodeIgniter 4 architecture with server-rendered views and a versioned REST API.

---

## 1) Folder Structure

This keeps CodeIgniter’s default MVC structure, but adds a *module* layer to organize bounded contexts (Auth, Branches, Inventory, Orders, etc.).

```
my-ci4-app/
  app/
    Config/
    Controllers/
      Web/
        HomeController.php
      Api/
        V1/
          HealthController.php
    Database/
      Migrations/
      Seeds/
    Filters/
    Helpers/
    Models/
    Services/
    Validation/
    Views/
      layout/
      auth/
      admin/
      branch/
      sales/
    Modules/
      Auth/
        Controllers/
          Web/
          Api/V1/
        Services/
        Views/
        Config/
      Branches/
        Controllers/
          Web/
          Api/V1/
        Models/
        Entities/
        Services/
        Views/
        Config/
      Products/
        ...
      Inventory/
        ...
      Transfers/
        ...
      Orders/
        ...
      Taxes/
        ...
      Audit/
        ...
  public/
  writable/
  docs/
    architecture.md
```

Notes:
- `app/Modules/<Module>` is a convention you control. CI4 doesn’t enforce modules; you wire them via autoloading + route includes.
- `app/Services` is for cross-module services (e.g., audit logging, money/tax helpers) when they’re truly shared.

---

## 2) Modules (Bounded Contexts)

### Core modules
- **Auth**: login/logout, password reset, token auth for API, roles/permissions.
- **Branches**: branch CRUD, branch settings (timezone, tax defaults), manager assignment.
- **Products**: product catalog, SKU/barcode, pricing, tax category.
- **Inventory**: per-branch stock on hand, stock adjustments, stock ledger.
- **Transfers**: inter-branch stock transfers (draft → sent → received/cancelled).
- **Orders**: order lifecycle, order items, payments (optional), invoices/receipts.
- **Taxes**: tax rates/rules, tax calculation service.
- **Audit**: immutable audit log, security events, inventory/order events.

### Cross-cutting concerns
- **RBAC**: Admin, Branch Manager, Sales.
- **Transaction safety**: DB transactions + row-level locking for inventory.
- **Auditability**: append-only ledgers for inventory and important state changes.

---

## 3) Controllers (Web + API)

Keep controllers thin: validate input, call services, return a view or JSON.

### Web controllers (server-rendered)
- `Modules/Branches/Controllers/Web/BranchesController`
  - index/create/store/edit/update/show
- `Modules/Products/Controllers/Web/ProductsController`
- `Modules/Inventory/Controllers/Web/InventoryController`
  - by-branch stock view, adjustments
- `Modules/Transfers/Controllers/Web/TransfersController`
  - create transfer, add items, send, receive
- `Modules/Orders/Controllers/Web/OrdersController`
  - create order, add items, submit, view receipt

### API controllers (REST)
Version everything: `/api/v1/...`
- `Controllers/Api/V1/HealthController` (system health)
- `Modules/Branches/Controllers/Api/V1/BranchesController` (ResourceController)
- `Modules/Products/Controllers/Api/V1/ProductsController`
- `Modules/Inventory/Controllers/Api/V1/InventoryController`
- `Modules/Transfers/Controllers/Api/V1/TransfersController`
- `Modules/Orders/Controllers/Api/V1/OrdersController`

Controller guideline:
- Web controllers return `view(...)`.
- API controllers return `return $this->respond($payload, 200);`.

---

## 4) Models (MySQL tables)

Use `Model` classes for persistence and optional `Entity` classes for domain objects.

### Suggested tables
Authentication (recommended): use **CodeIgniter Shield** tables (users, identities, groups, permissions, tokens).

Business tables:
- `branches`
- `branch_users` (optional if you want explicit branch assignment)
- `products`
- `product_prices` (optional; if pricing varies by branch)
- `product_tax_categories` (optional)
- `branch_inventory` (current stock per branch & product)
- `inventory_movements` (append-only ledger)
- `stock_transfers`
- `stock_transfer_items`
- `orders`
- `order_items`
- `tax_rates` (and/or `tax_rules`)
- `audit_logs`

### Model classes
- `Modules/Branches/Models/BranchModel`
- `Modules/Products/Models/ProductModel`
- `Modules/Inventory/Models/BranchInventoryModel`
- `Modules/Inventory/Models/InventoryMovementModel`
- `Modules/Transfers/Models/StockTransferModel`
- `Modules/Transfers/Models/StockTransferItemModel`
- `Modules/Orders/Models/OrderModel`
- `Modules/Orders/Models/OrderItemModel`
- `Modules/Taxes/Models/TaxRateModel`
- `Modules/Audit/Models/AuditLogModel`

Recommended DB engine & constraints:
- Use **InnoDB** everywhere.
- Add foreign keys for referential integrity.
- Add unique constraints where needed (e.g., `products.sku`, `branch_inventory (branch_id, product_id)` unique).

Money fields:
- Use `DECIMAL(13,2)` for currency amounts.
- Always store computed totals on the order (`subtotal`, `tax_total`, `grand_total`) and on items (`line_subtotal`, `line_tax`, `line_total`) to preserve history.

---

## 5) Services (business logic)

Services are the heart of the system. They run inside transactions and are the only layer allowed to mutate critical state like inventory.

### Key services
- `Modules/Orders/Services/OrderService`
  - `createDraftOrder(...)`
  - `addItem(orderId, productId, qty, priceOverride?)`
  - `submit(orderId)` → deduct inventory, compute tax, finalize totals
- `Modules/Inventory/Services/InventoryService`
  - `increase(branchId, productId, qty, reason, refType, refId)`
  - `decrease(branchId, productId, qty, reason, refType, refId)`
  - writes to `inventory_movements` and updates `branch_inventory`
- `Modules/Transfers/Services/TransferService`
  - `createDraft(fromBranchId, toBranchId)`
  - `send(transferId)` → reserve/deduct from source (policy choice)
  - `receive(transferId)` → increase destination
- `Modules/Taxes/Services/TaxService`
  - `calculateOrderTaxes(orderDraft, branchId, customerContext?)`
  - encapsulates tax rules so controllers/models don’t compute tax.
- `Modules/Audit/Services/AuditService`
  - `log(eventType, actorUserId, payload, branchId?)`

### Transaction safety (critical)
Use explicit transactions and row-level locks for inventory rows.

Policy options (pick one):
1) **Deduct-on-submit** for Orders (simple)
2) **Reserve stock** then deduct on completion (more complex)

For deduct-on-submit:
- Start transaction.
- Lock inventory row: `SELECT ... FOR UPDATE` for `(branch_id, product_id)`.
- Validate enough quantity.
- Update `branch_inventory.current_qty` atomically.
- Insert `inventory_movements` row (append-only) with before/after.
- Commit.

In CI4, use:
- `db->transBegin() / transCommit() / transRollback()`
- Or `transStart()/transComplete()` if you don’t need fine control.

---

## 6) Routes (Web + API)

### Web routes
Group by area and enforce role filters.

Example:
- `/admin/*` → Admin only
- `/branch/*` → Branch Manager (and Admin)
- `/sales/*` → Sales (and Admin/Manager)

### API routes
- Base prefix: `/api/v1`
- Authentication: Bearer token
- Response: JSON only

Route grouping pattern:
- `$routes->group('api/v1', ['filter' => 'apiAuth'], function($routes) { ... })`

---

## 7) API Structure (versioned REST)

### General rules
- Always return: `data`, and optionally `meta`, `errors`.
- Use HTTP status codes correctly.
- Use idempotent PUT/PATCH where appropriate.

### Endpoints (suggested)
Auth:
- `POST /api/v1/auth/login` → returns token
- `POST /api/v1/auth/logout`

Branches:
- `GET /api/v1/branches`
- `POST /api/v1/branches`
- `GET /api/v1/branches/{id}`
- `PATCH /api/v1/branches/{id}`

Products:
- `GET /api/v1/products`
- `POST /api/v1/products`

Inventory:
- `GET /api/v1/branches/{branchId}/inventory`
- `POST /api/v1/branches/{branchId}/inventory/adjustments`

Transfers:
- `POST /api/v1/transfers`
- `POST /api/v1/transfers/{id}/send`
- `POST /api/v1/transfers/{id}/receive`

Orders:
- `POST /api/v1/orders` (draft)
- `POST /api/v1/orders/{id}/items`
- `POST /api/v1/orders/{id}/submit`
- `GET /api/v1/orders/{id}`

---

## Production-readiness checklist

Security:
- Use HTTPS in production.
- Use CodeIgniter Shield for authentication + RBAC.
- Enforce CSRF for web forms.
- Validate and authorize every write.

Reliability:
- Wrap inventory deductions/transfers in transactions.
- Use optimistic concurrency (updated_at checks) or row locks for stock.
- Log all critical operations (orders, transfers, adjustments).

Performance:
- Index: `branch_inventory(branch_id, product_id)`, `inventory_movements(branch_id, product_id, created_at)`, `orders(branch_id, created_at)`.

Observability:
- Structured logs to `writable/logs`.
- Audit table for business events.

---

## Recommended dependencies

- `codeigniter4/shield` for authentication, access tokens, groups/permissions.

(Install when ready)
- `composer require codeigniter4/shield`
