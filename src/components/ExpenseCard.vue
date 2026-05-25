<template>
  <div class="spes-card" @click="$router.push(`/expenses/${expense.id}`)">
    <div class="spes-card-header">
      <span class="spes-card-title">{{ expense.title }}</span>
      <StatusBadge :status="expense.status" />
    </div>
    <div class="spes-card-body">
      <div class="spes-card-amount">CHF {{ formatAmount(expense.amount) }}</div>
      <div class="spes-card-meta">
        <span>{{ expense.category }}</span>
        <span>{{ formatDate(expense.expenseDate) }}</span>
      </div>
    </div>
    <div v-if="showActions" class="spes-card-actions" @click.stop>
      <button class="spes-btn spes-btn-sm" @click="$router.push(`/expenses/${expense.id}/edit`)">{{ t('edit') }}</button>
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
    </div>
  </div>
</template>

<script setup>
import { useExpenseStore } from '../store/expenses'
import { useI18n } from '../i18n'
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
</script>
