<?php

namespace App\Controllers\Api\V1;

use App\Models\OrderModel;
use App\Services\AuthorizationService;
use App\Services\InventoryService;
use App\Services\OrderService;
use App\Services\TaxService;
use CodeIgniter\HTTP\ResponseInterface;

class OrderController extends BaseApiController
{
    public function index($branchId = null)
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $branchId = (int) $branchId;
        if ($branchId <= 0) {
            return $this->failMessage('Invalid branch id');
        }

        $session = service('session');
        $actorUserId = (int) ($session->get('user_id') ?? 0);
        $actorRole = (string) ($session->get('role') ?? '');

        try {
            (new AuthorizationService(db_connect()))->assertCanViewBranch($actorUserId, $actorRole, $branchId);
        } catch (\Throwable $e) {
            return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $page = max(1, (int) ($this->request->getGet('page') ?? 1));
        $perPage = (int) ($this->request->getGet('per_page') ?? 50);
        $perPage = max(1, min(100, $perPage));
        $q = trim((string) ($this->request->getGet('q') ?? ''));
        $status = trim((string) ($this->request->getGet('status') ?? ''));
        $status = $status !== '' ? strtoupper($status) : null;

        $model = new OrderModel();
        $result = $model->getByBranchPaged($branchId, $page, $perPage, $status, $q);
        $rows = $result['rows'];
        $total = (int) ($result['total'] ?? 0);
        $totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

        return $this->ok([
            'branch_id' => $branchId,
            'orders' => $rows,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'q' => $q,
                'status' => $status,
            ],
        ]);
    }

    public function create()
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $payload = $this->body();

        $validation = service('validation');
        $validation->setRules([
            'branch_id' => 'required|is_natural_no_zero',
            // Required input contract: products[] with product_id + quantity
            'products'  => 'required',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        if (!is_array($payload['products'])) {
            return $this->failMessage('products must be an array', ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
        }

        // Authenticated user only (prevents impersonation)
        $session = service('session');
        $sessionUserId = (int)($session->get('user_id') ?? 0);
        $actorRole = (string)($session->get('role') ?? '');
        if ($sessionUserId <= 0) {
            return $this->failMessage('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        // Rate limit order creation
        $throttler = service('throttler');
        $rawIp = (string)($this->request->getIPAddress() ?? 'unknown');
        $safeIp = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawIp) ?: 'unknown';
        $key = 'order_create_' . $safeIp . '_' . $sessionUserId;
        if (!$throttler->check($key, 30, MINUTE)) {
            return $this->failMessage('Too many requests', ResponseInterface::HTTP_TOO_MANY_REQUESTS);
        }

        // Validate each product row
        $products = [];
        foreach ($payload['products'] as $idx => $row) {
            if (!is_array($row)) {
                return $this->failMessage("products[$idx] must be an object", ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
            }

            $productId = (int)($row['product_id'] ?? 0);
            $quantity  = (int)($row['quantity'] ?? 0);

            if ($productId <= 0 || $quantity <= 0) {
                return $this->failMessage(
                    "Invalid products[$idx]: product_id and quantity are required and must be > 0",
                    ResponseInterface::HTTP_UNPROCESSABLE_ENTITY
                );
            }

            $products[] = [
                'product_id' => $productId,
                'quantity'   => $quantity,
            ];
        }

        $actorUserId = $sessionUserId;

        $db = db_connect();
        $service = new OrderService(
            $db,
            new TaxService(),
            new InventoryService($db)
        );

        try {
            $order = $service->createOrderFromProducts(
                (int)$payload['branch_id'],
                $sessionUserId,
                $products,
                $actorUserId ?: null,
                $actorRole
            );

            return $this->ok([
                'message' => 'Order placed (pending approval)',
                'order'   => $order,
            ], ResponseInterface::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->failMessage($e->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        }
    }

    public function approve($id = null)
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $orderId = (int) $id;
        if ($orderId <= 0) {
            return $this->failMessage('Invalid order id');
        }

        $session = service('session');
        $actorUserId = (int) ($session->get('user_id') ?? 0);
        $actorRole = (string) ($session->get('role') ?? '');
        if ($actorUserId <= 0) {
            return $this->failMessage('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $db = db_connect();
        $service = new OrderService(
            $db,
            new TaxService(),
            new InventoryService($db)
        );

        try {
            $order = $service->approveOrder($orderId, $actorUserId, $actorRole);
            return $this->ok([
                'message' => 'Order approved',
                'order' => $order,
            ]);
        } catch (\Throwable $e) {
            return $this->failMessage($e->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        }
    }
}
