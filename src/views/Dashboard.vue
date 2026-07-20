<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <div class="spes-page-title-row">
        <h1>{{ t('dashboard') }}</h1>
        <select v-model="selectedYear" class="spes-year-select">
          <option v-for="y in availableYears" :key="y.value" :value="y.value">
            {{ y.label }} ({{ y.actionCount }})
          </option>
        </select>
      </div>
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
        <div class="spes-summary-item">
          <span class="spes-summary-label">{{ t('statusSubmitted') }}</span>
          <span class="spes-summary-value">CHF {{ submittedAmount }}</span>
        </div>
        <div class="spes-summary-item">
          <span class="spes-summary-label">{{ t('statusPaid') }}</span>
          <span class="spes-summary-value">CHF {{ paidAmount }}</span>
        </div>
        <div class="spes-summary-item">
          <span class="spes-summary-label">{{ t('statusDone') }}</span>
          <span class="spes-summary-value">CHF {{ doneAmount }}</span>
        </div>
      </div>

      <div v-if="rejected.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('statusRejected') }} ({{ rejected.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard v-for="expense in rejected" :key="expense.id" :expense="expense" :show-actions="true" />
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

      <div v-if="paid.length" class="spes-section">
        <h2 class="spes-section-title">{{ t('statusPaid') }} ({{ paid.length }})</h2>
        <div class="spes-card-list">
          <ExpenseCard v-for="expense in paid" :key="expense.id" :expense="expense" :show-actions="true" />
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
import { ref, computed, onMounted } from 'vue'
import { useExpenseStore } from '../store/expenses'
import { useSettingsStore } from '../store/settings'
import { useI18n } from '../i18n'
import ExpenseCard from '../components/ExpenseCard.vue'

const store = useExpenseStore()
const settingsStore = useSettingsStore()
const { t } = useI18n()

const currentYear = new Date().getFullYear()
const selectedYear = ref(currentYear)

const availableYears = computed(() => {
  const years = {}
  store.expenses.forEach(e => {
    if (!e.expenseDate) return
    const y = new Date(e.expenseDate).getFullYear()
    if (!years[y]) years[y] = 0
    if (['draft', 'rejected', 'paid'].includes(e.status)) {
      years[y]++
    }
  })

  const allYears = Object.keys(years).map(Number).sort((a, b) => b - a)

  if (!(currentYear in years)) {
    years[currentYear] = 0
    allYears.unshift(currentYear)
  }

  return allYears.map(y => {
    return { value: y, label: String(y), actionCount: years[y] || 0 }
  })
})

const filteredExpenses = computed(() => {
  let list = store.expenses
  if (selectedYear.value) {
    list = list.filter(e => {
      if (!e.expenseDate) return true
      return new Date(e.expenseDate).getFullYear() === selectedYear.value
    })
  }
  return [...list].sort((a, b) => {
    const catCompare = (a.category || '').localeCompare(b.category || '')
    if (catCompare !== 0) return catCompare
    const dateCompare = (a.expenseDate || '').localeCompare(b.expenseDate || '')
    if (dateCompare !== 0) return dateCompare
    return (a.createdAt || '').localeCompare(b.createdAt || '')
  })
})

const totalAmount = computed(() => {
  const sum = filteredExpenses.value.reduce((s, e) => s + (parseFloat(e.amount) || 0), 0)
  return sum.toFixed(2)
})

const sumAmount = (list) => list.reduce((s, e) => s + (parseFloat(e.amount) || 0), 0).toFixed(2)
const submittedAmount = computed(() => sumAmount(submitted.value))
const paidAmount = computed(() => sumAmount(paid.value))
const doneAmount = computed(() => sumAmount(done.value))

const drafts = computed(() => filteredExpenses.value.filter(e => e.status === 'draft'))
const submitted = computed(() => filteredExpenses.value.filter(e => e.status === 'submitted'))
const approved = computed(() => filteredExpenses.value.filter(e => e.status === 'approved'))
const rejected = computed(() => filteredExpenses.value.filter(e => e.status === 'rejected'))
const paid = computed(() => filteredExpenses.value.filter(e => e.status === 'paid'))
const done = computed(() => filteredExpenses.value.filter(e => e.status === 'done'))

onMounted(async () => {
  await settingsStore.loadSettings()
  await store.loadExpenses()
})
</script>
