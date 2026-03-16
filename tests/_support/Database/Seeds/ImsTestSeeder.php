<?php

declare(strict_types=1);

namespace Tests\Support\Database\Seeds;

use CodeIgniter\Database\Seeder;

final class ImsTestSeeder extends Seeder
{
    public function run(): void
    {
        // Clean in FK-safe order
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        if ($this->db->tableExists('inventory_reservations')) {
            $this->db->table('inventory_reservations')->truncate();
        }
        $this->db->table('inventory_movements')->truncate();
        $this->db->table('order_items')->truncate();
        $this->db->table('orders')->truncate();
        $this->db->table('stock_transfers')->truncate();
        $this->db->table('inventory')->truncate();
        $this->db->table('products')->truncate();
        $this->db->table('branches')->truncate();
        $this->db->table('users')->truncate();
        $this->db->query('SET FOREIGN_KEY_CHECKS=1');

        // Users (insert those referenced by branches first)
        $this->db->table('users')->insertBatch([
            [
                'id'            => 1,
                'name'          => 'Admin User',
                'email'         => 'admin@example.com',
                'password_hash' => password_hash('password', PASSWORD_DEFAULT),
                'role'          => 'ADMIN',
                'status'        => 'ACTIVE',
                'branch_id'     => null,
            ],
            [
                'id'            => 2,
                'name'          => 'Manager A',
                'email'         => 'manager.a@example.com',
                'password_hash' => password_hash('password', PASSWORD_DEFAULT),
                'role'          => 'BRANCH_MANAGER',
                'status'        => 'ACTIVE',
                'branch_id'     => null,
            ],
        ]);

        // Branches (references users.manager_id)
        $this->db->table('branches')->insertBatch([
            [
                'id'         => 10,
                'name'       => 'Branch A',
                'address'    => 'Address A',
                'manager_id' => 2,
                'status'     => 'ACTIVE',
            ],
            [
                'id'         => 20,
                'name'       => 'Branch B',
                'address'    => 'Address B',
                'manager_id' => null,
                'status'     => 'ACTIVE',
            ],
        ]);

        // Sales user (references branches.branch_id)
        $this->db->table('users')->insert([
            'id'            => 3,
            'name'          => 'Sales A',
            'email'         => 'sales.a@example.com',
            'password_hash' => password_hash('password', PASSWORD_DEFAULT),
            'role'          => 'SALES',
            'status'        => 'ACTIVE',
            'branch_id'     => 10,
        ]);

        // Products
        $this->db->table('products')->insertBatch([
            [
                'id'             => 100,
                'name'           => 'Product Taxed',
                'sku'            => 'P-TAX',
                'cost_price'     => 60.00,
                'sale_price'     => 100.00,
                'tax_percentage' => 15.00,
                'status'         => 'ACTIVE',
            ],
            [
                'id'             => 200,
                'name'           => 'Product No Tax',
                'sku'            => 'P-NOTAX',
                'cost_price'     => 20.00,
                'sale_price'     => 50.00,
                'tax_percentage' => 0.00,
                'status'         => 'ACTIVE',
            ],
        ]);

        // Inventory
        $this->db->table('inventory')->insertBatch([
            ['branch_id' => 10, 'product_id' => 100, 'quantity' => 20],
            ['branch_id' => 10, 'product_id' => 200, 'quantity' => 20],
            ['branch_id' => 20, 'product_id' => 100, 'quantity' => 1],
            ['branch_id' => 20, 'product_id' => 200, 'quantity' => 1],
        ]);
    }
}
