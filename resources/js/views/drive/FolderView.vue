<template>
  <section class="panel">
    <h1>Folder {{ id }}</h1>
    <p>Child folders are loaded from the API.</p>
    <ul class="list">
      <li v-for="folder in folders.children" :key="folder.id">{{ folder.name }}</li>
    </ul>
  </section>
</template>

<script setup>
import { onMounted } from 'vue';
import { useFoldersStore } from '../../stores/folders';

const props = defineProps({ id: { type: String, required: true } });
const folders = useFoldersStore();

onMounted(async () => {
  await folders.loadChildren(props.id);
});
</script>
