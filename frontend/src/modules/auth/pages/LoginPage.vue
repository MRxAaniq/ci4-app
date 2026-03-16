<template>
  <div style="min-height: 100vh; display: grid; place-items: center; padding: 18px;">
    <div class="card" style="width: min(440px, 100%);">
      <div class="row" style="margin-bottom: 10px;">
        <div>
          <div class="h1">Sign in</div>
          <div class="muted" style="margin-top: 4px; font-size: 13px;">Use your IMS credentials</div>
        </div>
      </div>

      <div v-if="error" class="error">{{ error }}</div>

      <form class="form" style="margin-top: 12px;" @submit.prevent="onSubmit">
        <div class="field">
          <label>Email</label>
          <input v-model.trim="email" type="email" autocomplete="username" placeholder="admin@example.com" />
        </div>

        <div class="field">
          <label>Password</label>
          <input v-model="password" type="password" autocomplete="current-password" placeholder="••••••••" />
        </div>

        <button class="btn primary" type="submit" :disabled="loading" style="width: 100%;">
          {{ loading ? 'Signing in…' : 'Login' }}
        </button>

        <div class="muted" style="font-size: 12px;">
          Backend runs on <span class="badge">/api</span> via Vite proxy.
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAuthStore } from '../../../stores/auth';

const auth = useAuthStore();
const router = useRouter();
const route = useRoute();

const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

async function onSubmit() {
  error.value = '';
  loading.value = true;
  try {
    await auth.login(email.value, password.value);
    const next = (route.query.next as string | undefined) || '/app/inventory';
    router.push(next);
  } catch (e: any) {
    error.value = e?.message || 'Login failed';
  } finally {
    loading.value = false;
  }
}
</script>
