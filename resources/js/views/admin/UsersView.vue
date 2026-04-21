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
          <label>Full Name <input v-model="form.full_name" type="text" required></label>
          <label>Employee ID <input v-model="form.employee_id" type="text" required></label>
          <label>Mobile <input v-model="form.mobile" type="text" required></label>
          <label>Ext ID <input v-model="form.ext_id" type="text" required></label>
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
          <label>
            Quota
            <div class="menu-row">
              <input v-model.number="form.quota_value" type="number" min="1" step="1" placeholder="10">
              <select v-model="form.quota_unit">
                <option value="MB">MB</option>
                <option value="GB">GB</option>
              </select>
            </div>
          </label>
          <button type="submit">Create User</button>
        </form>
      </article>

      <article class="panel admin-list-card">
        <h3>Users</h3>
        <ul class="list admin-user-list">
          <li v-for="user in users" :key="user.id">
            <div>
              <span>{{ user.full_name || user.name }}</span>
              <small>{{ user.employee_id || 'no-employee-id' }} | {{ user.mobile || 'no-mobile' }} | {{ user.ext_id || 'no-ext-id' }} | {{ user.email }} | {{ user.roles?.[0]?.name || 'no-role' }}</small>

              <form v-if="editingUserId === user.id" class="stack" @submit.prevent="saveEdit(user.id)">
                <label>Full Name <input v-model="editForm.full_name" type="text" required></label>
                <label>Employee ID <input v-model="editForm.employee_id" type="text" required></label>
                <label>Mobile <input v-model="editForm.mobile" type="text" required></label>
                <label>Ext ID <input v-model="editForm.ext_id" type="text" required></label>
                <label>Email <input v-model="editForm.email" type="email" required></label>
                <label>Password <input v-model="editForm.password" type="password" placeholder="Leave empty to keep current password"></label>
                <label>
                  Role
                  <select v-model="editForm.role">
                    <option value="viewer">viewer</option>
                    <option value="employee">employee</option>
                    <option value="manager">manager</option>
                    <option value="super_admin">super_admin</option>
                  </select>
                </label>
                <label>
                  Status
                  <select v-model="editForm.is_active">
                    <option :value="true">active</option>
                    <option :value="false">inactive</option>
                  </select>
                </label>
                <label>
                  Quota
                  <div class="menu-row">
                    <input v-model.number="editForm.quota_value" type="number" min="1" step="1" placeholder="10">
                    <select v-model="editForm.quota_unit">
                      <option value="MB">MB</option>
                      <option value="GB">GB</option>
                    </select>
                  </div>
                </label>
                <div class="menu-row">
                  <button type="submit" class="admin-action-btn">
                    <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M20 6L9 17l-5-5" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                    <span>Save</span>
                  </button>
                  <button type="button" class="btn-ghost" @click="cancelEdit">Cancel</button>
                </div>
              </form>
            </div>
            <div class="menu-row">
              <small class="admin-status" :class="user.is_active ? 'is-active' : 'is-inactive'">{{ user.is_active ? 'active' : 'inactive' }}</small>
              <button type="button" class="admin-action-btn" @click="startEdit(user)">
                <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M4 20h4l10-10-4-4L4 16v4z" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span>Edit</span>
              </button>
              <button type="button" class="admin-action-btn" v-if="!user.is_active" @click="activate(user.id)">
                <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M12 2v20M2 12h20" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span>Activate</span>
              </button>
              <button type="button" class="admin-action-btn" v-else @click="deactivate(user.id)">
                <svg viewBox="0 0 24 24" aria-hidden="true" width="14" height="14"><path d="M3 12h18" fill="none" stroke="currentColor" stroke-width="2"/></svg>
                <span>Deactivate</span>
              </button>
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
const editingUserId = ref(null);
const form = reactive({
  full_name: '',
  employee_id: '',
  mobile: '',
  ext_id: '',
  email: '',
  password: '',
  role: 'employee',
  quota_value: 10,
  quota_unit: 'GB',
});
const editForm = reactive({
  full_name: '',
  employee_id: '',
  mobile: '',
  ext_id: '',
  email: '',
  password: '',
  role: 'employee',
  is_active: true,
  quota_value: 10,
  quota_unit: 'GB',
});

const load = async () => {
  const response = await api.get('/users');
  users.value = response.data.data ?? [];
};

onMounted(load);

const createUser = async () => {
  const payload = {
    full_name: form.full_name,
    employee_id: form.employee_id,
    mobile: form.mobile,
    ext_id: form.ext_id,
    email: form.email,
    password: form.password,
    role: form.role,
    quota_bytes: toBytes(form.quota_value, form.quota_unit),
  };

  await api.post('/users', payload);
  form.full_name = '';
  form.employee_id = '';
  form.mobile = '';
  form.ext_id = '';
  form.email = '';
  form.password = '';
  form.role = 'employee';
  form.quota_value = 10;
  form.quota_unit = 'GB';
  await load();
};

const toBytes = (value, unit) => {
  const numericValue = Number(value || 0);
  const base = unit === 'GB' ? 1024 ** 3 : 1024 ** 2;

  return Math.max(1, Math.floor(numericValue * base));
};

const activate = async (userId) => {
  await api.patch(`/users/${userId}/activate`);
  await load();
};

const deactivate = async (userId) => {
  await api.patch(`/users/${userId}/deactivate`);
  await load();
};

const startEdit = (user) => {
  editingUserId.value = user.id;
  editForm.full_name = user.full_name || user.name || '';
  editForm.employee_id = user.employee_id || '';
  editForm.mobile = user.mobile || '';
  editForm.ext_id = user.ext_id || '';
  editForm.email = user.email || '';
  editForm.password = '';
  editForm.role = user.roles?.[0]?.name || 'employee';
  editForm.is_active = Boolean(user.is_active);

  const quotaBytes = Number(user.storage_quota?.quota_bytes || 0);
  const asGb = quotaBytes / (1024 ** 3);
  editForm.quota_value = asGb >= 1 ? Number(asGb.toFixed(2)) : Number((quotaBytes / (1024 ** 2)).toFixed(2));
  editForm.quota_unit = asGb >= 1 ? 'GB' : 'MB';
};

const cancelEdit = () => {
  editingUserId.value = null;
};

const saveEdit = async (userId) => {
  await api.patch(`/users/${userId}`, {
    full_name: editForm.full_name,
    employee_id: editForm.employee_id,
    mobile: editForm.mobile,
    ext_id: editForm.ext_id,
    email: editForm.email,
    password: editForm.password || undefined,
    role: editForm.role,
    is_active: editForm.is_active,
    quota_bytes: toBytes(editForm.quota_value, editForm.quota_unit),
  });

  editingUserId.value = null;
  await load();
};
</script>
