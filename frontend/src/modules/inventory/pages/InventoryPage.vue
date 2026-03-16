<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Inventory</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Branch stock with product details</div>
    </div>

    <div style="display: flex; gap: 10px; align-items: center;">
      <select v-model.number="branchId" style="min-width: 240px;" :disabled="branchesLoading || branches.length === 0 || branchLocked">
        <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
      </select>
      <button class="btn" :disabled="loading" @click="load">Refresh</button>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="grid2">
    <div class="card">
      <div class="row" style="margin-bottom: 10px;">
        <div>
          <div class="h2">Inventory List</div>
        </div>
        <div class="muted" style="font-size: 12px;">{{ loading ? 'Loading…' : rows.length + ' items' }}</div>
      </div>

      <SearchBar v-model="q" placeholder="Search by product name or SKU" :disabled="loading" @search="onSearch" @clear="onClear" />

      <table class="table" style="margin-top: 12px;">
        <thead>
          <tr>
            <th>Product</th>
            <th>SKU</th>
            <th>Qty</th>
            <th>Price</th>
            <th>Tax %</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="r in rows" :key="r.product_id">
            <td>{{ r.name }}</td>
            <td class="muted">{{ r.sku }}</td>
            <td>
              <span class="badge" :class="r.quantity <= lowStockThreshold ? 'warn' : ''">{{ r.quantity }}</span>
            </td>
            <td>{{ r.sale_price }}</td>
            <td>{{ r.tax_percentage }}</td>
            <td>
              <span class="badge" :class="r.status === 'ACTIVE' ? 'ok' : 'danger'">{{ r.status }}</span>
            </td>
          </tr>
          <tr v-if="!loading && rows.length === 0">
            <td colspan="6" class="muted">No inventory rows.</td>
          </tr>
        </tbody>
      </table>

      <PaginationBar v-if="pagination" :pagination="pagination" :disabled="loading" @change="onPage" />
    </div>

    <div class="card">
      <div class="h2" style="margin-bottom: 10px;">Stock Actions</div>

      <div v-if="!canWrite" class="muted" style="font-size: 13px;">
        You can view inventory only. Stock updates require Admin/Branch Manager.
      </div>

      <div v-else class="form">
        <div class="row" style="justify-content: flex-end;">
          <button class="btn primary" type="button" @click="goAddStock">Add Stock</button>
        </div>

        <div class="card" style="background: var(--panel-2);">
          <div class="row" style="margin-bottom: 10px;">
            <div style="font-weight: 600;">Adjust Stock</div>
          </div>

          <div class="row" style="margin-bottom: 10px;">
            <div class="search" style="width: 100%;">
              <input v-model.trim="productQuery" placeholder="Search products by name or SKU" @keydown.enter.prevent="loadProducts" />
            </div>
            <button class="btn" type="button" :disabled="productsLoading" @click="loadProducts">Search</button>
            <button class="btn" type="button" :disabled="productsLoading" @click="clearProductQuery">Clear</button>
          </div>

          <form class="form" @submit.prevent="onAdjust">
            <div class="field">
              <label>Product</label>
              <select v-model.number="adjust.productId" :disabled="productsLoading">
                <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
              </select>
            </div>
            <div class="field">
              <label>Delta (+/-)</label>
              <input v-model.number="adjust.delta" type="number" step="1" />
            </div>
            <div class="field">
              <label>Note (optional)</label>
              <input v-model.trim="adjust.note" placeholder="Adjust stock" />
            </div>
            <button class="btn" type="submit" :disabled="loading || !canAdjust">Adjust</button>
          </form>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../../../stores/auth';
import type { Branch, InventoryRow, Product } from '../../../types/models';
import type { PaginationMeta } from '../../../types/pagination';
import SearchBar from '../../../shared/components/SearchBar.vue';
import PaginationBar from '../../../shared/components/PaginationBar.vue';
import { branchesController } from '../../branches/branches.controller';
import { productsController } from '../../products/products.controller';
import { inventoryController } from '../inventory.controller';

const auth = useAuthStore();
const router = useRouter();

const branches = ref<Branch[]>([]);
const products = ref<Product[]>([]);
const rows = ref<InventoryRow[]>([]);
const pagination = ref<PaginationMeta | null>(null);

const q = ref('');
const page = ref(1);

const branchId = ref<number>(auth.user?.branch_id || 0);

const loading = ref(false);
const error = ref('');

const branchesLoading = ref(false);
const productsLoading = ref(false);

const lowStockThreshold = 10;
const productQuery = ref('');

const canWrite = computed(() => auth.user?.role === 'ADMIN' || auth.user?.role === 'SUPER_ADMIN' || auth.user?.role === 'BRANCH_MANAGER');
const isManager = computed(() => auth.user?.role === 'BRANCH_MANAGER');
const isSales = computed(() => auth.user?.role === 'SALES');
const branchLocked = computed(() => isSales.value || (isManager.value && branchId.value > 0));

const adjust = reactive({
  productId: 0,
  delta: 0,
  note: '',
});

const canAdjust = computed(() => branchId.value > 0 && adjust.productId > 0 && adjust.delta !== 0);

function goAddStock() {
  router.push({ path: '/app/inventory/add-stock', query: { branch_id: String(branchId.value || '') } });
}

async function loadBranches() {
  branchesLoading.value = true;
  try {
    const res = await branchesController.list({ page: 1, per_page: 100 });
    branches.value = res.items;

    if (isManager.value && auth.user) {
      const managed = branches.value.find((b) => b.manager_id === auth.user?.id);
      branchId.value = managed?.id || 0;
      return;
    }

    if (isSales.value && auth.user) {
      branchId.value = auth.user.branch_id || 0;
      return;
    }

    if (!branchId.value) branchId.value = branches.value[0]?.id || 0;
  } finally {
    branchesLoading.value = false;
  }
}

async function loadProducts() {
  if (!canWrite.value) return;
  productsLoading.value = true;
  try {
    const res = await productsController.list({ page: 1, per_page: 50, q: productQuery.value.trim() || undefined });
    products.value = res.items;
    if (products.value.length > 0) {
      if (!products.value.some((p) => p.id === adjust.productId)) adjust.productId = products.value[0].id;
    }
  } finally {
    productsLoading.value = false;
  }
}

function clearProductQuery() {
  productQuery.value = '';
  loadProducts();
}

async function load() {
  error.value = '';
  if (!branchId.value) return;
  loading.value = true;
  try {
    const res = await inventoryController.getBranchInventory(branchId.value, {
      page: page.value,
      per_page: 25,
      q: q.value.trim() || undefined,
    });
    rows.value = res.items;
    pagination.value = res.pagination || null;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load inventory';
    rows.value = [];
    pagination.value = null;
  } finally {
    loading.value = false;
  }
}

function onSearch() {
  page.value = 1;
  load();
}

function onClear() {
  q.value = '';
  page.value = 1;
  load();
}

function onPage(p: number) {
  page.value = p;
  load();
}

async function onAdjust() {
  error.value = '';
  if (!canAdjust.value) {
    error.value = 'Select a product and enter a non-zero delta';
    return;
  }
  loading.value = true;
  try {
    await inventoryController.adjustStock(branchId.value, adjust.productId, adjust.delta, adjust.note || undefined);
    await load();
    adjust.delta = 0;
    adjust.note = '';
  } catch (e: any) {
    error.value = e?.message || 'Failed to adjust stock';
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
  await loadProducts();
  if (branchId.value) await load();
});
</script>
