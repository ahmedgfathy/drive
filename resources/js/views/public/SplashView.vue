<template>
  <section class="splash-shell">
    <div class="splash-video-wrap" aria-hidden="true">
      <video
        ref="bgVideo"
        class="splash-video"
        autoplay
        muted
        playsinline
        preload="metadata"
      >
        <source :src="'/media/landing-bg.mp4'" type="video/mp4">
      </video>
      <div class="splash-video-overlay"></div>
    </div>

    <div class="splash-content">
      <div class="splash-hero">
        <div class="splash-copy">
          <div class="splash-head">
            <img class="splash-logo" src="https://www.pmsoffshore.com/assets/images/global/logo.svg" alt="PMS Offshore">
            <p class="splash-kicker">Petroleum Marine Services</p>
          </div>
          <h1>Secure Internal Drive for Offshore Operations</h1>
          <p>
            Centralize technical documents, operation files, and project records in one protected
            workspace with strict role-based access control.
          </p>
          <div class="splash-cta-row splash-actions">
            <RouterLink class="btn-primary" to="/login">Employee Login</RouterLink>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

const bgVideo = ref(null);
const LOOP_END_SECONDS = 14;

const handleLoadedMetadata = () => {
  const video = bgVideo.value;

  if (!video) {
    return;
  }

  // If the video is shorter than loop window, fallback to native full-video looping.
  if (video.duration > 0 && video.duration <= LOOP_END_SECONDS) {
    video.loop = true;
  }

  video.currentTime = 0;
  video.play().catch(() => {});
};

const handleTimeUpdate = () => {
  const video = bgVideo.value;

  if (!video || video.loop) {
    return;
  }

  if (video.currentTime >= LOOP_END_SECONDS) {
    video.currentTime = 0;
    video.play().catch(() => {});
  }
};

onMounted(() => {
  const video = bgVideo.value;

  if (!video) {
    return;
  }

  video.addEventListener('loadedmetadata', handleLoadedMetadata);
  video.addEventListener('timeupdate', handleTimeUpdate);
});

onBeforeUnmount(() => {
  const video = bgVideo.value;

  if (!video) {
    return;
  }

  video.removeEventListener('loadedmetadata', handleLoadedMetadata);
  video.removeEventListener('timeupdate', handleTimeUpdate);
});
</script>
