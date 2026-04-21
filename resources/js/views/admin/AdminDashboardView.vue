<template>
  <section class="admin-grid">
    <article class="panel admin-module-header">
      <p class="dashboard-kicker">Admin Dashboard</p>
      <h1>Overview and Monitoring</h1>
      <p>Central overview of users, files, storage, sharing, and security events.</p>
    </article>

    <section class="admin-kpi-grid">
      <article class="panel stat-card admin-kpi-card" v-for="tile in tiles" :key="tile.label">
        <h3>{{ tile.label }}</h3>
        <strong>{{ tile.value }}</strong>
        <span>{{ tile.note }}</span>
      </article>
    </section>

    <article class="panel admin-full admin-list-card">
      <h3>Recent Activity Stream</h3>
      <ul class="list admin-activity-list">
        <li v-for="row in activities" :key="row.id">
          <span>{{ row.action }}</span>
          <small>{{ row.actor?.name || 'System' }} | {{ row.created_at }}</small>
        </li>
      </ul>
    </article>
  </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import api from '../../services/api';

const stats = ref({});
const activities = ref([]);

const tiles = computed(() => [
  { label: 'Total Users', value: stats.value.total_users ?? 0, note: 'Registered accounts' },
  { label: 'Active Users', value: stats.value.active_users ?? 0, note: 'Enabled users' },
  { label: 'Total Files', value: stats.value.total_files ?? 0, note: 'Uploaded files' },
  { label: 'Shared Files', value: stats.value.shared_files ?? 0, note: 'Files currently shared' },
  { label: 'Storage Used', value: formatBytes(stats.value.storage_used_bytes ?? 0), note: 'Allocated storage consumption' },
  { label: 'Storage Left', value: formatBytes(stats.value.storage_left_bytes ?? 0), note: 'Remaining allocated quota' },
  { label: 'Failed Logins (24h)', value: stats.value.failed_logins_last_24h ?? 0, note: 'Security indicator' },
]);

onMounted(async () => {
  const response = await api.get('/admin/overview');
  stats.value = response.data.stats ?? {};
  activities.value = response.data.recent_activities ?? [];
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
