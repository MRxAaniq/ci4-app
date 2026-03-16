<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Orders</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Submit a new order (pending manager approval)</div>
    </div>
    <div style="display: flex; gap: 10px; align-items: center;">
      <RouterLink class="btn" to="/app/orders/reserved">Reserved Orders</RouterLink>
      <button class="btn" :disabled="loading" @click="loadLookups">Reload Lookups</button>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="grid2">
    <div class="card">
      <div class="h2" style="margin-bottom: 10px;">Create Order</div>

      <form class="form" @submit.prevent="onSubmit">
        <div class="field">
          <label>Search Branch</label>
          <div class="row">
            <input v-model.trim="branchQuery" placeholder="Search branches" @keydown.enter.prevent="loadBranches" />
            <button class="btn" type="button" :disabled="branchesLoading" @click="loadBranches">Search</button>
            <button class="btn" type="button" :disabled="branchesLoading" @click="clearBranchQuery">Clear</button>
          </div>
        </div>

        <div class="field">
          <label>Branch</label>
          <select v-model.number="branchId" :disabled="branchLocked || branchesLoading">
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>

        <div class="card" style="background: var(--panel-2);">
          <div class="row" style="margin-bottom: 10px;">
            <div style="font-weight: 600;">Products</div>
            <button class="btn" type="button" @click="addLine">Add line</button>
          </div>

          <div class="row" style="margin-bottom: 10px;">
            <div class="search" style="width: 100%;">
              <input v-model.trim="productQuery" placeholder="Search products by name or SKU" @keydown.enter.prevent="loadProducts" />
            </div>
            <button class="btn" type="button" :disabled="productsLoading" @click="loadProducts">Search</button>
            <button class="btn" type="button" :disabled="productsLoading" @click="clearProductQuery">Clear</button>
          </div>

          <div class="form">
            <div v-for="(l, idx) in lines" :key="l.key" class="row" style="align-items: end;">
              <div class="field" style="flex: 1;">
                <label>Product</label>
                <select v-model.number="l.productId" :disabled="productsLoading">
                  <option v-for="p in products" :key="p.id" :value="p.id">{{ p.name }} ({{ p.sku }})</option>
                </select>
              </div>
              <div class="field" style="width: 160px;">
                <label>Qty</label>
                <input v-model.number="l.quantity" type="number" min="1" step="1" />
              </div>
              <button class="btn danger" type="button" @click="removeLine(idx)">Remove</button>
            </div>
          </div>
        </div>

        <div class="card" style="background: var(--panel-2); margin-top: 12px;">
          <div style="font-weight: 600; margin-bottom: 10px;">Totals (preview)</div>
          <div class="row">
            <div class="muted">Subtotal</div>
            <div>{{ fmtMoney(totalsPreview.subtotal) }}</div>
          </div>
          <div class="row">
            <div class="muted">Tax</div>
            <div>{{ fmtMoney(totalsPreview.tax_total) }}</div>
          </div>
          <div class="row">
            <div class="muted">Grand Total</div>
            <div style="font-weight: 700;">{{ fmtMoney(totalsPreview.grand_total) }}</div>
          </div>
          <div class="muted" style="font-size: 12px; margin-top: 8px;">Final totals are computed server-side.</div>
        </div>

        <button class="btn primary" type="submit" :disabled="loading || !canSubmit">Submit Order</button>
      </form>
    </div>

    <div class="card">
      <div class="h2" style="margin-bottom: 10px;">Last Result</div>
      <div v-if="!lastOrder" class="muted" style="font-size: 13px;">No order submitted yet.</div>
      <div v-else class="form">
        <div class="row">
          <div class="muted">Order ID</div>
          <div style="font-weight: 600;">{{ lastOrder.id }}</div>
        </div>
        <div class="row">
          <div class="muted">Status</div>
          <div><span class="badge" :class="lastOrder.status === 'PENDING' ? 'warn' : 'ok'">{{ lastOrder.status }}</span></div>
        </div>
        <div class="row">
          <div class="muted">Subtotal</div>
          <div>{{ lastOrder.subtotal }}</div>
        </div>
        <div class="row">
          <div class="muted">Tax</div>
          <div>{{ lastOrder.tax_total }}</div>
        </div>
        <div class="row">
          <div class="muted">Grand Total</div>
          <div style="font-weight: 700;">{{ lastOrder.grand_total }}</div>
        </div>
        <div v-if="lastOrder.status === 'PENDING'" class="muted" style="font-size: 12px; margin-top: 8px;">
          Inventory is not deducted until a branch manager approves the order.
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import type { Branch, Order, Product } from '../../../types/models';
import { branchesController } from '../../branches/branches.controller';
import { productsController } from '../../products/products.controller';
import { ordersController } from '../orders.controller';

