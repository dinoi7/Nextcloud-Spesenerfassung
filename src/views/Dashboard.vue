<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ t('dashboard') }}</h1>
      <button class="spes-btn spes-btn-primary" @click="$router.push('/new')">+ {{ t('newExpense') }}</button>
    </div>

    <div v-if="store.loading" class="spes-loading">{{ t('loading') }}</div>
    <div v-else-if="store.error" class="spes-error">{{ store.error }}</div>
    <div v-else-if="store.expenses.length === 0" class="spes-empty">
      <p>{{ t('noExpenses') }}</p>
      <button class="spes-btn spes-btn-primary" @click="$router.push('/new')">+ {{ t('newExpense') }}</button>
    </div>

    <template v-else>
      <div class="spes-summary">
        <div class="spes-summary-item">
          <span class="spes-summary-label">{{ t('pending') }}</span>
          <span class="spes-summary-value">{{ store.submittedExpenses.length }}</span>
        </div>
        <div class="spes-summary-item">
          <span class="spes-summary-label">{{ t('completed') }}</span>
          <span class="spes-summary-value">{{ store.doneExpenses.length }}</span>
        </div>
        <div class="spes-summary-item">
          <span class="spes-summary-label">{{ t('totalAmount') }}</span>
          <span class="spes-summary-value">CHF {{ totalAmount }}</span>
        </div>
      </div>

      <div v-if="store.draftExpenses.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('statusDraft') }} / {{ t('statusRejected') }} ({{ store.draftExpenses.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard
            v-for="expense in store.draftExpenses"
            :key="expense.id"
            :expense="expense"
            :show-actions="true"
          />
        </div>
      </div>

      <div v-if="store.submittedExpenses.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('pending') }} ({{ store.submittedExpenses.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard
            v-for="expense in store.submittedExpenses"
            :key="expense.id"
            :expense="expense"
          />
        </div>
      </div>

      <div v-if="store.doneExpenses.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('completed') }} ({{ store.doneExpenses.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard
            v-for="expense in store.doneExpenses"
            :key="expense.id"
            :expense="expense"
          />
        </div>
      </div>
    </template>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useExpenseStore } from '../store/expenses'
import { useI18n } from '../i18n'
import ExpenseCard from '../components/ExpenseCard.vue'

const store = useExpenseStore()
const { t } = useI18n()

const totalAmount = computed(() => {
  const sum = store.expenses.reduce((s, e) => s + (parseFloat(e.amount) || 0), 0)
  return sum.toFixed(2)
})

onMounted(() => {
  store.loadExpenses()
})
</script>
