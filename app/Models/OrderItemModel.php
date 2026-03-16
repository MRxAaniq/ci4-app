<?php

namespace App\Models;

use CodeIgniter\Model;

class OrderItemModel extends Model
{
    protected $table            = 'order_items';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    // Table has created_at only
    protected $useTimestamps = false;

    protected $allowedFields = [
        'order_id',
        'product_id',
        'quantity',
        'price',
        'tax',
        'created_at',
    ];

    protected $protectFields = true;

    /** @return array<int, array<string, mixed>> */
    public function getItemsByOrder(int $orderId): array
    {
        return $this->where('order_id', $orderId)
            ->orderBy('id', 'ASC')
            ->findAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function getItemsWithProduct(int $orderId): array
    {
        $builder = $this->db->table('order_items oi');
        $builder->select('oi.*, p.name AS product_name, p.sku, p.tax_percentage');
        $builder->join('products p', 'p.id = oi.product_id', 'inner');
        $builder->where('oi.order_id', $orderId);
        $builder->orderBy('oi.id', 'ASC');

        return $builder->get()->getResultArray();
    }

    /**
     * Upserts (order_id, product_id) due to unique constraint.
     *
     * If the row exists, increases quantity and overwrites unit price/tax.
     */
    public function addOrUpdateItem(int $orderId, int $productId, int $quantity, float $unitPrice, float $lineTax): void
    {
        $existing = $this->where('order_id', $orderId)
            ->where('product_id', $productId)
            ->first();

        if ($existing) {
            $this->update($existing['id'], [
                'quantity' => (int)$existing['quantity'] + $quantity,
                'price'    => $unitPrice,
                'tax'      => $lineTax,
            ]);
            return;
        }

        $this->insert([
            'order_id'   => $orderId,
            'product_id' => $productId,
            'quantity'   => $quantity,
            'price'      => $unitPrice,
            'tax'        => $lineTax,
        ]);
    }
}
