<?php

namespace App\Controllers\Api\V1;

use App\Models\InventoryMovementModel;
use App\Models\InventoryModel;
use App\Services\AuthorizationService;
use App\Services\InventoryAdjustmentService;
use App\Services\InventoryService;
use CodeIgniter\HTTP\ResponseInterface;

class InventoryController extends BaseApiController
{
    public function index($branchId = null)
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $branchId = (int)$branchId;
        if ($branchId <= 0) {
            return $this->failMessage('Invalid branch id');
        }

        $session = service('session');
        $actorUserId = (int)($session->get('user_id') ?? 0);
        $actorRole = (string)($session->get('role') ?? '');

        try {
            (new AuthorizationService(db_connect()))->assertCanViewBranch($actorUserId, $actorRole, $branchId);
        } catch (\Throwable $e) {
            return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $inventoryModel = new InventoryModel();
        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = (int)($this->request->getGet('per_page') ?? 50);
        $perPage = max(1, min(100, $perPage));
        $q = trim((string)($this->request->getGet('q') ?? ''));

        $result = $inventoryModel->getBranchInventoryWithProductsPaged($branchId, $page, $perPage, $q);
        $rows = $result['rows'];
        $total = (int)$result['total'];
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        return $this->ok([
            'branch_id' => $branchId,
            'inventory' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'q' => $q,
            ],
        ]);
    }

    public function show($branchId = null, $productId = null)
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $branchId = (int)$branchId;
        $productId = (int)$productId;

        if ($branchId <= 0 || $productId <= 0) {
            return $this->failMessage('Invalid branch_id or product_id');
        }

        $session = service('session');
        $actorUserId = (int)($session->get('user_id') ?? 0);
        $actorRole = (string)($session->get('role') ?? '');

        try {
            (new AuthorizationService(db_connect()))->assertCanViewBranch($actorUserId, $actorRole, $branchId);
        } catch (\Throwable $e) {
            return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $inventoryModel = new InventoryModel();
        $qty = $inventoryModel->getProductStock($branchId, $productId);

        return $this->ok([
            'branch_id'  => $branchId,
            'product_id' => $productId,
            'quantity'   => $qty,
        ]);
    }

    public function add($branchId = null)
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $session = service('session');
        $actorUserId = (int)($session->get('user_id') ?? 0);
        $actorRole = (string)($session->get('role') ?? '');

        // Rate limit inventory writes
        $throttler = service('throttler');
        $rawIp = (string)($this->request->getIPAddress() ?? 'unknown');
        $safeIp = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawIp) ?: 'unknown';
        $key = 'inv_add_' . $safeIp . '_' . $actorUserId;
        if (!$throttler->check($key, 60, MINUTE)) {
            return $this->failMessage('Too many requests', ResponseInterface::HTTP_TOO_MANY_REQUESTS);
        }

        $branchId = (int)$branchId;
        if ($branchId <= 0) {
            return $this->failMessage('Invalid branch id');
        }

        $payload = $this->body();
        $validation = service('validation');
        $validation->setRules([
            'product_id' => 'required|is_natural_no_zero',
            'quantity'   => 'required|is_natural_no_zero',
            'note'       => 'permit_empty|max_length[255]',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        $db = db_connect();
        $service = new InventoryAdjustmentService(
            $db,
            new AuthorizationService($db),
            new InventoryService($db)
        );

        try {
            $service->addStock(
                $actorUserId,
                $actorRole,
                $branchId,
                (int)$payload['product_id'],
                (int)$payload['quantity'],
                $payload['note'] ?? 'Add stock'
            );
        } catch (\Throwable $e) {
            return $this->failMessage($e->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        }

        return $this->ok([
            'message' => 'Stock added',
        ], ResponseInterface::HTTP_CREATED);
    }

    public function adjust($branchId = null)
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $session = service('session');
        $actorUserId = (int)($session->get('user_id') ?? 0);
        $actorRole = (string)($session->get('role') ?? '');

        // Rate limit inventory writes
        $throttler = service('throttler');
        $rawIp = (string)($this->request->getIPAddress() ?? 'unknown');
        $safeIp = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawIp) ?: 'unknown';
        $key = 'inv_adjust_' . $safeIp . '_' . $actorUserId;
        if (!$throttler->check($key, 60, MINUTE)) {
            return $this->failMessage('Too many requests', ResponseInterface::HTTP_TOO_MANY_REQUESTS);
        }

        $branchId = (int)$branchId;
        if ($branchId <= 0) {
            return $this->failMessage('Invalid branch id');
        }

        $payload = $this->body();
        $validation = service('validation');
        $validation->setRules([
            'product_id' => 'required|is_natural_no_zero',
            'delta'      => 'required|integer',
            'note'       => 'permit_empty|max_length[255]',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        $db = db_connect();
        $service = new InventoryAdjustmentService(
            $db,
            new AuthorizationService($db),
            new InventoryService($db)
        );

        try {
            $service->adjustStock(
                $actorUserId,
                $actorRole,
                $branchId,
                (int)$payload['product_id'],
                (int)$payload['delta'],
                $payload['note'] ?? 'Adjust stock'
            );
        } catch (\Throwable $e) {
            return $this->failMessage($e->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        }

        return $this->ok([
            'message' => 'Stock adjusted',
        ]);
    }

    public function movements($branchId = null)
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $branchId = (int)$branchId;
        if ($branchId <= 0) {
            return $this->failMessage('Invalid branch id');
        }

        $session = service('session');
        $actorUserId = (int)($session->get('user_id') ?? 0);
        $actorRole = (string)($session->get('role') ?? '');

        try {
            (new AuthorizationService(db_connect()))->assertCanViewBranch($actorUserId, $actorRole, $branchId);
            if (strtoupper(trim($actorRole)) === 'SALES') {
                return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
            }
        } catch (\Throwable $e) {
            return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = (int)($this->request->getGet('per_page') ?? 50);
        $perPage = max(1, min(100, $perPage));
        $q = trim((string)($this->request->getGet('q') ?? ''));
        $productId = (int)($this->request->getGet('product_id') ?? 0);
        $refType = trim((string)($this->request->getGet('ref_type') ?? ''));
        $refType = $refType !== '' ? $refType : null;

        $model = new InventoryMovementModel();
        $result = $model->getBranchMovementsPaged($branchId, $page, $perPage, $q, $productId > 0 ? $productId : null, $refType);
        $rows = $result['rows'];
        $total = (int)$result['total'];
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        return $this->ok([
            'branch_id' => $branchId,
            'movements' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'q' => $q,
            ],
        ]);
    }
}
