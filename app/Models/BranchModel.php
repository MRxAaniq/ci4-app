<?php

namespace App\Models;

use CodeIgniter\Model;

class BranchModel extends Model
{
    protected $table            = 'branches';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'name',
        'address',
        'manager_id',
        'status',
    ];

    protected $protectFields = true;

    /** @return array<string, mixed>|null */
    public function getWithManager(int $branchId): ?array
    {
        $builder = $this->db->table($this->table . ' b');
        $builder->select('b.*, u.name AS manager_name, u.email AS manager_email');
        $builder->join('users u', 'u.id = b.manager_id', 'left');
        $builder->where('b.id', $branchId);

        $row = $builder->get()->getRowArray();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getActiveBranches(): array
    {
        return $this->where('status', 'ACTIVE')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    /** @return array<int, array<string, mixed>> */
    public function getByManager(int $managerUserId): array
    {
        return $this->where('manager_id', $managerUserId)
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
