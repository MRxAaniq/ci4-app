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
        <button class="btn primary" type="submit" :disabled="loading">Create</button>
        <div class="muted" style="font-size: 12px;">Fields validated by API.</div>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import { useRouter } from 'vue-router';
import type { Branch } from '../../../types/models';
import { branchesController } from '../branches.controller';
import { usersController } from '../../users/users.controller';

const router = useRouter();

const loading = ref(false);
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
    // Keep page usable even if dropdown fails.
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

async function onCreate() {
  error.value = '';
  loading.value = true;
  try {
    await branchesController.create({
      name: form.name,
      address: form.address,
      manager_name: form.managerName.trim() || undefined,
      manager_email: form.managerEmail.trim() || undefined,
      status: form.status,
    });
    router.push('/app/branches');
  } catch (e: any) {
    error.value = e?.message || 'Failed to create branch';
  } finally {
    loading.value = false;
  }
}

onMounted(loadManagers);
</script>
