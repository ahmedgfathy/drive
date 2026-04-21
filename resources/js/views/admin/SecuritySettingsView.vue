<template>
  <section class="admin-grid">
    <article class="panel admin-module-header">
      <p class="dashboard-kicker">Administration - Security</p>
      <h1>Security Settings</h1>
      <p>Manage password policy, lockout thresholds, and user sessions.</p>
    </article>

    <article class="panel admin-form-card">
      <form class="stack" @submit.prevent="savePolicy">
        <label>Password min length <input type="number" min="8" max="64" v-model.number="policy.password_min_length"></label>
        <label><input type="checkbox" v-model="policy.password_requires_uppercase"> Require uppercase</label>
        <label><input type="checkbox" v-model="policy.password_requires_number"> Require number</label>
        <label><input type="checkbox" v-model="policy.password_requires_symbol"> Require symbol</label>
        <label>Max failed logins <input type="number" min="3" max="20" v-model.number="policy.max_failed_logins"></label>
        <label>Lockout minutes <input type="number" min="1" max="1440" v-model.number="policy.lockout_minutes"></label>
        <label>Session timeout minutes <input type="number" min="5" max="1440" v-model.number="policy.session_timeout_minutes"></label>
        <label><input type="checkbox" v-model="policy.enforce_2fa_for_admins"> Enforce 2FA for admins</label>
        <button type="submit">Save Security Policy</button>
      </form>
    </article>

    <article class="panel admin-list-card">
      <h3>Active User Sessions</h3>
      <ul class="list admin-session-list">
        <li v-for="user in sessions" :key="user.id">
          <div>
            <span>{{ user.name }}</span>
            <small>{{ user.email }} | {{ user.tokens_count }} tokens</small>
          </div>
          <button type="button" class="btn-ghost" @click="revoke(user.id)">Revoke Sessions</button>
        </li>
      </ul>
    </article>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../services/api';

const policy = reactive({
  password_min_length: 8,
  password_requires_uppercase: true,
  password_requires_number: true,
  password_requires_symbol: true,
  max_failed_logins: 5,
  lockout_minutes: 15,
  session_timeout_minutes: 120,
  enforce_2fa_for_admins: false,
});

const sessions = ref([]);

const load = async () => {
  const [policyResponse, sessionsResponse] = await Promise.all([
    api.get('/admin/security-policy'),
    api.get('/admin/sessions'),
  ]);

  Object.assign(policy, policyResponse.data);
  sessions.value = sessionsResponse.data;
};

onMounted(load);

const savePolicy = async () => {
  const response = await api.put('/admin/security-policy', policy);
  Object.assign(policy, response.data);
};

const revoke = async (userId) => {
  await api.post(`/admin/sessions/${userId}/revoke`);
  await load();
};
</script>
