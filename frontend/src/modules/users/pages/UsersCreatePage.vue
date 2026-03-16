<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Users</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Create branch manager or sales users</div>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>
  <div v-if="success" class="card" style="margin-bottom: 12px; border-left: 3px solid var(--ok);">
    <div style="font-weight: 600;">{{ success }}</div>
  </div>

  <div class="card" style="max-width: 720px;">
    <form class="form" @submit.prevent="onCreate">
      <div class="grid2">
        <div class="field">
          <label>Role</label>
          <select v-model="form.role">
            <option value="SALES">SALES</option>
            <option value="BRANCH_MANAGER">BRANCH_MANAGER</option>
          </select>
        </div>

        <div class="field">
          <label>Branch</label>
          <select v-model.number="form.branch_id" :disabled="branchesLoading || branches.length === 0">
            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
          </select>
        </div>
      </div>

      <div class="field">
        <label>Name</label>
        <input v-model.trim="form.name" placeholder="Full name" />
      </div>

      <div class="field">
        <label>Email</label>
        <input v-model.trim="form.email" placeholder="user@example.com" />
      </div>

      <div class="field">
        <label>Password</label>
        <input v-model="form.password" type="password" placeholder="Minimum 6 characters" />
      </div>

      <div class="row">
        <button class="btn primary" type="submit" :disabled="loading || !form.branch_id">Create User</button>
        <div class="muted" style="font-size: 12px;">Branch is required for both roles.</div>
      </div>
    </form>
  </div>
</template>

<script setup lang="ts">
import { onMounted, reactive, ref } from 'vue';
import type { Branch } from '../../../types/models';
import { branchesController } from '../../branches/branches.controller';
import { usersController } from '../users.controller';

const branches = ref<Branch[]>([]);
const branchesLoading = ref(false);

const loading = ref(false);
const error = ref('');
const success = ref('');

const form = reactive({
  role: 'SALES' as 'SALES' | 'BRANCH_MANAGER',
  branch_id: 0,
  name: '',
  email: '',
  password: '',
});

async function loadBranches() {
  branchesLoading.value = true;
  try {
    const res = await branchesController.list({ page: 1, per_page: 100 });
    branches.value = res.items;
    if (!form.branch_id) form.branch_id = branches.value[0]?.id || 0;
  } finally {
    branchesLoading.value = false;
  }
}

async function onCreate() {
  error.value = '';
  success.value = '';
  loading.value = true;
  try {
    const resp = await usersController.create({
      name: form.name,
      email: form.email,
      password: form.password,
      role: form.role,
      branch_id: form.branch_id,
    });

    const extra = resp.managed_branch_id ? ` (manages branch #${resp.managed_branch_id})` : '';
    success.value = `Created ${resp.user.role}: ${resp.user.email}${extra}`;

    form.name = '';
    form.email = '';
    form.password = '';
    form.role = 'SALES';
  } catch (e: any) {
    error.value = e?.message || 'Failed to create user';
  } finally {
    loading.value = false;
  }
}

onMounted(loadBranches);
</script>
