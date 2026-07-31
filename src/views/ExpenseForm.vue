<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ isEdit ? t('editExpense') : t('newExpense') }}</h1>
    </div>

    <form class="spes-form" @submit.prevent="handleSubmit" @dragover.prevent @drop="onFormDrop">
      <div class="spes-form-group">
        <label class="spes-label" for="title">{{ t('title') }} *</label>
        <input id="title" v-model="form.title" class="spes-input" required maxlength="255" />
      </div>

      <div class="spes-form-group">
        <label class="spes-label" for="category">{{ t('category') }} *</label>
        <select id="category" v-model="form.category" class="spes-input" required>
          <option value="" disabled>--</option>
          <option v-for="cat in categories" :key="cat" :value="cat">{{ cat }}</option>
        </select>
      </div>

      <div class="spes-form-group">
        <label class="spes-label" for="description">{{ t('descriptionOptional') }}</label>
        <textarea id="description" v-model="form.description" class="spes-input spes-textarea" rows="3" maxlength="160"></textarea>
        <span class="spes-field-hint">{{ form.description.length || 0 }}/160</span>
      </div>

      <div class="spes-form-group">
        <label class="spes-label" for="payoutMethod">{{ t('payoutMethod') }}</label>
        <select id="payoutMethod" v-model="form.payoutMethod" class="spes-input">
          <option value="bank">{{ t('payoutBank') }}</option>
          <option value="">{{ t('payoutCash') }}</option>
        </select>
        <div v-if="showBankWarning" class="spes-bank-warning">
          <span>{{ t('bankWarning') }}</span>
          <router-link to="/profile" class="spes-bank-warning-link">{{ t('gotoProfile') }}</router-link>
        </div>
      </div>

      <div class="spes-form-row">
        <div class="spes-form-group">
          <label class="spes-label" for="amount">{{ t('amount') }} *</label>
          <input id="amount" :value="amountDisplay" @input="onAmountInput" @focus="onAmountFocus" @blur="onAmountBlur" class="spes-input" type="text" inputmode="decimal" autocomplete="off" required />
        </div>
        <div class="spes-form-group">
          <label class="spes-label" for="foreignAmount">{{ t('foreignAmount') }}</label>
          <input id="foreignAmount" :value="foreignAmountDisplay" @input="onForeignAmountInput" @focus="onForeignAmountFocus" @blur="onForeignAmountBlur" class="spes-input" type="text" inputmode="decimal" autocomplete="off" />
        </div>
      </div>

      <div class="spes-form-row">
        <div class="spes-form-group">
          <label class="spes-label" for="expenseDate">{{ t('expenseDate') }} *</label>
          <input id="expenseDate" v-model="form.expenseDate" class="spes-input" type="date" :max="defaultDate" required />
        </div>
        <div class="spes-form-group">
          <label class="spes-label" for="foreignCurrency">{{ t('foreignCurrency') }}</label>
          <input id="foreignCurrency" v-model="form.foreignCurrency" class="spes-input" maxlength="32" placeholder="z.B. USD, EUR" />
        </div>
      </div>

      <div class="spes-form-group">
        <label class="spes-label">{{ t('receipts') }} *</label>
        <ReceiptUpload :expense-id="currentExpenseId" :receipts="existingReceipts" :uploading="uploading" @file="handleFile" @delete="onDeleteReceipt" />
        <span v-if="receiptError" class="spes-field-error">{{ t('receiptRequired') }}</span>
      </div>

      <div class="spes-form-footnote">
        <span>{{ t('mandatoryFields') }}</span>
      </div>

      <div class="spes-form-actions">
        <button type="button" class="spes-btn" @click="$router.back()">{{ t('cancel') }}</button>
        <button type="submit" class="spes-btn spes-btn-primary" @click="submitAction = 'draft'">{{ t('saveDraft') }}</button>
        <button type="submit" class="spes-btn spes-btn-primary" @click="submitAction = 'submit'">{{ t('submit') }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted, computed, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useExpenseStore } from '../store/expenses'
import { useSettingsStore } from '../store/settings'
import { useI18n } from '../i18n'
import { api } from '../api'
import { showError } from '@nextcloud/dialogs'
import { formatAmount } from '../utils'
import ReceiptUpload from '../components/ReceiptUpload.vue'

const route = useRoute()
const router = useRouter()
const store = useExpenseStore()
const settingsStore = useSettingsStore()
const { t } = useI18n()

const isEdit = computed(() => !!route.params.id)
const expenseId = computed(() => parseInt(route.params.id) || null)
const currentExpenseId = ref(expenseId.value)

const existingReceipts = ref([])

const uploading = ref(false)

const submitAction = ref('draft')

const receiptError = ref(false)

