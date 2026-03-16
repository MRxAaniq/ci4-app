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
          <label>Manager Name (optional)</label>
          <select v-model.number="selectedManagerId" :disabled="managersLoading || managers.length === 0" @change="syncManagerFields">
            <option :value="0">— None —</option>
            <option v-for="u in managers" :key="u.id" :value="u.id">{{ u.name }}</option>
          </select>
        </div>
        <div class="field">
          <label>Manager Email (optional)</label>
          <select v-model.number="selectedManagerId" :disabled="managersLoading || managers.length === 0" @change="syncManagerFields">
            <option :value="0">—</option>
            <option v-for="u in managers" :key="u.id" :value="u.id">{{ u.email }}</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label>Status</label>
        <select v-model="form.status">
          <option value="ACTIVE">ACTIVE</option>
          <option value="INACTIVE">INACTIVE</option>
        </select>
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
import { usersController } from '../../users/users.controller';

const route = useRoute();
const router = useRouter();

const id = Number.parseInt(route.params.id as string, 10);

const loading = ref(false);
const saving = ref(false);
const error = ref('');

const managersLoading = ref(false);
const managers = ref<Array<{ id: number; name: string; email: string }>>([]);
const selectedManagerId = ref(0);

const form = reactive({
  name: '',
  address: '',
  managerName: '',
  managerEmail: '',
  status: 'ACTIVE' as Branch['status'],
});

async function loadManagers() {
  managersLoading.value = true;
  try {
    const users = await usersController.list({ page: 1, per_page: 200, role: 'BRANCH_MANAGER', status: 'ACTIVE' });
    managers.value = users
      .filter((u: any) => (u?.id || 0) > 0)
      .map((u: any) => ({ id: u.id, name: u.name, email: u.email }));
  } catch (e) {
    managers.value = [];
  } finally {
    managersLoading.value = false;
  }
}

function syncManagerFields() {
  const id = Number(selectedManagerId.value || 0);
  if (id <= 0) {
    form.managerName = '';
    form.managerEmail = '';
    return;
  }

  const u = managers.value.find((m) => m.id === id);
  if (!u) return;
  form.managerName = u.name;
  form.managerEmail = u.email;
}

async function load() {
  error.value = '';
  loading.value = true;
  try {
    const b = await branchesController.get(id);
    form.name = b.name;
    form.address = b.address;
    selectedManagerId.value = Number(b.manager_id || 0) || 0;
    form.managerName = (b.manager_name as string) || '';
    form.managerEmail = (b.manager_email as string) || '';
    form.status = b.status;

    // If the selected manager is in the dropdown list, keep fields synced.
    syncManagerFields();
  } catch (e: any) {
    error.value = e?.message || 'Failed to load branch';
  } finally {
    loading.value = false;
  }
}

async function onSave() {
  error.value = '';
  if (!confirm('Save changes to this branch?')) return;
  saving.value = true;
  try {
    await branchesController.update(id, {
      name: form.name,
      address: form.address,
      manager_name: form.managerName.trim() || undefined,
      manager_email: form.managerEmail.trim() || undefined,
      status: form.status,
    });
    router.push('/app/branches');
  } catch (e: any) {
    error.value = e?.message || 'Failed to update branch';
  } finally {
    saving.value = false;
  }
}

onMounted(async () => {
  await loadManagers();
  await load();
});
</script>
