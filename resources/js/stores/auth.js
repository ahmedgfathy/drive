import { defineStore } from 'pinia';
import authService from '../services/auth.service';

export const useAuthStore = defineStore('auth', {
  state: () => ({
    token: localStorage.getItem('token'),
    user: null,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
  },
  actions: {
    async login(payload) {
      try {
        const response = await authService.login(payload);
        this.token = response.data.token;
        this.user = response.data.user;
        localStorage.setItem('token', this.token);
      } catch (error) {
        const message = error?.response?.data?.message ?? 'Unable to sign in. Please check your credentials.';
        throw new Error(message);
      }
    },
    async fetchMe() {
      try {
        const response = await authService.me();
        this.user = response.data;
      } catch {
        await this.logout();
      }
    },
    async logout() {
      if (this.token) {
        try {
          await authService.logout();
        } catch {
          // Ignore logout transport errors and always clear local auth state.
        }
      }

      this.token = null;
      this.user = null;
      localStorage.removeItem('token');
    },
  },
});
