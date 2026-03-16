<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;

class InventoryService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function getReservedQty(int $branchId, int $productId): int
    {
        $row = $this->db->table('inventory_reservations')
            ->selectSum('quantity', 'qty')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->get()
            ->getRowArray();

        return (int) ($row['qty'] ?? 0);
    }

    public function getReservedQtyForOrder(int $orderId, int $productId): int
    {
        $row = $this->db->table('inventory_reservations')
            ->selectSum('quantity', 'qty')
            ->where('order_id', $orderId)
            ->where('product_id', $productId)
            ->get()
            ->getRowArray();

        return (int) ($row['qty'] ?? 0);
    }

    /**
     * Fast stock read (no locking). Use for display only.
     */
    public function getStock(int $branchId, int $productId): int
    {
        $row = $this->db->table('inventory')
            ->select('quantity')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->get()
            ->getRowArray();

        return (int)($row['quantity'] ?? 0);
    }

    /**
     * Stock read with row-level lock (FOR UPDATE) to handle race conditions.
     * Caller must be inside an active DB transaction.
     */
    public function getStockForUpdate(int $branchId, int $productId): int
    {
        return $this->lockInventoryRow($branchId, $productId);
    }

    /**
     * Asserts stock is sufficient while holding a row lock.
     * Caller must be inside an active DB transaction.
     */
    public function assertSufficientStock(int $branchId, int $productId, int $requiredQty): void
    {
        if ($requiredQty <= 0) {
            return;
        }

        $available = $this->lockInventoryRow($branchId, $productId);
        if ($available < $requiredQty) {
            throw new DatabaseException('Insufficient stock');
        }
    }

    /**
     * Ensures an inventory row exists, and locks it FOR UPDATE.
     * Caller should be inside a transaction.
     */
    private function lockInventoryRow(int $branchId, int $productId): int
    {
        // Ensure row exists
        $ok = $this->db->query(
            'INSERT INTO inventory (branch_id, product_id, quantity) VALUES (?, ?, 0) '
            . 'ON DUPLICATE KEY UPDATE quantity = quantity',
            [$branchId, $productId]
        );

        if ($ok === false) {
            $err = $this->db->error();
            throw new DatabaseException('Database error ensuring inventory row: ' . (string)($err['message'] ?? 'unknown'));
        }

        $result = $this->db->query(
            'SELECT quantity FROM inventory WHERE branch_id = ? AND product_id = ? FOR UPDATE',
            [$branchId, $productId]
        );

        if ($result === false) {
            $err = $this->db->error();
            throw new DatabaseException('Database error locking inventory row: ' . (string)($err['message'] ?? 'unknown'));
        }

        $row = $result->getRowArray();

        return (int)($row['quantity'] ?? 0);
    }

    /**
     * @throws DatabaseException
     */
    public function increase(
        int $branchId,
        int $productId,
        int $qty,
        string $refType,
        int $refId,
        ?int $actorUserId = null,
        ?string $note = null,
        bool $manageTransaction = true,
    ): void {
        if ($qty <= 0) {
            throw new DatabaseException('Increase quantity must be > 0');
        }

        if ($manageTransaction) {
            $this->db->transBegin();
        }

        try {
            $before = $this->lockInventoryRow($branchId, $productId);
            $after  = $before + $qty;

            $this->db->table('inventory')
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->update(['quantity' => $after]);

            $this->db->table('inventory_movements')->insert([
                'branch_id'     => $branchId,
                'product_id'    => $productId,
                'delta_qty'     => $qty,
                'qty_before'    => $before,
                'qty_after'     => $after,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'actor_user_id' => $actorUserId,
                'note'          => $note,
            ]);

            if ($manageTransaction) {
                $this->db->transCommit();
            }
        } catch (\Throwable $e) {
            if ($manageTransaction) {
                $this->db->transRollback();
            }
            throw $e;
        }
    }

    /**
     * @throws DatabaseException
     */
    public function decrease(
        int $branchId,
        int $productId,
        int $qty,
        string $refType,
        int $refId,
        ?int $actorUserId = null,
        ?string $note = null,
        bool $manageTransaction = true,
    ): void {
        if ($qty <= 0) {
            throw new DatabaseException('Decrease quantity must be > 0');
        }

        if ($manageTransaction) {
            $this->db->transBegin();
        }

        try {
            $before = $this->lockInventoryRow($branchId, $productId);

            if ($before < $qty) {
                throw new DatabaseException('Insufficient stock');
            }

            $after = $before - $qty;

            $this->db->table('inventory')
                ->where('branch_id', $branchId)
                ->where('product_id', $productId)
                ->update(['quantity' => $after]);

            $this->db->table('inventory_movements')->insert([
                'branch_id'     => $branchId,
                'product_id'    => $productId,
                'delta_qty'     => -$qty,
                'qty_before'    => $before,
                'qty_after'     => $after,
                'ref_type'      => $refType,
                'ref_id'        => $refId,
                'actor_user_id' => $actorUserId,
                'note'          => $note,
            ]);

            if ($manageTransaction) {
                $this->db->transCommit();
            }
        } catch (\Throwable $e) {
            if ($manageTransaction) {
                $this->db->transRollback();
            }
            throw $e;
        }
    }

    /**
     * Adjust can be positive or negative.
     */
    public function adjust(
        int $branchId,
        int $productId,
        int $deltaQty,
        int $refId,
        ?int $actorUserId = null,
        ?string $note = null,
        bool $manageTransaction = true,
    ): void {
        if ($deltaQty === 0) {
            return;
        }

        if ($deltaQty > 0) {
            $this->increase($branchId, $productId, $deltaQty, 'ADJUSTMENT', $refId, $actorUserId, $note, $manageTransaction);
            return;
        }

        $this->decrease($branchId, $productId, abs($deltaQty), 'ADJUSTMENT', $refId, $actorUserId, $note, $manageTransaction);
    }
}
