<template>
  <section class="desktop-drive panel">
    <header class="desktop-drive__header">
      <h1>Drive</h1>
      <p>Desktop-style folders and files view.</p>
    </header>

    <div class="desktop-area">
      <article
        v-for="folder in quickFolders"
        :key="folder.id"
        class="desktop-item desktop-item-folder"
      >
        <div class="desktop-icon" aria-hidden="true">📁</div>
        <strong>{{ folder.name }}</strong>
        <span>{{ folder.items }} items</span>
      </article>

      <article
        v-for="file in recentFiles"
        :key="file.id"
        class="desktop-item desktop-item-file"
      >
        <div class="desktop-icon" aria-hidden="true">📄</div>
        <strong>{{ file.original_name || file.name }}</strong>
        <span>{{ formatBytes(file.size_bytes || 0) }}</span>
      </article>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted } from 'vue';
import { useFilesStore } from '../../stores/files';

const files = useFilesStore();

const quickFolders = computed(() => [
  { id: 'projects', name: 'Projects', items: Math.max(3, Math.ceil(files.items.length / 6)) },
  { id: 'engineering', name: 'Engineering', items: Math.max(2, Math.ceil(files.items.length / 8)) },
  { id: 'finance', name: 'Finance', items: Math.max(1, Math.ceil(files.items.length / 10)) },
  { id: 'shared', name: 'Shared Team', items: Math.max(2, Math.ceil(files.items.length / 7)) },
]);

const recentFiles = computed(() => files.items.slice(0, 12));

onMounted(async () => {
  if (!files.items.length) {
    await files.search();
  }
});

const formatBytes = (bytes) => {
  const value = Number(bytes || 0);

  if (value < 1024) {
    return `${value} B`;
  }

  if (value < 1024 ** 2) {
    return `${(value / 1024).toFixed(1)} KB`;
  }

  if (value < 1024 ** 3) {
    return `${(value / 1024 ** 2).toFixed(2)} MB`;
  }

  return `${(value / 1024 ** 3).toFixed(2)} GB`;
};
</script>
