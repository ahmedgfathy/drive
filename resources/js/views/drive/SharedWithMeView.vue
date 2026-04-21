<template>
  <section class="panel">
    <h1>Shared With Me</h1>
    <ul class="list">
      <li v-for="share in shares" :key="share.id">{{ share.shareable_type }} #{{ share.shareable_id }} ({{ share.permission }})</li>
    </ul>
  </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import sharesService from '../../services/shares.service';

const shares = ref([]);

onMounted(async () => {
  const response = await sharesService.mine();
  shares.value = response.data.data ?? [];
});
</script>
