<template>
  <section class="splash-shell">
    <div class="splash-hero">
      <div class="splash-copy">
        <img class="splash-logo" src="https://www.pmsoffshore.com/assets/images/global/logo.svg" alt="PMS Offshore">
        <p class="splash-kicker">Petroleum Marine Services</p>
        <h1>Secure Internal Drive for Offshore Operations</h1>
        <p>
          Centralize technical documents, operation files, and project records in one protected
          workspace with strict role-based access control.
        </p>
        <div class="splash-cta-row">
          <RouterLink class="btn-primary" to="/login">Employee Login</RouterLink>
        </div>
      </div>
      <div class="splash-card">
        <h2>Built for PMS Teams</h2>
        <ul>
          <li>Project folders by department and vessel scope</li>
          <li>Controlled file sharing inside one company tenant</li>
          <li>Audit-ready activity history and permission checks</li>
        </ul>
      </div>
    </div>

    <div class="splash-metrics">
      <article>
        <strong>40+</strong>
        <span>Years Experience</span>
      </article>
      <article>
        <strong>798+</strong>
        <span>Completed Projects</span>
      </article>
      <article>
        <strong>9,994+</strong>
        <span>Tons Platform Installation</span>
      </article>
      <article>
        <strong>3,994+</strong>
        <span>KMs Subsea Pipeline & Cable Laying</span>
      </article>
    </div>
  </section>
</template>

<script setup>
import { RouterLink } from 'vue-router';
</script>
