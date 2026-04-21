<template>
  <section class="login-shell">
    <article class="login-panel register-panel">
      <img class="login-logo" src="https://www.pmsoffshore.com/assets/images/global/logo.svg" alt="PMS Offshore">
      <p class="login-label">Self Registration</p>
      <h1>Create Access Request</h1>
      <p class="login-subtitle">Use your existing company details. Your account will stay pending until admin activation.</p>

      <form class="stack" @submit.prevent="submit">
        <label>
          Company Email
          <input v-model="email" type="email" placeholder="name@pms.eg" required>
        </label>

        <label>
          Employee ID
          <input v-model="employeeId" type="text" placeholder="EMP000123" required>
        </label>

        <label>
          Mobile Number
          <input v-model="mobile" type="text" placeholder="01XXXXXXXXX" required>
        </label>

        <label>
          Password
          <input v-model="password" type="password" placeholder="Create password" minlength="8" required>
        </label>

        <label>
          Confirm Password
          <input v-model="passwordConfirmation" type="password" placeholder="Confirm password" minlength="8" required>
        </label>

        <button type="submit" :disabled="submitting">{{ submitting ? 'Submitting...' : 'Submit Registration' }}</button>
      </form>

      <p v-if="errorMessage" class="login-error">{{ errorMessage }}</p>

      <RouterLink class="login-back" to="/login">Back to login</RouterLink>
    </article>

    <div v-if="showSuccess" class="reg-modal-backdrop" @click="closeSuccess">
      <div class="reg-modal" @click.stop>
        <h3>Registration Submitted</h3>
        <p>Your request was saved successfully.</p>
        <p>Please wait for activation by administrator.</p>
        <button type="button" @click="goToLogin">OK</button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { ref } from 'vue';
import { RouterLink, useRouter } from 'vue-router';
import authService from '../../services/auth.service';

const router = useRouter();

const email = ref('');
const employeeId = ref('');
const mobile = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const errorMessage = ref('');
const submitting = ref(false);
const showSuccess = ref(false);

const submit = async () => {
  errorMessage.value = '';

  if (password.value !== passwordConfirmation.value) {
    errorMessage.value = 'Password confirmation does not match.';
    return;
  }

  submitting.value = true;

  try {
    await authService.register({
      email: email.value.trim(),
      employee_id: employeeId.value.trim(),
      mobile: mobile.value.trim(),
      password: password.value,
      password_confirmation: passwordConfirmation.value,
    });

    showSuccess.value = true;
  } catch (error) {
    errorMessage.value = error?.response?.data?.message
      || error?.message
      || 'Registration failed. Please verify your information.';
  } finally {
    submitting.value = false;
  }
};

const closeSuccess = () => {
  showSuccess.value = false;
};

const goToLogin = async () => {
  showSuccess.value = false;
  await router.push('/login');
};
</script>
