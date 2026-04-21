<template>
  <section class="admin-grid">
    <article class="panel admin-module-header">
      <p class="dashboard-kicker">Administration - Audit</p>
      <h1>Audit Logs</h1>
      <p>Review system activity and export operational logs.</p>
    </article>

    <article class="panel admin-list-card">
      <div class="menu-row admin-toolbar">
        <input v-model="actionFilter" type="text" placeholder="Filter by action">
        <button type="button" @click="load">Apply Filter</button>
        <button type="button" class="btn-ghost" @click="exportCsv">Export CSV</button>
      </div>

      <ul class="list admin-audit-list">
        <li v-for="item in logs" :key="item.id">
          <span>{{ item.action }}</span>
          <small>{{ item.actor?.email || 'system' }}</small>
          <small>{{ item.created_at }}</small>
        </li>
      </ul>
    </article>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../services/api';

const logs = ref([]);
const actionFilter = ref('');

const load = async () => {
  const response = await api.get('/audit-logs');
  const rows = response.data.data ?? [];

  if (!actionFilter.value) {
    logs.value = rows;
    return;
  }

  const term = actionFilter.value.toLowerCase();
  logs.value = rows.filter((row) => String(row.action || '').toLowerCase().includes(term));
};

onMounted(load);

const exportCsv = () => {
  const lines = ['id,action,actor,created_at'];

  logs.value.forEach((row) => {
    const actor = row.actor?.email || 'system';
    lines.push(`${row.id},${row.action},${actor},${row.created_at}`);
  });

  const blob = new Blob([lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const link = document.createElement('a');
  link.href = url;
  link.download = 'audit-logs.csv';
  link.click();
  URL.revokeObjectURL(url);
};
</script>
