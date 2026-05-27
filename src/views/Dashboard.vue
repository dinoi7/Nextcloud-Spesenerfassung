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
          <span class="spes-summary-label">{{ t('totalAmount') }}</span>
          <span class="spes-summary-value">CHF {{ totalAmount }}</span>
        </div>
      </div>

      <div v-if="drafts.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('statusDraft') }} ({{ drafts.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard v-for="expense in drafts" :key="expense.id" :expense="expense" :show-actions="true" />
        </div>
      </div>

      <div v-if="submitted.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('statusSubmitted') }} ({{ submitted.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard v-for="expense in submitted" :key="expense.id" :expense="expense" />
        </div>
      </div>

      <div v-if="approved.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('statusApproved') }} ({{ approved.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard v-for="expense in approved" :key="expense.id" :expense="expense" />
        </div>
      </div>

      <div v-if="rejected.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('statusRejected') }} ({{ rejected.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard v-for="expense in rejected" :key="expense.id" :expense="expense" :show-actions="true" />
        </div>
      </div>

      <div v-if="paid.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('statusPaid') }} ({{ paid.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard v-for="expense in paid" :key="expense.id" :expense="expense" />
        </div>
      </div>

      <div v-if="done.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('statusDone') }} ({{ done.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard v-for="expense in done" :key="expense.id" :expense="expense" />
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

const drafts = computed(() => store.expenses.filter(e => e.status === 'draft'))
const submitted = computed(() => store.expenses.filter(e => e.status === 'submitted'))
const approved = computed(() => store.expenses.filter(e => e.status === 'approved'))
const rejected = computed(() => store.expenses.filter(e => e.status === 'rejected'))
const paid = computed(() => store.expenses.filter(e => e.status === 'paid'))
const done = computed(() => store.expenses.filter(e => e.status === 'done'))

onMounted(() => {
  store.loadExpenses()
})
</script>
