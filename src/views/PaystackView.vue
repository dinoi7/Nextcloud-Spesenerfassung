<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ t('paystack') }} <span v-if="expenses.length" class="spes-paystack-count">({{ expenses.length }})</span></h1>
      <div class="spes-paystack-header-actions">
        <button v-if="expenses.length" class="spes-btn spes-btn-success" @click="handlePayAll">{{ t('payAll') }}</button>
      </div>
    </div>

    <div v-if="loading" class="spes-loading">{{ t('loading') }}</div>
    <div v-else-if="error" class="spes-error">{{ error }}</div>
    <div v-else-if="expenses.length === 0" class="spes-empty">
      <p>{{ t('noPaystack') }}</p>
    </div>

    <div v-else class="spes-paystack-list">
      <div v-for="expense in expenses" :key="expense.id" class="spes-paystack-row">
        <div class="spes-paystack-main" @click="$router.push(`/expenses/${expense.id}?from=paystack`)">
          <div class="spes-paystack-header">
            <span class="spes-paystack-title">{{ expense.title }}</span>
            <span class="spes-card-user">{{ expense.displayName || expense.userId }}</span>
            <StatusBadge :status="expense.status" />
          </div>
          <div class="spes-paystack-details">
            <span class="spes-paystack-amount">CHF {{ formatAmount(expense.amount) }}</span>
            <span v-if="expense.foreignCurrency">{{ expense.foreignCurrency }} {{ formatAmount(expense.foreignAmount) }}</span>
            <span>{{ expense.category }}</span>
            <span v-if="expense.sollKonto">&rarr; {{ expense.sollKonto }}</span>
            <span>{{ formatDate(expense.expenseDate) }}</span>
            <span v-if="expense.payoutMethod === 'bank'">{{ t('payoutBank') }}</span>
            <span v-else>{{ t('payoutCash') }}</span>
          </div>
          <div v-if="expense.description" class="spes-paystack-desc">{{ expense.description }}</div>
        </div>

        <div v-if="expense.payoutMethod === 'bank' && expense.iban" class="spes-paystack-payment">
          <div class="spes-paystack-payment-details">
            <span class="spes-payment-label">{{ t('paymentIban') }}</span>
            <span class="spes-paystack-iban">{{ expense.iban }}</span>
            <span class="spes-payment-label">{{ t('recipient') }}</span>
            <span class="spes-payment-value">{{ expense.submitterName || expense.displayName }}</span>
          </div>
          <SwissQrCode :iban="expense.iban" :name="expense.submitterName || expense.displayName" :amount="expense.amount" :plz="expense.plz" :reference="'SpesenNr. ' + expense.id + ': ' + expense.title" />
        </div>

        <div class="spes-paystack-actions" @click.stop>
          <button class="spes-btn spes-btn-sm spes-btn-success" @click="payExpense(expense.id)">{{ t('pay') }}</button>
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
import { showError, showSuccess } from '@nextcloud/dialogs'
import StatusBadge from '../components/StatusBadge.vue'
import SwissQrCode from '../components/SwissQrCode.vue'

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

async function loadPaystack() {
  loading.value = true
  error.value = null
  try {
    const data = await api.getPaystack()
    if (Array.isArray(data)) {
      data.sort((a, b) => {
        const d = (a.expenseDate || '').localeCompare(b.expenseDate || '')
        if (d !== 0) return d
        return (a.createdAt || '').localeCompare(b.createdAt || '')
      })
    }
    expenses.value = Array.isArray(data) ? data : []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function payExpense(id) {
  try {
    const exp = await api.pay(id)
    await loadPaystack()
    if (exp.bookingReceipt?.message) showSuccess(exp.bookingReceipt.message)
  } catch (e) { showError(e.message) }
}

async function handlePayAll() {
  if (!confirm(t('payAllConfirm') || 'Alle Zahlstapel-Einträge ausbezahlen?')) return
  try {
    const result = await api.payAll()
    await loadPaystack()
    if (result.bookingReceipt?.message) showSuccess(result.bookingReceipt.message)
  } catch (e) { showError(e.message) }
}

async function rejectExpense(id) {
  const reason = prompt(t('rejectConfirmation'))
  if (!reason) return
  try {
    await api.reject(id, reason)
    await loadPaystack()
  } catch (e) { showError(e.message) }
}

onMounted(() => {
  loadPaystack()
})
</script>
