<template>
  <section class="admin-grid">
    <article class="panel admin-module-header">
      <p class="dashboard-kicker">Administration - Sharing</p>
      <h1>Sharing Control</h1>
      <p>Configure how users can share internal and external links.</p>
    </article>

    <article class="panel admin-form-card">
      <form class="stack" @submit.prevent="save">
        <label><input type="checkbox" v-model="form.internal_sharing_enabled"> Internal sharing enabled</label>
        <label><input type="checkbox" v-model="form.allow_external_links"> Allow external links</label>
        <label><input type="checkbox" v-model="form.require_password_for_external_links"> Require password for external links</label>
        <label>
          Default link expiry (days)
          <input type="number" min="1" max="365" v-model.number="form.default_link_expiry_days">
        </label>
        <label>
          Max share duration (days)
          <input type="number" min="1" max="3650" v-model.number="form.max_share_duration_days">
        </label>
        <button type="submit">Save Sharing Policy</button>
      </form>
    </article>
  </section>
</template>

<script setup>
import { onMounted, reactive } from 'vue';
import api from '../../services/api';

const form = reactive({
  internal_sharing_enabled: true,
  allow_external_links: false,
  require_password_for_external_links: true,
  default_link_expiry_days: 7,
  max_share_duration_days: 30,
});

onMounted(async () => {
  const response = await api.get('/admin/sharing-policy');
  Object.assign(form, response.data);
});

const save = async () => {
  const response = await api.put('/admin/sharing-policy', form);
  Object.assign(form, response.data);
};
</script>
