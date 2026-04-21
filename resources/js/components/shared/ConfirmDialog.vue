<template>
  <div class="panel">
    <p>{{ message }}</p>
    <div class="menu-row">
      <button @click="$emit('confirm')">Confirm</button>
      <button @click="$emit('cancel')">Cancel</button>
    </div>
  </div>
</template>

<script setup>
defineProps({ message: { type: String, default: 'Are you sure?' } });
defineEmits(['confirm', 'cancel']);
</script>
