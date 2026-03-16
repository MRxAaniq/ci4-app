<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    protected $allowedFields = [
        'name',
        'email',
        'password_hash',
        'role',
        'branch_id',
        'status',
    ];

    // Basic hygiene
    protected $protectFields = true;

    /** @return array<string, mixed>|null */
    public function findByEmail(string $email): ?array
    {
        $row = $this->where('email', $email)->first();
        return $row ?: null;
    }

    /** @return array<int, array<string, mixed>> */
    public function getActiveByRole(string $role): array
    {
        return $this->where('role', $role)
            ->where('status', 'ACTIVE')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function isAdmin(array $user): bool
    {
        $role = strtoupper((string) ($user['role'] ?? ''));
        return $role === 'ADMIN' || $role === 'SUPER_ADMIN';
    }

    public function isBranchManager(array $user): bool
    {
        return ($user['role'] ?? null) === 'BRANCH_MANAGER';
    }

    public function isSales(array $user): bool
    {
        return ($user['role'] ?? null) === 'SALES';
    }
}
