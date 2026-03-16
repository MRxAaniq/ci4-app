<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">New Product</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Create a product</div>
    </div>
    <div style="display: flex; gap: 10px;">
      <RouterLink class="btn" to="/app/products">Back</RouterLink>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="card" style="max-width: 720px;">
    <form class="form" @submit.prevent="onCreate">
      <div class="field">
        <label>Name</label>
        <input v-model.trim="form.name" placeholder="Product name" />
      </div>
      <div class="field">
        <label>SKU</label>
        <input v-model.trim="form.sku" placeholder="SKU-001" />
      </div>
      <div class="grid2">
        <div class="field">
          <label>Cost Price</label>
          <input v-model.trim="form.cost" placeholder="10.50" />
        </div>
        <div class="field">
          <label>Sale Price</label>
          <input v-model.trim="form.sale" placeholder="15.00" />
        </div>
      </div>
      <div class="grid2">
        <div class="field">
          <label>Tax Percentage</label>
          <input v-model.trim="form.tax" placeholder="0" />
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
        <button class="btn primary" type="submit" :disabled="loading">Create</button>
        <div class="muted" style="font-size: 12px;">Fields validated by API.</div>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import type { Product } from '../../../types/models';
import { productsController } from '../products.controller';

const router = useRouter();

const loading = ref(false);
const error = ref('');

const form = reactive({
  name: '',
  sku: '',
  cost: '',
  sale: '',
  tax: '0',
  status: 'ACTIVE' as Product['status'],
});

function num(v: string): number {
  const n = Number.parseFloat(v);
  return Number.isFinite(n) ? n : 0;
}

async function onCreate() {
  error.value = '';
  loading.value = true;
  try {
    await productsController.create({
      name: form.name,
      sku: form.sku,
      cost_price: num(form.cost),
      sale_price: num(form.sale),
      tax_percentage: num(form.tax),
      status: form.status,
    });
    router.push('/app/products');
  } catch (e: any) {
    error.value = e?.message || 'Failed to create product';
  } finally {
    loading.value = false;
  }
}
</script>
