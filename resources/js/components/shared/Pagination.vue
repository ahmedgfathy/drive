<template>
  <div class="menu-row">
    <button @click="$emit('prev')">Prev</button>
    <button @click="$emit('next')">Next</button>
  </div>
</template>

<script setup>
defineEmits(['prev', 'next']);
</script>
