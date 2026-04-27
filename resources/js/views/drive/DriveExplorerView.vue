<template>
  <section class="desktop-drive panel drive-explorer">
    <header class="desktop-drive__header drive-explorer__head">
      <div class="drive-explorer__meta">
        <h1>Drive Explorer</h1>
        <p>{{ currentFolder?.path_cache || '/' }}</p>
      </div>
      <div class="drive-explorer__controls">
        <div class="drive-explorer__primary-row">
          <div class="menu-row explorer-actions">
            <button type="button" class="admin-action-btn" @click="triggerFilesUpload">Upload Files</button>
            <button type="button" class="admin-action-btn" @click="triggerFolderUpload">Upload Folder</button>
            <button type="button" class="admin-action-btn" @click="selectAllItems">Select All</button>
            <button type="button" class="admin-action-btn" :disabled="!hasSelection" @click="clearAllSelection">Clear Selection</button>
            <button type="button" class="admin-action-btn" :disabled="!hasSelection || !canShare" @click="openShareDialog">Share</button>
            <button type="button" class="admin-action-btn" :disabled="!canRename" @click="beginRename">Rename</button>
            <button type="button" class="admin-action-btn" :disabled="!canMove" @click="moveSelected">Move</button>
            <button type="button" class="admin-action-btn" :disabled="!hasSelection" @click="deleteSelected">Delete</button>
            <button type="button" class="admin-action-btn" :disabled="!hasSelection" @click="downloadSelected">Download</button>
          </div>

          <div class="menu-row explorer-move-tools">
            <select v-model="moveTargetId">
              <option :value="null">Move target folder...</option>
              <option v-for="folder in folderTreeOptions" :key="folder.id" :value="folder.id">{{ folder.path_cache }}</option>
            </select>
            <button type="button" class="btn-ghost explorer-new-folder-btn" @click="createFolder">New Folder</button>
          </div>
        </div>

        <div class="dropzone explorer-drop-panel" :class="{ 'dropzone-active': isDragOver }" @dragover.prevent="onDragOver" @dragleave.prevent="onDragLeave" @drop.prevent="onDrop">
          <strong>Drop files or folders into current folder</strong>
          <span>Folder structure is preserved.</span>
        </div>
      </div>
      <input ref="filesInput" class="hidden" type="file" multiple @change="onFilesSelected">
      <input ref="folderInput" class="hidden" type="file" webkitdirectory directory multiple @change="onFilesSelected">
    </header>

    <div v-if="isRenaming" class="panel">
      <form class="menu-row" @submit.prevent="confirmRename">
        <input v-model="renameValue" type="text" placeholder="New name" required>
        <button type="submit">Save</button>
        <button type="button" class="btn-ghost" @click="cancelRename">Cancel</button>
      </form>
    </div>

    <div class="desktop-area">
      <article class="desktop-item desktop-item-folder" v-if="currentFolder?.parent_id" @click="openParentFolder">
        <div class="desktop-icon" aria-hidden="true">↩</div>
        <strong>..</strong>
        <span>Go up</span>
      </article>

      <article
        v-for="folder in folders"
        :key="`folder-${folder.id}`"
        :class="['desktop-item', 'desktop-item-folder', { 'is-selected': selectedFolderIds.includes(folder.id) }]"
        @click="openFolder(folder.id)"
      >
        <label class="desktop-check" @click.stop><input type="checkbox" :value="folder.id" v-model="selectedFolderIds"></label>
        <div class="desktop-icon" aria-hidden="true">📁</div>
        <strong>{{ folder.name }}</strong>
        <span>{{ folder.files_count || 0 }} items</span>
      </article>

      <article
        v-for="file in files"
        :key="`file-${file.id}`"
        :class="['desktop-item', 'desktop-item-file', { 'is-selected': selectedFileIds.includes(file.id) }]"
        @click="toggleFileSelection(file.id)"
      >
        <label class="desktop-check" @click.stop><input type="checkbox" :value="file.id" v-model="selectedFileIds"></label>
        <div class="desktop-icon desktop-icon-preview" :class="`is-${(filePreviewMap[file.id] || { kind: 'file' }).kind}`" aria-hidden="true">
          <svg class="desktop-icon-preview__svg" viewBox="0 0 24 24" aria-hidden="true">
            <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" fill="currentColor" opacity="0.15"/>
            <path d="M14 3v5h5" fill="none" stroke="currentColor" stroke-width="1.8"/>
            <path d="M9 13h6M9 16h6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M7 3h7l5 5v13a1 1 0 0 1-1 1H7a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1z" fill="none" stroke="currentColor" stroke-width="1.8"/>
          </svg>
          <small>{{ (filePreviewMap[file.id] || { label: 'FILE' }).label }}</small>
        </div>
        <strong>{{ file.original_name || file.name }}</strong>
        <span>{{ formatBytes(file.size_bytes || 0) }}</span>
      </article>
    </div>

    <ShareDialog
      :open="isShareDialogOpen"
      :items="selectedItems"
      @close="isShareDialogOpen = false"
      @created="handleShareCreated"
    />
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import ShareDialog from '../../components/drive/ShareDialog.vue';
import filesService from '../../services/files.service';
import foldersService from '../../services/folders.service';
import { useAuthStore } from '../../stores/auth';

