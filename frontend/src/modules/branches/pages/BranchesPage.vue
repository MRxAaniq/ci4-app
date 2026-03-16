<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Branches</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Create and update branches</div>
    </div>
    <button class="btn" :disabled="loading" @click="load">Refresh</button>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="grid2">
    <div class="card">
      <div class="h2" style="margin-bottom: 10px;">Create Branch</div>

      <div v-if="!isAdmin" class="muted" style="font-size: 13px;">
        Only Admin can create/update branches.
      </div>

      <form v-else class="form" @submit.prevent="onCreate">
        <div class="field">
          <label>Name</label>
          <input v-model.trim="create.name" placeholder="Main Branch" />
        </div>
        <div class="field">
          <label>Address</label>
          <input v-model.trim="create.address" placeholder="City, Street…" />
        </div>
        <div class="field">
          <label>Manager ID (optional)</label>
          <input v-model.trim="create.managerId" placeholder="e.g. 2" />
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
        <div class="h2">Branch List</div>
        <div class="muted" style="font-size: 12px;">{{ branches.length }}</div>
      </div>

      <table class="table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Address</th>
            <th>Status</th>
            <th></th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="b in branches" :key="b.id">
            <td class="muted">{{ b.id }}</td>
            <td>{{ b.name }}</td>
            <td class="muted">{{ b.address }}</td>
            <td><span class="badge" :class="b.status === 'ACTIVE' ? 'ok' : 'danger'">{{ b.status }}</span></td>
            <td>
              <button v-if="isAdmin" class="btn" @click="startEdit(b)">Edit</button>
            </td>
          </tr>
          <tr v-if="!loading && branches.length === 0">
            <td colspan="5" class="muted">No branches.</td>
          </tr>
        </tbody>
      </table>

      <div v-if="isAdmin && editing" style="margin-top: 12px;" class="card">
        <div class="row" style="margin-bottom: 10px;">
          <div style="font-weight: 600;">Edit Branch #{{ editing.id }}</div>
          <button class="btn danger" @click="cancelEdit">Cancel</button>
        </div>

        <form class="form" @submit.prevent="onUpdate">
          <div class="field">
            <label>Name</label>
            <input v-model.trim="edit.name" />
          </div>
          <div class="field">
            <label>Address</label>
            <input v-model.trim="edit.address" />
          </div>
          <div class="field">
            <label>Manager ID (optional)</label>
            <input v-model.trim="edit.managerId" />
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
import type { Branch } from '../../../types/models';
import { branchesController } from '../branches.controller';

const auth = useAuthStore();
const isAdmin = computed(() => auth.user?.role === 'ADMIN' || auth.user?.role === 'SUPER_ADMIN');

const branches = ref<Branch[]>([]);
const loading = ref(false);
const error = ref('');

const create = reactive({
  name: '',
  address: '',
  managerId: '',
  status: 'ACTIVE' as Branch['status'],
});

const editing = ref<Branch | null>(null);
const edit = reactive({
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
    const res = await branchesController.list({ page: 1, per_page: 100 });
    branches.value = res.items;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load branches';
  } finally {
    loading.value = false;
  }
}

async function onCreate() {
  error.value = '';
  loading.value = true;
  try {
    await branchesController.create({
      name: create.name,
      address: create.address,
      manager_id: parseManagerId(create.managerId),
      status: create.status,
    });
    create.name = '';
    create.address = '';
    create.managerId = '';
    create.status = 'ACTIVE';
    await load();
  } catch (e: any) {
    error.value = e?.message || 'Failed to create branch';
  } finally {
    loading.value = false;
  }
}

function startEdit(b: Branch) {
  editing.value = b;
  edit.name = b.name;
  edit.address = b.address;
  edit.managerId = b.manager_id ? String(b.manager_id) : '';
  edit.status = b.status;
}

function cancelEdit() {
  editing.value = null;
}

async function onUpdate() {
  if (!editing.value) return;
  error.value = '';
  loading.value = true;
  try {
    await branchesController.update(editing.value.id, {
      name: edit.name,
      address: edit.address,
      manager_id: parseManagerId(edit.managerId),
      status: edit.status,
    });
    editing.value = null;
    await load();
  } catch (e: any) {
    error.value = e?.message || 'Failed to update branch';
  } finally {
    loading.value = false;
  }
}

onMounted(load);
</script>