const amountDisplay = ref('')
const foreignAmountDisplay = ref('')

const userMissingBankData = ref(false)

const formTouched = ref(false)
const formReady = ref(false)

const defaultDate = new Date().toISOString().slice(0, 10)

const form = ref({
  title: '',
  description: '',
  amount: null,
  category: '',
  expenseDate: defaultDate,
  payoutMethod: 'bank',
  foreignCurrency: '',
  foreignAmount: null,
})

const showBankWarning = computed(() => form.value.payoutMethod === 'bank' && userMissingBankData.value)

const categories = computed(() => settingsStore.settings.categories || [])

onMounted(async () => {
  await settingsStore.loadSettings()

  try {
    const data = await api.getUserSettings()
    userMissingBankData.value = !(data && data.iban && data.plz && data.city)
  } catch {
    userMissingBankData.value = true
  }

  if (isEdit.value) {
    const expense = await store.getExpense(expenseId.value)
    if (expense) {
      form.value = {
        title: expense.title,
        description: expense.description || '',
        amount: expense.amount,
        category: expense.category,
        expenseDate: expense.expenseDate,
        payoutMethod: expense.payoutMethod || '',
        foreignCurrency: expense.foreignCurrency || '',
        foreignAmount: expense.foreignAmount !== null ? expense.foreignAmount : null,
      }
      existingReceipts.value = expense.receipts || []
      amountDisplay.value = formatCurrency(expense.amount)
      foreignAmountDisplay.value = formatCurrency(expense.foreignAmount)
    }
  }
  formReady.value = true
})

watch(form, () => {
  if (formReady.value) formTouched.value = true
}, { deep: true })

function onBeforeUnload(e) {
  if (formTouched.value) {
    e.preventDefault()
    e.returnValue = ''
  }
}

onMounted(() => {
  window.addEventListener('beforeunload', onBeforeUnload)
})

onUnmounted(() => {
  window.removeEventListener('beforeunload', onBeforeUnload)
})

async function ensureSaved() {
  if (currentExpenseId.value) return currentExpenseId.value
  const data = { ...form.value, status: 'draft' }
  const created = await store.createExpense(data)
  currentExpenseId.value = created.id
  return created.id
}

async function handleFile(file) {
  uploading.value = true
  try {
    const id = await ensureSaved()
    const receipt = await api.uploadReceipt(id, file)
    existingReceipts.value.push(receipt)
  } catch (e) {
    showError(e.message)
  } finally {
    uploading.value = false
  }
}

async function onDeleteReceipt(receiptId) {
  if (!currentExpenseId.value) return
  existingReceipts.value = existingReceipts.value.filter(r => r.id !== receiptId)
  try {
    await api.deleteReceipt(currentExpenseId.value, receiptId)
  } catch (e) {
    showError(e.message)
  }
}

function formatCurrency(val) {
  if (val === null || val === '' || val === undefined) return ''
  return formatAmount(val)
}

function parseCurrency(raw) {
  const cleaned = String(raw).replace(/'/g, '').trim()
  const num = parseFloat(cleaned)
  return isNaN(num) ? null : num
}

function onAmountInput(e) {
  amountDisplay.value = e.target.value
  form.value.amount = parseCurrency(e.target.value)
}

function onAmountFocus() {
  if (form.value.amount != null) {
    amountDisplay.value = String(form.value.amount)
  }
}

function onAmountBlur() {
  amountDisplay.value = formatCurrency(form.value.amount)
}

function onForeignAmountInput(e) {
  foreignAmountDisplay.value = e.target.value
  form.value.foreignAmount = parseCurrency(e.target.value)
}

function onForeignAmountFocus() {
  if (form.value.foreignAmount != null) {
    foreignAmountDisplay.value = String(form.value.foreignAmount)
  }
}

function onForeignAmountBlur() {
  foreignAmountDisplay.value = formatCurrency(form.value.foreignAmount)
}

function onFormDrop(e) {
  const file = e.dataTransfer?.files?.[0]
  if (file) handleFile(file)
}

async function handleSubmit(e) {
  const action = submitAction.value
  const status = action === 'submit' ? 'submitted' : 'draft'

  if (status === 'submitted' && existingReceipts.value.length === 0) {
    receiptError.value = true
    return
  }
  receiptError.value = false

  if (status === 'submitted' && !confirm(t('submitConfirmation'))) {
    return
  }

  const data = {
    ...form.value,
    status,
  }

  try {
    if (currentExpenseId.value) {
      await store.updateExpense(currentExpenseId.value, data)
    } else {
      const created = await store.createExpense(data)
      currentExpenseId.value = created.id
    }
    if (status === 'submitted') {
      await store.reloadCounts()
    }
    router.push('/')
  } catch (err) {
    showError(err.message)
  }
}
</script>
