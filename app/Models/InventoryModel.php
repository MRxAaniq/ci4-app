<?php

namespace App\Models;

use CodeIgniter\Model;

class InventoryModel extends Model
{
    protected $table      = 'inventory';
    protected $primaryKey = '';
    protected $returnType = 'array';

    // Composite PK (branch_id, product_id) => disable Model's single-key assumptions
    protected $useAutoIncrement = false;

    // Table has only updated_at, not created_at
    protected $useTimestamps = false;

    protected $allowedFields = [
        'branch_id',
        'product_id',
        'quantity',
        'updated_at',
    ];

    protected $protectFields = true;

    public function getProductStock(int $branchId, int $productId): int
    {
        $row = $this->select('quantity')
            ->where('branch_id', $branchId)
            ->where('product_id', $productId)
            ->first();

        return (int)($row['quantity'] ?? 0);
    }

    /**
     * Returns inventory rows for a branch joined with product info (for UI/API listing).
     *
     * @return array<int, array<string, mixed>>
     */
    public function getBranchInventoryWithProducts(int $branchId): array
    {
        $builder = $this->db->table('inventory i');
        $builder->select('i.branch_id, i.product_id, i.quantity, i.updated_at, p.name, p.sku, p.sale_price, p.tax_percentage, p.status');
        $builder->join('products p', 'p.id = i.product_id', 'inner');
        $builder->where('i.branch_id', $branchId);
        $builder->orderBy('p.name', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Paginated inventory rows for a branch joined with product info.
     *
     * @return array{rows: array<int, array<string, mixed>>, total: int}
     */
    public function getBranchInventoryWithProductsPaged(int $branchId, int $page, int $perPage, string $q = ''): array
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $q = trim($q);

        $apply = static function ($builder) use ($branchId, $q) {
            $builder->where('i.branch_id', $branchId);
            if ($q !== '') {
                $builder->groupStart()
                    ->like('p.name', $q)
                    ->orLike('p.sku', $q)
                    ->groupEnd();
            }
        };

        $countBuilder = $this->db->table('inventory i');
        $countBuilder->join('products p', 'p.id = i.product_id', 'inner');
        $apply($countBuilder);
        $total = (int)$countBuilder->countAllResults();

        $builder = $this->db->table('inventory i');
        $builder->select('i.branch_id, i.product_id, i.quantity, i.updated_at, p.name, p.sku, p.sale_price, p.tax_percentage, p.status');
        $builder->join('products p', 'p.id = i.product_id', 'inner');
        $apply($builder);
        $builder->orderBy('p.name', 'ASC');
        $builder->limit($perPage, ($page - 1) * $perPage);

        return [
            'rows' => $builder->get()->getResultArray(),
            'total' => $total,
        ];
    }

    /**
     * Safe stock decrease using the DB stored procedure (row-locking + non-negative guarantee).
     *
     * Note: This is intentionally a thin helper; business workflows should live in Services.
     */
    public function decreaseStockViaProcedure(
        int $branchId,
        int $productId,
        int $qty,
        string $refType,
        int $refId,
        ?int $actorUserId = null,
        ?string $note = null
    ): void {
        $this->db->query(
            'CALL sp_inventory_decrease(?, ?, ?, ?, ?, ?, ?)',
            [
                $branchId,
                $productId,
                $qty,
                $refType,
                $refId,
                $actorUserId,
                $note,
            ]
        );
    }

    public function increaseStockViaProcedure(
        int $branchId,
        int $productId,
        int $qty,
        string $refType,
        int $refId,
        ?int $actorUserId = null,
        ?string $note = null
    ): void {
        $this->db->query(
            'CALL sp_inventory_increase(?, ?, ?, ?, ?, ?, ?)',
            [
                $branchId,
                $productId,
                $qty,
                $refType,
                $refId,
                $actorUserId,
                $note,
            ]
        );
    }
}
