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
        <label class="spes-label">{{ t('bookingFolder') }}</label>
        <input v-model="form.bookingFolder" class="spes-input" placeholder="Buchungsbelege" />
        <p class="spes-hint">{{ t('bookingFolderHint') }}</p>
      </div>

      <div class="spes-form-group">
        <label class="spes-label">{{ t('debitAccounts') }}</label>
        <table class="spes-accounts-table" v-if="rows.length">
          <thead>
            <tr>
              <th>{{ t('category') }}</th>
              <th>Soll-Konto</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="(row, idx) in rows" :key="idx">
              <td><input v-model="row.category" class="spes-input" /></td>
              <td><input v-model="row.account" class="spes-input" placeholder="z.B. 4450 Projekte/Anlässe" /></td>
              <td><button class="spes-btn spes-btn-sm spes-btn-danger" @click="removeRow(idx)">&times;</button></td>
            </tr>
          </tbody>
        </table>
        <div class="spes-add-category">
          <input v-model="newCategory" class="spes-input" :placeholder="t('categoryName')" />
          <button class="spes-btn spes-btn-sm" @click="addRow">{{ t('addCategory') }}</button>
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
import { showError, showSuccess } from '@nextcloud/dialogs'

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
  exportAccounts: {},
  bookingFolder: 'Buchungsbelege',
})

const rows = ref([])

onMounted(async () => {
  await settingsStore.loadSettings()
  form.value = { ...settingsStore.settings }
  buildRows()
  try {
    users.value = await api.getUsers()
  } catch {}
  loading.value = false
})

function buildRows() {
  const accounts = form.value.exportAccounts || {}
  rows.value = form.value.categories.map(cat => ({
    category: cat,
    account: accounts[cat] || '',
  }))
}

function syncForm() {
  form.value.categories = rows.value.map(r => r.category.trim()).filter(Boolean)
  const accounts = {}
  for (const r of rows.value) {
    const cat = r.category.trim()
    if (cat) {
      accounts[cat] = r.account
    }
  }
  form.value.exportAccounts = accounts
}

async function save() {
  syncForm()
  try {
    await settingsStore.saveSettings(form.value)
    buildRows()
    showSuccess(t('settingsSaved'))
  } catch (e) {
    showError(e.message)
  }
}

function addRow() {
  const name = newCategory.value.trim()
  if (!name) return
  if (rows.value.some(r => r.category.trim() === name)) return
  rows.value.push({ category: name, account: '' })
  newCategory.value = ''
}

function removeRow(idx) {
  rows.value.splice(idx, 1)
}
</script>
