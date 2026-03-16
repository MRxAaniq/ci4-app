<?php

namespace App\Controllers\Api;

use CodeIgniter\RESTful\ResourceController;

class Hello extends ResourceController
{
    public function index()
    {
        return $this->respond(['message' => 'Hello, CodeIgniter 4!']);
    }
}