import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const isStandaloneMode = () => window.matchMedia('(display-mode: standalone)').matches
    || window.navigator.standalone === true
    || document.referrer.startsWith('android-app://');

export function usePwaInstall() {
  const deferredPrompt = ref(null);
  const isInstalled = ref(false);
  const isInstallPromptVisible = ref(false);
  const platformHint = ref('desktop');
  const installStatus = ref('');

  const canTriggerNativePrompt = computed(() => Boolean(deferredPrompt.value));
  const instructions = computed(() => {
    if (installStatus.value) {
      return installStatus.value;
    }

    if (platformHint.value === 'android') {
      return 'Tap Install App to add PMS Drive to your Android home screen. If your browser does not show the install prompt, open the browser menu and choose Install app.';
    }

    if (platformHint.value === 'ios') {
      return 'Tap Install App to continue. On iPhone or iPad, the browser may open the share sheet first, then choose Add to Home Screen.';
    }

    return 'Tap Install App to install PMS Drive on this computer or mobile device for a full-screen workspace and faster launch.';
  });

  const detectPlatform = () => {
    const agent = navigator.userAgent.toLowerCase();

    if (/iphone|ipad|ipod/.test(agent)) {
      platformHint.value = 'ios';
      return;
    }

    if (/android/.test(agent)) {
      platformHint.value = 'android';
      return;
    }

    platformHint.value = 'desktop';
  };

  const refreshVisibility = () => {
    isInstalled.value = isStandaloneMode();
    isInstallPromptVisible.value = !isInstalled.value;

    if (isInstalled.value) {
      installStatus.value = '';
    }
  };

  const onBeforeInstallPrompt = (event) => {
    event.preventDefault();
    deferredPrompt.value = event;
    refreshVisibility();
  };

  const onInstalled = () => {
    deferredPrompt.value = null;
    isInstalled.value = true;
    isInstallPromptVisible.value = false;
  };

  onMounted(() => {
    detectPlatform();
    refreshVisibility();
    window.addEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.addEventListener('appinstalled', onInstalled);
  });

  onBeforeUnmount(() => {
    window.removeEventListener('beforeinstallprompt', onBeforeInstallPrompt);
    window.removeEventListener('appinstalled', onInstalled);
  });

  const install = async () => {
    installStatus.value = '';

    if (!deferredPrompt.value) {
      if (platformHint.value === 'ios' && typeof navigator.share === 'function') {
        try {
          await navigator.share({
            title: 'PMS Drive',
            text: 'After the share sheet opens, choose Add to Home Screen to install PMS Drive.',
            url: window.location.href,
          });
          installStatus.value = 'When the share menu opens, tap Add to Home Screen to finish installing PMS Drive on your iPhone or iPad.';
          refreshVisibility();
          return false;
        } catch {
          installStatus.value = 'To install on iPhone or iPad, open the share menu in Safari and choose Add to Home Screen.';
          refreshVisibility();
          return false;
        }
      }

      if (platformHint.value === 'android') {
        installStatus.value = 'If your Android browser does not show the install prompt, open the browser menu and tap Install app or Add to Home screen.';
        refreshVisibility();
        return false;
      }

      installStatus.value = 'If your desktop browser does not show the install prompt, use the browser address bar or menu and choose Install PMS Drive.';
      refreshVisibility();
      return false;
    }

    await deferredPrompt.value.prompt();
    const result = await deferredPrompt.value.userChoice;

    if (result?.outcome === 'accepted') {
      onInstalled();
      return true;
    }

    refreshVisibility();
    return false;
  };

  const dismissForSession = () => {
    isInstallPromptVisible.value = false;
  };

  return {
    install,
    instructions,
    isInstallPromptVisible,
    isInstalled,
    installStatus,
    platformHint,
    refreshVisibility,
  };
}