const auth = useAuthStore();
const currentFolder = ref(null);
const folders = ref([]);
const files = ref([]);
const folderTreeOptions = ref([]);
const selectedFolderIds = ref([]);
const selectedFileIds = ref([]);
const moveTargetId = ref(null);
const isRenaming = ref(false);
const renameValue = ref('');
const filesInput = ref(null);
const folderInput = ref(null);
const isDragOver = ref(false);
const isShareDialogOpen = ref(false);

const hasSelection = computed(() => selectedFolderIds.value.length > 0 || selectedFileIds.value.length > 0);
const canShare = computed(() => (auth.user?.permissions || []).includes('files.share_internal'));
const canRename = computed(() => (selectedFolderIds.value.length + selectedFileIds.value.length) === 1);
const canMove = computed(() => hasSelection.value && Boolean(moveTargetId.value));
const selectedItems = computed(() => [
  ...selectedFolderIds.value.map((id) => ({ shareable_type: 'folder', shareable_id: id })),
  ...selectedFileIds.value.map((id) => ({ shareable_type: 'file', shareable_id: id })),
]);
const filePreviewMap = computed(() => {
  const map = {};

  files.value.forEach((file) => {
    map[file.id] = getFilePreview(file);
  });

  return map;
});

onMounted(async () => {
  await loadRoot();
  await loadTree();
});

const clearSelection = () => {
  selectedFolderIds.value = [];
  selectedFileIds.value = [];
};

const loadRoot = async () => {
  const response = await foldersService.root();
  currentFolder.value = response.data.folder;
  folders.value = response.data.children ?? [];
  files.value = response.data.files ?? [];
  clearSelection();
};

const openFolder = async (folderId) => {
  const response = await foldersService.children(folderId);
  currentFolder.value = response.data.folder;
  folders.value = response.data.children ?? [];
  files.value = response.data.files ?? [];
  clearSelection();
};

const openParentFolder = async () => {
  if (!currentFolder.value?.parent_id) {
    await loadRoot();
    return;
  }

  await openFolder(currentFolder.value.parent_id);
};

const loadTree = async () => {
  const response = await foldersService.tree();
  folderTreeOptions.value = response.data ?? [];
};

const selectAllItems = () => {
  selectedFolderIds.value = folders.value.map((folder) => folder.id);
  selectedFileIds.value = files.value.map((file) => file.id);
};

const clearAllSelection = () => {
  clearSelection();
};

const openShareDialog = () => {
  if (!hasSelection.value) {
    return;
  }

  isShareDialogOpen.value = true;
};

