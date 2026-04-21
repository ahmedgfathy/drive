import { defineStore } from 'pinia';

export const useUiStore = defineStore('ui', {
  state: () => ({
    breadcrumbs: [],
    viewMode: 'grid',
  }),
  actions: {
    setBreadcrumbs(breadcrumbs) {
      this.breadcrumbs = breadcrumbs;
    },
    setViewMode(mode) {
      this.viewMode = mode;
    },
  },
});
