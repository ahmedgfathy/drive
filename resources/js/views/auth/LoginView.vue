<template>
  <section class="login-shell">
    <article class="login-panel">
      <img class="login-logo" src="https://www.pmsoffshore.com/assets/images/global/logo.svg" alt="PMS Offshore">
      <p class="login-label">Internal Access</p>
      <h1>Welcome Back</h1>
      <p class="login-subtitle">Sign in with your AD username and company password to access secure folders and project files.</p>

      <form class="stack" @submit.prevent="submit">
        <label>
          AD Username
          <input v-model="login" type="text" placeholder="Enter your AD username, e.g. 2696" required>
        </label>
        <label>
          Password
          <input v-model="password" type="password" placeholder="Enter your password" required>
        </label>
        <button type="submit">Sign In to Drive</button>
      </form>

      <p v-if="errorMessage" class="login-error">{{ errorMessage }}</p>

      <RouterLink class="login-back" to="/">Back to splash</RouterLink>
    </article>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import { useAuthStore } from '../../stores/auth';

const router = useRouter();
const auth = useAuthStore();
const login = ref('');
const password = ref('');
const errorMessage = ref('');

const submit = async () => {
  errorMessage.value = '';

  try {
    await auth.login({ login: login.value.trim(), password: password.value });
    await router.push('/drive');
  } catch (error) {
    errorMessage.value = error?.message ?? 'Login failed. Please try again.';
  }
};
</script>
