<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $session = service('session');
        $role = (string) ($session->get('role') ?? '');

        $roleUpper = strtoupper(trim($role));

        $allowed = [];
        if (is_array($arguments)) {
            // Support both: role:ADMIN,SALES and role:ADMIN role:SALES
            foreach ($arguments as $arg) {
                foreach (explode(',', (string) $arg) as $piece) {
                    $piece = strtoupper(trim($piece));
                    if ($piece !== '') {
                        $allowed[] = $piece;
                    }
                }
            }
        }

        $isAllowed = true;
        if (count($allowed) > 0) {
            $isAllowed = in_array($roleUpper, $allowed, true);

            // Treat SUPER_ADMIN as an ADMIN-equivalent for access checks.
            if (!$isAllowed && $roleUpper === 'SUPER_ADMIN') {
                $isAllowed = in_array('ADMIN', $allowed, true);
            }
        }

        if ($roleUpper === '' || !$isAllowed) {
            return service('response')
                ->setStatusCode(ResponseInterface::HTTP_FORBIDDEN)
                ->setJSON(['message' => 'Forbidden']);
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }
}
