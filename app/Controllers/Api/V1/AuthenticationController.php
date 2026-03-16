<?php

namespace App\Controllers\Api\V1;

use App\Models\UserModel;
use CodeIgniter\HTTP\ResponseInterface;

class AuthenticationController extends BaseApiController
{
    public function login()
    {
        $payload = $this->body();

        // Basic brute-force throttling (IP + email)
        $emailKey = strtolower(trim((string)($payload['email'] ?? '')));
        $throttler = service('throttler');
        // Cache backends can reject reserved characters like ':'; keep keys simple and bounded.
        $rawIp = (string)($this->request->getIPAddress() ?? 'unknown');
        $safeIp = preg_replace('/[^A-Za-z0-9._-]/', '_', $rawIp) ?: 'unknown';
        $throttleKey = 'login_' . $safeIp . '_' . sha1($emailKey);
        if (!$throttler->check($throttleKey, 5, MINUTE)) {
            return $this->failMessage('Too many login attempts. Try again later.', ResponseInterface::HTTP_TOO_MANY_REQUESTS);
        }

        $validation = service('validation');
        $validation->setRules([
            'email'    => 'required|valid_email|max_length[191]',
            'password' => 'required|min_length[6]',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        $userModel = new UserModel();
        $user = $userModel->findByEmail((string)$payload['email']);

        if (!$user || ($user['status'] ?? 'DISABLED') !== 'ACTIVE') {
            return $this->failMessage('Invalid credentials', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        if (!password_verify((string)$payload['password'], (string)$user['password_hash'])) {
            return $this->failMessage('Invalid credentials', ResponseInterface::HTTP_UNAUTHORIZED);
        }

        $session = service('session');
        // Prevent session fixation
        $session->regenerate(true);
        $session->set([
            'user_id' => (int)$user['id'],
            'role'    => (string)$user['role'],
            'branch_id' => (int)($user['branch_id'] ?? 0),
        ]);

        // Return minimal user profile
        $safeUser = [
            'id'    => (int)$user['id'],
            'name'  => (string)$user['name'],
            'email' => (string)$user['email'],
            'role'  => (string)$user['role'],
            'branch_id' => (int)($user['branch_id'] ?? 0),
        ];

        return $this->ok([
            'message' => 'Logged in',
            'user'    => $safeUser,
        ]);
    }

    public function logout()
    {
        $session = service('session');
        $session->destroy();

        return $this->ok([
            'message' => 'Logged out',
        ]);
    }
}
