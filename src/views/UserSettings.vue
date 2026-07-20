<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ t('profile') }}</h1>
    </div>

    <div v-if="loading" class="spes-loading">{{ t('loading') }}</div>
    <div v-else class="spes-form">
      <div class="spes-form-group">
        <label class="spes-label" for="iban">{{ t('ibanLabel') }}</label>
        <input
          id="iban"
          v-model="ibanDisplay"
          class="spes-input"
          :class="{ 'spes-input--error': ibanError }"
          placeholder="CH00 0000 0000 0000 0000 0"
          @input="onIbanInput"
        />
        <span v-if="ibanError" class="spes-field-error">{{ ibanError }}</span>
      </div>

      <div class="spes-form-actions">
        <button class="spes-btn spes-btn-primary" @click="save">{{ t('save') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue'
import { useI18n } from '../i18n'
import { api } from '../api'
import { showError, showSuccess } from '@nextcloud/dialogs'

const { t } = useI18n()
const ibanDisplay = ref('')
const ibanError = ref('')
const loading = ref(true)

const IBAN_LETTERS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ'

function charValue(c) {
  const code = c.charCodeAt(0)
  if (code >= 48 && code <= 57) return code - 48
  return IBAN_LETTERS.indexOf(c) + 10
}

function formatIban(raw) {
  return raw.replace(/\s/g, '').replace(/(.{4})/g, '$1 ').trim()
}

function validateCHIban(raw) {
  const stripped = raw.replace(/\s/g, '')
  if (!stripped) return null

  if (!/^[A-Z]{2}[0-9]{2}[A-Za-z0-9]+$/.test(stripped)) {
    return t('ibanInvalid')
  }

  if (stripped.slice(0, 2).toUpperCase() !== 'CH') {
    return t('ibanNotCH')
  }

  if (stripped.length !== 21) {
    return t('ibanInvalid')
  }

  const rearranged = stripped.slice(4) + stripped.slice(0, 4)
  let remainder = 0n
  for (const ch of rearranged) {
    const val = BigInt(charValue(ch))
    const digitCount = val < 10n ? 10n : 100n
    remainder = (remainder * digitCount + val) % 97n
  }

  if (remainder !== 1n) {
    return t('ibanInvalid')
  }

  return null
}

function onIbanInput() {
  const raw = ibanDisplay.value.replace(/\s/g, '').toUpperCase()
  ibanDisplay.value = formatIban(raw)
  ibanError.value = validateCHIban(ibanDisplay.value)
}

onMounted(async () => {
  try {
    const data = await api.getUserSettings()
    if (data.iban) {
      ibanDisplay.value = formatIban(data.iban)
    }
  } catch {}
  loading.value = false
})

async function save() {
  const stripped = ibanDisplay.value.replace(/\s/g, '')
  const err = validateCHIban(ibanDisplay.value)
  if (err) {
    ibanError.value = err
    return
  }
  try {
    await api.updateUserSettings({ iban: stripped })
    showSuccess(t('settingsSaved'))
  } catch (e) {
    showError(e.message)
  }
}
</script>
