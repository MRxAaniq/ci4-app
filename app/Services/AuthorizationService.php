<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;

class AuthorizationService
{
    public function __construct(private readonly BaseConnection $db)
    {
    }

    public function assertCanCreateOrder(int $userId, string $role, int $branchId): void
    {
        $role = strtoupper(trim($role));

        // Requirement: Admin (Super Admin) cannot create orders.
        if ($role === 'ADMIN' || $role === 'SUPER_ADMIN') {
            throw new DatabaseException('Forbidden');
        }

        $this->assertCanAccessBranch($userId, $role, $branchId, 'create order');
    }

    public function assertCanViewBranch(int $userId, string $role, int $branchId): void
    {
        $this->assertCanAccessBranch($userId, $role, $branchId, 'view branch data');
    }

    public function assertCanAdjustInventory(int $userId, string $role, int $branchId): void
    {
        $this->assertCanAccessBranch($userId, $role, $branchId, 'adjust inventory');

        $role = strtoupper($role);
        if ($role === 'SALES') {
            throw new DatabaseException('Forbidden');
        }
    }

    public function assertCanTransferFromBranch(int $userId, string $role, int $fromBranchId): void
    {
        $this->assertCanAccessBranch($userId, $role, $fromBranchId, 'transfer stock');

        $role = strtoupper($role);
        if ($role === 'SALES') {
            throw new DatabaseException('Forbidden');
        }
    }

    private function assertCanAccessBranch(int $userId, string $role, int $branchId, string $action): void
    {
        $role = strtoupper(trim($role));

        if ($userId <= 0 || $branchId <= 0) {
            throw new DatabaseException('Invalid authorization context');
        }

        if ($role === 'ADMIN' || $role === 'SUPER_ADMIN') {
            return;
        }

        if ($role === 'BRANCH_MANAGER') {
            $isManager = (bool) $this->db->table('branches')
                ->select('id')
                ->where('id', $branchId)
                ->where('manager_id', $userId)
                ->get()
                ->getRowArray();

            if (!$isManager) {
                throw new DatabaseException('Forbidden');
            }

            return;
        }

        if ($role === 'SALES') {
            $row = $this->db->table('users')
                ->select('branch_id')
                ->where('id', $userId)
                ->get()
                ->getRowArray();

            $userBranchId = (int)($row['branch_id'] ?? 0);

            if ($userBranchId <= 0 || $userBranchId !== $branchId) {
                throw new DatabaseException('Forbidden');
            }

            return;
        }

        throw new DatabaseException('Forbidden: unknown role for ' . $action);
    }
}
