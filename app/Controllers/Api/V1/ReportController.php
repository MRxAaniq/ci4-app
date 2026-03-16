<?php

namespace App\Controllers\Api\V1;

use App\Models\BranchModel;
use App\Services\AuthorizationService;
use CodeIgniter\HTTP\ResponseInterface;

class ReportController extends BaseApiController
{
    public function branchDashboard($branchId = null)
    {
        if ($resp = $this->guardRequestSize(65536)) {
            return $resp;
        }

        $branchId = (int) $branchId;
        if ($branchId <= 0) {
            return $this->failMessage('Invalid branch id');
        }

        $session = service('session');
        $actorUserId = (int) ($session->get('user_id') ?? 0);
        $actorRole = (string) ($session->get('role') ?? '');

        try {
            (new AuthorizationService(db_connect()))->assertCanViewBranch($actorUserId, $actorRole, $branchId);
            if (strtoupper(trim($actorRole)) === 'SALES') {
                return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
            }
        } catch (\Throwable $e) {
            return $this->failMessage('Forbidden', ResponseInterface::HTTP_FORBIDDEN);
        }

        $threshold = (int) ($this->request->getGet('low_stock_threshold') ?? 10);
        $threshold = max(0, min(1000000, $threshold));

        $seriesDays = (int) ($this->request->getGet('series_days') ?? 7);
        $seriesDays = max(7, min(30, $seriesDays));

        $db = db_connect();

        $branch = (new BranchModel())->find($branchId);
        if (!$branch) {
            return $this->failMessage('Branch not found', ResponseInterface::HTTP_NOT_FOUND);
        }

        // Sales totals
        $salesToday = (float) ($db->table('orders')
            ->selectSum('grand_total', 'sum')
            ->where('branch_id', $branchId)
            ->where('status', 'SUBMITTED')
            ->where('created_at >=', date('Y-m-d 00:00:00'))
            ->where('created_at <=', date('Y-m-d 23:59:59'))
            ->get()
            ->getRowArray()['sum'] ?? 0);

        $monthStart = date('Y-m-01 00:00:00');
        $monthEnd = date('Y-m-t 23:59:59');

        $salesMonth = (float) ($db->table('orders')
            ->selectSum('grand_total', 'sum')
            ->where('branch_id', $branchId)
            ->where('status', 'SUBMITTED')
            ->where('created_at >=', $monthStart)
            ->where('created_at <=', $monthEnd)
            ->get()
            ->getRowArray()['sum'] ?? 0);

        $ordersMonth = (int) ($db->table('orders')
            ->selectCount('id', 'cnt')
            ->where('branch_id', $branchId)
            ->where('status', 'SUBMITTED')
            ->where('created_at >=', $monthStart)
            ->where('created_at <=', $monthEnd)
            ->get()
            ->getRowArray()['cnt'] ?? 0);

        $ordersTotal = (int) ($db->table('orders')
            ->selectCount('id', 'cnt')
            ->where('branch_id', $branchId)
            ->where('status', 'SUBMITTED')
            ->get()
            ->getRowArray()['cnt'] ?? 0);

        // Top 5 selling products (by quantity) this month
        $topProducts = $db->table('order_items oi')
            ->select('oi.product_id, p.name, p.sku')
            ->selectSum('oi.quantity', 'qty_sold')
            ->selectSum('(oi.quantity * oi.price)', 'revenue')
            ->join('orders o', 'o.id = oi.order_id', 'inner')
            ->join('products p', 'p.id = oi.product_id', 'inner')
            ->where('o.branch_id', $branchId)
            ->where('o.status', 'SUBMITTED')
            ->where('o.created_at >=', $monthStart)
            ->where('o.created_at <=', $monthEnd)
            ->groupBy('oi.product_id, p.name, p.sku')
            ->orderBy('qty_sold', 'DESC')
            ->limit(5)
            ->get()
            ->getResultArray();

        // Low stock items
        $lowStock = $db->table('inventory i')
            ->select('i.product_id, i.quantity, p.name, p.sku, p.status')
            ->join('products p', 'p.id = i.product_id', 'inner')
            ->where('i.branch_id', $branchId)
            ->where('i.quantity <=', $threshold)
            ->orderBy('i.quantity', 'ASC')
            ->limit(50)
            ->get()
            ->getResultArray();

        // Sales series for charting (last N days)
        $seriesStart = (new \DateTimeImmutable('today'))->modify('-' . ($seriesDays - 1) . ' days')->setTime(0, 0, 0);
        $seriesEnd = (new \DateTimeImmutable('today'))->setTime(23, 59, 59);

        $seriesRows = $db->table('orders')
            ->select('DATE(created_at) AS day')
            ->selectSum('grand_total', 'sales')
            ->selectCount('id', 'orders')
            ->where('branch_id', $branchId)
            ->where('status', 'SUBMITTED')
            ->where('created_at >=', $seriesStart->format('Y-m-d H:i:s'))
            ->where('created_at <=', $seriesEnd->format('Y-m-d H:i:s'))
            ->groupBy('DATE(created_at)')
            ->orderBy('day', 'ASC')
            ->get()
            ->getResultArray();

        $byDay = [];
        foreach ($seriesRows as $r) {
            $day = (string) ($r['day'] ?? '');
            if ($day === '') continue;
            $byDay[$day] = [
                'sales' => (float) ($r['sales'] ?? 0),
                'orders' => (int) ($r['orders'] ?? 0),
            ];
        }

        $salesSeries = [];
        $cursor = $seriesStart;
        for ($i = 0; $i < $seriesDays; $i++) {
            $d = $cursor->format('Y-m-d');
            $salesSeries[] = [
                'date' => $d,
                'sales' => (float) ($byDay[$d]['sales'] ?? 0),
                'orders' => (int) ($byDay[$d]['orders'] ?? 0),
            ];
            $cursor = $cursor->modify('+1 day');
        }

        return $this->ok([
            'branch' => $branch,
            'low_stock_threshold' => $threshold,
            'stats' => [
                'sales_today' => $salesToday,
                'sales_month' => $salesMonth,
                'orders_month' => $ordersMonth,
                'orders_total' => $ordersTotal,
            ],
            'sales_series' => [
                'days' => $seriesDays,
                'points' => $salesSeries,
            ],
            'top_products' => $topProducts,
            'low_stock_items' => $lowStock,
        ]);
    }
}
