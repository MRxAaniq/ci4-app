<?php

namespace App\Models;

use CodeIgniter\Model;

class ProductModel extends Model
{
    protected $table            = 'products';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'name',
        'sku',
        'cost_price',
        'sale_price',
        'tax_percentage',
        'status',
    ];

    protected $protectFields = true;

    /** @return array<string, mixed>|null */
    public function findBySku(string $sku): ?array
    {
        $row = $this->where('sku', $sku)->first();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getActiveProducts(): array
    {
        return $this->where('status', 'ACTIVE')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function calculateUnitTax(float $unitPrice, float $taxPercentage): float
    {
        // Example: 15% => unit tax = price * 0.15
        return round($unitPrice * ($taxPercentage / 100), 2);
    }
}
