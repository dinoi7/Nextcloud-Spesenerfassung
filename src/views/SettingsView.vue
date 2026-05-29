<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ t('settings') }}</h1>
    </div>

    <div v-if="loading" class="spes-loading">{{ t('loading') }}</div>
    <div v-else class="spes-form">
      <div class="spes-form-group">
        <label class="spes-label">{{ t('presidentUid') }}</label>
        <select v-model="form.presidentUid" class="spes-input">
          <option value="">--</option>
          <option v-for="u in users" :key="u.uid" :value="u.uid">{{ u.displayName }} ({{ u.uid }})</option>
        </select>
      </div>
      <div class="spes-form-group">
        <label class="spes-label">{{ t('treasurerUid') }}</label>
        <select v-model="form.treasurerUid" class="spes-input">
          <option value="">--</option>
          <option v-for="u in users" :key="u.uid" :value="u.uid">{{ u.displayName }} ({{ u.uid }})</option>
        </select>
      </div>
      <div class="spes-form-group">
        <label class="spes-label">{{ t('threshold') }}</label>
        <input v-model.number="form.threshold" class="spes-input" type="number" step="1" min="0" />
      </div>

      <div class="spes-form-group">
        <label class="spes-label">{{ t('defaultPayoutMethod') }}</label>
        <select v-model="form.defaultPayoutMethod" class="spes-input">
          <option value="bank">{{ t('payoutBank') }}</option>
          <option value="">{{ t('payoutCash') }}</option>
        </select>
      </div>

      <div class="spes-form-group">
        <label class="spes-label">{{ t('categories') }}</label>
        <div class="spes-categories">
          <div v-for="(cat, idx) in form.categories" :key="idx" class="spes-category-row">
            <input v-model="form.categories[idx]" class="spes-input" />
            <button class="spes-btn spes-btn-sm spes-btn-danger" @click="removeCategory(idx)">&times;</button>
          </div>
        </div>
        <div class="spes-add-category">
          <input v-model="newCategory" class="spes-input" :placeholder="t('categoryName')" />
          <button class="spes-btn spes-btn-sm" @click="addCategory">{{ t('addCategory') }}</button>
        </div>
      </div>

      <div class="spes-form-actions">
        <button class="spes-btn spes-btn-primary" @click="save">{{ t('save') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useSettingsStore } from '../store/settings'
import { useI18n } from '../i18n'
import { api } from '../api'

const settingsStore = useSettingsStore()
const { t } = useI18n()
const loading = ref(true)
const users = ref([])
const newCategory = ref('')

const form = ref({
  presidentUid: '',
  treasurerUid: '',
  threshold: 250,
  categories: [],
  defaultPayoutMethod: 'bank',
})

onMounted(async () => {
  await settingsStore.loadSettings()
  form.value = { ...settingsStore.settings }
  try {
    users.value = await api.getUsers()
  } catch {}
  loading.value = false
})

async function save() {
  try {
    await settingsStore.saveSettings(form.value)
    alert(t('settingsSaved'))
  } catch (e) {
    alert(e.message)
  }
}

function addCategory() {
  const name = newCategory.value.trim()
  if (name && !form.value.categories.includes(name)) {
    form.value.categories.push(name)
    newCategory.value = ''
  }
}

function removeCategory(idx) {
  form.value.categories.splice(idx, 1)
}
</script>
