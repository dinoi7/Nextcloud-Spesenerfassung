<template>
  <div class="spes-card" @click="$router.push(`/expenses/${expense.id}`)">
    <div class="spes-card-header">
      <div class="spes-card-header-left">
        <span class="spes-card-title">{{ expense.title }}</span>
        <span class="spes-card-user">{{ expense.displayName || expense.userId }}</span>
      </div>
      <span v-if="expense.receiptCount > 0" class="spes-card-receipt-badge" :title="expense.receiptCount + ' Belege'"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg> {{ expense.receiptCount }}</span>
      <StatusBadge :status="expense.status" />
    </div>
    <div class="spes-card-body">
      <div class="spes-card-amount">CHF {{ formatAmount(expense.amount) }}</div>
      <div v-if="expense.foreignCurrency" class="spes-card-foreign-amount">{{ expense.foreignCurrency }} {{ formatAmount(expense.foreignAmount) }}</div>
      <div class="spes-card-meta">
        <span>{{ expense.category }}</span>
        <span>{{ formatDate(expense.expenseDate) }}</span>
        <span v-if="expense.payoutMethod" class="spes-card-payout">{{ expense.payoutMethod === 'bank' ? t('payoutBank') : t('payoutCash') }}</span>
      </div>
    </div>
    <div v-if="showActions" class="spes-card-actions" @click.stop>
      <button
        v-if="expense.status === 'draft' || expense.status === 'rejected'"
        class="spes-btn spes-btn-sm"
        @click="$router.push(`/expenses/${expense.id}/edit`)"
      >{{ t('edit') }}</button>
      <button
        v-if="expense.status === 'draft'"
        class="spes-btn spes-btn-sm spes-btn-primary"
        @click="handleSubmit"
      >{{ t('submit') }}</button>
      <button
        v-if="expense.status === 'draft' || expense.status === 'rejected'"
        class="spes-btn spes-btn-sm spes-btn-danger"
        @click="handleDelete"
      >{{ t('delete') }}</button>
      <button
        v-if="expense.status === 'paid'"
        class="spes-btn spes-btn-sm spes-btn-success"
        @click="handleDone"
      >{{ t('done') }}</button>
    </div>
  </div>
</template>

<script setup>
import { useExpenseStore } from '../store/expenses'
import { useI18n } from '../i18n'
import { api } from '../api'
import StatusBadge from './StatusBadge.vue'

const props = defineProps({
  expense: { type: Object, required: true },
  showActions: { type: Boolean, default: false },
})

const store = useExpenseStore()
const { t } = useI18n()

function formatAmount(amount) {
  return parseFloat(amount || 0).toFixed(2)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH')
}

async function handleSubmit() {
  if (confirm(t('submitConfirmation'))) {
    await store.submitExpense(props.expense.id)
  }
}

async function handleDelete() {
  if (confirm(t('confirmDelete'))) {
    await store.deleteExpense(props.expense.id)
  }
}

async function handleDone() {
  try {
    await api.done(props.expense.id)
    window.location.reload()
  } catch (e) {
    alert(e.message)
  }
}
</script>
