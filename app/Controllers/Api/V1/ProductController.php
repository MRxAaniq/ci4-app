<?php

namespace App\Controllers\Api\V1;

use App\Models\ProductModel;
use CodeIgniter\HTTP\ResponseInterface;

class ProductController extends BaseApiController
{
    public function index()
    {
        $page = max(1, (int)($this->request->getGet('page') ?? 1));
        $perPage = (int)($this->request->getGet('per_page') ?? 50);
        $perPage = max(1, min(100, $perPage));
        $q = trim((string)($this->request->getGet('q') ?? ''));

        $db = db_connect();

        $countBuilder = $db->table('products');
        if ($q !== '') {
            $countBuilder->groupStart()
                ->like('name', $q)
                ->orLike('sku', $q)
                ->groupEnd();
        }
        $total = (int)$countBuilder->countAllResults();

        $builder = $db->table('products');
        $builder->select('*');
        if ($q !== '') {
            $builder->groupStart()
                ->like('name', $q)
                ->orLike('sku', $q)
                ->groupEnd();
        }
        $builder->orderBy('id', 'DESC');
        $builder->limit($perPage, ($page - 1) * $perPage);

        $products = $builder->get()->getResultArray();
        $totalPages = $perPage > 0 ? (int)ceil($total / $perPage) : 1;

        return $this->ok([
            'products' => $products,
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
        $productId = (int)$id;
        if ($productId <= 0) {
            return $this->failMessage('Invalid product id');
        }

        $model = new ProductModel();
        $product = $model->find($productId);
        if (!$product) {
            return $this->failMessage('Product not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        return $this->ok([
            'product' => $product,
        ]);
    }

    public function create()
    {
        $payload = $this->body();

        $validation = service('validation');
        $validation->setRules([
            'name'           => 'required|max_length[200]',
            'sku'            => 'required|max_length[80]',
            'cost_price'     => 'required|decimal',
            'sale_price'     => 'required|decimal',
            'tax_percentage' => 'permit_empty|decimal',
            'status'         => 'permit_empty|in_list[ACTIVE,INACTIVE]',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        $model = new ProductModel();

        if ($model->findBySku((string)$payload['sku'])) {
            return $this->failMessage('SKU already exists', ResponseInterface::HTTP_CONFLICT);
        }

        $id = (int)$model->insert([
            'name'           => (string)$payload['name'],
            'sku'            => (string)$payload['sku'],
            'cost_price'     => (float)$payload['cost_price'],
            'sale_price'     => (float)$payload['sale_price'],
            'tax_percentage' => isset($payload['tax_percentage']) ? (float)$payload['tax_percentage'] : 0.0,
            'status'         => $payload['status'] ?? 'ACTIVE',
        ], true);

        return $this->ok([
            'message' => 'Product created',
            'product' => $model->find($id),
        ], ResponseInterface::HTTP_CREATED);
    }

    public function update($id = null)
    {
        $productId = (int)$id;
        if ($productId <= 0) {
            return $this->failMessage('Invalid product id');
        }

        $payload = $this->body();

        $validation = service('validation');
        $validation->setRules([
            'name'           => 'permit_empty|max_length[200]',
            'sku'            => 'permit_empty|max_length[80]',
            'cost_price'     => 'permit_empty|decimal',
            'sale_price'     => 'permit_empty|decimal',
            'tax_percentage' => 'permit_empty|decimal',
            'status'         => 'permit_empty|in_list[ACTIVE,INACTIVE]',
        ]);

        if (!$validation->run($payload)) {
            return $this->failValidation($validation->getErrors());
        }

        $model = new ProductModel();
        $existing = $model->find($productId);
        if (!$existing) {
            return $this->failMessage('Product not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        if (isset($payload['sku']) && (string)$payload['sku'] !== (string)$existing['sku']) {
            if ($model->findBySku((string)$payload['sku'])) {
                return $this->failMessage('SKU already exists', ResponseInterface::HTTP_CONFLICT);
            }
        }

        $update = array_intersect_key($payload, array_flip([
            'name', 'sku', 'cost_price', 'sale_price', 'tax_percentage', 'status'
        ]));

        if (empty($update)) {
            return $this->ok([
                'message' => 'No changes',
                'product' => $existing,
            ]);
        }

        $model->update($productId, $update);

        return $this->ok([
            'message' => 'Product updated',
            'product' => $model->find($productId),
        ]);
    }

    public function delete($id = null)
    {
        $productId = (int)$id;
        if ($productId <= 0) {
            return $this->failMessage('Invalid product id');
        }

        $model = new ProductModel();
        $existing = $model->find($productId);
        if (!$existing) {
            return $this->failMessage('Product not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        // Soft-delete is usually better; schema uses hard delete so we do hard delete here.
        $model->delete($productId);

        return $this->ok([
            'message' => 'Product deleted',
        ]);
    }
}
