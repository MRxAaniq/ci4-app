<template>
  <div class="container">
    <aside class="sidebar">
      <div class="brand">
        <span>IMS</span>
        <span class="muted">Dashboard</span>
      </div>

      <nav class="nav">
        <RouterLink v-if="canAny(['ADMIN','BRANCH_MANAGER'])" to="/app/dashboard" active-class="active">Dashboard</RouterLink>
        <RouterLink to="/app/inventory" active-class="active">Inventory</RouterLink>
        <RouterLink v-if="canAny(['ADMIN','BRANCH_MANAGER'])" to="/app/inventory/movements" active-class="active">Movements</RouterLink>
        <RouterLink v-if="can('ADMIN')" to="/app/branches" active-class="active">Branches</RouterLink>
        <RouterLink v-if="can('ADMIN')" to="/app/users" active-class="active">Users</RouterLink>
        <RouterLink v-if="can('ADMIN')" to="/app/products" active-class="active">Products</RouterLink>
        <RouterLink v-if="canAny(['BRANCH_MANAGER','SALES'])" to="/app/orders" active-class="active">Orders</RouterLink>
        <RouterLink v-if="canAny(['BRANCH_MANAGER','SALES'])" to="/app/orders/reserved" active-class="active">Reserved Orders</RouterLink>
        <RouterLink v-if="canAny(['ADMIN','BRANCH_MANAGER'])" to="/app/transfers" active-class="active">Transfers</RouterLink>
      </nav>

      <div style="margin-top: 18px;" class="card">
        <div class="muted" style="font-size: 12px;">Signed in as</div>
        <div style="font-weight: 600; margin-top: 6px;">{{ auth.user?.name }}</div>
        <div class="muted" style="font-size: 12px; margin-top: 2px;">{{ auth.user?.role }}</div>
        <button class="btn" style="margin-top: 12px; width: 100%;" @click="onLogout">Logout</button>
      </div>
    </aside>

    <section class="main">
      <header class="topbar">
        <div class="muted" style="font-size: 13px;">{{ auth.user?.email }}</div>
      </header>

      <main class="content">
        <RouterView />
      </main>
    </section>
  </div>
</template>

<script setup lang="ts">
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';
import type { Role } from '../../types/models';

const auth = useAuthStore();
const router = useRouter();

function can(role: Role) {
  const r = auth.user?.role;
  if (!r) return false;
  if (r === role) return true;
  return r === 'SUPER_ADMIN' && role === 'ADMIN';
}

function canAny(roles: Role[]) {
  const r = auth.user?.role;
  if (!r) return false;
  if (roles.includes(r)) return true;
  return r === 'SUPER_ADMIN' && roles.includes('ADMIN');
}

async function onLogout() {
  await auth.logout();
  router.push('/login');
}
</script>