const auth = useAuthStore();

const branches = ref<Branch[]>([]);
const products = ref<Product[]>([]);

const branchesLoading = ref(false);
const productsLoading = ref(false);
const productQuery = ref('');
const branchQuery = ref('');

const branchLocked = computed(() => auth.user?.role === 'SALES');
const branchId = ref<number>(auth.user?.branch_id || 0);

type Line = { key: string; productId: number; quantity: number };
const lines = ref<Line[]>([]);

const loading = ref(false);
const error = ref('');

const lastOrder = ref<Order | null>(null);

function toNumber(v: unknown): number {
  if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
  if (typeof v === 'string') {
    const n = Number(v);
    return Number.isFinite(n) ? n : 0;
  }
  return 0;
}

const money = new Intl.NumberFormat(undefined, {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
});

function fmtMoney(n: number): string {
  return money.format(Number.isFinite(n) ? n : 0);
}

const totalsPreview = computed(() => {
  let subtotal = 0;
  let taxTotal = 0;

  for (const l of lines.value) {
    const qty = toNumber(l.quantity);
    if (qty <= 0) continue;

    const p = products.value.find((x) => x.id === l.productId);
    if (!p) continue;

    const price = toNumber(p.sale_price);
    const taxPct = toNumber(p.tax_percentage);
    const lineSubtotal = qty * price;
    const lineTax = lineSubtotal * (taxPct / 100);

    subtotal += lineSubtotal;
    taxTotal += lineTax;
  }

  const grandTotal = subtotal + taxTotal;
  return { subtotal, tax_total: taxTotal, grand_total: grandTotal };
});

const canSubmit = computed(() => branchId.value > 0 && lines.value.length > 0 && lines.value.every(l => l.productId > 0 && l.quantity > 0));

function addLine() {
  const firstProduct = products.value[0]?.id || 0;
  lines.value.push({ key: crypto.randomUUID(), productId: firstProduct, quantity: 1 });
}

function removeLine(idx: number) {
  lines.value.splice(idx, 1);
}

async function loadBranches() {
  branchesLoading.value = true;
  try {
    const res = await branchesController.list({ page: 1, per_page: 50, q: branchQuery.value.trim() || undefined });
    branches.value = res.items;
    if (!branchId.value) branchId.value = auth.user?.branch_id || branches.value[0]?.id || 0;
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
    // If list is empty keep existing selections.
    if (products.value.length > 0) {
      // Ensure existing lines reference a loaded product
      for (const l of lines.value) {
        if (!products.value.some((p) => p.id === l.productId)) {
          l.productId = products.value[0].id;
        }
      }
    }
    if (lines.value.length === 0) addLine();
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

  await loadBranches();

  await loadProducts();
}

async function onSubmit() {
  error.value = '';
  loading.value = true;
  try {
    const productsPayload = lines.value.map((l) => ({ product_id: l.productId, quantity: l.quantity }));
    lastOrder.value = await ordersController.create(branchId.value, productsPayload);
  } catch (e: any) {
    error.value = e?.message || 'Failed to submit order';
  } finally {
    loading.value = false;
  }
}

onMounted(loadLookups);
</script>
