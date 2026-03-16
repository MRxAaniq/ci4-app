<?php

namespace App\Services;

use App\Models\StockTransferModel;
use CodeIgniter\Database\BaseConnection;
use CodeIgniter\Database\Exceptions\DatabaseException;

class StockTransferService
{
    public function __construct(
        private readonly BaseConnection $db,
        private readonly InventoryService $inventoryService,
    ) {
    }

    /** @return array<string, mixed> */
    public function transfer(
        int $fromBranch,
        int $toBranch,
        int $productId,
        int $quantity,
        ?int $actorUserId = null,
        string $actorRole = '',
    ): array {
        if ($fromBranch <= 0 || $toBranch <= 0 || $productId <= 0 || $quantity <= 0) {
            throw new DatabaseException('Invalid transfer payload');
        }

        if ($fromBranch === $toBranch) {
            throw new DatabaseException('from_branch and to_branch must be different');
        }

        $transferModel = new StockTransferModel($this->db);

        $this->db->transBegin();

        try {
            // Defense-in-depth authorization
            if ($actorUserId !== null && $actorUserId > 0) {
                (new AuthorizationService($this->db))->assertCanTransferFromBranch($actorUserId, $actorRole, $fromBranch);
            } else {
                throw new DatabaseException('Unauthorized');
            }

            $transferId = (int)$transferModel->insert([
                'from_branch' => $fromBranch,
                'to_branch'   => $toBranch,
                'product_id'  => $productId,
                'quantity'    => $quantity,
                'status'      => 'DRAFT',
                'created_by'  => $actorUserId,
            ], true);

            if ($transferId <= 0) {
                throw new DatabaseException('Failed to create transfer');
            }

            // Deduct from source and add to destination in the SAME transaction
            $this->inventoryService->decrease(
                $fromBranch,
                $productId,
                $quantity,
                'TRANSFER',
                $transferId,
                $actorUserId,
                'Transfer out',
                false
            );

            $this->inventoryService->increase(
                $toBranch,
                $productId,
                $quantity,
                'TRANSFER',
                $transferId,
                $actorUserId,
                'Transfer in',
                false
            );

            $transferModel->update($transferId, [
                'status'      => 'RECEIVED',
                'sent_at'     => date('Y-m-d H:i:s'),
                'received_at' => date('Y-m-d H:i:s'),
            ]);

            $this->db->transCommit();

            $transfer = $transferModel->getWithNames($transferId);
            return $transfer ?: ['id' => $transferId];
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
    }
}
