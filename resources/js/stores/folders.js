import { defineStore } from 'pinia';
import foldersService from '../services/folders.service';

export const useFoldersStore = defineStore('folders', {
  state: () => ({
    currentFolder: null,
    children: [],
  }),
  actions: {
    async loadChildren(folderId) {
      const response = await foldersService.children(folderId);
      this.currentFolder = response.data.folder;
      this.children = response.data.children ?? [];
    },
  },
});
