import { defineStore } from 'pinia';
import filesService from '../services/files.service';

export const useFilesStore = defineStore('files', {
  state: () => ({
    items: [],
    selected: [],
    loading: false,
  }),
  actions: {
    async search(params = {}) {
      this.loading = true;
      try {
        const response = await filesService.search(params);
        this.items = response.data.data ?? [];
      } finally {
        this.loading = false;
      }
    },
  },
});
