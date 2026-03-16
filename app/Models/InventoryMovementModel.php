<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryMovementModel extends Model
{
    protected $table      = 'inventory_movements';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'branch_id',
        'product_id',
        'delta_qty',
        'qty_before',
        'qty_after',
        'ref_type',
        'ref_id',
        'actor_user_id',
        'note',
        'created_at',
    ];

    protected $protectFields = true;

    /**
     * Paginated movement history for a branch.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function getBranchMovementsPaged(
        int $branchId,
        int $page,
        int $perPage,
        string $q = '',
        ?int $productId = null,
        ?string $refType = null
    ): array {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $q = trim($q);
        $refType = $refType !== null ? strtoupper(trim($refType)) : null;

        $apply = static function ($builder) use ($branchId, $q, $productId, $refType) {
            $builder->where('m.branch_id', $branchId);

            if ($productId !== null && $productId > 0) {
                $builder->where('m.product_id', $productId);
            }

            if ($refType !== null && in_array($refType, ['ORDER', 'TRANSFER', 'ADJUSTMENT'], true)) {
                $builder->where('m.ref_type', $refType);
            }

            if ($q !== '') {
                $builder->groupStart()
                    ->like('p.name', $q)
                    ->orLike('p.sku', $q)
                    ->groupEnd();
            }
        };

        $countBuilder = $this->db->table('inventory_movements m');
        $countBuilder->join('products p', 'p.id = m.product_id', 'inner');
        $apply($countBuilder);
        $total = (int) $countBuilder->countAllResults();

        $builder = $this->db->table('inventory_movements m');
        $builder->select('m.id, m.branch_id, m.product_id, m.delta_qty, m.qty_before, m.qty_after, m.ref_type, m.ref_id, m.actor_user_id, m.note, m.created_at');
        $builder->select('p.name AS product_name, p.sku AS product_sku');
        $builder->select('u.name AS actor_name, u.email AS actor_email');
        $builder->join('products p', 'p.id = m.product_id', 'inner');
        $builder->join('users u', 'u.id = m.actor_user_id', 'left');
        $apply($builder);
        $builder->orderBy('m.id', 'DESC');
        $builder->limit($perPage, ($page - 1) * $perPage);

        return [
            'rows'  => $builder->get()->getResultArray(),
            'total' => $total,
        ];
    }
}
