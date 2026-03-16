<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\Seeds\ImsTestSeeder;

/**
 * @internal
 */
final class ImsTransferFeatureTest extends CIUnitTestCase
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

    public function testStockTransferBetweenBranches(): void
    {
        $payload = [
            'from_branch' => 10,
            'to_branch'   => 20,
            'product_id'  => 100,
            'quantity'    => 4,
        ];

        $result = $this
            ->withSession(['user_id' => 2, 'role' => 'BRANCH_MANAGER', 'branch_id' => 0])
            ->withBodyFormat('json')
            ->post('api/v1/transfers', $payload);

        $result->assertStatus(201);

        // Branch A: 20 -> 16
        $this->seeInDatabase('inventory', [
            'branch_id'  => 10,
            'product_id' => 100,
            'quantity'   => 16,
        ]);

        // Branch B: 1 -> 5
        $this->seeInDatabase('inventory', [
            'branch_id'  => 20,
            'product_id' => 100,
            'quantity'   => 5,
        ]);
    }

    public function testTransferFailsWhenInsufficientStockAndPreventsNegativeInventory(): void
    {
        // Set source stock to 0
        $this->db->table('inventory')
            ->where(['branch_id' => 10, 'product_id' => 100])
            ->update(['quantity' => 0]);

        $payload = [
            'from_branch' => 10,
            'to_branch'   => 20,
            'product_id'  => 100,
            'quantity'    => 1,
        ];

        $result = $this
            ->withSession(['user_id' => 2, 'role' => 'BRANCH_MANAGER', 'branch_id' => 0])
            ->withBodyFormat('json')
            ->post('api/v1/transfers', $payload);

        $result->assertStatus(400);

        $this->seeInDatabase('inventory', [
            'branch_id'  => 10,
            'product_id' => 100,
            'quantity'   => 0,
        ]);

        $this->seeInDatabase('inventory', [
            'branch_id'  => 20,
            'product_id' => 100,
            'quantity'   => 1,
        ]);
    }
}
