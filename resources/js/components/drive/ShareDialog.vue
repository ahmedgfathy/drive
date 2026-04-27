<template>
  <div v-if="open" class="share-modal-backdrop" @click.self="closeDialog">
    <section class="panel share-modal">
      <header class="share-modal__header">
        <div>
          <p class="dashboard-kicker">Secure Sharing</p>
          <h2>Share selected items</h2>
          <p>{{ selectionSummary }}</p>
        </div>
        <button type="button" class="btn-ghost" @click="closeDialog">Close</button>
      </header>

      <div class="share-mode-tabs">
        <button
          type="button"
          :class="['share-mode-tab', { 'is-active': form.channel === 'internal' }]"
          @click="form.channel = 'internal'"
        >
          Internal member
        </button>
        <button
          type="button"
          :class="['share-mode-tab', { 'is-active': form.channel === 'external' }]"
          :disabled="!policy.allow_external_links"
          @click="form.channel = 'external'"
        >
          External link
        </button>
      </div>

      <form class="share-form" @submit.prevent="submitShare">
        <div v-if="form.channel === 'internal'" class="share-block">
          <label>
            Search employee, department, or everyone
            <input
              v-model="query"
              type="text"
              placeholder="Type any part of the name, department, email, or employee ID"
              autocomplete="off"
            >
          </label>

          <div class="share-target-list">
            <button
              v-for="target in targets"
              :key="target.key"
              type="button"
              :class="['share-target-card', { 'is-selected': selectedTarget?.key === target.key }]"
              @click="selectTarget(target)"
            >
              <strong>{{ target.label }}</strong>
              <span>{{ target.description || target.type }}</span>
              <small>{{ target.type }}</small>
            </button>
          </div>

          <label>
            Internal permission
            <select v-model="form.permission">
              <option value="view">View</option>
              <option value="edit">Edit</option>
            </select>
          </label>
        </div>

        <div v-else class="share-block">
          <label>
            External recipient email
            <input v-model="form.target_email" type="email" placeholder="external.user@example.com" required>
          </label>

          <label>
            Recipient name
            <input v-model="form.target_name" type="text" placeholder="Optional display name">
          </label>

          <label v-if="policy.require_password_for_external_links">
            External link password
            <input v-model="form.public_password" type="text" placeholder="Required by current policy">
          </label>

          <label>
            <input v-model="form.allow_download" type="checkbox">
            Allow external download
          </label>
        </div>

        <div class="share-grid">
          <label>
            Access duration
            <select v-model="expiryMode">
              <option value="lifetime">Lifetime sharing</option>
              <option value="limited">Limit by date and time</option>
            </select>
          </label>

          <label v-if="expiryMode === 'limited'">
            Expiry date and time
            <input v-model="form.expires_at" type="datetime-local">
          </label>
        </div>

        <p v-if="selectedTarget" class="share-selection-note">
          Target: <strong>{{ selectedTarget.label }}</strong>
        </p>

        <p v-if="errorMessage" class="login-error">{{ errorMessage }}</p>

        <div class="menu-row">
          <button type="submit" :disabled="submitting">{{ submitting ? 'Sharing...' : 'Create Share' }}</button>
          <button type="button" class="btn-ghost" @click="closeDialog">Cancel</button>
        </div>
      </form>

      <section v-if="createdShares.length" class="share-result-panel">
        <h3>Share created</h3>
        <ul class="list">
          <li v-for="share in createdShares" :key="share.id">
            <div>
              <strong>{{ share.target_name || share.target_email || share.target_department || 'Internal share' }}</strong>
              <small>{{ share.channel }} • {{ share.permission }} • {{ share.expires_at ? 'limited' : 'lifetime' }}</small>
            </div>
            <button v-if="share.public_url" type="button" class="btn-ghost" @click="copyLink(share.public_url)">Copy Link</button>
          </li>
        </ul>
      </section>
    </section>
  </div>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue';
import sharesService from '../../services/shares.service';

const props = defineProps({
  open: { type: Boolean, default: false },
  items: { type: Array, default: () => [] },
});

const emit = defineEmits(['close', 'created']);

const policy = reactive({
  internal_sharing_enabled: true,
  allow_external_links: false,
  default_link_expiry_days: 7,
  max_share_duration_days: 30,
  require_password_for_external_links: true,
});

const form = reactive({
  channel: 'internal',
  permission: 'view',
  target_email: '',
  target_name: '',
  target_user_id: null,
  target_department: '',
  public_password: '',
  allow_download: true,
  expires_at: '',
});

