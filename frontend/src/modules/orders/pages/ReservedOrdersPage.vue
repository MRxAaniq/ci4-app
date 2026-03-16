<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Reserved Orders</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Orders placed and waiting for manager approval</div>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <select v-model.number="branchId" style="min-width: 240px;" :disabled="branchesLoading || branches.length === 0 || branchLocked">
        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <button class="btn" :disabled="loading" @click="load">Refresh</button>
    </div>
  </div>

  <div class="row" style="margin-bottom: 12px;">
    <div class="card" style="flex: 1; background: var(--panel-2);">
      <div class="muted" style="font-size: 12px;">Reserved (Pending)</div>
      <div style="font-weight: 700; font-size: 20px; margin-top: 6px;">{{ pendingTotal }}</div>
    </div>
    <div class="card" style="flex: 1; background: var(--panel-2);">
      <div class="muted" style="font-size: 12px;">Approved (Submitted)</div>
      <div style="font-weight: 700; font-size: 20px; margin-top: 6px;">{{ approvedTotal }}</div>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="card">
    <div class="row" style="margin-bottom: 10px;">
      <div class="h2">Pending Orders</div>
      <div class="muted" style="font-size: 12px;">{{ loading ? 'Loading…' : orders.length + ' shown' }}</div>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>Order</th>
          <th>Placed By</th>
          <th>Total</th>
          <th>Status</th>
          <th style="width: 140px;">Action</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="o in orders" :key="o.id">
          <td>#{{ o.id }}</td>
          <td>{{ o.user_name || o.user_email || o.user_id }}</td>
          <td>{{ o.grand_total }}</td>
          <td>
            <span class="badge warn">{{ o.status }}</span>
          </td>
          <td>
            <button v-if="canApprove" class="btn" type="button" :disabled="loading" @click="onApprove(o.id)">Approve</button>
            <span v-else class="muted" style="font-size: 12px;">—</span>
          </td>
        </tr>
        <tr v-if="!loading && orders.length === 0">
          <td colspan="5" class="muted">No reserved orders.</td>
        </tr>
      </tbody>
    </table>

    <PaginationBar v-if="pagination" :pagination="pagination" :disabled="loading" @change="onPage" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import type { Branch, Order } from '../../../types/models';
import type { PaginationMeta } from '../../../types/pagination';
import PaginationBar from '../../../shared/components/PaginationBar.vue';
import { branchesController } from '../../branches/branches.controller';
import { ordersController } from '../orders.controller';

type OrderRow = Order & { user_name?: string; user_email?: string };

const auth = useAuthStore();

const branches = ref<Branch[]>([]);
const branchesLoading = ref(false);

const branchId = ref<number>(auth.user?.branch_id || 0);
const page = ref(1);

const orders = ref<OrderRow[]>([]);
const pagination = ref<PaginationMeta | null>(null);

const pendingTotal = ref(0);
const approvedTotal = ref(0);

const loading = ref(false);
const error = ref('');

const isManager = computed(() => auth.user?.role === 'BRANCH_MANAGER');
const isSales = computed(() => auth.user?.role === 'SALES');
const branchLocked = computed(() => isSales.value || isManager.value);
const canApprove = computed(() => isManager.value);

async function loadBranches() {
  branchesLoading.value = true;
  try {
    const res = await branchesController.list({ page: 1, per_page: 100 });
    branches.value = res.items;

    // API is already scoped by role:
    // - BRANCH_MANAGER only sees their managed branch
    // - SALES only sees their assigned branch
    // So we can safely default to the first item.
    const firstId = Number((branches.value[0] as any)?.id || 0);
    if (!branchId.value) branchId.value = firstId;
    if (isManager.value || isSales.value) branchId.value = firstId;
  } finally {
    branchesLoading.value = false;
  }
}

async function loadTotals() {
  if (!branchId.value) return;
  try {
    const pending = await ordersController.listByBranch(branchId.value, { page: 1, per_page: 1, status: 'PENDING' });
    pendingTotal.value = pending.pagination?.total ?? 0;
  } catch {
    pendingTotal.value = 0;
  }

  try {
    const approved = await ordersController.listByBranch(branchId.value, { page: 1, per_page: 1, status: 'SUBMITTED' });
    approvedTotal.value = approved.pagination?.total ?? 0;
  } catch {
    approvedTotal.value = 0;
  }
}

async function load() {
  error.value = '';
  if (!branchId.value) return;

  loading.value = true;
  try {
    const res = await ordersController.listByBranch(branchId.value, { page: page.value, per_page: 25, status: 'PENDING' });
    orders.value = res.items;
    pagination.value = res.pagination || null;
    await loadTotals();
  } catch (e: any) {
    error.value = e?.message || 'Failed to load reserved orders';
    orders.value = [];
    pagination.value = null;
  } finally {
    loading.value = false;
  }
}

function onPage(p: number) {
  page.value = p;
  load();
}

async function onApprove(orderId: number) {
  error.value = '';
  loading.value = true;
  try {
    await ordersController.approve(orderId);
    await load();
  } catch (e: any) {
    error.value = e?.message || 'Failed to approve order';
  } finally {
    loading.value = false;
  }
}

watch(branchId, async () => {
  page.value = 1;
  await load();
});

onMounted(async () => {
  await loadBranches();
  await load();
});
</script>
