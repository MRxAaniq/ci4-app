<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddUserBranchId extends Migration
{
    public function up()
    {
        // Add a direct branch assignment for SALES users.
        // (Admins and managers may be NULL.)
        $this->db->query('ALTER TABLE users ADD COLUMN branch_id BIGINT UNSIGNED NULL AFTER role');
        $this->db->query('CREATE INDEX idx_users_branch_id ON users(branch_id)');
        $this->db->query('ALTER TABLE users ADD CONSTRAINT fk_users_branch FOREIGN KEY (branch_id) REFERENCES branches(id) ON UPDATE CASCADE ON DELETE SET NULL');
    }

    public function down()
    {
        $this->db->query('ALTER TABLE users DROP FOREIGN KEY fk_users_branch');
        $this->db->query('DROP INDEX idx_users_branch_id ON users');
        $this->db->query('ALTER TABLE users DROP COLUMN branch_id');
    }
}
