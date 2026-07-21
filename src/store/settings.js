import { defineStore } from 'pinia'
import { ref } from 'vue'
import { api } from '../api'

function getInitialSettings() {
  try {
    const el = document.getElementById('spesenerfassung-initial-data')
    if (el && el.textContent) {
      const data = JSON.parse(el.textContent)
      return data.settings || {}
    }
  } catch {}
  return {}
}

export const useSettingsStore = defineStore('settings', () => {
  const defaultSettings = getInitialSettings()
  const settings = ref({
    presidentUid: '',
    treasurerUid: '',
    threshold: defaultSettings.threshold || 250,
    categories: defaultSettings.categories || ['Material', 'Verpflegung', 'Reise', 'Büro', 'Sonstiges'],
		defaultPayoutMethod: defaultSettings.defaultPayoutMethod || 'bank',
		exportAccounts: {},
		senderEmail: '',
		senderName: '',
	})
  const loading = ref(false)
  const error = ref(null)

  const roles = ref({ isPresident: false, isTreasurer: false })

  async function loadRoles() {
    try {
      const result = await api.getRoles()
      roles.value = result
    } catch {}
  }

  async function loadSettings() {
    loading.value = true
    error.value = null
    try {
      const result = await api.getSettings()
      settings.value = result
      return result
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
    return null
  }

  async function saveSettings(data) {
    const result = await api.updateSettings(data)
    settings.value = result
    return result
  }

  return { settings, roles, loading, error, loadSettings, loadRoles, saveSettings }
})
