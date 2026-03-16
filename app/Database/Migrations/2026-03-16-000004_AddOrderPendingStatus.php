<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddOrderPendingStatus extends Migration
{
    public function up()
    {
        // Add PENDING for "reserved" orders awaiting manager approval.
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('DRAFT','PENDING','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT'");
    }

    public function down()
    {
        // Revert enum: migrate any PENDING rows back to DRAFT first.
        $this->db->query("UPDATE orders SET status='DRAFT' WHERE status='PENDING'");
        $this->db->query("ALTER TABLE orders MODIFY status ENUM('DRAFT','SUBMITTED','CANCELLED') NOT NULL DEFAULT 'DRAFT'");
    }
}
