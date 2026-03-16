<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderModel extends Model
{
    protected $table            = 'orders';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'branch_id',
        'user_id',
        'subtotal',
        'tax_total',
        'grand_total',
        'status',
    ];

    protected $protectFields = true;

    /** @return array<string, mixed>|null */
    public function getOrderWithItems(int $orderId): ?array
    {
        $order = $this->find($orderId);
        if (!$order) {
            return null;
        }

        $items = (new OrderItemModel())
            ->getItemsWithProduct($orderId);

        $order['items'] = $items;

        return $order;
    }

    /**
     * Recalculates totals from existing order_items.
     * Useful as a consistency helper; finalization should still happen in a Service + transaction.
     */
    public function recalculateTotals(int $orderId): void
    {
        $builder = $this->db->table('order_items');
        $builder->select('SUM(quantity * price) AS subtotal, SUM(tax) AS tax_total');
        $builder->where('order_id', $orderId);
        $totals = $builder->get()->getRowArray() ?: ['subtotal' => 0, 'tax_total' => 0];

        $subtotal  = (float)($totals['subtotal'] ?? 0);
        $taxTotal  = (float)($totals['tax_total'] ?? 0);
        $grandTotal = $subtotal + $taxTotal;

        $this->update($orderId, [
            'subtotal'    => $subtotal,
            'tax_total'   => $taxTotal,
            'grand_total' => $grandTotal,
        ]);
    }

    /** @return array<int, array<string, mixed>> */
    public function getByBranch(int $branchId, ?string $status = null, int $limit = 50): array
    {
        $builder = $this->where('branch_id', $branchId);

        if ($status !== null) {
            $builder = $builder->where('status', $status);
        }

        return $builder->orderBy('id', 'DESC')
            ->findAll($limit);
    }

    /**
     * Paginated orders for a branch.
     * Includes creator user info (name/email) for display.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function getByBranchPaged(int $branchId, int $page, int $perPage, ?string $status = null, string $q = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $q = trim($q);

        $apply = static function ($builder) use ($branchId, $status, $q) {
            $builder->where('o.branch_id', $branchId);
            if ($status !== null && $status !== '') {
                $builder->where('o.status', $status);
            }
            if ($q !== '') {
                $builder->groupStart()
                    ->like('u.name', $q)
                    ->orLike('u.email', $q)
                    ->orLike('o.id', $q)
                    ->groupEnd();
            }
        };

        $countBuilder = $this->db->table('orders o');
        $countBuilder->join('users u', 'u.id = o.user_id', 'inner');
        $apply($countBuilder);
        $total = (int) $countBuilder->countAllResults();

        $builder = $this->db->table('orders o');
        $builder->select('o.*, u.name AS user_name, u.email AS user_email');
        $builder->join('users u', 'u.id = o.user_id', 'inner');
        $apply($builder);
        $builder->orderBy('o.id', 'DESC');
        $builder->limit($perPage, ($page - 1) * $perPage);

        return [
            'rows' => $builder->get()->getResultArray(),
            'total' => $total,
        ];
    }
}
