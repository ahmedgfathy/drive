<template>
  <section class="admin-shell">
    <header class="panel admin-shell__head admin-hero-card">
      <div>
        <p class="dashboard-kicker">Administration</p>
        <h1>Administration Control Center</h1>
        <p>Manage users, permissions, storage governance, security policies, and system configuration from one interface.</p>
      </div>
    </header>

    <nav class="panel admin-tabs">
      <RouterLink to="/administration/dashboard"><span>Overview</span></RouterLink>
      <RouterLink to="/administration/users"><span>Users</span></RouterLink>
      <RouterLink to="/administration/roles" class="admin-tab--role"><span>Roles</span></RouterLink>
      <RouterLink to="/administration/storage"><span>Storage</span></RouterLink>
      <RouterLink to="/administration/audit" class="admin-tab--audit"><span>Audit</span></RouterLink>
      <RouterLink to="/administration/sharing"><span>Sharing</span></RouterLink>
      <RouterLink to="/administration/security" class="admin-tab--policy"><span>Security</span></RouterLink>
      <RouterLink to="/administration/system"><span>System</span></RouterLink>
    </nav>

    <RouterView />
  </section>
</template>

<script setup>
import { RouterLink, RouterView } from 'vue-router';
</script>
