<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Dashboard</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Sales + top products + low stock</div>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <select v-model.number="branchId" style="min-width: 240px;" :disabled="branchesLoading || branches.length === 0">
        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <button class="btn" :disabled="loading" @click="load">Refresh</button>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="grid2">
    <div class="card">
      <div class="h2" style="margin-bottom: 10px;">Totals</div>

      <div v-if="loading" class="muted">Loading…</div>
      <div v-else class="form">
        <div class="row">
          <div class="muted">Sales (Today)</div>
          <div style="font-weight: 700;">{{ stats?.sales_today ?? 0 }}</div>
        </div>
        <div class="row">
          <div class="muted">Sales (This Month)</div>
          <div style="font-weight: 700;">{{ stats?.sales_month ?? 0 }}</div>
        </div>
        <div class="row">
          <div class="muted">Orders (This Month)</div>
          <div style="font-weight: 700;">{{ stats?.orders_month ?? 0 }}</div>
        </div>
        <div class="row">
          <div class="muted">Orders (Total)</div>
          <div style="font-weight: 700;">{{ stats?.orders_total ?? 0 }}</div>
        </div>
      </div>
    </div>

    <div class="card">
      <div class="row" style="margin-bottom: 10px;">
        <div class="h2">Low Stock</div>
        <div class="muted" style="font-size: 12px;">Threshold: {{ lowStockThreshold }}</div>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Qty</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in lowStockItems" :key="r.product_id">
            <td>{{ r.name }}</td>
            <td class="muted">{{ r.sku }}</td>
            <td>
              <span class="badge" :class="r.quantity <= lowStockThreshold ? 'warn' : ''">{{ r.quantity }}</span>
            </td>
            <td>
              <span class="badge" :class="r.status === 'ACTIVE' ? 'ok' : 'danger'">{{ r.status }}</span>
            </td>
          </tr>
          <tr v-if="!loading && lowStockItems.length === 0">
            <td colspan="4" class="muted">No low stock items.</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card" style="margin-top: 14px;">
    <div class="row" style="margin-bottom: 10px;">
      <div class="h2">Top 5 Selling Products (This Month)</div>
    </div>

    <table class="table">
      <thead>
        <tr>
          <th>Product</th>
          <th>SKU</th>
          <th>Qty Sold</th>
          <th>Revenue</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="r in topProducts" :key="r.product_id">
          <td>{{ r.name }}</td>
          <td class="muted">{{ r.sku }}</td>
          <td>{{ r.qty_sold }}</td>
          <td>{{ r.revenue }}</td>
        </tr>
        <tr v-if="!loading && topProducts.length === 0">
          <td colspan="4" class="muted">No sales yet.</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import type { Branch } from '../../../types/models';
import { branchesController } from '../../branches/branches.controller';
import { reportsController } from '../reports.controller';
import type { BranchDashboardStats, LowStockRow, TopProductRow } from '../reports.service';

const auth = useAuthStore();

const branches = ref<Branch[]>([]);
const branchesLoading = ref(false);

const branchId = ref<number>(0);

const loading = ref(false);
const error = ref('');

const stats = ref<BranchDashboardStats | null>(null);
const topProducts = ref<TopProductRow[]>([]);
const lowStockItems = ref<LowStockRow[]>([]);
const lowStockThreshold = ref(10);

const isManager = computed(() => auth.user?.role === 'BRANCH_MANAGER');

async function loadBranches() {
  branchesLoading.value = true;
  try {
    const res = await branchesController.list({ page: 1, per_page: 100 });
    branches.value = res.items;

    if (!branchId.value) {
      if (isManager.value && auth.user) {
        const managed = branches.value.find((b) => b.manager_id === auth.user?.id);
        branchId.value = managed?.id || branches.value[0]?.id || 0;
      } else {
        branchId.value = branches.value[0]?.id || 0;
      }
    }
  } finally {
    branchesLoading.value = false;
  }
}

async function load() {
  error.value = '';
  if (!branchId.value) return;

  loading.value = true;
  try {
    const data = await reportsController.getBranchDashboard(branchId.value, { low_stock_threshold: lowStockThreshold.value });
    stats.value = data.stats;
    topProducts.value = data.top_products;
    lowStockItems.value = data.low_stock_items;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load dashboard';
    stats.value = null;
    topProducts.value = [];
    lowStockItems.value = [];
  } finally {
    loading.value = false;
  }
}

watch(branchId, () => load());

onMounted(async () => {
  await loadBranches();
  await load();
});
</script>
