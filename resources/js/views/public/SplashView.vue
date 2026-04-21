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
            <RouterLink class="btn-primary" to="/register">New Registration</RouterLink>
          </div>
        </div>

        <aside class="splash-card">
          <h2>Built for PMS Teams</h2>
          <p class="splash-card-intro">Premier Egyptian offshore construction and services contractor.</p>
          <ul>
            <li>Established as an EGPC company in 2001 with 40+ years cumulative experience.</li>
            <li>Core business lines include offshore construction and offshore services.</li>
            <li>Execution track record exceeds 800 completed projects across regional fields.</li>
            <li>Delivered over 9,999 tons of platform installation scope.</li>
            <li>Installed more than 3,999 km of subsea pipelines and cable laying works.</li>
          </ul>
        </aside>
      </div>

      <div class="splash-metrics" aria-label="PMS highlights">
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
