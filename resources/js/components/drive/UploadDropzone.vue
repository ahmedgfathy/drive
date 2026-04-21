<template>
  <label class="dropzone">
    <span>Drop file here or click to upload</span>
    <input type="file" class="hidden" @change="onChange">
  </label>
</template>

<script setup>
const emit = defineEmits(['change']);

const onChange = (event) => {
  emit('change', event.target.files?.[0] ?? null);
};
</script>
