<template>
  <div class="row" style="gap: 10px;">
    <div class="search" style="width: 100%;">
      <input :value="modelValue" :placeholder="placeholder" @input="$emit('update:modelValue', ($event.target as HTMLInputElement).value)" @keydown.enter.prevent="$emit('search')" />
    </div>
    <button class="btn" :disabled="disabled" @click="$emit('search')">Search</button>
    <button v-if="showClear" class="btn" :disabled="disabled" @click="$emit('clear')">Clear</button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = defineProps<{ modelValue: string; placeholder?: string; disabled?: boolean }>();

defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'search'): void;
  (e: 'clear'): void;
}>();

const showClear = computed(() => props.modelValue.trim().length > 0);
</script>
