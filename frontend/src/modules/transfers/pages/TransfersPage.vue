<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Transfers</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Move stock between branches</div>
    </div>
    <button class="btn" :disabled="loading" @click="loadLookups">Reload Lookups</button>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="grid2">
    <div class="card">
      <div class="h2" style="margin-bottom: 10px;">Create Transfer</div>

      <div v-if="!canTransfer" class="muted" style="font-size: 13px;">
        Only Admin/Branch Manager can create transfers.
      </div>

      <form v-else class="form" @submit.prevent="onSubmit">
        <div class="field">
          <label>Search Branch</label>
          <div class="row">
            <input v-model.trim="branchQuery" placeholder="Search branches" @keydown.enter.prevent="loadBranches" />
            <button class="btn" type="button" :disabled="branchesLoading" @click="loadBranches">Search</button>
            <button class="btn" type="button" :disabled="branchesLoading" @click="clearBranchQuery">Clear</button>
          </div>
        </div>

        <div class="field">
          <label>From Branch</label>
          <select v-model.number="fromBranch" :disabled="branchesLoading">
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
        <div class="field">
          <label>To Branch</label>
          <select v-model.number="toBranch" :disabled="branchesLoading">
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>

        <div class="field">
          <label>Search Product</label>
          <div class="row">
            <input v-model.trim="productQuery" placeholder="Search products by name or SKU" @keydown.enter.prevent="loadProducts" />
            <button class="btn" type="button" :disabled="productsLoading" @click="loadProducts">Search</button>
            <button class="btn" type="button" :disabled="productsLoading" @click="clearProductQuery">Clear</button>
          </div>
        </div>

        <div class="field">
          <label>Product</label>
          <select v-model.number="productId" :disabled="productsLoading">
            <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
          </select>
        </div>
        <div class="field">
          <label>Quantity</label>
          <input v-model.number="quantity" type="number" min="1" step="1" />
        </div>
        <button class="btn primary" type="submit" :disabled="loading || !canSubmit">Transfer</button>
      </form>
    </div>

    <div class="card">
      <div class="h2" style="margin-bottom: 10px;">Last Result</div>
      <div v-if="!lastTransfer" class="muted" style="font-size: 13px;">No transfer created yet.</div>
      <div v-else class="form">
        <div class="row">
          <div class="muted">Transfer ID</div>
          <div style="font-weight: 600;">{{ lastTransfer.id }}</div>
        </div>
        <div class="row">
          <div class="muted">Status</div>
          <div><span class="badge ok">{{ lastTransfer.status }}</span></div>
        </div>
        <div class="row">
          <div class="muted">Quantity</div>
          <div>{{ lastTransfer.quantity }}</div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import type { Branch, Product, StockTransfer } from '../../../types/models';
import { branchesController } from '../../branches/branches.controller';
import { productsController } from '../../products/products.controller';
import { transfersController } from '../transfers.controller';

const auth = useAuthStore();
const canTransfer = computed(() => auth.user?.role === 'ADMIN' || auth.user?.role === 'SUPER_ADMIN' || auth.user?.role === 'BRANCH_MANAGER');

const branches = ref<Branch[]>([]);
const products = ref<Product[]>([]);

const branchesLoading = ref(false);
const productsLoading = ref(false);
const loading = ref(false);
const error = ref('');

const branchQuery = ref('');
const productQuery = ref('');

const fromBranch = ref<number>(auth.user?.branch_id || 0);
const toBranch = ref<number>(0);
const productId = ref<number>(0);
const quantity = ref<number>(1);

const lastTransfer = ref<StockTransfer | null>(null);

const canSubmit = computed(() => fromBranch.value > 0 && toBranch.value > 0 && fromBranch.value !== toBranch.value && productId.value > 0 && quantity.value > 0);

async function loadBranches() {
  branchesLoading.value = true;
  try {
    const res = await branchesController.list({ page: 1, per_page: 50, q: branchQuery.value.trim() || undefined });
    branches.value = res.items;
    if (!fromBranch.value) fromBranch.value = auth.user?.branch_id || branches.value[0]?.id || 0;
    if (!toBranch.value) toBranch.value = branches.value.find((b) => b.id !== fromBranch.value)?.id || fromBranch.value;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load branches';
  } finally {
    branchesLoading.value = false;
  }
}

function clearBranchQuery() {
  branchQuery.value = '';
  loadBranches();
}

async function loadProducts() {
  productsLoading.value = true;
  try {
    const res = await productsController.list({ page: 1, per_page: 50, q: productQuery.value.trim() || undefined });
    products.value = res.items;
    productId.value = productId.value || products.value[0]?.id || 0;
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

async function loadLookups() {
  error.value = '';

  await Promise.all([loadBranches(), loadProducts()]);
}

async function onSubmit() {
  error.value = '';
  loading.value = true;
  try {
    lastTransfer.value = await transfersController.create(fromBranch.value, toBranch.value, productId.value, quantity.value);
  } catch (e: any) {
    error.value = e?.message || 'Failed to create transfer';
  } finally {
    loading.value = false;
  }
}

onMounted(loadLookups);
</script>
