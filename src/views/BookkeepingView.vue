<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ t('bookkeeping') }} <span v-if="expenses.length" class="spes-paystack-count">({{ expenses.length }})</span></h1>
      <div class="spes-paystack-header-actions">
        <button class="spes-btn" @click="handleExport">{{ t('exportCsv') }}</button>
      </div>
    </div>

    <div v-if="loading" class="spes-loading">{{ t('loading') }}</div>
    <div v-else-if="error" class="spes-error">{{ error }}</div>
    <div v-else-if="expenses.length === 0" class="spes-empty">
      <p>{{ t('noBookkeeping') }}</p>
    </div>

    <div v-else class="spes-card-list">
      <div v-for="expense in expenses" :key="expense.id" class="spes-card" @click="$router.push(`/expenses/${expense.id}?from=bookkeeping`)">
        <div class="spes-card-header">
          <div class="spes-card-header-left">
            <span class="spes-card-title">{{ expense.title }}</span>
            <span class="spes-card-user">{{ expense.displayName || expense.userId }}</span>
          </div>
          <StatusBadge :status="expense.status" />
        </div>
        <div class="spes-card-body">
          <div>
            <div class="spes-card-amount">CHF {{ formatAmount(expense.amount) }}</div>
            <div v-if="expense.foreignCurrency" class="spes-card-foreign-amount">{{ expense.foreignCurrency }} {{ formatAmount(expense.foreignAmount) }}</div>
          </div>
          <div v-if="expense.description" class="spes-card-desc">{{ expense.description }}</div>
          <div class="spes-card-meta">
            <span>{{ expense.category }}</span>
            <span v-if="expense.sollKonto" class="spes-card-account">&rarr; {{ expense.sollKonto }}</span>
            <span>{{ formatDate(expense.expenseDate) }}</span>
            <span v-if="expense.payoutMethod" class="spes-card-payout">{{ expense.payoutMethod === 'bank' ? t('payoutBank') : t('payoutCash') }}</span>
          </div>
        </div>
        <div class="spes-card-actions" @click.stop>
          <button class="spes-btn spes-btn-sm" @click="handleExportSingle(expense.id)">{{ t('exportCsv') }}</button>
          <button v-if="expense.payoutMethod === 'bank'" class="spes-btn spes-btn-sm spes-btn-paystack" @click="toPaystack(expense.id)">{{ t('addToPaystack') }}</button>
          <button v-else class="spes-btn spes-btn-sm spes-btn-success" @click="payExpense(expense.id)">{{ t('pay') }}</button>
          <button class="spes-btn spes-btn-sm spes-btn-danger" @click="rejectExpense(expense.id)">{{ t('reject') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useI18n } from '../i18n'
import { api } from '../api'
import StatusBadge from '../components/StatusBadge.vue'

const { t } = useI18n()

const expenses = ref([])
const loading = ref(true)
const error = ref(null)

function formatAmount(amount) {
  return parseFloat(amount || 0).toFixed(2)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH')
}

async function loadBookkeeping() {
  loading.value = true
  error.value = null
  try {
    const data = await api.getBookkeeping()
    data.sort((a, b) => {
      const d = (a.expenseDate || '').localeCompare(b.expenseDate || '')
      if (d !== 0) return d
      return (a.createdAt || '').localeCompare(b.createdAt || '')
    })
    expenses.value = data
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function toPaystack(id) {
  try {
    await api.addToPaystack(id)
    await loadBookkeeping()
  } catch (e) { alert(e.message) }
}

async function payExpense(id) {
  try {
    await api.pay(id)
    await loadBookkeeping()
  } catch (e) { alert(e.message) }
}

async function rejectExpense(id) {
  const reason = prompt(t('rejectConfirmation'))
  if (!reason) return
  try {
    await api.reject(id, reason)
    await loadBookkeeping()
  } catch (e) { alert(e.message) }
}

async function handleExport() {
  try {
    await api.exportBookkeeping()
  } catch (e) { alert(e.message) }
}

async function handleExportSingle(id) {
  try {
    await api.exportBookkeepingSingle(id)
  } catch (e) { alert(e.message) }
}

onMounted(() => {
  loadBookkeeping()
})
</script>
