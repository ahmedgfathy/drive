import { createRouter, createWebHistory } from 'vue-router';
import LoginView from '../views/auth/LoginView.vue';
import RegisterView from '../views/auth/RegisterView.vue';
import SplashView from '../views/public/SplashView.vue';
import DriveHomeView from '../views/drive/DriveHomeView.vue';
import DriveExplorerView from '../views/drive/DriveExplorerView.vue';
import FolderView from '../views/drive/FolderView.vue';
import TrashView from '../views/drive/TrashView.vue';
import SharedWithMeView from '../views/drive/SharedWithMeView.vue';
import UsersView from '../views/admin/UsersView.vue';
import RolesView from '../views/admin/RolesView.vue';
import StorageUsageView from '../views/admin/StorageUsageView.vue';
import AuditView from '../views/admin/AuditView.vue';
import AdminDashboardView from '../views/admin/AdminDashboardView.vue';
import SharingControlView from '../views/admin/SharingControlView.vue';
import SecuritySettingsView from '../views/admin/SecuritySettingsView.vue';
import SystemConfigView from '../views/admin/SystemConfigView.vue';
import AdministrationView from '../views/admin/AdministrationView.vue';
import { useAuthStore } from '../stores/auth';

const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'splash', component: SplashView, meta: { publicOnly: true } },
    { path: '/login', name: 'login', component: LoginView, meta: { publicOnly: true } },
    { path: '/register', name: 'register', component: RegisterView, meta: { publicOnly: true } },
    { path: '/drive', name: 'drive.home', component: DriveHomeView, meta: { requiresAuth: true } },
    { path: '/drive/explorer', name: 'drive.explorer', component: DriveExplorerView, meta: { requiresAuth: true } },
    { path: '/folders/:id', name: 'drive.folder', component: FolderView, props: true, meta: { requiresAuth: true } },
    { path: '/trash', name: 'drive.trash', component: TrashView, meta: { requiresAuth: true } },
    { path: '/shared', name: 'drive.shared', component: SharedWithMeView, meta: { requiresAuth: true } },
    {
      path: '/administration',
      component: AdministrationView,
      meta: { requiresAuth: true, requiresAdmin: true },
      children: [
        { path: '', redirect: '/administration/dashboard' },
        { path: 'dashboard', name: 'admin.dashboard', component: AdminDashboardView, meta: { requiresAuth: true } },
        { path: 'users', name: 'admin.users', component: UsersView, meta: { requiresAuth: true } },
        { path: 'roles', name: 'admin.roles', component: RolesView, meta: { requiresAuth: true } },
        { path: 'storage', name: 'admin.storage', component: StorageUsageView, meta: { requiresAuth: true } },
        { path: 'audit', name: 'admin.audit', component: AuditView, meta: { requiresAuth: true } },
        { path: 'sharing', name: 'admin.sharing', component: SharingControlView, meta: { requiresAuth: true } },
        { path: 'security', name: 'admin.security', component: SecuritySettingsView, meta: { requiresAuth: true } },
        { path: 'system', name: 'admin.system', component: SystemConfigView, meta: { requiresAuth: true } },
      ],
    },
    { path: '/admin', redirect: '/administration/dashboard' },
    { path: '/admin/dashboard', redirect: '/administration/dashboard' },
    { path: '/admin/users', redirect: '/administration/users' },
    { path: '/admin/roles', redirect: '/administration/roles' },
    { path: '/admin/storage', redirect: '/administration/storage' },
    { path: '/admin/audit', redirect: '/administration/audit' },
    { path: '/admin/sharing', redirect: '/administration/sharing' },
    { path: '/admin/security', redirect: '/administration/security' },
    { path: '/admin/system', redirect: '/administration/system' },
  ],
});

router.beforeEach(async (to) => {
  const auth = useAuthStore();
  const hasToken = Boolean(auth.token || localStorage.getItem('token'));

  if (to.meta.requiresAuth && !hasToken) {
    return { name: 'login' };
  }

  if (hasToken && !auth.user) {
    await auth.fetchMe();
  }

  if (to.meta.requiresAdmin) {
    const adminCapability = auth.user?.capabilities?.admin_access;
    const hasAdminRole = (auth.user?.roles || []).some((role) => ['super_admin', 'manager'].includes(role.name));
    const canAccessAdmin = adminCapability !== undefined ? Boolean(adminCapability) : hasAdminRole;

    if (!canAccessAdmin) {
      return { name: 'drive.home' };
    }
  }

  if (to.meta.publicOnly && hasToken) {
    return { name: 'drive.home' };
  }

  return true;
});

router.afterEach((to) => {
  const titleMap = {
    splash: 'PMS Drive | Welcome',
    login: 'PMS Drive | Login',
    register: 'PMS Drive | Register',
    'drive.home': 'PMS Drive | Dashboard',
    'drive.explorer': 'PMS Drive | Drive',
    'drive.folder': 'PMS Drive | Folder',
    'drive.trash': 'PMS Drive | Trash',
    'drive.shared': 'PMS Drive | Shared',
    'admin.dashboard': 'PMS Drive | Administration',
    'admin.users': 'PMS Drive | Administration | Users',
    'admin.roles': 'PMS Drive | Administration | Roles',
    'admin.storage': 'PMS Drive | Administration | Storage',
    'admin.audit': 'PMS Drive | Administration | Audit',
    'admin.sharing': 'PMS Drive | Administration | Sharing',
    'admin.security': 'PMS Drive | Administration | Security',
    'admin.system': 'PMS Drive | Administration | System',
  };

  document.title = titleMap[to.name] || 'PMS Drive';
});

export default router;
