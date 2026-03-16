<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

final class CreateInventoryReservations extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('inventory_reservations')) {
            return;
        }

        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'constraint'     => 11,
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'branch_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'product_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'order_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'quantity' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['branch_id', 'product_id']);
        $this->forge->addKey(['order_id']);

        $this->forge->createTable('inventory_reservations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('inventory_reservations', true);
    }
}