const toggleFileSelection = (fileId) => {
  if (selectedFileIds.value.includes(fileId)) {
    selectedFileIds.value = selectedFileIds.value.filter((id) => id !== fileId);
    return;
  }

  selectedFileIds.value = [...selectedFileIds.value, fileId];
};

const createFolder = async () => {
  const name = window.prompt('Folder name');
  if (!name) {
    return;
  }

  await foldersService.create({
    name,
    parent_id: currentFolder.value?.id ?? null,
  });
  await reloadCurrent();
  await loadTree();
};

const beginRename = () => {
  if (!canRename.value) {
    return;
  }

  const isFile = selectedFileIds.value.length === 1;
  const subject = isFile
    ? files.value.find((row) => row.id === selectedFileIds.value[0])
    : folders.value.find((row) => row.id === selectedFolderIds.value[0]);

  renameValue.value = isFile ? (subject?.original_name || '') : (subject?.name || '');
  isRenaming.value = true;
};

const cancelRename = () => {
  isRenaming.value = false;
  renameValue.value = '';
};

const confirmRename = async () => {
  if (!renameValue.value.trim()) {
    return;
  }

  if (selectedFileIds.value.length === 1) {
    await filesService.update(selectedFileIds.value[0], { original_name: renameValue.value.trim() });
  } else if (selectedFolderIds.value.length === 1) {
    await foldersService.update(selectedFolderIds.value[0], { name: renameValue.value.trim() });
  }

  isRenaming.value = false;
  renameValue.value = '';
  await reloadCurrent();
  await loadTree();
};

const moveSelected = async () => {
  if (!canMove.value) {
    return;
  }

  for (const fileId of selectedFileIds.value) {
    await filesService.update(fileId, { folder_id: moveTargetId.value });
  }

  for (const folderId of selectedFolderIds.value) {
    await foldersService.update(folderId, { parent_id: moveTargetId.value });
  }

  await reloadCurrent();
  await loadTree();
};

const deleteSelected = async () => {
  for (const fileId of selectedFileIds.value) {
    await filesService.delete(fileId);
  }

  for (const folderId of selectedFolderIds.value) {
    await foldersService.delete(folderId);
  }

  await reloadCurrent();
};

const downloadSelected = async () => {
  if (!hasSelection.value) {
    return;
  }

  const response = await filesService.downloadArchive([
    ...selectedFolderIds.value.map((id) => ({ type: 'folder', id })),
    ...selectedFileIds.value.map((id) => ({ type: 'file', id })),
  ]);
  const disposition = response.headers['content-disposition'] || '';
  const match = disposition.match(/filename="?([^"]+)"?/i);
  const filename = match?.[1] || 'pms-drive-download.zip';
  const blobUrl = URL.createObjectURL(response.data);
  const link = document.createElement('a');
  link.href = blobUrl;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(blobUrl);
};

const handleShareCreated = () => {
  // Keep the current selection visible so the modal result still matches what the user shared.
};

const reloadCurrent = async () => {
  if (!currentFolder.value?.id) {
    await loadRoot();
    return;
  }

  await openFolder(currentFolder.value.id);
};

const triggerFilesUpload = () => {
  filesInput.value?.click();
};

const triggerFolderUpload = () => {
  folderInput.value?.click();
};

const onFilesSelected = async (event) => {
  const selected = Array.from(event.target?.files || []).map((file) => ({
    file,
    relativePath: file.webkitRelativePath || file.name,
  }));

  await uploadMany(selected);
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
  const dropped = await extractDroppedFiles(event);
  await uploadMany(dropped);
};

const uploadMany = async (items) => {
  if (!items.length) {
    return;
  }

  for (const item of items) {
    const payload = new FormData();
    payload.append('file', item.file);
    payload.append('relative_path', item.relativePath || item.file.name);

    if (currentFolder.value?.id) {
      payload.append('folder_id', String(currentFolder.value.id));
    }

    if (item.file.lastModified) {
      payload.append('source_modified_at', new Date(item.file.lastModified).toISOString());
    }

    try {
      await filesService.upload(payload);
    } catch {
      // Continue uploading remaining files.
    }
  }

  await reloadCurrent();
  await loadTree();
};

