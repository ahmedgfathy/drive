<template>
  <section class="admin-grid">
    <article class="panel admin-module-header">
      <p class="dashboard-kicker">Administration - Roles</p>
      <h1>Roles and Permissions</h1>
      <p>Manage permission matrix and keep admin access controlled.</p>
    </article>

    <section class="admin-grid role-grid">
      <article class="panel role-card" v-for="role in roles" :key="role.id">
        <div class="role-card__head">
          <h3>{{ role.name }}</h3>
          <small>{{ role.permissions?.length || 0 }} permissions</small>
        </div>
        <div class="permission-grid">
          <label v-for="permission in permissions" :key="`${role.id}-${permission.id}`" class="permission-item">
            <input
              type="checkbox"
              :checked="hasPermission(role, permission.name)"
              @change="togglePermission(role, permission.name, $event.target.checked)"
            >
            <span>{{ permission.name }}</span>
          </label>
        </div>
      </article>
    </section>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import api from '../../services/api';

const roles = ref([]);
const permissions = ref([]);

const load = async () => {
  const response = await api.get('/admin/roles-permissions');
  roles.value = response.data.roles ?? [];
  permissions.value = response.data.permissions ?? [];
};

onMounted(load);

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
</script>
