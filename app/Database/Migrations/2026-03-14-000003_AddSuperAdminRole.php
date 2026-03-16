<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSuperAdminRole extends Migration
{
    public function up()
    {
        // Expand role enum to include SUPER_ADMIN.
        // Keep ADMIN for backward compatibility with existing data/tests.
        $this->db->query("ALTER TABLE users MODIFY role ENUM('ADMIN','SUPER_ADMIN','BRANCH_MANAGER','SALES') NOT NULL");
    }

    public function down()
    {
        // Revert enum: any SUPER_ADMIN rows must be migrated back to ADMIN first.
        $this->db->query("UPDATE users SET role='ADMIN' WHERE role='SUPER_ADMIN'");
        $this->db->query("ALTER TABLE users MODIFY role ENUM('ADMIN','BRANCH_MANAGER','SALES') NOT NULL");
    }
}
