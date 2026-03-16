# AI-Assisted SDLC (AI_SDLC)

This document describes how AI-assisted development was used to design, implement, and validate a CodeIgniter 4 multi-branch Inventory & Order Management System.

Scope note:
- The examples below reflect the **actual components present in this repository** (versioned REST API routes, MySQL schema + routines, RBAC filters, and a PHPUnit test suite).
- "AI" refers to an LLM-powered assistant used as a pair-programming and documentation partner.
- Example prompts/responses are **representative** and simplified for readability.

---

## 1) Requirements Analysis with AI

### How AI helped

AI was used to:
- Convert a high-level business problem (multi-branch inventory + ordering) into a concrete set of functional requirements.
- Enumerate edge cases that are easy to miss (insufficient stock, negative inventory, partial failures, and concurrency).
- Translate requirements into **verifiable acceptance criteria** and **test scenarios**.

### Outputs produced

- A consistent set of end-to-end behaviors:
  - Create/update branches and products
  - View inventory by branch and product
  - Adjust inventory (add and delta adjustments)
  - Place orders that decrement inventory
  - Transfer stock between branches
  - Calculate tax per product and store order totals
  - Prevent negative inventory under normal and concurrent usage

### Example prompt and AI response

**Prompt**
> I need QA test scenarios for a multi-branch inventory + order system. Cover: sufficient stock order, insufficient stock order, concurrent orders, stock transfer, tax calculations, and prevention of negative inventory. Provide step-by-step tests.

**AI response (excerpt)**
- Sufficient stock order:
  - Preconditions: Branch A has 20 units of Product X.
  - Steps: Login as Sales → POST `/api/v1/orders` with `{ branch_id, products: [{product_id, quantity}] }`.
  - Expected: 201 Created; order status SUBMITTED; inventory reduced exactly by requested quantity.
- Insufficient stock order:
  - Preconditions: Branch B has 1 unit.
  - Steps: POST order for quantity 2.
  - Expected: 400/422 error; no inventory change; no negative inventory.
- Concurrent orders:
  - Preconditions: Branch B has 1 unit.
  - Steps: Send two orders at the same time.
  - Expected: Only one succeeds; the other fails with insufficient stock (or a safe retry outcome).

---

## 2) Architecture Design with AI

### How AI helped

AI was used to propose an architecture that is:
- Easy to test (thin controllers, services holding business logic)
- Safe (RBAC + validation)
- Consistent (versioned API routes)

In this repo, the architecture is implemented as:
- **Versioned JSON API** under `/api/v1/*` (see `app/Config/Routes.php`)
- **Filters** for auth and authorization:
  - `ApiAuthFilter` ensures a session exists
  - `RoleFilter` restricts routes based on `ADMIN`, `BRANCH_MANAGER`, `SALES`
- **Services** for business logic (e.g., order submission, inventory mutations, transfers)
- **Database enforcement** for the hardest invariants (non-negative inventory + concurrency correctness)

### Example prompt and AI response

**Prompt**
> Design an architecture for CI4 where API controllers are thin, and inventory updates are safe under concurrency. Keep it practical for a small assignment.

**AI response (excerpt)**
- Use `/api/v1/*` routing with filters for auth + role.
- Put business logic in services:
  - OrderService: validate, compute totals, write order header/items, decrement inventory.
  - InventoryService: the only layer allowed to mutate inventory.
- Enforce hard invariants in the database:
  - Prevent negative stock via triggers.
  - Perform increments/decrements using transactional routines with row locks.

---

## 3) Database Design with AI

### How AI helped

AI assisted in:
- Drafting a normalized schema that matches the workflows.
- Identifying required constraints and indexes.
- Choosing a concurrency strategy that is robust (row locks + transactions).

### Schema decisions (as implemented)

The primary migration (`CreateImsSchema`) creates:
- `users` with a `role` enum and `status` enum
- `branches` with optional `manager_id`
- `products` with a unique SKU and `tax_percentage`
- `inventory` keyed by `(branch_id, product_id)`
- `inventory_movements` as an audit-friendly stock ledger
- `orders` and `order_items`
- `stock_transfers`

Critical integrity controls:
- `CHECK (quantity >= 0)` on `inventory` (where supported)
- Triggers:
  - `trg_inventory_bi_non_negative`
  - `trg_inventory_bu_non_negative`
- Stored procedures:
  - `sp_inventory_increase`
  - `sp_inventory_decrease` (signals `Insufficient stock`)

### Example prompt and AI response

**Prompt**
> Propose a MySQL schema for multi-branch inventory where negative stock is impossible even under concurrent updates.

**AI response (excerpt)**
- Use `inventory(branch_id, product_id)` as a composite primary key.
- Add a movement ledger table to preserve before/after history.
- Enforce non-negative inventory via:
  - CHECK constraint when supported
  - triggers as a fallback
- Handle concurrency in the decrement path with:
  - `SELECT ... FOR UPDATE` inside a transaction
  - fail fast if `before_qty < requested_qty`

---

## 4) Code Generation with AI

### How AI helped

AI was used to generate initial versions of:
- API route tables (what endpoints exist, what roles may call them)
- Controller skeletons (validation + response shapes)
- Service-layer logic patterns
- Test scaffolding (seed data + feature tests)

A key practice was to treat AI output as a **first draft**, then immediately:
- Verify it against CodeIgniter 4 conventions
- Adjust for the repository’s actual naming and response format
- Add validation and error handling

