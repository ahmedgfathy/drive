<template>
  <section class="admin-grid">
    <article class="panel admin-module-header">
      <p class="dashboard-kicker">Administration - Storage</p>
      <h1>Storage and Quotas</h1>
      <p>Monitor usage and update user quotas centrally.</p>
    </article>

    <article class="panel admin-list-card">
      <ul class="list admin-storage-list">
        <li v-for="row in rows" :key="row.id">
          <div>
            <span>{{ row.user?.email }}</span>
            <small>Used {{ formatBytes(row.used_bytes) }} / Quota {{ formatBytes(row.quota_bytes) }}</small>
          </div>
          <div class="menu-row">
            <input type="number" min="0" v-model.number="quotaDraft[row.user_id]" placeholder="new quota bytes">
            <button type="button" class="btn-ghost" @click="updateQuota(row.user_id)">Update Quota</button>
          </div>
        </li>
      </ul>
    </article>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../services/api';

const rows = ref([]);
const quotaDraft = ref({});

const load = async () => {
  const response = await api.get('/storage/usage');
  rows.value = response.data.data ?? [];

  rows.value.forEach((row) => {
    quotaDraft.value[row.user_id] = row.quota_bytes;
  });
};

onMounted(load);

const updateQuota = async (userId) => {
  const quota = Number(quotaDraft.value[userId] ?? 0);
  await api.patch(`/storage/quotas/${userId}`, { quota_bytes: quota });
  await load();
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
