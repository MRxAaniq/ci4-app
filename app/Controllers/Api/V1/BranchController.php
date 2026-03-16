<?php

namespace App\Controllers\Api\V1;

use App\Models\BranchModel;
use App\Services\AuthorizationService;
use CodeIgniter\HTTP\ResponseInterface;

class BranchController extends BaseApiController
{
    public function index()
    {
        $session = service('session');
        $actorUserId = (int) ($session->get('user_id') ?? 0);
        $actorRole = strtoupper((string) ($session->get('role') ?? ''));
        $actorBranchId = (int) ($session->get('branch_id') ?? 0);

        if ($actorUserId <= 0 || $actorRole === '') {
            return $this->failMessage('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = (int)($this->request->getGet('per_page') ?? 50);
        $perPage = max(1, min(100, $perPage));
        $q = trim((string)($this->request->getGet('q') ?? ''));

        $db = db_connect();

        $countBuilder = $db->table('branches');
        if ($q !== '') {
            $countBuilder->groupStart()
                ->like('name', $q)
                ->orLike('address', $q)
                ->groupEnd();
        }

        // Role-based scoping:
        // - ADMIN/SUPER_ADMIN: all branches
        // - BRANCH_MANAGER: only the branch they manage
        // - SALES: only their assigned branch
        if ($actorRole === 'BRANCH_MANAGER') {
            $countBuilder->where('manager_id', $actorUserId);
        } elseif ($actorRole === 'SALES') {
            if ($actorBranchId <= 0) {
                return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
            }
            $countBuilder->where('id', $actorBranchId);
        } elseif ($actorRole !== 'ADMIN' && $actorRole !== 'SUPER_ADMIN') {
            return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $total = (int)$countBuilder->countAllResults();

        $builder = $db->table('branches');
        $builder->select('*');
        if ($q !== '') {
            $builder->groupStart()
                ->like('name', $q)
                ->orLike('address', $q)
                ->groupEnd();
        }

        if ($actorRole === 'BRANCH_MANAGER') {
            $builder->where('manager_id', $actorUserId);
        } elseif ($actorRole === 'SALES') {
            $builder->where('id', $actorBranchId);
        }

        $builder->orderBy('id', 'DESC');
        $builder->limit($perPage, ($page - 1) * $perPage);

        $branches = $builder->get()->getResultArray();
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        return $this->ok([
            'branches' => $branches,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'q' => $q,
            ],
        ]);
    }

    public function show($id = null)
    {
        $branchId = (int)$id;
        if ($branchId <= 0) {
            return $this->failMessage('Invalid branch id');
        }

        $session = service('session');
        $actorUserId = (int) ($session->get('user_id') ?? 0);
        $actorRole = (string) ($session->get('role') ?? '');
        if ($actorUserId <= 0) {
            return $this->failMessage('Unauthorized', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        try {
            (new AuthorizationService(db_connect()))->assertCanViewBranch($actorUserId, $actorRole, $branchId);
        } catch (\Throwable $e) {
            return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $model = new BranchModel();
        $branch = $model->find($branchId);
        if (!$branch) {
            return $this->failMessage('Branch not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        return $this->ok([
            'branch' => $branch,
        ]);
    }

    public function create()
    {
        $payload = $this->body();

        $validation = service('validation');
        $validation->setRules([
            'name'       => 'required|max_length[150]',
            'address'    => 'required|max_length[255]',
            'manager_id' => 'permit_empty|is_natural_no_zero',
            'status'     => 'permit_empty|in_list[ACTIVE,INACTIVE]',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        $model = new BranchModel();

        $id = (int)$model->insert([
            'name'       => (string)$payload['name'],
            'address'    => (string)$payload['address'],
            'manager_id' => $payload['manager_id'] ?? null,
            'status'     => $payload['status'] ?? 'ACTIVE',
        ], true);

        return $this->ok([
            'message' => 'Branch created',
            'branch'  => $model->find($id),
        ], ResponseInterface::HTTP_CREATED);
    }

    public function update($id = null)
    {
        $branchId = (int)$id;
        if ($branchId <= 0) {
            return $this->failMessage('Invalid branch id');
        }

        $payload = $this->body();

        $validation = service('validation');
        $validation->setRules([
            'name'       => 'permit_empty|max_length[150]',
            'address'    => 'permit_empty|max_length[255]',
            'manager_id' => 'permit_empty|is_natural_no_zero',
            'status'     => 'permit_empty|in_list[ACTIVE,INACTIVE]',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        $model = new BranchModel();
        $existing = $model->find($branchId);
        if (!$existing) {
            return $this->failMessage('Branch not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $update = array_intersect_key($payload, array_flip(['name', 'address', 'manager_id', 'status']));

        if (empty($update)) {
            return $this->ok([
                'message' => 'No changes',
                'branch'  => $existing,
            ]);
        }

        $model->update($branchId, $update);

        return $this->ok([
            'message' => 'Branch updated',
            'branch'  => $model->find($branchId),
        ]);
    }
}
