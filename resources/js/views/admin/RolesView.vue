<template>
  <section class="admin-grid">
    <article class="panel admin-module-header">
      <p class="dashboard-kicker">Administration - Roles</p>
      <h1>Roles and Permissions</h1>
      <p>Manage permission matrix and keep admin access controlled.</p>
    </article>

    <article class="panel admin-form-card role-create-card">
      <h3>Create New Role</h3>
      <form class="stack" @submit.prevent="createRole">
        <label>
          Role Name
          <input v-model="createForm.name" type="text" placeholder="project_manager" required>
        </label>
        <small>Use lowercase letters, numbers, and underscores only.</small>
        <p v-if="createError" class="admin-form-error">{{ createError }}</p>
        <button type="submit" :disabled="isCreating">{{ isCreating ? 'Creating...' : 'Create Role' }}</button>
      </form>
    </article>

    <article class="panel rbac-form-panel">
      <div class="rbac-form-tabs" role="tablist" aria-label="RBAC sections">
        <button
          v-for="section in permissionSections"
          :key="section.key"
          type="button"
          class="rbac-form-tab"
          :class="activeSection === section.key ? 'is-active' : ''"
          @click="activeSection = section.key"
        >
          {{ section.label }}
        </button>
      </div>
      <p class="rbac-form-note">
        Controlling section: <strong>{{ activeSectionLabel }}</strong>
      </p>
    </article>

    <section class="admin-grid role-grid">
      <article class="panel role-card" v-for="role in roles" :key="role.id">
        <div class="role-card__head">
          <h3>{{ role.name }}</h3>
          <div class="role-card__head-tools">
            <label class="role-bulk-toggle">
              <input
                type="checkbox"
                :checked="hasAllPermissions(role, filteredPermissions)"
                :disabled="filteredPermissions.length === 0"
                @change="toggleAllPermissions(role, $event.target.checked)"
              >
              <span>All in section</span>
            </label>
            <small>{{ role.permissions?.length || 0 }} permissions</small>
          </div>
        </div>
        <div class="permission-grid">
          <label v-for="permission in filteredPermissions" :key="`${role.id}-${permission.id}`" class="permission-item">
            <input
              type="checkbox"
              :checked="hasPermission(role, permission.name)"
              @change="togglePermission(role, permission.name, $event.target.checked)"
            >
            <span>{{ permission.name }}</span>
          </label>
          <p v-if="filteredPermissions.length === 0" class="admin-note">No permissions in this section yet.</p>
        </div>
      </article>
    </section>
  </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import api from '../../services/api';

const roles = ref([]);
const permissions = ref([]);
const activeSection = ref('all');
const isCreating = ref(false);
const createError = ref('');
const createForm = reactive({
  name: '',
});

const permissionSections = [
  { key: 'all', label: 'All Sections' },
  { key: 'admin', label: 'Admin' },
  { key: 'users', label: 'Users' },
  { key: 'roles', label: 'Roles' },
  { key: 'folders', label: 'Folders' },
  { key: 'files', label: 'Files' },
  { key: 'shares', label: 'Shares' },
  { key: 'storage', label: 'Storage' },
  { key: 'audit', label: 'Audit' },
  { key: 'security', label: 'Security' },
  { key: 'system', label: 'System' },
  { key: 'backups', label: 'Backups' },
];

const permissionSectionKey = (permissionName) => permissionName.split('.')[0] || 'other';

const filteredPermissions = computed(() => {
  if (activeSection.value === 'all') {
    return permissions.value;
  }

  return permissions.value.filter((permission) => permissionSectionKey(permission.name) === activeSection.value);
});

const activeSectionLabel = computed(() => {
  const current = permissionSections.find((section) => section.key === activeSection.value);
  return current?.label || 'All Sections';
});

const load = async () => {
  const response = await api.get('/admin/roles-permissions');
  roles.value = response.data.roles ?? [];
  permissions.value = response.data.permissions ?? [];
};

onMounted(load);

const createRole = async () => {
  createError.value = '';
  const normalizedName = createForm.name.trim().toLowerCase().replace(/\s+/g, '_');

  if (!normalizedName) {
    createError.value = 'Role name is required.';
    return;
  }

  isCreating.value = true;
  try {
    await api.post('/admin/roles', { name: normalizedName });
    createForm.name = '';
    await load();
  } catch (error) {
    createError.value = error?.response?.data?.message || 'Unable to create role.';
  } finally {
    isCreating.value = false;
  }
};

const hasPermission = (role, permissionName) => {
  return (role.permissions || []).some((permission) => permission.name === permissionName);
};

const togglePermission = async (role, permissionName, isEnabled) => {
  const next = new Set((role.permissions || []).map((permission) => permission.name));

  if (isEnabled) {
    next.add(permissionName);
  } else {
    next.delete(permissionName);
  }

  await api.put(`/admin/roles/${role.id}/permissions`, {
    permissions: Array.from(next),
  });

  await load();
};

const hasAllPermissions = (role, scopedPermissions) => {
  if (!scopedPermissions.length) {
    return false;
  }

  const assigned = new Set((role.permissions || []).map((permission) => permission.name));
  return scopedPermissions.every((permission) => assigned.has(permission.name));
};

const toggleAllPermissions = async (role, isEnabled) => {
  if (!filteredPermissions.value.length) {
    return;
  }

  const next = new Set((role.permissions || []).map((permission) => permission.name));
  const scopedNames = filteredPermissions.value.map((permission) => permission.name);

  if (isEnabled) {
    scopedNames.forEach((name) => next.add(name));
  } else {
    scopedNames.forEach((name) => next.delete(name));
  }

  await api.put(`/admin/roles/${role.id}/permissions`, {
    permissions: Array.from(next),
  });

  await load();
};
</script>
