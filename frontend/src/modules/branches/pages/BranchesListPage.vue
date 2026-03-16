<template>
  <div class="row" style="margin-bottom: 14px;">
    <div>
      <div class="h1">Branches</div>
      <div class="muted" style="font-size: 13px; margin-top: 4px;">Search and manage branches</div>
    </div>

    <div style="display: flex; gap: 10px;">
      <RouterLink class="btn primary" to="/app/branches/new">New Branch</RouterLink>
      <button class="btn" :disabled="loading" @click="load(1)">Refresh</button>
    </div>
  </div>

  <div v-if="error" class="error" style="margin-bottom: 12px;">{{ error }}</div>

  <div class="card">
    <SearchBar v-model="q" placeholder="Search by name or address" :disabled="loading" @search="onSearch" @clear="onClear" />

    <table class="table" style="margin-top: 12px;">
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
        <tr v-for="b in items" :key="b.id">
          <td class="muted">{{ b.id }}</td>
          <td>{{ b.name }}</td>
          <td class="muted">{{ b.address }}</td>
          <td><span class="badge" :class="b.status === 'ACTIVE' ? 'ok' : 'danger'">{{ b.status }}</span></td>
          <td>
            <div style="display: flex; gap: 8px;">
              <RouterLink class="btn" :to="`/app/branches/${b.id}/edit`">Edit</RouterLink>
              <button v-if="canDelete" class="btn danger" :disabled="loading" @click="onDelete(b.id, b.name)">Delete</button>
            </div>
          </td>
        </tr>
        <tr v-if="!loading && items.length === 0">
          <td colspan="5" class="muted">No branches found.</td>
        </tr>
      </tbody>
    </table>

    <PaginationBar v-if="pagination" :pagination="pagination" :disabled="loading" @change="onPage" />
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { useAuthStore } from '../../../stores/auth';
import type { Branch } from '../../../types/models';
import type { PaginationMeta } from '../../../types/pagination';
import SearchBar from '../../../shared/components/SearchBar.vue';
import PaginationBar from '../../../shared/components/PaginationBar.vue';
import { branchesController } from '../branches.controller';

const auth = useAuthStore();
const canDelete = computed(() => auth.user?.role === 'ADMIN' || auth.user?.role === 'SUPER_ADMIN');

const items = ref<Branch[]>([]);
const pagination = ref<PaginationMeta | null>(null);

const q = ref('');
const loading = ref(false);
const error = ref('');

async function load(page = 1) {
  error.value = '';
  loading.value = true;
  try {
    const res = await branchesController.list({ page, per_page: 25, q: q.value.trim() || undefined });
    items.value = res.items;
    pagination.value = res.pagination || null;
  } catch (e: any) {
    error.value = e?.message || 'Failed to load branches';
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

async function onDelete(id: number, name: string) {
  if (!confirm(`Delete branch "${name}"?`)) return;
  error.value = '';
  loading.value = true;
  try {
    await branchesController.remove(id);
    await load(pagination.value?.page || 1);
  } catch (e: any) {
    error.value = e?.message || 'Failed to delete branch';
  } finally {
    loading.value = false;
  }
}

onMounted(() => load(1));
</script>
