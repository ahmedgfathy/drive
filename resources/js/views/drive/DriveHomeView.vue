<template>
  <section class="drive-dashboard">
    <header class="dashboard-head panel">
      <div>
        <p class="dashboard-kicker">Storage Analytics</p>
        <h1>My Dashboard</h1>
        <p>Track uploaded files, used disk space, remaining storage, and sharing activity in one view.</p>
      </div>
      <div class="dashboard-actions">
        <button class="btn-primary" type="button" @click="pickFiles">Upload Files</button>
        <button class="btn-ghost" type="button" @click="pickFolder">Upload Folder</button>
      </div>
      <input ref="filesInput" class="hidden" type="file" multiple @change="onFilesSelected">
      <input ref="folderInput" class="hidden" type="file" webkitdirectory directory multiple @change="onFilesSelected">
    </header>

    <section class="dashboard-grid">
      <article class="panel stat-card">
        <h3>Total Uploaded Files</h3>
        <strong>{{ totalFiles }}</strong>
        <span>All files in your account</span>
      </article>

      <article class="panel stat-card">
        <h3>Used Disk Space</h3>
        <strong>{{ formatBytes(usedBytes) }}</strong>
        <span>{{ diskUsagePercent.toFixed(1) }}% of {{ formatBytes(totalCapacityBytes) }}</span>
      </article>

      <article class="panel stat-card">
        <h3>Left Space</h3>
        <strong>{{ formatBytes(leftBytes) }}</strong>
        <span>Available for upload</span>
      </article>

      <article class="panel stat-card">
        <h3>Shared Files</h3>
        <strong>{{ sharedFiles }}</strong>
        <span>Files shared with other users</span>
      </article>
    </section>

    <section class="dashboard-content-grid">
      <article class="panel chart-card">
        <h3>Disk Space Usage</h3>
        <div class="usage-ring" :style="{ '--usage': `${diskUsagePercent.toFixed(2)}%` }">
          <div>
            <strong>{{ diskUsagePercent.toFixed(1) }}%</strong>
            <span>Used</span>
          </div>
        </div>
      </article>

      <article class="panel chart-card">
        <h3>Uploaded File Types</h3>
        <ul class="type-chart">
          <li v-for="row in typeRows" :key="row.label">
            <div class="type-row-head">
              <span>{{ row.label }}</span>
              <strong>{{ row.count }}</strong>
            </div>
            <div class="type-bar-track">
              <div class="type-bar-fill" :style="{ width: `${row.percent}%` }"></div>
            </div>
          </li>
        </ul>
      </article>

      <article class="panel upload-drop-card">
        <h3>Quick Upload</h3>
        <p>Drag and drop files here, or use the upload buttons above.</p>
        <div class="dropzone" :class="{ 'dropzone-active': isDragOver }" @dragover.prevent="onDragOver" @dragleave.prevent="onDragLeave" @drop.prevent="onDrop">
          <strong>Drop files to upload</strong>
          <span>Supports multiple files and folders</span>
        </div>
      </article>
    </section>

    <section class="panel files-panel">
      <div class="files-panel__head">
        <h3>Browse Files</h3>
        <div class="search-inline">
          <input v-model="query" placeholder="Search by file name">
          <button type="button" @click="search">Search</button>
        </div>
      </div>
      <ul class="list">
        <li v-for="file in files.items" :key="file.id">
          <span>{{ file.original_name }}</span>
          <small>{{ formatBytes(file.size_bytes || 0) }}</small>
        </li>
      </ul>
    </section>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useFilesStore } from '../../stores/files';
import filesService from '../../services/files.service';

const files = useFilesStore();
const query = ref('');
const filesInput = ref(null);
const folderInput = ref(null);
const isDragOver = ref(false);
const totalCapacityBytes = 20 * 1024 * 1024 * 1024;

const normalizedFiles = computed(() => files.items || []);
const totalFiles = computed(() => normalizedFiles.value.length);
const usedBytes = computed(() => normalizedFiles.value.reduce((total, item) => {
  const size = Number(item.size_bytes ?? item.size ?? 0);
  return total + (Number.isFinite(size) ? size : 0);
}, 0));
const leftBytes = computed(() => Math.max(totalCapacityBytes - usedBytes.value, 0));
const diskUsagePercent = computed(() => {
  if (totalCapacityBytes <= 0) {
    return 0;
  }

  return Math.min((usedBytes.value / totalCapacityBytes) * 100, 100);
});
const sharedFiles = computed(() => normalizedFiles.value.filter((item) => {
  return Boolean(item.is_shared) || Number(item.shared_with_count ?? 0) > 0 || String(item.visibility || '').toLowerCase() === 'shared';
}).length);

const typeRows = computed(() => {
  const rows = {
    Documents: 0,
    Images: 0,
    Videos: 0,
    Archives: 0,
    Other: 0,
  };

  normalizedFiles.value.forEach((item) => {
    const name = String(item.original_name || item.name || '').toLowerCase();
    const mime = String(item.mime_type || '').toLowerCase();

    if (mime.startsWith('image/') || /\.(png|jpe?g|gif|webp|svg|bmp)$/i.test(name)) {
      rows.Images += 1;
      return;
    }

    if (mime.startsWith('video/') || /\.(mp4|mov|avi|wmv|mkv)$/i.test(name)) {
      rows.Videos += 1;
      return;
    }

    if (/\.(zip|rar|7z|tar|gz)$/i.test(name)) {
      rows.Archives += 1;
      return;
    }

    if (/\.(pdf|docx?|xlsx?|pptx?|txt|csv)$/i.test(name) || mime.includes('document') || mime.includes('spreadsheet') || mime.includes('pdf')) {
      rows.Documents += 1;
      return;
    }

    rows.Other += 1;
  });

  const maxCount = Math.max(1, ...Object.values(rows));

  return Object.entries(rows).map(([label, count]) => ({
    label,
    count,
    percent: (count / maxCount) * 100,
  }));
});

onMounted(async () => {
  await files.search();
});

const search = async () => {
  await files.search({ q: query.value });
};

const pickFiles = () => {
  filesInput.value?.click();
};

const pickFolder = () => {
  folderInput.value?.click();
};

const onFilesSelected = async (event) => {
  const selectedFiles = Array.from(event.target?.files || []);
  await uploadMany(selectedFiles);
  event.target.value = '';
};

const onDragOver = () => {
  isDragOver.value = true;
};

const onDragLeave = () => {
  isDragOver.value = false;
};

const onDrop = async (event) => {
  isDragOver.value = false;
  const droppedFiles = Array.from(event.dataTransfer?.files || []);
  await uploadMany(droppedFiles);
};

const uploadMany = async (fileList) => {
  if (!fileList.length) {
    return;
  }

  for (const file of fileList) {
    const payload = new FormData();
    payload.append('file', file);

    try {
      await filesService.upload(payload);
    } catch {
      // Continue uploading other files even if one fails.
    }
  }

  await files.search({ q: query.value });
};

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
