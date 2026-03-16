<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">New Branch</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Create a branch</div>
    </div>
    <div style="display: flex; gap: 10px;">
      <RouterLink class="btn" to="/app/branches">Back</RouterLink>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="card" style="max-width: 720px;">
    <form class="form" @submit.prevent="onCreate">
      <div class="field">
        <label>Name</label>
        <input v-model.trim="form.name" placeholder="Branch name" />
      </div>
      <div class="field">
        <label>Address</label>
        <input v-model.trim="form.address" placeholder="Branch address" />
      </div>
      <div class="grid2">
        <div class="field">
          <label>Manager ID (optional)</label>
          <input v-model.trim="form.managerId" placeholder="e.g. 2" />
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
import type { Branch } from '../../../types/models';
import { branchesController } from '../branches.controller';

const router = useRouter();

const loading = ref(false);
const error = ref('');

const form = reactive({
  name: '',
  address: '',
  managerId: '',
  status: 'ACTIVE' as Branch['status'],
});

function parseManagerId(v: string): number | undefined {
  const n = Number.parseInt(v, 10);
  return Number.isFinite(n) && n > 0 ? n : undefined;
}

async function onCreate() {
  error.value = '';
  loading.value = true;
  try {
    await branchesController.create({
      name: form.name,
      address: form.address,
      manager_id: parseManagerId(form.managerId),
      status: form.status,
    });
    router.push('/app/branches');
  } catch (e: any) {
    error.value = e?.message || 'Failed to create branch';
  } finally {
    loading.value = false;
  }
}
</script>
