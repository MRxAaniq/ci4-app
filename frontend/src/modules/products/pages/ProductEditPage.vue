<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Edit Product</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Update product details</div>
    </div>
    <div style="display: flex; gap: 10px;">
      <RouterLink class="btn" to="/app/products">Back</RouterLink>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="card" style="max-width: 720px;">
    <div v-if="loading" class="muted">Loading…</div>

    <form v-else class="form" @submit.prevent="onSave">
      <div class="field">
        <label>Name</label>
        <input v-model.trim="form.name" />
      </div>
      <div class="grid2">
        <div class="field">
          <label>Cost Price</label>
          <input v-model.trim="form.cost" />
        </div>
        <div class="field">
          <label>Sale Price</label>
          <input v-model.trim="form.sale" />
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label>Tax Percentage</label>
          <input v-model.trim="form.tax" />
        </div>
        <div class="field">
          <label>Status</label>
          <select v-model="form.status">
            <option value="ACTIVE">ACTIVE</option>
            <option value="INACTIVE">INACTIVE</option>
          </select>
        </div>
      </div>

      <div class="row">
        <button class="btn primary" type="submit" :disabled="saving">Save</button>
        <div class="muted" style="font-size: 12px;">ID: {{ id }}</div>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import type { Product } from '../../../types/models';
import { productsController } from '../products.controller';

const route = useRoute();
const router = useRouter();

const id = Number.parseInt(route.params.id as string, 10);

const loading = ref(false);
const saving = ref(false);
const error = ref('');

const form = reactive({
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
    const p = await productsController.get(id);
    form.name = p.name;
    form.cost = String(p.cost_price);
    form.sale = String(p.sale_price);
    form.tax = String(p.tax_percentage);
    form.status = p.status;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load product';
  } finally {
    loading.value = false;
  }
}

async function onSave() {
  error.value = '';
  if (!confirm('Save changes to this product?')) return;
  saving.value = true;
  try {
    await productsController.update(id, {
      name: form.name,
      cost_price: num(form.cost),
      sale_price: num(form.sale),
      tax_percentage: num(form.tax),
      status: form.status,
    });
    router.push('/app/products');
  } catch (e: any) {
    error.value = e?.message || 'Failed to update product';
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
