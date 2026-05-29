<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ isEdit ? t('editExpense') : t('newExpense') }}</h1>
    </div>

    <form class="spes-form" @submit.prevent="handleSubmit">
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
        <textarea id="description" v-model="form.description" class="spes-input spes-textarea" rows="3"></textarea>
      </div>

      <div class="spes-form-group">
        <label class="spes-label" for="payoutMethod">{{ t('payoutMethod') }}</label>
        <select id="payoutMethod" v-model="form.payoutMethod" class="spes-input">
          <option value="bank">{{ t('payoutBank') }}</option>
          <option value="">{{ t('payoutCash') }}</option>
        </select>
      </div>

      <div class="spes-form-row">
        <div class="spes-form-group">
          <label class="spes-label" for="amount">{{ t('amount') }} *</label>
          <input id="amount" v-model.number="form.amount" class="spes-input" type="number" step="0.01" min="0.01" required />
        </div>
        <div class="spes-form-group">
          <label class="spes-label" for="foreignAmount">{{ t('foreignAmount') }}</label>
          <input id="foreignAmount" v-model.number="form.foreignAmount" class="spes-input" type="number" step="0.01" min="0" />
        </div>
      </div>

      <div class="spes-form-row">
        <div class="spes-form-group">
          <label class="spes-label" for="expenseDate">{{ t('expenseDate') }} *</label>
          <input id="expenseDate" v-model="form.expenseDate" class="spes-input" type="date" required />
        </div>
        <div class="spes-form-group">
          <label class="spes-label" for="foreignCurrency">{{ t('foreignCurrency') }}</label>
          <input id="foreignCurrency" v-model="form.foreignCurrency" class="spes-input" maxlength="32" placeholder="z.B. USD, EUR" />
        </div>
      </div>

      <div class="spes-form-group">
        <ReceiptUpload :expense-id="currentExpenseId" :receipts="existingReceipts" @file="handleFile" @delete="onDeleteReceipt" />
      </div>

      <div class="spes-form-actions">
        <button type="button" class="spes-btn" @click="$router.back()">{{ t('cancel') }}</button>
        <button type="submit" class="spes-btn spes-btn-primary" name="action" value="draft">{{ t('saveDraft') }}</button>
        <button type="submit" class="spes-btn spes-btn-primary" name="action" value="submit">{{ t('submit') }}</button>
      </div>
    </form>
  </div>
</template>

<script setup>
import { ref, onMounted, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useExpenseStore } from '../store/expenses'
import { useSettingsStore } from '../store/settings'
import { useI18n } from '../i18n'
import { api } from '../api'
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

const categories = computed(() => settingsStore.settings.categories || [])

onMounted(async () => {
  await settingsStore.loadSettings()

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
    }
  }
})

async function ensureSaved() {
  if (currentExpenseId.value) return currentExpenseId.value
  const data = { ...form.value, status: 'draft' }
  const created = await store.createExpense(data)
  currentExpenseId.value = created.id
  return created.id
}

async function handleFile(file) {
  try {
    const id = await ensureSaved()
    const receipt = await api.uploadReceipt(id, file)
    existingReceipts.value.push(receipt)
  } catch (e) {
    alert(e.message)
  }
}

async function onDeleteReceipt(receiptId) {
  if (!currentExpenseId.value) return
  existingReceipts.value = existingReceipts.value.filter(r => r.id !== receiptId)
  try {
    await api.deleteReceipt(currentExpenseId.value, receiptId)
  } catch (e) {
    alert(e.message)
  }
}

async function handleSubmit(e) {
  const action = e.submitter?.value || 'draft'
  const status = action === 'submit' ? 'submitted' : 'draft'

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
    router.push('/')
  } catch (err) {
    alert(err.message)
  }
}
</script>
