<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use CodeIgniter\Test\FeatureTestTrait;
use Tests\Support\Database\Seeds\ImsTestSeeder;

/**
 * @internal
 */
final class ImsOrderFeatureTest extends CIUnitTestCase
{
    use FeatureTestTrait;
    use DatabaseTestTrait;

    protected $namespace = null;
    protected $seed = ImsTestSeeder::class;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();

        // Avoid throttler cross-test pollution (throttler uses cache)
        service('cache')->clean();
    }

    public function testOrderWithSufficientStock(): void
    {
        $payload = [
            'branch_id' => 10,
            'products'  => [
                ['product_id' => 100, 'quantity' => 2],
            ],
        ];

        $movementsBefore = (int) $this->db->table('inventory_movements')->countAllResults();

        $result = $this
            ->withSession(['user_id' => 3, 'role' => 'SALES', 'branch_id' => 10])
            ->withBodyFormat('json')
            ->post('api/v1/orders', $payload);

        $result->assertStatus(201);

        $json = json_decode((string) $result->response()->getBody(), true);
        $this->assertIsArray($json);
        $this->assertSame('Order placed (pending approval)', $json['data']['message'] ?? null);

        $order = $json['data']['order'] ?? null;
        $this->assertIsArray($order);
        $this->assertSame('PENDING', $order['status'] ?? null);
        $orderId = (int) ($order['id'] ?? 0);
        $this->assertGreaterThan(0, $orderId);

        // Inventory should not change until approval (stays 20)
        $this->seeInDatabase('inventory', [
            'branch_id'  => 10,
            'product_id' => 100,
            'quantity'   => 20,
        ]);

        // Reservation should exist for this pending order
        $this->seeInDatabase('inventory_reservations', [
            'branch_id'  => 10,
            'product_id' => 100,
            'order_id'   => $orderId,
            'quantity'   => 2,
        ]);

        $movementsAfter = (int) $this->db->table('inventory_movements')->countAllResults();
        $this->assertSame($movementsBefore, $movementsAfter);
    }

    public function testOrderWithInsufficientStockFailsAndDoesNotChangeInventory(): void
    {
        // Force inventory low
        $this->db->table('inventory')
            ->where(['branch_id' => 10, 'product_id' => 100])
            ->update(['quantity' => 1]);

        $payload = [
            'branch_id' => 10,
            'products'  => [
                ['product_id' => 100, 'quantity' => 2],
            ],
        ];

        $ordersBefore = (int) $this->db->table('orders')->countAllResults();

        $result = $this
            ->withSession(['user_id' => 3, 'role' => 'SALES', 'branch_id' => 10])
            ->withBodyFormat('json')
            ->post('api/v1/orders', $payload);

        $result->assertStatus(400);

        // Inventory should not go negative and should stay unchanged
        $this->seeInDatabase('inventory', [
            'branch_id'  => 10,
            'product_id' => 100,
            'quantity'   => 1,
        ]);

        $ordersAfter = (int) $this->db->table('orders')->countAllResults();
        $this->assertSame($ordersBefore, $ordersAfter);
    }

    public function testTaxCalculationsAreCorrect(): void
    {
        $payload = [
            'branch_id' => 10,
            'products'  => [
                ['product_id' => 100, 'quantity' => 2], // 2 * 100, tax 15% => 30
                ['product_id' => 200, 'quantity' => 1], // 1 * 50,  tax 0%  => 0
            ],
        ];

        $result = $this
            ->withSession(['user_id' => 3, 'role' => 'SALES', 'branch_id' => 10])
            ->withBodyFormat('json')
            ->post('api/v1/orders', $payload);

        $result->assertStatus(201);

        $json = json_decode((string) $result->response()->getBody(), true);
        $order = $json['data']['order'] ?? null;
        $this->assertIsArray($order);
        $this->assertSame('PENDING', $order['status'] ?? null);

        // Expected: subtotal 250, tax 30, grand 280
        $this->assertSame('250.00', (string)($order['subtotal'] ?? ''));
        $this->assertSame('30.00', (string)($order['tax_total'] ?? ''));
        $this->assertSame('280.00', (string)($order['grand_total'] ?? ''));
    }

    public function testManagerCanApprovePendingOrderAndInventoryIsDeducted(): void
    {
        $payload = [
            'branch_id' => 10,
            'products'  => [
                ['product_id' => 100, 'quantity' => 2],
            ],
        ];

        // Place order as sales (should be pending and not deduct inventory)
        $place = $this
            ->withSession(['user_id' => 3, 'role' => 'SALES', 'branch_id' => 10])
            ->withBodyFormat('json')
            ->post('api/v1/orders', $payload);

        $place->assertStatus(201);
        $placeJson = json_decode((string) $place->response()->getBody(), true);
        $order = $placeJson['data']['order'] ?? null;
        $this->assertIsArray($order);
        $this->assertSame('PENDING', $order['status'] ?? null);
        $orderId = (int) ($order['id'] ?? 0);
        $this->assertGreaterThan(0, $orderId);

        $this->seeInDatabase('inventory_reservations', [
            'branch_id'  => 10,
            'product_id' => 100,
            'order_id'   => $orderId,
            'quantity'   => 2,
        ]);

        $movementsBefore = (int) $this->db->table('inventory_movements')->countAllResults();

        // Approve as the branch manager
        $approve = $this
            ->withSession(['user_id' => 2, 'role' => 'BRANCH_MANAGER'])
            ->withBodyFormat('json')
            ->post('api/v1/orders/' . $orderId . '/approve', []);

        $approve->assertStatus(200);
        $approveJson = json_decode((string) $approve->response()->getBody(), true);
        $approvedOrder = $approveJson['data']['order'] ?? null;
        $this->assertIsArray($approvedOrder);
        $this->assertSame('SUBMITTED', $approvedOrder['status'] ?? null);

        // Inventory should have decreased from 20 -> 18
        $this->seeInDatabase('inventory', [
            'branch_id'  => 10,
            'product_id' => 100,
            'quantity'   => 18,
        ]);

        $movementsAfter = (int) $this->db->table('inventory_movements')->countAllResults();
        $this->assertSame($movementsBefore + 1, $movementsAfter);

        $this->seeInDatabase('inventory_movements', [
            'branch_id'  => 10,
            'product_id' => 100,
            'delta_qty'  => -2,
            'ref_type'   => 'ORDER',
            'ref_id'     => $orderId,
        ]);

        $this->dontSeeInDatabase('inventory_reservations', [
            'order_id' => $orderId,
        ]);
    }
}
