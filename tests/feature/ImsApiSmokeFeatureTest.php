<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\Seeds\ImsTestSeeder;

/**
 * @internal
 */
final class ImsApiSmokeFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;
    protected $seed = ImsTestSeeder::class;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();
    }

    public function testAuthLoginReturnsUserProfile(): void
    {
        $payload = [
            'email'    => 'admin@example.com',
            'password' => 'password',
        ];

        $result = $this
            ->withBodyFormat('json')
            ->post('api/v1/auth/login', $payload);

        $result->assertStatus(200);

        $json = json_decode((string) $result->response()->getBody(), true);
        $this->assertIsArray($json);

        $data = $json['data'] ?? null;
        $this->assertIsArray($data);
        $this->assertSame('Logged in', $data['message'] ?? null);

        $user = $data['user'] ?? null;
        $this->assertIsArray($user);
        $this->assertSame('ADMIN', $user['role'] ?? null);
        $this->assertSame('admin@example.com', $user['email'] ?? null);
    }

    public function testBranchesIndexAndCrudAsAdmin(): void
    {
        // Index
        $result = $this
            ->withSession(['user_id' => 1, 'role' => 'ADMIN', 'branch_id' => 0])
            ->get('api/v1/branches');

        $result->assertStatus(200);

        $json = json_decode((string) $result->response()->getBody(), true);
        $this->assertIsArray($json);
        $this->assertIsArray($json['data']['branches'] ?? null);

        // Create
        $create = $this
            ->withSession(['user_id' => 1, 'role' => 'ADMIN', 'branch_id' => 0])
            ->withBodyFormat('json')
            ->post('api/v1/branches', [
                'name'       => 'Branch C',
                'address'    => 'Address C',
                'manager_id' => 2,
                'status'     => 'ACTIVE',
            ]);

        $create->assertStatus(201);

        $createJson = json_decode((string) $create->response()->getBody(), true);
        $branch = $createJson['data']['branch'] ?? null;
        $this->assertIsArray($branch);
        $branchId = (int) ($branch['id'] ?? 0);
        $this->assertGreaterThan(0, $branchId);

        // Update
        $update = $this
            ->withSession(['user_id' => 1, 'role' => 'ADMIN', 'branch_id' => 0])
            ->withBodyFormat('json')
            ->patch('api/v1/branches/' . $branchId, [
                'address' => 'Address C - Updated',
            ]);

        $update->assertStatus(200);

        $this->seeInDatabase('branches', [
            'id'      => $branchId,
            'address' => 'Address C - Updated',
        ]);
    }

    public function testProductsIndexAndCrud(): void
    {
        // Index
        $result = $this
            ->withSession(['user_id' => 3, 'role' => 'SALES', 'branch_id' => 10])
            ->get('api/v1/products');

        $result->assertStatus(200);

        // Create as admin
        $create = $this
            ->withSession(['user_id' => 1, 'role' => 'ADMIN', 'branch_id' => 0])
            ->withBodyFormat('json')
            ->post('api/v1/products', [
                'name'           => 'Product C',
                'sku'            => 'P-C',
                'cost_price'     => 10.00,
                'sale_price'     => 25.00,
                'tax_percentage' => 5.00,
                'status'         => 'ACTIVE',
            ]);

        $create->assertStatus(201);

        $createJson = json_decode((string) $create->response()->getBody(), true);
        $product = $createJson['data']['product'] ?? null;
        $this->assertIsArray($product);
        $productId = (int) ($product['id'] ?? 0);
        $this->assertGreaterThan(0, $productId);

        // Update as admin
        $update = $this
            ->withSession(['user_id' => 1, 'role' => 'ADMIN', 'branch_id' => 0])
            ->withBodyFormat('json')
            ->patch('api/v1/products/' . $productId, [
                'sale_price' => 30.00,
            ]);

        $update->assertStatus(200);

        // Delete as admin only
        $del = $this
            ->withSession(['user_id' => 1, 'role' => 'ADMIN', 'branch_id' => 0])
            ->delete('api/v1/products/' . $productId);

        $del->assertStatus(200);
    }

    public function testInventoryMovementsAndBranchDashboardAsManager(): void
    {
        // Branch manager seeded for Branch A (id 10)
        $session = ['user_id' => 2, 'role' => 'BRANCH_MANAGER', 'branch_id' => 0];

        $movements = $this
            ->withSession($session)
            ->get('api/v1/branches/10/inventory/movements?page=1&per_page=10');

        $movements->assertStatus(200);

        $movJson = json_decode((string) $movements->response()->getBody(), true);
        $this->assertIsArray($movJson);
        $this->assertIsArray($movJson['data']['movements'] ?? null);

        $dashboard = $this
            ->withSession($session)
            ->get('api/v1/branches/10/dashboard');

        $dashboard->assertStatus(200);

        $dashJson = json_decode((string) $dashboard->response()->getBody(), true);
        $this->assertIsArray($dashJson);
        $this->assertIsArray($dashJson['data']['stats'] ?? null);
        $this->assertIsArray($dashJson['data']['top_products'] ?? null);
        $this->assertIsArray($dashJson['data']['low_stock_items'] ?? null);
    }

    public function testInventoryReadWriteAccessRules(): void
    {
        // Sales can view their own branch inventory
        $resultOk = $this
            ->withSession(['user_id' => 3, 'role' => 'SALES', 'branch_id' => 10])
            ->get('api/v1/branches/10/inventory');
        $resultOk->assertStatus(200);

        // Sales cannot view another branch
        $resultForbidden = $this
            ->withSession(['user_id' => 3, 'role' => 'SALES', 'branch_id' => 10])
            ->get('api/v1/branches/20/inventory');
        $resultForbidden->assertStatus(403);

        // Manager of Branch A can add stock
        $add = $this
            ->withSession(['user_id' => 2, 'role' => 'BRANCH_MANAGER', 'branch_id' => 0])
            ->withBodyFormat('json')
            ->post('api/v1/branches/10/inventory/add', [
                'product_id' => 100,
                'quantity'   => 3,
                'note'       => 'Smoke test add',
            ]);
        $add->assertStatus(201);

        // Manager can adjust stock
        $adjust = $this
            ->withSession(['user_id' => 2, 'role' => 'BRANCH_MANAGER', 'branch_id' => 0])
            ->withBodyFormat('json')
            ->post('api/v1/branches/10/inventory/adjust', [
                'product_id' => 100,
                'delta'      => -2,
                'note'       => 'Smoke test adjust',
            ]);
        $adjust->assertStatus(200);

        // Verify final quantity: initial 20 + 3 - 2 = 21
        $this->seeInDatabase('inventory', [
            'branch_id'  => 10,
            'product_id' => 100,
            'quantity'   => 21,
        ]);

        // Sales cannot write inventory (blocked by role filter)
        $salesWrite = $this
            ->withSession(['user_id' => 3, 'role' => 'SALES', 'branch_id' => 10])
            ->withBodyFormat('json')
            ->post('api/v1/branches/10/inventory/add', [
                'product_id' => 100,
                'quantity'   => 1,
            ]);
        $salesWrite->assertStatus(403);
    }

    public function testAdminCanCreateSalesAndManagerUsers(): void
    {
        $session = ['user_id' => 1, 'role' => 'ADMIN', 'branch_id' => 0];

        $salesCreate = $this
            ->withSession($session)
            ->withBodyFormat('json')
            ->post('api/v1/users', [
                'name'      => 'Sales Created',
                'email'     => 'sales.created@example.com',
                'password'  => 'password',
                'role'      => 'SALES',
                'branch_id' => 10,
            ]);

        $salesCreate->assertStatus(201);
        $this->seeInDatabase('users', [
            'email'     => 'sales.created@example.com',
            'role'      => 'SALES',
            'branch_id' => 10,
            'status'    => 'ACTIVE',
        ]);

        $mgrCreate = $this
            ->withSession($session)
            ->withBodyFormat('json')
            ->post('api/v1/users', [
                'name'      => 'Manager Created',
                'email'     => 'manager.created@example.com',
                'password'  => 'password',
                'role'      => 'BRANCH_MANAGER',
                'branch_id' => 20,
            ]);

        $mgrCreate->assertStatus(201);

        $mgrJson = json_decode((string) $mgrCreate->response()->getBody(), true);
        $mgrId = (int) ($mgrJson['data']['user']['id'] ?? 0);
        $this->assertGreaterThan(0, $mgrId);

        $this->seeInDatabase('users', [
            'email'  => 'manager.created@example.com',
            'role'   => 'BRANCH_MANAGER',
            'status' => 'ACTIVE',
        ]);

        $this->seeInDatabase('branches', [
            'id'         => 20,
            'manager_id' => $mgrId,
        ]);
    }
}
