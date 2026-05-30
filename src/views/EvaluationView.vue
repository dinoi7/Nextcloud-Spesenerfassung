<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ t('evaluation') }}</h1>
    </div>

    <div v-if="loading" class="spes-loading">{{ t('loading') }}</div>
    <div v-else-if="error" class="spes-error">{{ error }}</div>
    <template v-else>
      <div class="spes-evaluation-filters">
        <label class="spes-filter-group">
          <span class="spes-filter-label">{{ t('status') }}</span>
          <select v-model="filters.status">
            <option value="">{{ t('all') }}</option>
            <option v-for="s in statuses" :key="s.value" :value="s.value">{{ s.label }}</option>
          </select>
        </label>
        <label class="spes-filter-group">
          <span class="spes-filter-label">{{ t('year') }}</span>
          <select v-model="filters.year">
            <option value="">{{ t('all') }}</option>
            <option v-for="y in availableYears" :key="y" :value="y">{{ y }}</option>
          </select>
        </label>
        <label class="spes-filter-group">
          <span class="spes-filter-label">{{ t('submitter') }}</span>
          <select v-model="filters.userId">
            <option value="">{{ t('all') }}</option>
            <option v-for="u in availableUsers" :key="u.value" :value="u.value">{{ u.label }}</option>
          </select>
        </label>
        <label class="spes-filter-group">
          <span class="spes-filter-label">{{ t('category') }}</span>
          <select v-model="filters.category">
            <option value="">{{ t('all') }}</option>
            <option v-for="c in availableCategories" :key="c" :value="c">{{ c }}</option>
          </select>
        </label>
        <label class="spes-filter-group">
          <span class="spes-filter-label">{{ t('foreignCurrency') }}</span>
          <select v-model="filters.foreignCurrency">
            <option value="">{{ t('all') }}</option>
            <option v-for="c in availableCurrencies" :key="c" :value="c">{{ c }}</option>
          </select>
        </label>
        <label class="spes-filter-group">
          <span class="spes-filter-label">{{ t('search') }}</span>
          <input v-model="filters.search" :placeholder="t('filterByTitle')" type="text" />
        </label>
        <label class="spes-filter-group">
          <span class="spes-filter-label">{{ t('amountFrom') }}</span>
          <input v-model.number="filters.amountFrom" type="number" step="0.01" min="0" class="spes-input" style="width:120px" />
        </label>
        <label class="spes-filter-group">
          <span class="spes-filter-label">{{ t('amountTo') }}</span>
          <input v-model.number="filters.amountTo" type="number" step="0.01" min="0" class="spes-input" style="width:120px" />
        </label>
      </div>

      <div v-if="filteredExpenses.length === 0" class="spes-empty">
        <p>{{ t('noExpenses') }}</p>
      </div>
      <template v-else>
        <div class="spes-history-table-wrap">
          <table class="spes-history-table spes-evaluation-table">
            <thead>
              <tr>
                <th @click="toggleSort('status')" class="sortable" :class="sortClass('status')">Status</th>
                <th @click="toggleSort('expenseDate')" class="sortable" :class="sortClass('expenseDate')">{{ t('expenseDate') }}</th>
                <th @click="toggleSort('displayName')" class="sortable" :class="sortClass('displayName')">Erfasser</th>
                <th @click="toggleSort('title')" class="sortable" :class="sortClass('title')">{{ t('title') }}</th>
                <th @click="toggleSort('category')" class="sortable" :class="sortClass('category')">{{ t('category') }}</th>
                <th @click="toggleSort('amount')" class="sortable" :class="sortClass('amount')">{{ t('amount') }}</th>
                <th>Fremdw.</th>
                <th>{{ t('payoutMethod') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="expense in sortedExpenses" :key="expense.id">
                <td><StatusBadge :status="expense.status" /></td>
                <td class="spes-history-date">{{ formatDate(expense.expenseDate) }}</td>
                <td>{{ expense.displayName || expense.userId }}</td>
                <td>{{ expense.title }}</td>
                <td>{{ expense.category }}</td>
                <td>CHF {{ formatAmount(expense.amount) }}</td>
                <td>
                  <span v-if="expense.foreignCurrency">{{ expense.foreignCurrency }} {{ formatAmount(expense.foreignAmount) }}</span>
                </td>
                <td>{{ expense.payoutMethod === 'bank' ? t('payoutBank') : (expense.payoutMethod ? t('payoutCash') : '') }}</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="spes-evaluation-summary">
          <span>{{ t('totalCount') }}: <strong>{{ filteredExpenses.length }}</strong></span>
          <span>{{ t('totalSum') }}: <strong>CHF {{ totalSum }}</strong></span>
        </div>
      </template>
    </template>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useI18n } from '../i18n'
import { api } from '../api'
import StatusBadge from '../components/StatusBadge.vue'

const { t } = useI18n()

const allExpenses = ref([])
const loading = ref(true)
const error = ref(null)

const statuses = [
  { value: 'draft', label: t('statusDraft') },
  { value: 'submitted', label: t('statusSubmitted') },
  { value: 'approved', label: t('statusApproved') },
  { value: 'rejected', label: t('statusRejected') },
  { value: 'paid', label: t('statusPaid') },
  { value: 'done', label: t('statusDone') },
]

const filters = ref({
  status: '',
  year: '',
  userId: '',
  category: '',
  foreignCurrency: '',
  search: '',
  amountFrom: null,
  amountTo: null,
})

const sortKey = ref('expenseDate')
const sortDir = ref('desc')

const availableYears = computed(() => {
  const years = new Set()
  allExpenses.value.forEach(e => {
    if (e.expenseDate) {
      years.add(new Date(e.expenseDate).getFullYear())
    }
  })
  return [...years].sort((a, b) => b - a)
})

const availableUsers = computed(() => {
  const userMap = {}
  allExpenses.value.forEach(e => {
    if (!userMap[e.userId]) {
      userMap[e.userId] = e.displayName || e.userId
    }
  })
  return Object.entries(userMap)
    .map(([uid, name]) => ({ value: uid, label: name }))
    .sort((a, b) => a.label.localeCompare(b.label))
})

const availableCategories = computed(() => {
  const cats = new Set()
  allExpenses.value.forEach(e => { if (e.category) cats.add(e.category) })
  return [...cats].sort()
})

const availableCurrencies = computed(() => {
  const curs = new Set()
  allExpenses.value.forEach(e => { if (e.foreignCurrency) curs.add(e.foreignCurrency) })
  return [...curs].sort()
})

const filteredExpenses = computed(() => {
  return allExpenses.value.filter(e => {
    if (filters.value.status && e.status !== filters.value.status) return false
    if (filters.value.year) {
      if (!e.expenseDate) return false
      if (new Date(e.expenseDate).getFullYear() !== Number(filters.value.year)) return false
    }
    if (filters.value.userId && e.userId !== filters.value.userId) return false
    if (filters.value.category && e.category !== filters.value.category) return false
    if (filters.value.foreignCurrency && e.foreignCurrency !== filters.value.foreignCurrency) return false
    if (filters.value.amountFrom !== null && filters.value.amountFrom !== '' && parseFloat(e.amount || 0) < parseFloat(filters.value.amountFrom)) return false
    if (filters.value.amountTo !== null && filters.value.amountTo !== '' && parseFloat(e.amount || 0) > parseFloat(filters.value.amountTo)) return false
    if (filters.value.search) {
      const q = filters.value.search.toLowerCase()
      const title = (e.title || '').toLowerCase()
      const desc = (e.description || '').toLowerCase()
      if (!title.includes(q) && !desc.includes(q)) return false
    }
    return true
  })
})

const sortedExpenses = computed(() => {
  const list = [...filteredExpenses.value]
  const key = sortKey.value
  const dir = sortDir.value === 'asc' ? 1 : -1
  list.sort((a, b) => {
    let va = a[key]
    let vb = b[key]
    if (va == null) va = ''
    if (vb == null) vb = ''
    if (typeof va === 'number' && typeof vb === 'number') {
      return (va - vb) * dir
    }
    va = String(va)
    vb = String(vb)
    return va.localeCompare(vb) * dir
  })
  return list
})

const totalSum = computed(() => {
  const sum = filteredExpenses.value.reduce((s, e) => s + (parseFloat(e.amount) || 0), 0)
  return sum.toFixed(2)
})

function toggleSort(key) {
  if (sortKey.value === key) {
    sortDir.value = sortDir.value === 'asc' ? 'desc' : 'asc'
  } else {
    sortKey.value = key
    sortDir.value = 'asc'
  }
}

function sortClass(key) {
  if (sortKey.value !== key) return ''
  return sortDir.value === 'asc' ? 'sort-asc' : 'sort-desc'
}

function formatAmount(amount) {
  return parseFloat(amount || 0).toFixed(2)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH')
}

onMounted(async () => {
  try {
    const data = await api.getEvaluation()
    allExpenses.value = Array.isArray(data) ? data : []
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
})
</script>
