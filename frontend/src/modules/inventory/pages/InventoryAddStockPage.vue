<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Add Stock</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Increase inventory for a branch</div>
    </div>

    <div class="row" style="gap: 10px; justify-content: flex-end;">
      <button class="btn" :disabled="loading" @click="goBack">Back to Inventory</button>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="card" style="max-width: 720px;">
    <form class="form" @submit.prevent="onSubmit">
      <div class="field">
        <label>Branch</label>
        <select v-model.number="branchId" :disabled="loading || branchesLoading || branchLocked">
          <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
        </select>
      </div>

      <div class="card" style="background: var(--panel-2);">
        <div class="row" style="margin-bottom: 10px;">
          <div style="font-weight: 600;">Select Product</div>
        </div>

        <div class="row" style="margin-bottom: 10px;">
          <div class="search" style="width: 100%;">
            <input v-model.trim="productQuery" placeholder="Search products by name or SKU" @keydown.enter.prevent="loadProducts" />
          </div>
          <button class="btn" type="button" :disabled="productsLoading" @click="loadProducts">Search</button>
          <button class="btn" type="button" :disabled="productsLoading" @click="clearProductQuery">Clear</button>
        </div>

        <div class="field">
          <label>Product</label>
          <select v-model.number="productId" :disabled="loading || productsLoading">
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label>Quantity</label>
        <input v-model.number="quantity" type="number" min="1" step="1" :disabled="loading" />
      </div>

      <div class="field">
        <label>Note (optional)</label>
        <input v-model.trim="note" placeholder="Add stock" :disabled="loading" />
      </div>

      <button class="btn primary" type="submit" :disabled="loading || !canSubmit">Add</button>
    </form>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../../stores/auth';
import type { Branch, Product } from '../../../types/models';
import { branchesController } from '../../branches/branches.controller';
import { productsController } from '../../products/products.controller';
import { inventoryController } from '../inventory.controller';

const router = useRouter();
const route = useRoute();
const auth = useAuthStore();

const branches = ref<Branch[]>([]);
const products = ref<Product[]>([]);

const branchesLoading = ref(false);
const productsLoading = ref(false);

const loading = ref(false);
const error = ref('');

const isManager = computed(() => auth.user?.role === 'BRANCH_MANAGER');
const isSales = computed(() => auth.user?.role === 'SALES');
const branchId = ref<number>(auth.user?.branch_id || 0);
const branchLocked = computed(() => isSales.value || (isManager.value && branchId.value > 0));

const productQuery = ref('');
const productId = ref<number>(0);

const quantity = ref<number>(1);
const note = ref('');

const canSubmit = computed(() => branchId.value > 0 && productId.value > 0 && quantity.value > 0);

function goBack() {
  router.push('/app/inventory');
}

async function loadBranches() {
  branchesLoading.value = true;
  try {
    const res = await branchesController.list({ page: 1, per_page: 100 });
    branches.value = res.items;

    const qBranch = Number.parseInt(String(route.query.branch_id ?? ''), 10);
    const queryBranchId = Number.isFinite(qBranch) && qBranch > 0 ? qBranch : 0;

    if (isManager.value && auth.user) {
      const managed = branches.value.find((b) => b.manager_id === auth.user?.id);
      branchId.value = managed?.id || 0;
      return;
    }

    if (isSales.value && auth.user) {
      branchId.value = auth.user.branch_id || 0;
      return;
    }

    if (queryBranchId > 0 && branches.value.some((b) => b.id === queryBranchId)) {
      branchId.value = queryBranchId;
      return;
    }

    if (!branchId.value) branchId.value = branches.value[0]?.id || 0;
  } finally {
    branchesLoading.value = false;
  }
}

async function loadProducts() {
  productsLoading.value = true;
  try {
    const res = await productsController.list({ page: 1, per_page: 50, q: productQuery.value.trim() || undefined });
    products.value = res.items;
    if (products.value.length > 0 && !products.value.some((p) => p.id === productId.value)) {
      productId.value = products.value[0].id;
    }
  } catch (e: any) {
    error.value = e?.message || 'Failed to load products';
  } finally {
    productsLoading.value = false;
  }
}

function clearProductQuery() {
  productQuery.value = '';
  loadProducts();
}

async function onSubmit() {
  error.value = '';
  loading.value = true;
  try {
    await inventoryController.addStock(branchId.value, productId.value, quantity.value, note.value.trim() || undefined);
    goBack();
  } catch (e: any) {
    error.value = e?.message || 'Failed to add stock';
  } finally {
    loading.value = false;
  }
}

onMounted(async () => {
  await loadBranches();
  await loadProducts();
});
</script>
