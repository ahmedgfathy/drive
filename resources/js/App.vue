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
        <button v-if="showInstallShortcut && !isInstallPromptVisible" class="install-chip" type="button" @click="refreshVisibility">
          Install App
        </button>

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

    <div v-if="isInstallPromptVisible" class="pwa-prompt-backdrop">
      <section class="pwa-prompt-card panel">
        <div class="pwa-prompt-art">
          <img class="pwa-prompt-icon" :src="pwaIconUrl" alt="PMS Drive app icon">
          <div>
            <p class="pwa-kicker">Install PMS Drive</p>
            <h3>Put PMS Drive on {{ platformLabel }}</h3>
          </div>
        </div>

        <p class="pwa-copy">{{ instructions }}</p>

        <div class="pwa-bullets">
          <span>Offline shell ready</span>
          <span>Desktop launch icon</span>
          <span>Mobile home screen access</span>
        </div>

        <div class="pwa-actions">
          <button type="button" class="btn-primary" @click="installApp">
            Install App
          </button>
        </div>
      </section>
    </div>

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
import { computed, onMounted, ref, watch } from 'vue';
import { RouterLink, RouterView, useRoute, useRouter } from 'vue-router';
import { usePwaInstall } from './composables/usePwaInstall';
import { useAuthStore } from './stores/auth';
import sharesService from './services/shares.service';

const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const isNoticeOpen = ref(false);
const notifications = ref([]);
const {
  install,
  instructions,
  isInstallPromptVisible,
  isInstalled,
  platformHint,
  refreshVisibility,
} = usePwaInstall();

const showPrivateShell = computed(() => Boolean(route.meta.requiresAuth));
const displayName = computed(() => auth.user?.name || 'PMS User');
const notificationCount = computed(() => notifications.value.length);
const pwaIconUrl = `${import.meta.env.BASE_URL}pwa/icon-192.png`;
const showInstallShortcut = computed(() => !isInstalled.value);
const platformLabel = computed(() => {
  if (platformHint.value === 'android') {
    return 'Android';
  }

  if (platformHint.value === 'ios') {
    return 'iPhone';
  }

  return 'your device';
});
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

  if (auth.isAuthenticated) {
    await loadNotifications();
  }
});

watch(
  () => auth.user?.id,
  async (userId) => {
    if (userId) {
      await loadNotifications();
      return;
    }

    notifications.value = [];
  },
  { immediate: false }
);

const signOut = async () => {
  await auth.logout();
  await router.push('/');
};

const installApp = async () => {
  await install();
};

const loadNotifications = async () => {
  try {
    const response = await sharesService.list({ per_page: 10 });
    const items = response.data.data ?? [];
    notifications.value = items.map((item) => ({
      id: item.id,
      title: sharedTitle(item),
      meta: sharedMeta(item),
    }));
  } catch {
    notifications.value = [];
  }
};

const sharedTitle = (item) => {
  if (item.shareable_type?.includes('File')) {
    return item.shareable?.original_name || 'Shared file';
  }

  if (item.shareable_type?.includes('Folder')) {
    return item.shareable?.name || 'Shared folder';
  }

  return 'Shared item';
};

const sharedMeta = (item) => {
  const owner = item.granted_by?.full_name
    || item.granted_by?.name
    || item.grantedBy?.full_name
    || item.grantedBy?.name
    || 'PMS Drive';

  return `Shared by ${owner}`;
};
</script>
