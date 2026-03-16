<?php

use CodeIgniter\Router\RouteCollection;

/**
 * @var RouteCollection $routes
 */
$routes->setAutoRoute(false);

$routes->get('/', 'Home::index');

$routes->get('api/hello', 'Api\Hello::index');

// Versioned JSON API
$routes->group('api/v1', static function (RouteCollection $routes) {
	// Authentication
	$routes->post('auth/login', 'Api\V1\AuthenticationController::login');

	$routes->group('', ['filter' => 'apiAuth'], static function (RouteCollection $routes) {
		$routes->post('auth/logout', 'Api\V1\AuthenticationController::logout');

		// Branches
		$routes->get('branches', 'Api\V1\BranchController::index', ['filter' => 'role:ADMIN,BRANCH_MANAGER,SALES']);
		$routes->get('branches/(:num)', 'Api\V1\BranchController::show/$1', ['filter' => 'role:ADMIN,BRANCH_MANAGER,SALES']);
		$routes->post('branches', 'Api\V1\BranchController::create', ['filter' => 'role:ADMIN']);
		$routes->patch('branches/(:num)', 'Api\V1\BranchController::update/$1', ['filter' => 'role:ADMIN']);

		// Products
		$routes->get('products', 'Api\V1\ProductController::index', ['filter' => 'role:ADMIN,BRANCH_MANAGER,SALES']);
		$routes->get('products/(:num)', 'Api\V1\ProductController::show/$1', ['filter' => 'role:ADMIN,BRANCH_MANAGER,SALES']);
		$routes->post('products', 'Api\V1\ProductController::create', ['filter' => 'role:ADMIN']);
		$routes->patch('products/(:num)', 'Api\V1\ProductController::update/$1', ['filter' => 'role:ADMIN']);
		$routes->delete('products/(:num)', 'Api\V1\ProductController::delete/$1', ['filter' => 'role:ADMIN']);

		// Inventory
		$routes->get('branches/(:num)/inventory', 'Api\V1\InventoryController::index/$1', ['filter' => 'role:ADMIN,BRANCH_MANAGER,SALES']);
		$routes->get('branches/(:num)/inventory/(:num)', 'Api\V1\InventoryController::show/$1/$2', ['filter' => 'role:ADMIN,BRANCH_MANAGER,SALES']);
		$routes->get('branches/(:num)/inventory/movements', 'Api\V1\InventoryController::movements/$1', ['filter' => 'role:ADMIN,BRANCH_MANAGER']);
		$routes->post('branches/(:num)/inventory/add', 'Api\V1\InventoryController::add/$1', ['filter' => 'role:ADMIN,BRANCH_MANAGER']);
		$routes->post('branches/(:num)/inventory/adjust', 'Api\V1\InventoryController::adjust/$1', ['filter' => 'role:ADMIN,BRANCH_MANAGER']);

		// Reports / dashboard (branch-scoped)
		$routes->get('branches/(:num)/dashboard', 'Api\V1\ReportController::branchDashboard/$1', ['filter' => 'role:ADMIN,BRANCH_MANAGER']);

		// Users (admin-only)
		$routes->post('users', 'Api\V1\UserController::create', ['filter' => 'role:ADMIN']);

		// Orders
		$routes->get('branches/(:num)/orders', 'Api\V1\OrderController::index/$1', ['filter' => 'role:ADMIN,BRANCH_MANAGER,SALES']);
		$routes->post('orders', 'Api\V1\OrderController::create', ['filter' => 'role:BRANCH_MANAGER,SALES']);
		$routes->post('orders/(:num)/approve', 'Api\V1\OrderController::approve/$1', ['filter' => 'role:BRANCH_MANAGER']);

		// Stock transfers
		$routes->post('transfers', 'Api\V1\StockTransferController::create', ['filter' => 'role:ADMIN,BRANCH_MANAGER']);
	});
});