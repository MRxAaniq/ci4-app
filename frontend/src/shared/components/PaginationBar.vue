<template>
  <div class="row" style="margin-top: 12px;">
    <div class="muted" style="font-size: 12px;">
      Page {{ pagination.page }} / {{ Math.max(1, pagination.total_pages) }} · {{ pagination.total }} total
    </div>

    <div style="display: flex; gap: 8px;">
      <button class="btn" :disabled="disabled || pagination.page <= 1" @click="$emit('change', pagination.page - 1)">
        Prev
      </button>
      <button class="btn" :disabled="disabled || pagination.page >= pagination.total_pages" @click="$emit('change', pagination.page + 1)">
        Next
      </button>
    </div>
  </div>
</template>

<script setup lang="ts">
import type { PaginationMeta } from '../../types/pagination';

defineProps<{ pagination: PaginationMeta; disabled?: boolean }>();

defineEmits<{ (e: 'change', page: number): void }>();
</script>
