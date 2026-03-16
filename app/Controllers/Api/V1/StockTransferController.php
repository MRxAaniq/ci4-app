<?php

namespace App\Controllers\Api\V1;

use App\Services\InventoryService;
use App\Services\StockTransferService;
use CodeIgniter\HTTP\ResponseInterface;

class StockTransferController extends BaseApiController
{
    public function create()
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $payload = $this->body();

        $validation = service('validation');
        $validation->setRules([
            'from_branch' => 'required|is_natural_no_zero',
            'to_branch'   => 'required|is_natural_no_zero',
            'product_id'  => 'required|is_natural_no_zero',
            'quantity'    => 'required|is_natural_no_zero',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        $session = service('session');
        $actorUserId = (int)($session->get('user_id') ?? 0);
        $actorRole = (string)($session->get('role') ?? '');
        if ($actorUserId <= 0) {
            return $this->failMessage('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        // Rate limit transfers
        $throttler = service('throttler');
        $rawIp = (string)($this->request->getIPAddress() ?? 'unknown');
        $safeIp = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawIp) ?: 'unknown';
        $key = 'transfer_create_' . $safeIp . '_' . $actorUserId;
        if (!$throttler->check($key, 30, MINUTE)) {
            return $this->failMessage('Too many requests', ResponseInterface::HTTP_TOO_MANY_REQUESTS);
        }

        $db = db_connect();
        $service = new StockTransferService(
            $db,
            new InventoryService($db)
        );

        try {
            $transfer = $service->transfer(
                (int)$payload['from_branch'],
                (int)$payload['to_branch'],
                (int)$payload['product_id'],
                (int)$payload['quantity'],
                $actorUserId ?: null,
                $actorRole
            );

            return $this->ok([
                'message'  => 'Transfer completed',
                'transfer' => $transfer,
            ], ResponseInterface::HTTP_CREATED);
        } catch (\Throwable $e) {
            return $this->failMessage($e->getMessage(), ResponseInterface::HTTP_BAD_REQUEST);
        }
    }
}
