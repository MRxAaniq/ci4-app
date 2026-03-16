import { createRouter, createWebHistory } from 'vue-router';
import { useAuthStore } from '../stores/auth';
import { pinia } from '../pinia';

import LoginPage from '../modules/auth/pages/LoginPage.vue';
import AppShell from '../shared/layout/AppShell.vue';

import InventoryPage from '../modules/inventory/pages/InventoryPage.vue';
import InventoryMovementsPage from '../modules/inventory/pages/InventoryMovementsPage.vue';
import InventoryAddStockPage from '../modules/inventory/pages/InventoryAddStockPage.vue';
import BranchesListPage from '../modules/branches/pages/BranchesListPage.vue';
import BranchCreatePage from '../modules/branches/pages/BranchCreatePage.vue';
import BranchEditPage from '../modules/branches/pages/BranchEditPage.vue';

import ProductsListPage from '../modules/products/pages/ProductsListPage.vue';
import ProductCreatePage from '../modules/products/pages/ProductCreatePage.vue';
import ProductEditPage from '../modules/products/pages/ProductEditPage.vue';
import OrdersPage from '../modules/orders/pages/OrdersPage.vue';
import ReservedOrdersPage from '../modules/orders/pages/ReservedOrdersPage.vue';
import TransfersPage from '../modules/transfers/pages/TransfersPage.vue';
import DashboardPage from '../modules/reports/pages/DashboardPage.vue';
import UsersCreatePage from '../modules/users/pages/UsersCreatePage.vue';

export type Role = 'ADMIN' | 'BRANCH_MANAGER' | 'SALES';

// Keep route metadata stable (ADMIN) while supporting a separate SUPER_ADMIN user role.
export type UserRole = Role | 'SUPER_ADMIN';

function isRoleAllowed(allowed: Role[], userRole: UserRole): boolean {
  if (allowed.includes(userRole as Role)) return true;
  // Treat SUPER_ADMIN as ADMIN-equivalent for route gating.
  return userRole === 'SUPER_ADMIN' && allowed.includes('ADMIN');
}

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/app/dashboard' },
    { path: '/login', component: LoginPage, meta: { public: true } },
    {
      path: '/app',
      component: AppShell,
      children: [
        { path: 'inventory', component: InventoryPage },
        { path: 'inventory/add-stock', component: InventoryAddStockPage, meta: { roles: ['ADMIN', 'BRANCH_MANAGER'] as Role[] } },
        { path: 'inventory/movements', component: InventoryMovementsPage, meta: { roles: ['ADMIN', 'BRANCH_MANAGER'] as Role[] } },
        { path: 'dashboard', component: DashboardPage, meta: { roles: ['ADMIN', 'BRANCH_MANAGER'] as Role[] } },
        { path: 'branches', component: BranchesListPage, meta: { roles: ['ADMIN'] as Role[] } },
        { path: 'branches/new', component: BranchCreatePage, meta: { roles: ['ADMIN'] as Role[] } },
        { path: 'branches/:id/edit', component: BranchEditPage, meta: { roles: ['ADMIN'] as Role[] } },

        { path: 'users', component: UsersCreatePage, meta: { roles: ['ADMIN'] as Role[] } },

        { path: 'products', component: ProductsListPage, meta: { roles: ['ADMIN'] as Role[] } },
        { path: 'products/new', component: ProductCreatePage, meta: { roles: ['ADMIN'] as Role[] } },
        { path: 'products/:id/edit', component: ProductEditPage, meta: { roles: ['ADMIN'] as Role[] } },
        { path: 'orders', component: OrdersPage, meta: { roles: ['BRANCH_MANAGER', 'SALES'] as Role[] } },
        { path: 'orders/reserved', component: ReservedOrdersPage, meta: { roles: ['BRANCH_MANAGER', 'SALES'] as Role[] } },
        { path: 'transfers', component: TransfersPage, meta: { roles: ['ADMIN', 'BRANCH_MANAGER'] as Role[] } },
      ],
    },
    { path: '/:pathMatch(.*)*', redirect: '/app/dashboard' },
  ],
});

router.beforeEach((to) => {
  const isPublic = Boolean(to.meta.public);
  const auth = useAuthStore(pinia);
  auth.hydrate();

  if (!isPublic && !auth.isAuthenticated) {
    return { path: '/login', query: { next: to.fullPath } };
  }

  const roles = to.meta.roles as Role[] | undefined;
  if (roles && auth.user && !isRoleAllowed(roles, auth.user.role as UserRole)) {
    return { path: '/app/inventory' };
  }

  if (to.path === '/login' && auth.isAuthenticated) {
    return { path: '/app/dashboard' };
  }

  return true;
});
