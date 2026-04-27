<template>
  <section class="panel">
    <h1>Shared With Me</h1>
    <ul class="list">
      <li v-for="share in shares" :key="share.id">
        <div>
          <strong>{{ shareTitle(share) }}</strong>
          <small>{{ shareMeta(share) }}</small>
        </div>
        <RouterLink class="btn-ghost" :to="shareUrl(share)">Open</RouterLink>
      </li>
    </ul>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';
import sharesService from '../../services/shares.service';

const shares = ref([]);

onMounted(async () => {
  const response = await sharesService.mine();
  shares.value = response.data.data ?? [];
});

const shareTitle = (share) => {
  if (share.shareable_type?.includes('File')) {
    return share.shareable?.original_name || 'Shared file';
  }

  if (share.shareable_type?.includes('Folder')) {
    return share.shareable?.name || 'Shared folder';
  }

  return 'Shared item';
};

const shareMeta = (share) => {
  const sender = share.granted_by?.full_name || share.granted_by?.name || 'PMS Drive';
  return `${share.permission} access | shared by ${sender}`;
};

const shareUrl = (share) => share.share_url || '/shared';
</script>