### Example prompt and AI response

**Prompt**
> Generate a CI4 controller method for placing an order: validate JSON, ensure session user, compute totals including per-product tax percentage, and decrement inventory.

**AI response (excerpt)**
- Validate payload: `branch_id`, `products[]` with `product_id`, `quantity`.
- Read `user_id` from session (don’t trust payload for actor).
- In a transaction:
  - create order header
  - insert items
  - call an inventory decrement routine for each item
  - compute subtotal/tax/grand total and update order
- Return `201` with `{ data: { order } }`, and return `400` with `{ message }` on domain failures.

---

## 5) Debugging with AI

### How AI helped

AI was used as a debugging assistant to:
- Interpret stack traces and narrow down the failing layer (config vs schema vs tests vs controllers).
- Identify CI4 testing environment pitfalls.
- Propose minimal, safe patches and then verify with repeated test runs.

### Debugging themes encountered

1) **Test DB configuration mismatch**
- CI4 test defaults may assume SQLite; a missing extension (or different DB) can block execution.
- Fix: align PHPUnit/CI4 test DB config to the chosen database.

2) **Migrations not running as expected in tests**
- CI4 tests can default to a migrations namespace that does not include app migrations.
- Fix: configure tests to run the application migrations.

3) **FK constraint failures during seeding**
- Seed insert order matters when foreign keys are present.
- Fix: insert referenced rows first (e.g., users → branches → sales user).

4) **Cache key restrictions in rate limiting**
- Some cache backends reject certain characters.
- Fix: use cache-safe throttler keys (underscores) for write endpoints.

### Example prompt and AI response

**Prompt**
> PHPUnit fails with a DB error during a feature test, but the API works manually. How do I debug CI4 test DB/migrations issues?

**AI response (excerpt)**
- Confirm the test environment DB driver and database name.
- Ensure migrations run in tests (and not only `Tests\\Support` migrations).
- After migrations, seed data in FK-safe order.
- Re-run a single failing test to shorten feedback loops.

---

## 6) Testing with AI

### How AI helped

AI assisted in turning QA scenarios into automated tests:
- Feature tests for API endpoints:
  - orders: sufficient stock / insufficient stock / tax totals
  - transfers: success / insufficient stock
  - inventory: negative inventory prevention via failure behavior
- A best-effort concurrency test (acknowledging nondeterminism in single-process unit tests)

The repository includes deterministic seed data for testing:
- Branch A / Branch B
- Taxed and non-taxed products
- Inventory quantities suitable for success and failure paths

### Example prompt and AI response

**Prompt**
> Implement CI4 PHPUnit tests for order creation with enough stock and insufficient stock, and assert inventory changes in the database.

**AI response (excerpt)**
- Use CI4 feature testing utilities to POST JSON to `/api/v1/orders`.
- Seed DB with known product and inventory quantities.
- Assert `201` on success and verify inventory decreased.
- Assert `400` on insufficient stock and verify inventory unchanged.

### Concurrency testing note

Concurrency tests can be flaky when executed in a single PHP process due to scheduling and transaction semantics. A more deterministic approach is to:
- Spawn two PHP processes (or two HTTP clients) that submit orders at the same time.
- Assert that **only one** request succeeds when stock is limited to 1.

---

## 7) Security Analysis with AI

### How AI helped

AI was used to produce a security checklist and to review the API design for common issues:

- **Authentication**
  - Ensure protected routes require a valid session.
  - Avoid trusting user identifiers passed in request bodies.

- **Authorization (RBAC)**
  - Enforce role checks at the routing/filter level.
  - Consider branch scoping for `SALES` users.

- **Input validation**
  - Validate numeric IDs and quantities (`> 0` where required).
  - Fail with 422 for validation errors.

- **Rate limiting / abuse control**
  - Apply throttling to high-impact endpoints (login, inventory writes, order creation, transfers).

- **Data integrity**
  - Prevent negative inventory at the database layer.
  - Use transactions and locks for stock decrements.

### Example prompt and AI response

**Prompt**
> Do a quick security review of a session-authenticated REST API for inventory/orders. What are the biggest risks and easy improvements?

**AI response (excerpt)**
- Ensure every write endpoint checks auth and enforces roles.
- Tie actions to session user (don’t accept `user_id` from client).
- Add validation for payload shapes and length limits.
- Rate limit login and write endpoints.
- Use DB constraints + transactions to guarantee stock invariants.
- Consider migrating API auth to tokens for non-browser clients.

---

## 8) Lessons Learned

### What worked well

- Using AI for **test-first thinking** helped solidify the acceptance criteria early.
- AI was effective at quickly proposing:
  - route tables
  - request/response contracts
  - database constraints
  - test skeletons

### What required extra care

- **Environment and tooling**: AI can propose solutions that assume extensions or defaults that are not present (e.g., assuming SQLite is available). Always verify locally.
- **Concurrency tests**: proving concurrency behavior is much easier with multi-process tests than with a single-process unit test.
- **Over-trusting generated code**: AI output needs review for:
  - correct CI4 idioms
  - consistent status codes and response envelopes
  - safe authorization boundaries

### Practical guidance for future AI-assisted work

- Start with a small "definition of done" that includes tests.
- Convert every major requirement into at least one automated test.
- Push invariants into the database when correctness matters (like inventory).
- Keep controllers thin and services testable.
- Treat AI like a fast draft generator; apply human review for correctness and security.
