<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Inventory Movements</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Stock movement history per branch</div>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <select v-model.number="branchId" style="min-width: 240px;" :disabled="branchesLoading || branches.length === 0">
        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <button class="btn" :disabled="loading" @click="load(1)">Refresh</button>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="card">
    <SearchBar v-model="q" placeholder="Search by product name or SKU" :disabled="loading" @search="onSearch" @clear="onClear" />

    <table class="table" style="margin-top: 12px;">
      <thead>
        <tr>
          <th>Time</th>
          <th>Product</th>
          <th>Delta</th>
          <th>Before</th>
          <th>After</th>
          <th>Ref</th>
          <th>Actor</th>
          <th>Note</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="m in items" :key="m.id">
          <td class="muted">{{ m.created_at }}</td>
          <td>{{ m.product_name }} <span class="muted">({{ m.product_sku }})</span></td>
          <td>
            <span class="badge" :class="m.delta_qty < 0 ? 'danger' : 'ok'">{{ m.delta_qty }}</span>
          </td>
          <td class="muted">{{ m.qty_before }}</td>
          <td style="font-weight: 600;">{{ m.qty_after }}</td>
          <td class="muted">{{ m.ref_type }} #{{ m.ref_id }}</td>
          <td class="muted">{{ m.actor_name || '—' }}</td>
          <td class="muted">{{ m.note || '—' }}</td>
        </tr>
        <tr v-if="!loading && items.length === 0">
          <td colspan="8" class="muted">No movements found.</td>
        </tr>
      </tbody>
    </table>

    <PaginationBar v-if="pagination" :pagination="pagination" :disabled="loading" @change="onPage" />
  </div>
</template>

<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import type { Branch, InventoryMovement } from '../../../types/models';
import type { PaginationMeta } from '../../../types/pagination';
import SearchBar from '../../../shared/components/SearchBar.vue';
import PaginationBar from '../../../shared/components/PaginationBar.vue';
import { branchesController } from '../../branches/branches.controller';
import { inventoryController } from '../inventory.controller';

const branches = ref<Branch[]>([]);
const branchesLoading = ref(false);

const branchId = ref<number>(0);

const items = ref<InventoryMovement[]>([]);
const pagination = ref<PaginationMeta | null>(null);

const q = ref('');
const loading = ref(false);
const error = ref('');

async function loadBranches() {
  branchesLoading.value = true;
  try {
    const res = await branchesController.list({ page: 1, per_page: 100 });
    branches.value = res.items;
    if (!branchId.value) branchId.value = branches.value[0]?.id || 0;
  } finally {
    branchesLoading.value = false;
  }
}

async function load(page = 1) {
  error.value = '';
  if (!branchId.value) return;
  loading.value = true;
  try {
    const res = await inventoryController.getMovements(branchId.value, { page, per_page: 25, q: q.value.trim() || undefined });
    items.value = res.items;
    pagination.value = res.pagination || null;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load movements';
    items.value = [];
    pagination.value = null;
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  load(1);
}

function onClear() {
  q.value = '';
  load(1);
}

function onPage(page: number) {
  load(page);
}

watch(branchId, () => load(1));

onMounted(async () => {
  await loadBranches();
  await load(1);
});
</script>
