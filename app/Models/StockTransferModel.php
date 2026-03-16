<?php

namespace App\Models;

use CodeIgniter\Model;

class StockTransferModel extends Model
{
    protected $table            = 'stock_transfers';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'from_branch',
        'to_branch',
        'product_id',
        'quantity',
        'status',
        'created_by',
        'sent_at',
        'received_at',
    ];

    protected $protectFields = true;

    /** @return array<string, mixed>|null */
    public function getWithNames(int $transferId): ?array
    {
        $builder = $this->db->table('stock_transfers t');
        $builder->select('t.*, fb.name AS from_branch_name, tb.name AS to_branch_name, p.name AS product_name, p.sku');
        $builder->join('branches fb', 'fb.id = t.from_branch', 'inner');
        $builder->join('branches tb', 'tb.id = t.to_branch', 'inner');
        $builder->join('products p', 'p.id = t.product_id', 'inner');
        $builder->where('t.id', $transferId);

        $row = $builder->get()->getRowArray();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getQueueForBranch(int $branchId, string $direction = 'IN', ?string $status = null, int $limit = 50): array
    {
        $builder = $this->db->table('stock_transfers t');
        $builder->select('t.*, p.name AS product_name, p.sku');
        $builder->join('products p', 'p.id = t.product_id', 'inner');

        if (strtoupper($direction) === 'OUT') {
            $builder->where('t.from_branch', $branchId);
        } else {
            $builder->where('t.to_branch', $branchId);
        }

        if ($status !== null) {
            $builder->where('t.status', $status);
        }

        $builder->orderBy('t.id', 'DESC');
        $builder->limit($limit);

        return $builder->get()->getResultArray();
    }

    public function markSent(int $transferId): void
    {
        $this->update($transferId, [
            'status'  => 'SENT',
            'sent_at' => date('Y-m-d H:i:s'),
        ]);
    }

    public function markReceived(int $transferId): void
    {
        $this->update($transferId, [
            'status'      => 'RECEIVED',
            'received_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
