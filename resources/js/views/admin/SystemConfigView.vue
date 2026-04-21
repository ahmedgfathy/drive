<template>
  <section class="admin-grid">
    <article class="panel admin-module-header">
      <p class="dashboard-kicker">Administration - System</p>
      <h1>System Configuration</h1>
      <p>Control branding, company contact details, and maintenance mode.</p>
    </article>

    <section class="admin-two-col">
      <article class="panel admin-form-card">
        <h3>Platform Identity</h3>
        <form class="stack" @submit.prevent="saveSettings">
          <label>Company name <input v-model="settings.company_name" type="text"></label>
          <label>Company website <input v-model="settings.company_website" type="url"></label>
          <label>Support email <input v-model="settings.support_email" type="email"></label>
          <label>Support phone <input v-model="settings.support_phone" type="text"></label>
          <label>Footer address <input v-model="settings.footer_address" type="text"></label>
          <label><input type="checkbox" v-model="settings.maintenance_mode"> Maintenance mode</label>
          <label><input type="checkbox" v-model="settings.read_only_mode"> Read-only mode</label>
          <button type="submit">Save System Settings</button>
        </form>
      </article>

      <article class="panel admin-form-card">
        <h3>Backup and Recovery</h3>
        <form class="stack" @submit.prevent="saveBackup">
          <label><input type="checkbox" v-model="backup.enabled"> Backup enabled</label>
          <label>Database backup frequency <input v-model="backup.database_frequency" type="text"></label>
          <label>Files backup frequency <input v-model="backup.files_frequency" type="text"></label>
          <label>Retention period <input v-model="backup.retention_period" type="text"></label>
          <button type="submit">Save Backup Config</button>
          <button type="button" class="btn-ghost" @click="runBackup">Run Backup Now</button>
        </form>
        <p class="admin-note">Last status: {{ backup.last_backup_status }} | Last run: {{ backup.last_backup_at || 'never' }}</p>
      </article>
    </section>
  </section>
</template>

<script setup>
import { onMounted, reactive } from 'vue';
import api from '../../services/api';

const settings = reactive({
  company_name: 'Petroleum Marine Services',
  company_website: 'https://www.pmsoffshore.com',
  support_email: '',
  support_phone: '',
  footer_address: '',
  maintenance_mode: false,
  read_only_mode: false,
});

const backup = reactive({
  enabled: false,
  database_frequency: 'daily',
  files_frequency: 'daily',
  retention_period: '30 days',
  last_backup_status: 'never',
  last_backup_at: null,
});

const load = async () => {
  const [settingsResponse, backupResponse] = await Promise.all([
    api.get('/admin/system-settings'),
    api.get('/admin/backup-config'),
  ]);

  Object.assign(settings, settingsResponse.data);
  Object.assign(backup, backupResponse.data);
};

onMounted(load);

const saveSettings = async () => {
  const response = await api.put('/admin/system-settings', settings);
  Object.assign(settings, response.data);
};

const saveBackup = async () => {
  const response = await api.put('/admin/backup-config', backup);
  Object.assign(backup, response.data);
};

const runBackup = async () => {
  const response = await api.post('/admin/backup-run');
  Object.assign(backup, response.data.backup || backup);
};
</script>
