<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ t('evaluation') }}</h1>
      <button v-if="allExpenses.length" class="spes-btn" @click="handleExport">{{ t('exportCsv') }}</button>
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
          <span class="spes-filter-label">{{ t('expenseNumber') }}</span>
          <input v-model="filters.expenseNumber" placeholder="Nr." type="text" class="spes-input" style="width:80px" />
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
                <th @click="toggleSort('id')" class="sortable" :class="sortClass('id')">Nr.</th>
                <th @click="toggleSort('status')" class="sortable" :class="sortClass('status')">Status</th>
                <th @click="toggleSort('expenseDate')" class="sortable" :class="sortClass('expenseDate')">{{ t('expenseDate') }}</th>
                <th @click="toggleSort('displayName')" class="sortable" :class="sortClass('displayName')">Erfasser</th>
                <th @click="toggleSort('title')" class="sortable" :class="sortClass('title')">{{ t('title') }}</th>
                <th @click="toggleSort('category')" class="sortable" :class="sortClass('category')">{{ t('category') }}</th>
                <th @click="toggleSort('amount')" class="sortable" :class="sortClass('amount')">{{ t('amount') }}</th>
                <th>Fremdw.</th>
                <th>{{ t('payoutMethod') }}</th>
                <th>{{ t('receipts') }}</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="expense in sortedExpenses" :key="expense.id">
                <td class="spes-eval-number">{{ expense.id }}</td>
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
                <td class="spes-eval-receipts">
                  <template v-if="expense.receipts && expense.receipts.length">
                    <div v-for="rec in expense.receipts" :key="rec.id" class="spes-eval-receipt-item">
                      <span class="spes-receipt-icon">
                        <svg v-if="isImage(rec.mimeType)" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                        <svg v-else width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                      </span>
                      <a :href="getReceiptDownloadUrl(expense.id, rec.id)" :download="rec.fileName" class="spes-eval-receipt-link" :title="rec.fileName"
                         @mouseenter="previewRec = rec.id" @mouseleave="previewRec = null">{{ rec.fileName }}</a>
                      <a :href="getReceiptDownloadUrl(expense.id, rec.id)" :download="rec.fileName" class="spes-eval-receipt-download" :title="t('download')">
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                      </a>
                      <div v-if="previewRec === rec.id" class="spes-receipt-preview spes-eval-receipt-preview">
                        <img v-if="isImage(rec.mimeType)" :src="getReceiptPreviewUrl(expense.id, rec.id)" :alt="rec.fileName" />
                        <div v-else-if="rec.mimeType === 'application/pdf'" class="spes-receipt-preview-pdf">
                          <span class="spes-receipt-preview-icon">&#128196;</span>
                          <span>{{ rec.fileName }}</span>
                          <a :href="getReceiptPreviewUrl(expense.id, rec.id)" target="_blank">{{ t('openPreview') }}</a>
                        </div>
                        <div v-else class="spes-receipt-preview-unknown">
                          <span class="spes-receipt-preview-icon">&#128196;</span>
                          <span>{{ rec.fileName }}</span>
                        </div>
                      </div>
                    </div>
                  </template>
                  <span v-else class="spes-empty">—</span>
                </td>
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
  { value: 'paystack', label: t('statusPaystack') },
  { value: 'done', label: t('statusDone') },
]

const filters = ref({
  status: '',
  year: '',
  userId: '',
  category: '',
  foreignCurrency: '',
  expenseNumber: '',
  search: '',
  amountFrom: null,
  amountTo: null,
})

const sortKey = ref('expenseDate')
const sortDir = ref('desc')
const previewRec = ref(null)

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
    if (filters.value.expenseNumber) {
      if (!String(e.id).includes(filters.value.expenseNumber.trim())) return false
    }
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

function formatAmount(amount, precision = 2) {
  return parseFloat(amount || 0).toFixed(precision)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH')
}

function isImage(mime) {
  return mime && mime.startsWith('image/')
}

function getReceiptDownloadUrl(expenseId, receiptId) {
  return '/index.php/apps/spesenerfassung/api/expenses/' + expenseId + '/receipts/' + receiptId + '/download'
}

function getReceiptPreviewUrl(expenseId, receiptId) {
  return '/index.php/apps/spesenerfassung/api/expenses/' + expenseId + '/receipts/' + receiptId + '/preview'
}

async function handleExport() {
  try {
    await api.exportEvaluation()
  } catch (e) {
    error.value = e.message
  }
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
