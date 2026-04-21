<template>
  <div class="shell" :class="showPrivateShell ? 'app-shell' : 'public-shell'">
    <header v-if="showPrivateShell" class="drive-navbar">
      <div class="drive-navbar__brand">
        <div class="drive-navbar__mark">
          <img class="brand-logo brand-logo--nav" src="https://www.pmsoffshore.com/assets/images/global/logo.svg" alt="PMS Offshore">
        </div>
        <div class="drive-navbar__brand-text">
          <strong>PMS Offshore</strong>
          <small>Petroleum Marine Services</small>
          <span>Offshore Documents, Controlled and Secure</span>
        </div>
      </div>

      <nav class="drive-navbar__menu">
        <RouterLink to="/drive">Dashboard</RouterLink>
        <RouterLink to="/drive/explorer">Drive</RouterLink>
        <RouterLink to="/shared">Shared</RouterLink>
        <RouterLink to="/trash">Trash</RouterLink>
        <RouterLink v-if="canAccessAdmin" to="/administration/dashboard">Administration</RouterLink>
      </nav>

      <div class="drive-navbar__actions">
        <button class="notice-btn" type="button" @click="isNoticeOpen = !isNoticeOpen">
          <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M12 2a6 6 0 0 0-6 6v3.6c0 .9-.3 1.8-.9 2.5L3.8 16a1 1 0 0 0 .8 1.6h14.8a1 1 0 0 0 .8-1.6l-1.3-1.9a4.4 4.4 0 0 1-.9-2.5V8a6 6 0 0 0-6-6Zm0 20a3 3 0 0 0 2.8-2H9.2A3 3 0 0 0 12 22Z" fill="currentColor"/>
          </svg>
          <span class="notice-dot" v-if="notificationCount > 0">{{ notificationCount }}</span>
        </button>

        <aside v-if="isNoticeOpen" class="notice-panel">
          <h4>Shared With You</h4>
          <ul>
            <li v-for="item in notifications" :key="item.id">
              <strong>{{ item.title }}</strong>
              <span>{{ item.meta }}</span>
            </li>
          </ul>
        </aside>

        <div class="user-pill">
          <span class="user-pill__name">{{ displayName }}</span>
          <button class="user-pill__logout" type="button" @click="signOut">Logout</button>
        </div>
      </div>
    </header>

    <main class="content content-wide">
      <RouterView />
    </main>

    <footer class="drive-footer">
      <div>
        <strong>Petroleum Marine Services</strong>
        <p>Offshore Construction and Marine Services Digital Workspace.</p>
      </div>
      <div>
        <p>Alexandria, Egypt</p>
        <p>www.pmsoffshore.com</p>
      </div>
      <div>
        <p>Tel: +20 (3) 487 8351</p>
        <p>Email: info@pmsoffshore.com</p>
      </div>
    </footer>
  </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { useAuthStore } from './stores/auth';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const isNoticeOpen = ref(false);

const notifications = ref([
  { id: 1, title: 'Operations-Q2.pdf', meta: 'Shared by Marine Ops Team' },
  { id: 2, title: 'Safety-Checklist.xlsx', meta: 'Shared by HSE Department' },
  { id: 3, title: 'Tender-Revision.zip', meta: 'Shared by Procurement' },
]);

const showPrivateShell = computed(() => Boolean(route.meta.requiresAuth));
const displayName = computed(() => auth.user?.name || 'PMS User');
const notificationCount = computed(() => notifications.value.length);
const canAccessAdmin = computed(() => {
  if (!auth.user) {
    return false;
  }

  if (auth.user?.capabilities?.admin_access !== undefined) {
    return Boolean(auth.user.capabilities.admin_access);
  }

  return (auth.user.roles || []).some((role) => ['super_admin', 'manager'].includes(role.name));
});

onMounted(async () => {
  if (auth.isAuthenticated && !auth.user) {
    await auth.fetchMe();
  }
});

const signOut = async () => {
  await auth.logout();
  await router.push('/');
};
</script>
