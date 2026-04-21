<template>
  <section class="admin-shell">
    <header class="panel admin-shell__head admin-hero-card">
      <div>
        <p class="dashboard-kicker">Administration</p>
        <h1>Administration Control Center</h1>
        <p>Manage users, permissions, storage governance, security policies, and system configuration from one interface.</p>
      </div>
      <div class="admin-hero-card__chips">
        <span>Policy Driven</span>
        <span>Audit Ready</span>
        <span>Role Secured</span>
      </div>
    </header>

    <nav class="panel admin-tabs">
      <RouterLink to="/administration/dashboard"><span>Overview</span></RouterLink>
      <RouterLink to="/administration/users"><span>Users</span></RouterLink>
      <RouterLink to="/administration/roles"><span>Roles</span></RouterLink>
      <RouterLink to="/administration/storage"><span>Storage</span></RouterLink>
      <RouterLink to="/administration/audit"><span>Audit</span></RouterLink>
      <RouterLink to="/administration/sharing"><span>Sharing</span></RouterLink>
      <RouterLink to="/administration/security"><span>Security</span></RouterLink>
      <RouterLink to="/administration/system"><span>System</span></RouterLink>
    </nav>

    <RouterView />
  </section>
</template>

<script setup>
import { RouterLink, RouterView } from 'vue-router';
</script>
