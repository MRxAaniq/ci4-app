<?php

namespace App\Controllers\Api\V1;

use App\Models\BranchModel;
use App\Models\UserModel;
use App\Services\AuthorizationService;
use CodeIgniter\Database\Exceptions\DatabaseException;
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
                ->like('branches.name', $q)
                ->orLike('branches.address', $q)
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
        $builder->select('branches.*');
        $builder->select('u.name AS manager_name, u.email AS manager_email');
        $builder->join('users u', 'u.id = branches.manager_id', 'left');
        if ($q !== '') {
            $builder->groupStart()
                ->like('branches.name', $q)
                ->orLike('branches.address', $q)
                ->groupEnd();
        }

        if ($actorRole === 'BRANCH_MANAGER') {
            $builder->where('branches.manager_id', $actorUserId);
        } elseif ($actorRole === 'SALES') {
            $builder->where('branches.id', $actorBranchId);
        }

        $builder->orderBy('branches.id', 'DESC');
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

        // Enrich with manager name/email (if any) for admin UX.
        if (!empty($branch['manager_id'])) {
            $mgr = (new UserModel())->find((int) $branch['manager_id']);
            if ($mgr) {
                $branch['manager_name'] = $mgr['name'] ?? null;
                $branch['manager_email'] = $mgr['email'] ?? null;
            }
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
            'manager_name' => 'permit_empty|max_length[150]',
            'manager_email' => 'permit_empty|valid_email|max_length[191]',
            'status'     => 'permit_empty|in_list[ACTIVE,INACTIVE]',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        // Allow manager assignment by name + email.
        $managerEmail = trim((string) ($payload['manager_email'] ?? ''));
        if ($managerEmail !== '') {
            $mgr = (new UserModel())->findByEmail($managerEmail);
            if (!$mgr) {
                return $this->failValidation(['manager_email' => 'Manager not found']);
            }
            if (($mgr['role'] ?? null) !== 'BRANCH_MANAGER') {
                return $this->failValidation(['manager_email' => 'User is not a BRANCH_MANAGER']);
            }

            $managerName = trim((string) ($payload['manager_name'] ?? ''));
            if ($managerName !== '' && strcasecmp($managerName, (string) ($mgr['name'] ?? '')) !== 0) {
                return $this->failValidation(['manager_name' => 'Name does not match that email']);
            }

            $payload['manager_id'] = (int) $mgr['id'];
        }

        $model = new BranchModel();

        $id = (int)$model->insert([
            'name'       => (string)$payload['name'],
            'address'    => (string)$payload['address'],
            'manager_id' => $payload['manager_id'] ?? null,
            'status'     => $payload['status'] ?? 'ACTIVE',
        ], true);

        $branch = $model->find($id);
        if ($branch && !empty($branch['manager_id'])) {
            $mgr = (new UserModel())->find((int) $branch['manager_id']);
            if ($mgr) {
                $branch['manager_name'] = $mgr['name'] ?? null;
                $branch['manager_email'] = $mgr['email'] ?? null;
            }
        }

        return $this->ok([
            'message' => 'Branch created',
            'branch'  => $branch,
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
            'manager_name' => 'permit_empty|max_length[150]',
            'manager_email' => 'permit_empty|valid_email|max_length[191]',
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

        // Allow manager assignment by name + email.
        $managerEmail = trim((string) ($payload['manager_email'] ?? ''));
        if ($managerEmail !== '') {
            $mgr = (new UserModel())->findByEmail($managerEmail);
            if (!$mgr) {
                return $this->failValidation(['manager_email' => 'Manager not found']);
            }
            if (($mgr['role'] ?? null) !== 'BRANCH_MANAGER') {
                return $this->failValidation(['manager_email' => 'User is not a BRANCH_MANAGER']);
            }

            $managerName = trim((string) ($payload['manager_name'] ?? ''));
            if ($managerName !== '' && strcasecmp($managerName, (string) ($mgr['name'] ?? '')) !== 0) {
                return $this->failValidation(['manager_name' => 'Name does not match that email']);
            }

            $payload['manager_id'] = (int) $mgr['id'];
        }

        $update = array_intersect_key($payload, array_flip(['name', 'address', 'manager_id', 'status']));

        if (empty($update)) {
            return $this->ok([
                'message' => 'No changes',
                'branch'  => $existing,
            ]);
        }

        $model->update($branchId, $update);

        $branch = $model->find($branchId);
        if ($branch && !empty($branch['manager_id'])) {
            $mgr = (new UserModel())->find((int) $branch['manager_id']);
            if ($mgr) {
                $branch['manager_name'] = $mgr['name'] ?? null;
                $branch['manager_email'] = $mgr['email'] ?? null;
            }
        }

        return $this->ok([
            'message' => 'Branch updated',
            'branch'  => $branch,
        ]);
    }

    public function delete($id = null)
    {
        $branchId = (int) $id;
        if ($branchId <= 0) {
            return $this->failMessage('Invalid branch id');
        }

        $model = new BranchModel();
        $existing = $model->find($branchId);
        if (!$existing) {
            return $this->failMessage('Branch not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        $db = db_connect();

        $deps = [
            'inventory' => (int) $db->table('inventory')->where('branch_id', $branchId)->countAllResults(),
            'inventory_movements' => (int) $db->table('inventory_movements')->where('branch_id', $branchId)->countAllResults(),
            'orders' => (int) $db->table('orders')->where('branch_id', $branchId)->countAllResults(),
            'stock_transfers' => (int) $db->table('stock_transfers')
                ->groupStart()
                ->where('from_branch', $branchId)
                ->orWhere('to_branch', $branchId)
                ->groupEnd()
                ->countAllResults(),
        ];

        // This table exists in some environments without foreign keys; prevent orphaned rows.
        if ($db->tableExists('inventory_reservations')) {
            $deps['inventory_reservations'] = (int) $db->table('inventory_reservations')->where('branch_id', $branchId)->countAllResults();
        }

        $blocking = array_filter($deps, static fn ($n) => (int) $n > 0);
        if (!empty($blocking)) {
            return $this->failMessage(
                'Branch cannot be deleted because it has related records',
                ResponseInterface::HTTP_CONFLICT,
                ['dependencies' => $blocking]
            );
        }

        try {
            $model->delete($branchId);
        } catch (DatabaseException $e) {
            return $this->failMessage(
                'Branch cannot be deleted because it is referenced by other records',
                ResponseInterface::HTTP_CONFLICT
            );
        }

        return $this->ok([
            'message' => 'Branch deleted',
        ]);
    }
}
