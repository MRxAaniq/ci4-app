<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

class InventoryAdjustmentService
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly AuthorizationService $auth,
        private readonly InventoryService $inventory,
    ) {
    }

    public function addStock(int $actorUserId, string $actorRole, int $branchId, int $productId, int $qty, ?string $note = null): void
    {
        $this->auth->assertCanAdjustInventory($actorUserId, $actorRole, $branchId);

        $this->inventory->increase(
            $branchId,
            $productId,
            $qty,
            'ADJUSTMENT',
            0,
            $actorUserId,
            $note ?? 'Add stock'
        );
    }

    public function adjustStock(int $actorUserId, string $actorRole, int $branchId, int $productId, int $delta, ?string $note = null): void
    {
        $this->auth->assertCanAdjustInventory($actorUserId, $actorRole, $branchId);

        $this->inventory->adjust(
            $branchId,
            $productId,
            $delta,
            0,
            $actorUserId,
            $note ?? 'Adjust stock'
        );
    }
}
