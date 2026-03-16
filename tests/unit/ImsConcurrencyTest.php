<?php

declare(strict_types=1);

use CodeIgniter\Database\Config as DatabaseConfig;
use CodeIgniter\Database\Exceptions\DatabaseException;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Tests\Support\Database\Seeds\ImsTestSeeder;

/**
 * @internal
 */
final class ImsConcurrencyTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $namespace = null;
    protected $seed = ImsTestSeeder::class;
    protected $refresh = true;

    protected function setUp(): void
    {
        parent::setUp();
        service('cache')->clean();
    }

    public function testConcurrentOrderLockingPreventsOversell(): void
    {
        // Best-effort concurrency test on Windows: verify row lock blocks second connection
        // and second order cannot oversell.

        $db1 = DatabaseConfig::connect('tests', false);
        $db2 = DatabaseConfig::connect('tests', false);

        $db1->initialize();
        $db2->initialize();

        // Ensure known stock
        $db1->table('inventory')->where(['branch_id' => 10, 'product_id' => 100])->update(['quantity' => 5]);

        $db2->query('SET innodb_lock_wait_timeout = 1');

        $db1->transBegin();
        // Acquire row lock
        $db1->query(
            'SELECT quantity FROM inventory WHERE branch_id = ? AND product_id = ? FOR UPDATE',
            [10, 100]
        );

        $thrown = false;
        try {
            $service = new App\Services\OrderService(
                $db2,
                new App\Services\TaxService(),
                new App\Services\InventoryService($db2)
            );

            $service->createOrderFromProducts(
                10,
                3,
                [
                    ['product_id' => 100, 'quantity' => 3],
                ],
                3,
                'SALES'
            );
        } catch (Throwable $e) {
            $thrown = true;
            // MySQL typically throws a lock wait timeout exception.
            // If we see a PHP Error instead, surface the exact class/message.
            $this->assertTrue(
                $e instanceof Exception || $e instanceof DatabaseException,
                'Expected a database/lock exception, got ' . get_class($e) . ': ' . $e->getMessage()
            );
        } finally {
            $db1->transRollback();
        }

        $this->assertTrue($thrown, 'Expected second connection to be blocked by row lock.');

        // After releasing lock: first order succeeds and reserves stock,
        // second fails due to insufficient available stock.
        $service2 = new App\Services\OrderService(
            $db2,
            new App\Services\TaxService(),
            new App\Services\InventoryService($db2)
        );

        $service2->createOrderFromProducts(
            10,
            3,
            [
                ['product_id' => 100, 'quantity' => 3],
            ],
            3,
            'SALES'
        );

        $failed = false;
        try {
            $service2->createOrderFromProducts(
                10,
                3,
                [
                    ['product_id' => 100, 'quantity' => 3],
                ],
                3,
                'SALES'
            );
        } catch (Throwable $e) {
            $failed = true;
        }

        $this->assertTrue($failed, 'Expected oversell prevention to reject the second order.');

        // Inventory is not deducted until approval; reservation holds the stock.
        $row = $db2->table('inventory')->select('quantity')->where(['branch_id' => 10, 'product_id' => 100])->get()->getRowArray();
        $this->assertSame(5, (int)($row['quantity'] ?? -1));

        $reserved = $db2->table('inventory_reservations')
            ->selectSum('quantity', 'qty')
            ->where(['branch_id' => 10, 'product_id' => 100])
            ->get()
            ->getRowArray();

        $this->assertSame(3, (int)($reserved['qty'] ?? -1));
    }
}
