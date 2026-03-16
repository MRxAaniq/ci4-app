<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Products</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Search and manage products</div>
    </div>

    <div style="display: flex; gap: 10px;">
      <RouterLink class="btn primary" to="/app/products/new">New Product</RouterLink>
      <button class="btn" :disabled="loading" @click="load(1)">Refresh</button>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="card">
    <SearchBar v-model="q" placeholder="Search by name or SKU" :disabled="loading" @search="onSearch" @clear="onClear" />

    <table class="table" style="margin-top: 12px;">
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
        <tr v-for="p in items" :key="p.id">
          <td class="muted">{{ p.id }}</td>
          <td>{{ p.name }}</td>
          <td class="muted">{{ p.sku }}</td>
          <td>{{ p.sale_price }}</td>
          <td>{{ p.tax_percentage }}</td>
          <td><span class="badge" :class="p.status === 'ACTIVE' ? 'ok' : 'danger'">{{ p.status }}</span></td>
          <td style="display: flex; gap: 8px;">
            <RouterLink class="btn" :to="`/app/products/${p.id}/edit`">Edit</RouterLink>
            <button v-if="canDelete" class="btn danger" :disabled="loading" @click="onDelete(p.id)">Delete</button>
          </td>
        </tr>
        <tr v-if="!loading && items.length === 0">
          <td colspan="7" class="muted">No products found.</td>
        </tr>
      </tbody>
    </table>

    <PaginationBar v-if="pagination" :pagination="pagination" :disabled="loading" @change="onPage" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import type { Product } from '../../../types/models';
import type { PaginationMeta } from '../../../types/pagination';
import SearchBar from '../../../shared/components/SearchBar.vue';
import PaginationBar from '../../../shared/components/PaginationBar.vue';
import { productsController } from '../products.controller';

const auth = useAuthStore();
const canDelete = computed(() => auth.user?.role === 'ADMIN' || auth.user?.role === 'SUPER_ADMIN');

const items = ref<Product[]>([]);
const pagination = ref<PaginationMeta | null>(null);

const q = ref('');
const loading = ref(false);
const error = ref('');

async function load(page = 1) {
  error.value = '';
  loading.value = true;
  try {
    const res = await productsController.list({ page, per_page: 25, q: q.value.trim() || undefined });
    items.value = res.items;
    pagination.value = res.pagination || null;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load products';
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

async function onDelete(id: number) {
  if (!confirm('Delete this product?')) return;
  error.value = '';
  loading.value = true;
  try {
    await productsController.remove(id);
    await load(pagination.value?.page || 1);
  } catch (e: any) {
    error.value = e?.message || 'Failed to delete product';
  } finally {
    loading.value = false;
  }
}

onMounted(() => load(1));
</script>
