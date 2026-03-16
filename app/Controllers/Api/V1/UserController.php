<?php

namespace App\Controllers\Api\V1;

use App\Models\BranchModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class UserController extends BaseApiController
{
    public function index()
    {
        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = (int)($this->request->getGet('per_page') ?? 50);
        $perPage = max(1, min(200, $perPage));

        $q = trim((string)($this->request->getGet('q') ?? ''));
        $role = strtoupper(trim((string)($this->request->getGet('role') ?? '')));
        $status = strtoupper(trim((string)($this->request->getGet('status') ?? 'ACTIVE')));

        $allowedRoles = ['ADMIN', 'SUPER_ADMIN', 'BRANCH_MANAGER', 'SALES'];
        if ($role !== '' && !in_array($role, $allowedRoles, true)) {
            return $this->failMessage('Invalid role');
        }

        $allowedStatuses = ['ACTIVE', 'DISABLED'];
        if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
            return $this->failMessage('Invalid status');
        }

        $db = db_connect();

        $countBuilder = $db->table('users');
        if ($role !== '') {
            $countBuilder->where('role', $role);
        }
        if ($status !== '') {
            $countBuilder->where('status', $status);
        }
        if ($q !== '') {
            $countBuilder->groupStart()
                ->like('name', $q)
                ->orLike('email', $q)
                ->groupEnd();
        }
        $total = (int) $countBuilder->countAllResults();

        $builder = $db->table('users');
        $builder->select('id, name, email, role, branch_id, status');
        if ($role !== '') {
            $builder->where('role', $role);
        }
        if ($status !== '') {
            $builder->where('status', $status);
        }
        if ($q !== '') {
            $builder->groupStart()
                ->like('name', $q)
                ->orLike('email', $q)
                ->groupEnd();
        }
        $builder->orderBy('id', 'DESC');
        $builder->limit($perPage, ($page - 1) * $perPage);

        $rows = $builder->get()->getResultArray();
        $safe = array_map(static function (array $r): array {
            return [
                'id' => (int)($r['id'] ?? 0),
                'name' => (string)($r['name'] ?? ''),
                'email' => (string)($r['email'] ?? ''),
                'role' => (string)($r['role'] ?? ''),
                'branch_id' => (int)($r['branch_id'] ?? 0),
                'status' => (string)($r['status'] ?? ''),
            ];
        }, $rows);

        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        return $this->ok([
            'users' => $safe,
            'pagination' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'q' => $q,
                'role' => $role,
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
            'name'      => 'required|max_length[150]',
            'email'     => 'required|valid_email|max_length[191]|is_unique[users.email]',
            'password'  => 'required|min_length[6]|max_length[255]',
            'role'      => 'required|in_list[BRANCH_MANAGER,SALES]',
            'branch_id' => 'permit_empty|is_natural_no_zero',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        $role = strtoupper(trim((string) ($payload['role'] ?? '')));
        $branchId = (int) ($payload['branch_id'] ?? 0);

        if (($role === 'SALES' || $role === 'BRANCH_MANAGER') && $branchId <= 0) {
            return $this->failValidation([
                'branch_id' => 'Branch is required for this role',
            ]);
        }

        $branchModel = new BranchModel();
        $branch = $branchId > 0 ? $branchModel->find($branchId) : null;
        if ($branchId > 0 && !$branch) {
            return $this->failValidation([
                'branch_id' => 'Branch not found',
            ]);
        }

        $db = db_connect();
        $db->transStart();

        try {
            $userModel = new UserModel();

            $userId = (int) $userModel->insert([
                'name'          => (string) $payload['name'],
                'email'         => strtolower(trim((string) $payload['email'])),
                'password_hash' => password_hash((string) $payload['password'], PASSWORD_DEFAULT),
                'role'          => $role,
                'branch_id'     => $role === 'SALES' ? $branchId : null,
                'status'        => 'ACTIVE',
            ], true);

            if ($userId <= 0) {
                throw new \RuntimeException('Failed to create user');
            }

            if ($role === 'BRANCH_MANAGER') {
                $branchModel->update($branchId, [
                    'manager_id' => $userId,
                ]);
            }

            $db->transComplete();
        } catch (\Throwable $e) {
            $db->transRollback();
            return $this->failMessage('Failed to create user', ResponseInterface::HTTP_BAD_REQUEST);
        }

        $created = (new UserModel())->find($userId);
        if (!$created) {
            return $this->failMessage('User created but could not be loaded', ResponseInterface::HTTP_CREATED);
        }

        $safeUser = [
            'id'        => (int) ($created['id'] ?? 0),
            'name'      => (string) ($created['name'] ?? ''),
            'email'     => (string) ($created['email'] ?? ''),
            'role'      => (string) ($created['role'] ?? ''),
            'branch_id' => (int) ($created['branch_id'] ?? 0),
            'status'    => (string) ($created['status'] ?? ''),
        ];

        $resp = [
            'message' => 'User created',
            'user'    => $safeUser,
        ];

        if ($role === 'BRANCH_MANAGER') {
            $resp['managed_branch_id'] = $branchId;
        }

        return $this->ok($resp, ResponseInterface::HTTP_CREATED);
    }
}
