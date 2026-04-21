<template>
  <section class="admin-grid">
    <article class="panel admin-module-header">
      <p class="dashboard-kicker">Administration - Users</p>
      <h1>User Management</h1>
      <p>Create users, assign roles, and control account activation.</p>
    </article>

    <section class="admin-two-col">
      <article class="panel admin-form-card">
        <h3>Create New User</h3>
        <form class="stack" @submit.prevent="createUser">
          <label>Name <input v-model="form.name" type="text" required></label>
          <label>Email <input v-model="form.email" type="email" required></label>
          <label>Password <input v-model="form.password" type="password" required></label>
          <label>
            Role
            <select v-model="form.role">
              <option value="viewer">viewer</option>
              <option value="employee">employee</option>
              <option value="manager">manager</option>
              <option value="super_admin">super_admin</option>
            </select>
          </label>
          <label>Quota (bytes) <input v-model.number="form.quota_bytes" type="number" min="0"></label>
          <button type="submit">Create User</button>
        </form>
      </article>

      <article class="panel admin-list-card">
        <h3>Users</h3>
        <ul class="list admin-user-list">
          <li v-for="user in users" :key="user.id">
            <div>
              <span>{{ user.name }}</span>
              <small>{{ user.email }} | {{ user.roles?.[0]?.name || 'no-role' }}</small>
            </div>
            <div class="menu-row">
              <small class="admin-status" :class="user.is_active ? 'is-active' : 'is-inactive'">{{ user.is_active ? 'active' : 'inactive' }}</small>
              <button type="button" class="btn-ghost" v-if="!user.is_active" @click="activate(user.id)">Activate</button>
              <button type="button" class="btn-ghost" v-else @click="deactivate(user.id)">Deactivate</button>
            </div>
          </li>
        </ul>
      </article>
    </section>
  </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue';
import api from '../../services/api';

const users = ref([]);
const form = reactive({
  name: '',
  email: '',
  password: '',
  role: 'employee',
  quota_bytes: 0,
});

const load = async () => {
  const response = await api.get('/users');
  users.value = response.data.data ?? [];
};

onMounted(load);

const createUser = async () => {
  await api.post('/users', form);
  form.name = '';
  form.email = '';
  form.password = '';
  form.role = 'employee';
  form.quota_bytes = 0;
  await load();
};

const activate = async (userId) => {
  await api.patch(`/users/${userId}/activate`);
  await load();
};

const deactivate = async (userId) => {
  await api.patch(`/users/${userId}/deactivate`);
  await load();
};
</script>
