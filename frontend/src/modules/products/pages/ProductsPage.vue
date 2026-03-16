<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Products</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Create, update, delete products</div>
    </div>
    <button class="btn" :disabled="loading" @click="load">Refresh</button>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="grid2">
    <div class="card">
      <div class="h2" style="margin-bottom: 10px;">Create Product</div>

      <div v-if="!canUpsert" class="muted" style="font-size: 13px;">
        Only Admin/Branch Manager can create/update products.
      </div>

      <form v-else class="form" @submit.prevent="onCreate">
        <div class="field">
          <label>Name</label>
          <input v-model.trim="create.name" placeholder="Product name" />
        </div>
        <div class="field">
          <label>Cost Price</label>
          <input v-model.trim="create.cost" placeholder="10.50" />
        </div>
        <div class="field">
          <label>Sale Price</label>
          <input v-model.trim="create.sale" placeholder="15.00" />
        </div>
        <div class="field">
          <label>Tax Percentage</label>
          <input v-model.trim="create.tax" placeholder="0" />
        </div>
        <div class="field">
          <label>Status</label>
          <select v-model="create.status">
            <option value="ACTIVE">ACTIVE</option>
            <option value="INACTIVE">INACTIVE</option>
          </select>
        </div>
        <button class="btn primary" type="submit" :disabled="loading">Create</button>
      </form>
    </div>

    <div class="card">
      <div class="row" style="margin-bottom: 10px;">
        <div class="h2">Product List</div>
        <div class="muted" style="font-size: 12px;">{{ products.length }}</div>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>SKU</th>
            <th>Sale</th>
            <th>Tax %</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="p in products" :key="p.id">
            <td class="muted">{{ p.id }}</td>
            <td>{{ p.name }}</td>
            <td class="muted">{{ p.sku }}</td>
            <td>{{ p.sale_price }}</td>
            <td>{{ p.tax_percentage }}</td>
            <td><span class="badge" :class="p.status === 'ACTIVE' ? 'ok' : 'danger'">{{ p.status }}</span></td>
            <td style="display: flex; gap: 8px;">
              <button v-if="canUpsert" class="btn" @click="startEdit(p)">Edit</button>
              <button v-if="canDelete" class="btn danger" @click="onDelete(p)">Delete</button>
            </td>
          </tr>
          <tr v-if="!loading && products.length === 0">
            <td colspan="7" class="muted">No products.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="canUpsert && editing" style="margin-top: 12px;" class="card">
        <div class="row" style="margin-bottom: 10px;">
          <div style="font-weight: 600;">Edit Product #{{ editing.id }}</div>
          <button class="btn danger" @click="cancelEdit">Cancel</button>
        </div>

        <form class="form" @submit.prevent="onUpdate">
          <div class="field">
            <label>Name</label>
            <input v-model.trim="edit.name" />
          </div>
          <div class="field">
            <label>Cost Price</label>
            <input v-model.trim="edit.cost" />
          </div>
          <div class="field">
            <label>Sale Price</label>
            <input v-model.trim="edit.sale" />
          </div>
          <div class="field">
            <label>Tax Percentage</label>
            <input v-model.trim="edit.tax" />
          </div>
          <div class="field">
            <label>Status</label>
            <select v-model="edit.status">
              <option value="ACTIVE">ACTIVE</option>
              <option value="INACTIVE">INACTIVE</option>
            </select>
          </div>
          <button class="btn primary" type="submit" :disabled="loading">Save</button>
        </form>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, reactive, ref } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import type { Product } from '../../../types/models';
import { productsController } from '../products.controller';

const auth = useAuthStore();
const canUpsert = computed(() => auth.user?.role === 'ADMIN' || auth.user?.role === 'SUPER_ADMIN');
const canDelete = computed(() => auth.user?.role === 'ADMIN' || auth.user?.role === 'SUPER_ADMIN');

const products = ref<Product[]>([]);
const loading = ref(false);
const error = ref('');

const create = reactive({
  name: '',
  cost: '',
  sale: '',
  tax: '0',
  status: 'ACTIVE' as Product['status'],
});

const editing = ref<Product | null>(null);
const edit = reactive({
  name: '',
  cost: '',
  sale: '',
  tax: '0',
  status: 'ACTIVE' as Product['status'],
});

function num(v: string): number {
  const n = Number.parseFloat(v);
  return Number.isFinite(n) ? n : 0;
}

async function load() {
  error.value = '';
  loading.value = true;
  try {
    const res = await productsController.list({ page: 1, per_page: 100 });
    products.value = res.items;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load products';
  } finally {
    loading.value = false;
  }
}

async function onCreate() {
  error.value = '';
  loading.value = true;
  try {
    await productsController.create({
      name: create.name,
      cost_price: num(create.cost),
      sale_price: num(create.sale),
      tax_percentage: num(create.tax),
      status: create.status,
    });
    create.name = '';
    create.cost = '';
    create.sale = '';
    create.tax = '0';
    create.status = 'ACTIVE';
    await load();
  } catch (e: any) {
    error.value = e?.message || 'Failed to create product';
  } finally {
    loading.value = false;
  }
}

function startEdit(p: Product) {
  editing.value = p;
  edit.name = p.name;
  edit.cost = String(p.cost_price);
  edit.sale = String(p.sale_price);
  edit.tax = String(p.tax_percentage);
  edit.status = p.status;
}

function cancelEdit() {
  editing.value = null;
}

async function onUpdate() {
  if (!editing.value) return;
  error.value = '';
  if (!confirm('Save changes to this product?')) return;
  loading.value = true;
  try {
    await productsController.update(editing.value.id, {
      name: edit.name,
      cost_price: num(edit.cost),
      sale_price: num(edit.sale),
      tax_percentage: num(edit.tax),
      status: edit.status,
    });
    editing.value = null;
    await load();
  } catch (e: any) {
    error.value = e?.message || 'Failed to update product';
  } finally {
    loading.value = false;
  }
}

async function onDelete(p: Product) {
  error.value = '';
  if (!confirm('Delete this product?')) return;
  loading.value = true;
  try {
    await productsController.remove(p.id);
    await load();
  } catch (e: any) {
    error.value = e?.message || 'Failed to delete product';
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