const extractDroppedFiles = async (event) => {
  const items = Array.from(event.dataTransfer?.items || []);
  if (!items.length) {
    return Array.from(event.dataTransfer?.files || []).map((file) => ({ file, relativePath: file.name }));
  }

  const supportsEntry = items.some((item) => typeof item.webkitGetAsEntry === 'function');
  if (!supportsEntry) {
    return Array.from(event.dataTransfer?.files || []).map((file) => ({ file, relativePath: file.name }));
  }

  const output = [];
  for (const item of items) {
    const entry = item.webkitGetAsEntry?.();
    if (!entry) {
      continue;
    }

    const filesFromEntry = await walkEntry(entry, '');
    output.push(...filesFromEntry);
  }

  return output;
};

const walkEntry = async (entry, prefix) => {
  if (entry.isFile) {
    return new Promise((resolve) => {
      entry.file((file) => resolve([{ file, relativePath: `${prefix}${file.name}` }]));
    });
  }

  if (!entry.isDirectory) {
    return [];
  }

  const reader = entry.createReader();
  const children = await readAllDirectoryEntries(reader);
  const nested = await Promise.all(children.map((child) => walkEntry(child, `${prefix}${entry.name}/`)));
  return nested.flat();
};

const readAllDirectoryEntries = async (reader) => {
  const entries = [];

  const readBatch = () => new Promise((resolve) => reader.readEntries(resolve));

  while (true) {
    const batch = await readBatch();
    if (!batch.length) {
      break;
    }
    entries.push(...batch);
  }

  return entries;
};

const getFilePreview = (file) => {
  const name = String(file.original_name || file.name || '').toLowerCase();
  const mime = String(file.mime_type || '').toLowerCase();
  const extension = String(file.extension || '').toLowerCase() || (name.includes('.') ? name.split('.').pop() : '');

  if (mime.startsWith('image/') || ['png', 'jpg', 'jpeg', 'gif', 'webp', 'bmp', 'svg', 'tif', 'tiff'].includes(extension)) {
    return { kind: 'image', label: 'IMG' };
  }

  if (extension === 'pdf' || mime.includes('pdf')) {
    return { kind: 'pdf', label: 'PDF' };
  }

  if (['doc', 'docx', 'odt'].includes(extension) || mime.includes('word')) {
    return { kind: 'word', label: 'DOC' };
  }

  if (['xls', 'xlsx', 'csv', 'ods'].includes(extension) || mime.includes('spreadsheet') || mime.includes('excel')) {
    return { kind: 'excel', label: 'XLS' };
  }

  if (['ppt', 'pptx', 'odp'].includes(extension) || mime.includes('presentation') || mime.includes('powerpoint')) {
    return { kind: 'ppt', label: 'PPT' };
  }

  if (['zip', 'rar', '7z', 'tar', 'gz', 'tgz'].includes(extension)) {
    return { kind: 'archive', label: 'ZIP' };
  }

  if (mime.startsWith('video/') || ['mp4', 'mov', 'avi', 'mkv', 'wmv', 'webm'].includes(extension)) {
    return { kind: 'video', label: 'VID' };
  }

  if (mime.startsWith('audio/') || ['mp3', 'wav', 'aac', 'm4a', 'ogg'].includes(extension)) {
    return { kind: 'audio', label: 'AUD' };
  }

  if (['txt', 'md', 'rtf', 'log', 'json', 'xml', 'yml', 'yaml'].includes(extension) || mime.startsWith('text/')) {
    return { kind: 'text', label: 'TXT' };
  }

  return { kind: 'file', label: (extension || 'FILE').slice(0, 4).toUpperCase() };
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
