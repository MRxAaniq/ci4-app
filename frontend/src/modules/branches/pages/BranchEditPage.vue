<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Edit Branch</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Update branch details</div>
    </div>
    <div style="display: flex; gap: 10px;">
      <RouterLink class="btn" to="/app/branches">Back</RouterLink>
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
      <div class="field">
        <label>Address</label>
        <input v-model.trim="form.address" />
      </div>
      <div class="grid2">
        <div class="field">
          <label>Manager ID (optional)</label>
          <input v-model.trim="form.managerId" />
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
import type { Branch } from '../../../types/models';
import { branchesController } from '../branches.controller';

const route = useRoute();
const router = useRouter();

const id = Number.parseInt(route.params.id as string, 10);

const loading = ref(false);
const saving = ref(false);
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

async function load() {
  error.value = '';
  loading.value = true;
  try {
    const b = await branchesController.get(id);
    form.name = b.name;
    form.address = b.address;
    form.managerId = b.manager_id ? String(b.manager_id) : '';
    form.status = b.status;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load branch';
  } finally {
    loading.value = false;
  }
}

async function onSave() {
  error.value = '';
  saving.value = true;
  try {
    await branchesController.update(id, {
      name: form.name,
      address: form.address,
      manager_id: parseManagerId(form.managerId),
      status: form.status,
    });
    router.push('/app/branches');
  } catch (e: any) {
    error.value = e?.message || 'Failed to update branch';
  } finally {
    saving.value = false;
  }
}

onMounted(load);
</script>
