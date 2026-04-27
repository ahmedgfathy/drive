<template>
  <section class="public-share-shell">
    <div class="panel public-share-card">
      <p class="dashboard-kicker">External Share</p>
      <h1>{{ title }}</h1>
      <p class="public-share-meta">
        Shared by {{ grantedBy }}<span v-if="share?.expires_at"> • Expires {{ formatDate(share.expires_at) }}</span>
      </p>

      <form v-if="requiresPassword && !passwordAccepted" class="stack" @submit.prevent="unlockShare">
        <label>
          Share password
          <input v-model="password" type="text" placeholder="Enter the share password">
        </label>
        <p v-if="errorMessage" class="login-error">{{ errorMessage }}</p>
        <button type="submit">Open Share</button>
      </form>

      <div v-else class="stack">
        <p v-if="item?.type === 'file'">This shared file can be opened without signing in.</p>

        <div v-if="item?.type === 'file'" class="menu-row">
          <button type="button" @click="downloadRootFile">Download File</button>
        </div>

        <div v-else>
          <div class="public-share-path">
            <strong>{{ item?.path_cache || '/' }}</strong>
          </div>

          <div class="desktop-area public-share-grid">
            <article
              v-if="canGoUp"
              class="desktop-item desktop-item-folder"
              @click="openFolder(parentFolderId)"
            >
              <div class="desktop-icon" aria-hidden="true">..</div>
              <strong>Parent</strong>
              <span>Go up</span>
            </article>

            <article
              v-for="folder in children"
              :key="`folder-${folder.id}`"
              class="desktop-item desktop-item-folder"
              @click="openFolder(folder.id)"
            >
              <div class="desktop-icon" aria-hidden="true">Folder</div>
              <strong>{{ folder.name }}</strong>
              <span>{{ folder.files_count || 0 }} items</span>
            </article>

            <article
              v-for="file in files"
              :key="`file-${file.id}`"
              class="desktop-item desktop-item-file"
            >
              <div class="desktop-icon" aria-hidden="true">File</div>
              <strong>{{ file.original_name }}</strong>
              <span>{{ formatBytes(file.size_bytes || 0) }}</span>
              <button type="button" class="btn-ghost public-share-download" @click="downloadFile(file.id)">
                Download
              </button>
            </article>
          </div>
        </div>

        <p v-if="errorMessage" class="login-error">{{ errorMessage }}</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import sharesService from '../../services/shares.service';

const props = defineProps({
  token: { type: String, required: true },
});

const share = ref(null);
const item = ref(null);
const children = ref([]);
const files = ref([]);
const password = ref('');
const errorMessage = ref('');
const requiresPassword = ref(false);
const passwordAccepted = ref(false);
const rootPath = ref('');
const history = ref([]);

const title = computed(() => item.value?.name || 'Shared item');
const grantedBy = computed(() => share.value?.granted_by?.full_name || share.value?.granted_by?.name || 'PMS Drive');
const canGoUp = computed(() => history.value.length > 1);
const parentFolderId = computed(() => history.value.at(-2) || null);

onMounted(async () => {
  await loadShare();
});

const loadShare = async () => {
  try {
    const response = await sharesService.publicShow(props.token, password.value ? { password: password.value } : {});
    applyPayload(response.data, true);
    passwordAccepted.value = true;
    requiresPassword.value = Boolean(response.data?.share?.requires_password);
  } catch (error) {
    if (error?.response?.status === 423) {
      requiresPassword.value = true;
      errorMessage.value = error?.response?.data?.message || 'Password required.';
      return;
    }

    errorMessage.value = error?.response?.data?.message || 'Unable to open this share.';
  }
};

const unlockShare = async () => {
  errorMessage.value = '';
  await loadShare();
};

const applyPayload = (payload, resetHistory = false) => {
  share.value = payload.share;
  item.value = payload.item;
  children.value = payload.children ?? [];
  files.value = payload.files ?? [];

  if (resetHistory) {
    rootPath.value = payload.item?.path_cache || '';
    history.value = payload.item?.id ? [payload.item.id] : [];
  }
};

const openFolder = async (folderId) => {
  if (!folderId) {
    return;
  }

  const response = await sharesService.publicFolder(props.token, folderId, password.value ? { password: password.value } : {});
  applyPayload(response.data, false);

  const nextHistory = [...history.value];
  const existingIndex = nextHistory.indexOf(folderId);

  if (existingIndex >= 0) {
    history.value = nextHistory.slice(0, existingIndex + 1);
    return;
  }

  history.value = [...nextHistory, folderId];
};

const downloadBlob = (blob, filename) => {
  const blobUrl = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = blobUrl;
  link.download = filename;
  document.body.appendChild(link);
  link.click();
  link.remove();
  URL.revokeObjectURL(blobUrl);
};

const downloadRootFile = async () => {
  const response = await sharesService.publicDownload(props.token, null, password.value ? { password: password.value } : {});
  downloadBlob(response.data, item.value?.name || 'shared-file');
};

const downloadFile = async (fileId) => {
  const target = files.value.find((entry) => entry.id === fileId);
  const response = await sharesService.publicDownload(props.token, fileId, password.value ? { password: password.value } : {});
  downloadBlob(response.data, target?.original_name || 'shared-file');
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

const formatDate = (value) => new Date(value).toLocaleString();
</script>
