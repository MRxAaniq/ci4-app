<?php

namespace App\Controllers\Api\V1;

use CodeIgniter\RESTful\ResourceController;
use CodeIgniter\HTTP\ResponseInterface;

abstract class BaseApiController extends ResourceController
{
    protected $format = 'json';

    protected function guardRequestSize(int $maxBytes)
    {
        $lenHeader = $this->request->getHeaderLine('Content-Length');
        if ($lenHeader !== '' && is_numeric($lenHeader) && (int)$lenHeader > $maxBytes) {
            return $this->failMessage('Payload too large', ResponseInterface::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        $body = $this->request->getBody();
        if ($body !== null && strlen($body) > $maxBytes) {
            return $this->failMessage('Payload too large', ResponseInterface::HTTP_REQUEST_ENTITY_TOO_LARGE);
        }

        return null;
    }

    /** @return array<string, mixed> */
    protected function body(): array
    {
        $data = $this->request->getJSON(true);
        return is_array($data) ? $data : [];
    }

    protected function ok(array $data, int $code = ResponseInterface::HTTP_OK)
    {
        return $this->respond(['data' => $data], $code);
    }

    protected function failValidation(array $errors)
    {
        return $this->respond([
            'errors' => $errors,
        ], ResponseInterface::HTTP_UNPROCESSABLE_ENTITY);
    }

    protected function failMessage(string $message, int $code = ResponseInterface::HTTP_BAD_REQUEST, array $errors = [])
    {
        return $this->respond([
            'message' => $message,
            'errors'  => $errors,
        ], $code);
    }
}
