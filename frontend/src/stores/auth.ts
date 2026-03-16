import { defineStore } from 'pinia';
import type { User } from '../types/models';
import { authService } from '../modules/auth/auth.service';

const STORAGE_KEY = 'ims_auth_user_v1';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    user: null as User | null,
    hydrated: false,
  }),
  getters: {
    isAuthenticated: (s) => Boolean(s.user?.id),
  },
  actions: {
    hydrate() {
      if (this.hydrated) return;
      this.hydrated = true;
      try {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) this.user = JSON.parse(raw) as User;
      } catch {
        this.user = null;
      }
    },
    async login(email: string, password: string) {
      const user = await authService.login(email, password);
      this.user = user;
      localStorage.setItem(STORAGE_KEY, JSON.stringify(user));
    },
    async logout() {
      try {
        await authService.logout();
      } finally {
        this.user = null;
        localStorage.removeItem(STORAGE_KEY);
      }
    },
  },
});
