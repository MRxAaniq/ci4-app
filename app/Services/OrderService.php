<?php

namespace App\Services;

use App\Models\OrderItemModel;
use App\Models\OrderModel;
use App\Models\ProductModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;

class OrderService
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly TaxService $taxService,
        private readonly InventoryService $inventoryService,
    ) {
    }

    /**
     * Implements the requested workflow:
     * - start transaction
     * - validate stock (row locks)
     * - insert order
     * - insert order items
     * - deduct inventory
     * - commit
     *
     * @param array<int, array{product_id:int, quantity:int, price?:float}> $items
     * @return array<string, mixed>
     */
    public function createOrder(int $branchId, int $userId, array $items, ?int $actorUserId = null, string $actorRole = ''): array
    {
        return $this->createAndSubmitOrder($branchId, $userId, $items, $actorUserId, $actorRole);
    }

    /**
     * Convenience wrapper for the requested input shape:
     * payload contains products[] with {product_id, quantity}.
     * Pricing is always fetched from the product catalog (products.sale_price).
     *
     * @param array<int, array{product_id:int, quantity:int}> $products
     * @return array<string, mixed>
     */
    public function createOrderFromProducts(int $branchId, int $userId, array $products, ?int $actorUserId = null, string $actorRole = ''): array
    {
        // Normalize to the internal items shape (no client-provided price)
        $items = [];
        foreach ($products as $p) {
            $items[] = [
                'product_id' => (int)($p['product_id'] ?? 0),
                'quantity'   => (int)($p['quantity'] ?? 0),
            ];
        }

        return $this->createAndSubmitOrder($branchId, $userId, $items, $actorUserId, $actorRole, true);
    }

    /**
     * Manager approval step:
     * - must be BRANCH_MANAGER
     * - order must be PENDING
     * - deduct inventory and write movements
     * - set order status to SUBMITTED
     *
     * @return array<string, mixed>
     */
    public function approveOrder(int $orderId, int $actorUserId, string $actorRole): array
    {
        if ($orderId <= 0 || $actorUserId <= 0) {
            throw new DatabaseException('Invalid approval context');
        }

        $role = strtoupper(trim($actorRole));
        if ($role !== 'BRANCH_MANAGER') {
            throw new DatabaseException('Forbidden');
        }

        $orderModel = new OrderModel($this->db);
        $orderItemModel = new OrderItemModel($this->db);

        if (!$this->db->tableExists('inventory_reservations')) {
            throw new DatabaseException('Inventory reservations table is missing. Run migrations (php spark migrate).');
        }

        $this->db->transBegin();

        try {
            $row = $this->db->table('orders')
                ->where('id', $orderId)
                ->get()
                ->getRowArray();

            if (!$row) {
                throw new DatabaseException('Order not found');
            }

            $branchId = (int) ($row['branch_id'] ?? 0);
            if ($branchId <= 0) {
                throw new DatabaseException('Invalid order');
            }

            (new AuthorizationService($this->db))->assertCanAdjustInventory($actorUserId, $actorRole, $branchId);

            $status = strtoupper((string) ($row['status'] ?? ''));
            if ($status !== 'PENDING') {
                throw new DatabaseException('Only pending orders can be approved');
            }

            $items = $orderItemModel->getItemsByOrder($orderId);
            if (empty($items)) {
                throw new DatabaseException('Order has no items');
            }

            // Deduct inventory (writes inventory_movements with ref_type ORDER)
            foreach ($items as $it) {
                $productId = (int) ($it['product_id'] ?? 0);
                $qty = (int) ($it['quantity'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    throw new DatabaseException('Invalid order item');
                }

                $reserved = $this->inventoryService->getReservedQtyForOrder($orderId, $productId);
                if ($reserved < $qty) {
                    throw new DatabaseException('Missing reservation for order item');
                }

                $this->inventoryService->decrease(
                    $branchId,
                    $productId,
                    $qty,
                    'ORDER',
                    $orderId,
                    $actorUserId,
                    'Order approved',
                    false
                );
            }

            // Release reservations now that inventory has been deducted.
            $ok = $this->db->table('inventory_reservations')->where('order_id', $orderId)->delete();
            if ($ok === false) {
                $err = $this->db->error();
                throw new DatabaseException('Failed to release reservations: ' . (string) ($err['message'] ?? 'unknown'));
            }

            $orderModel->update($orderId, [
                'status' => 'SUBMITTED',
            ]);

            $this->db->transCommit();

            $order = $orderModel->getOrderWithItems($orderId);
            return $order ?: ['id' => $orderId];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }

    /**
     * @param array<int, array{product_id:int, quantity:int, price?:float}> $items
     * @return array<string, mixed>
     */
    public function createAndSubmitOrder(
        int $branchId,
        int $userId,
        array $items,
        ?int $actorUserId = null,
        string $actorRole = '',
        bool $useCatalogPriceOnly = false,
    ): array
    {
        if ($branchId <= 0 || $userId <= 0) {
            throw new DatabaseException('Invalid branch_id or user_id');
        }

        // Defense-in-depth: do not allow creating orders for other users unless admin.
        if ($actorUserId !== null && $actorUserId > 0 && $actorUserId !== $userId) {
            $role = strtoupper(trim($actorRole));
            if ($role !== 'ADMIN') {
                throw new DatabaseException('Forbidden');
            }
        }

        if (empty($items)) {
            throw new DatabaseException('Order items are required');
        }

        $orderModel = new OrderModel($this->db);
        $orderItemModel = new OrderItemModel($this->db);
        $productModel = new ProductModel($this->db);

        if (!$this->db->tableExists('inventory_reservations')) {
            throw new DatabaseException('Inventory reservations table is missing. Run migrations (php spark migrate).');
        }

        $this->db->transBegin();

        try {
            // Defense-in-depth authorization: ensure actor can create orders for this branch.
            if ($actorUserId !== null && $actorUserId > 0) {
                (new AuthorizationService($this->db))->assertCanCreateOrder($actorUserId, $actorRole, $branchId);
            } else {
                throw new DatabaseException('Unauthorized');
            }

            $normalized = [];
            foreach ($items as $item) {
                $productId = (int)($item['product_id'] ?? 0);
                $qty = (int)($item['quantity'] ?? 0);
                $price = isset($item['price']) ? (float)$item['price'] : null;

                if ($productId <= 0 || $qty <= 0) {
                    throw new DatabaseException('Invalid order item (product_id/quantity)');
                }

                if (!isset($normalized[$productId])) {
                    $normalized[$productId] = ['product_id' => $productId, 'quantity' => 0, 'price' => $price];
                }

                $normalized[$productId]['quantity'] += $qty;
                if ($price !== null) {
                    $normalized[$productId]['price'] = $price;
                }
            }

            // Validate available stock while holding row locks.
            // Available = inventory.quantity - reserved.quantity.
            // Lock in a stable order to reduce deadlock risk.
            $productIds = array_keys($normalized);
            sort($productIds, SORT_NUMERIC);
            foreach ($productIds as $productId) {
                $qty = (int) ($normalized[$productId]['quantity'] ?? 0);
                if ($productId <= 0 || $qty <= 0) {
                    throw new DatabaseException('Invalid order item');
                }

                $stock = $this->inventoryService->getStockForUpdate($branchId, $productId);
                $reserved = $this->inventoryService->getReservedQty($branchId, $productId);
                $available = $stock - $reserved;

                if ($available < $qty) {
                    throw new DatabaseException('Insufficient stock');
                }
            }

            $orderId = (int)$orderModel->insert([
                'branch_id'   => $branchId,
                'user_id'     => $userId,
                'subtotal'    => 0,
                'tax_total'   => 0,
                'grand_total' => 0,
                // New requirement: order is placed in reserve first, awaiting manager approval.
                'status'      => 'PENDING',
            ], true);

            if ($orderId <= 0) {
                throw new DatabaseException('Failed to create order');
            }

            $lineItemsForTotals = [];

            foreach ($normalized as $row) {
                $productId = (int)$row['product_id'];
                $qty = (int)$row['quantity'];

                $product = $productModel->find($productId);
                if (!$product) {
                    throw new DatabaseException('Product not found: ' . $productId);
                }

                if (($product['status'] ?? 'INACTIVE') !== 'ACTIVE') {
                    throw new DatabaseException('Product inactive: ' . $productId);
                }

                // Requirement: fetch product prices from catalog.
                // If $useCatalogPriceOnly is true, ignore any client-supplied price.
                $unitPrice = $useCatalogPriceOnly
                    ? (float)$product['sale_price']
                    : (isset($row['price']) && $row['price'] !== null
                        ? (float)$row['price']
                        : (float)$product['sale_price']);

                $taxPercentage = (float)$product['tax_percentage'];
                $lineTax = $this->taxService->calculateLineTax($unitPrice, $qty, $taxPercentage);

                $orderItemModel->insert([
                    'order_id'   => $orderId,
                    'product_id' => $productId,
                    'quantity'   => $qty,
                    'price'      => $unitPrice,
                    'tax'        => $lineTax,
                ]);

                $lineItemsForTotals[] = [
                    'quantity' => $qty,
                    'price'    => $unitPrice,
                    'tax'      => $lineTax,
                ];
            }

            $totals = $this->taxService->calculateTotals($lineItemsForTotals);

            // Reserve inventory for this pending order (no inventory change and no movements).
            foreach ($normalized as $row) {
                $ok = $this->db->table('inventory_reservations')->insert([
                    'branch_id'  => $branchId,
                    'product_id' => (int) $row['product_id'],
                    'order_id'   => $orderId,
                    'quantity'   => (int) $row['quantity'],
                    'created_at' => date('Y-m-d H:i:s'),
                ]);

                if ($ok === false) {
                    $err = $this->db->error();
                    throw new DatabaseException('Failed to reserve inventory: ' . (string) ($err['message'] ?? 'unknown'));
                }
            }

            $orderModel->update($orderId, [
                'subtotal'    => $totals['subtotal'],
                'tax_total'   => $totals['tax_total'],
                'grand_total' => $totals['grand_total'],
                // Keep PENDING until approved.
                'status'      => 'PENDING',
            ]);

            // Defensive check: if DB schema doesn't support PENDING (e.g., missing ENUM migration),
            // MySQL may coerce it to another value without throwing.
            $statusRow = $this->db->table('orders')->select('status')->where('id', $orderId)->get()->getRowArray();
            $storedStatus = strtoupper((string) ($statusRow['status'] ?? ''));
            if ($storedStatus !== 'PENDING') {
                throw new DatabaseException('Order status PENDING is not supported by the database schema. Run migrations (php spark migrate).');
            }

            $this->db->transCommit();

            $order = $orderModel->getOrderWithItems($orderId);
            return $order ?: ['id' => $orderId];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