const expiryMode = ref('lifetime');
const query = ref('');
const targets = ref([]);
const selectedTarget = ref(null);
const createdShares = ref([]);
const errorMessage = ref('');
const submitting = ref(false);
let searchTimer = null;

const selectionSummary = computed(() => {
  const filesCount = props.items.filter((item) => item.shareable_type === 'file').length;
  const foldersCount = props.items.filter((item) => item.shareable_type === 'folder').length;
  const parts = [];

  if (filesCount) {
    parts.push(`${filesCount} file${filesCount > 1 ? 's' : ''}`);
  }

  if (foldersCount) {
    parts.push(`${foldersCount} folder${foldersCount > 1 ? 's' : ''}`);
  }

  return parts.length ? parts.join(' and ') : 'No items selected';
});

watch(
  () => props.open,
  async (isOpen) => {
    if (!isOpen) {
      resetState();
      return;
    }

    await loadPolicy();
    await loadTargets('');
  }
);

watch(query, (value) => {
  if (searchTimer) {
    window.clearTimeout(searchTimer);
  }

  searchTimer = window.setTimeout(() => {
    loadTargets(value);
  }, 250);
});

watch(
  () => form.channel,
  (channel) => {
    errorMessage.value = '';
    createdShares.value = [];

    if (channel === 'external') {
      selectedTarget.value = null;
      form.permission = 'view';
      return;
    }

    form.target_email = '';
    form.target_name = '';
    form.public_password = '';
    loadTargets(query.value);
  }
);

const loadPolicy = async () => {
  const response = await sharesService.policy();
  Object.assign(policy, response.data || {});

  if (!policy.allow_external_links) {
    form.channel = 'internal';
  }

  if (policy.require_password_for_external_links && !form.public_password) {
    form.public_password = '';
  }
};

const loadTargets = async (search) => {
  if (!props.open || form.channel !== 'internal') {
    return;
  }

  const response = await sharesService.targets({ q: search });
  targets.value = response.data.targets ?? [];
};

const selectTarget = (target) => {
  selectedTarget.value = target;
  form.target_user_id = target.target_user_id || null;
  form.target_department = target.department || '';
};

const closeDialog = () => {
  emit('close');
};

const resetState = () => {
  createdShares.value = [];
  errorMessage.value = '';
  selectedTarget.value = null;
  query.value = '';
  targets.value = [];
  expiryMode.value = 'lifetime';
  form.channel = 'internal';
  form.permission = 'view';
  form.target_email = '';
  form.target_name = '';
  form.target_user_id = null;
  form.target_department = '';
  form.public_password = '';
  form.allow_download = true;
  form.expires_at = '';
};

const submitShare = async () => {
  errorMessage.value = '';
  submitting.value = true;

  try {
    const resolvedTargetType = form.channel === 'external'
      ? 'external'
      : (selectedTarget.value?.type === 'employee' ? 'user' : (selectedTarget.value?.type || 'user'));

    const payload = {
      items: props.items,
      channel: form.channel,
      target_type: resolvedTargetType,
      permission: form.channel === 'external' ? 'view' : form.permission,
      allow_download: form.allow_download,
      expires_at: expiryMode.value === 'limited' && form.expires_at ? new Date(form.expires_at).toISOString() : null,
    };

    if (form.channel === 'internal') {
      if (!selectedTarget.value) {
        throw new Error('Select an internal target first.');
      }

      if (selectedTarget.value.type === 'employee') {
        payload.target_user_id = selectedTarget.value.target_user_id || null;
        payload.directory_user = {
          employee_id: selectedTarget.value.employee_id || null,
          display_name: selectedTarget.value.label || null,
          email: selectedTarget.value.email || null,
          samaccountname: selectedTarget.value.samaccountname || null,
          department: selectedTarget.value.department || null,
        };
      }

      if (selectedTarget.value.type === 'department') {
        payload.target_department = selectedTarget.value.department || selectedTarget.value.label;
      }
    } else {
      payload.target_email = form.target_email;
      payload.target_name = form.target_name || null;
      payload.public_password = form.public_password || null;
    }

    const response = await sharesService.create(payload);
    createdShares.value = response.data.shares ?? [];
    emit('created', createdShares.value);
  } catch (error) {
    errorMessage.value = error?.response?.data?.message || error?.message || 'Unable to create the share.';
  } finally {
    submitting.value = false;
  }
};

const copyLink = async (url) => {
  await navigator.clipboard.writeText(url);
};
</script>
