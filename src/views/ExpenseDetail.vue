<template>
  <div class="spes-page">
    <div class="spes-page-header" v-if="expense">
      <button class="spes-btn" @click="goBack">&larr; {{ backLabel }}</button>
      <div class="spes-header-right">
        <button
          v-if="canEdit"
          class="spes-btn"
          @click="$router.push(`/expenses/${expense.id}/edit`)"
        >{{ t('edit') }}</button>
        <button
          v-if="canDelete"
          class="spes-btn spes-btn-danger"
          @click="handleDelete"
        >{{ t('delete') }}</button>
      </div>
    </div>

    <div v-if="loading" class="spes-loading">{{ t('loading') }}</div>
    <div v-else-if="!expense" class="spes-error">{{ t('error') }}</div>

    <div v-else class="spes-detail">
      <div class="spes-detail-header">
        <h1>{{ expense.title }}</h1>
        <StatusBadge :status="expense.status" class="spes-detail-status" />
      </div>

      <div class="spes-detail-grid">
        <div class="spes-detail-item">
          <span class="spes-detail-label">{{ t('amount') }}</span>
          <span class="spes-detail-value">CHF {{ formatAmount(expense.amount) }}</span>
        </div>
        <div class="spes-detail-item">
          <span class="spes-detail-label">{{ t('category') }}</span>
          <select v-if="canPay" v-model="categoryValue" @change="onCategoryChange" class="spes-input spes-input-sm">
            <option v-for="cat in settingsStore.settings.categories" :key="cat" :value="cat">{{ cat }}</option>
          </select>
          <span v-else class="spes-detail-value">{{ expense.category }}</span>
        </div>
        <div v-if="expense.foreignCurrency" class="spes-detail-item">
          <span class="spes-detail-label">{{ t('foreignAmount') }}</span>
          <span class="spes-detail-value">{{ expense.foreignCurrency }} {{ formatAmount(expense.foreignAmount) }}</span>
        </div>
        <div class="spes-detail-item">
          <span class="spes-detail-label">{{ t('expenseDate') }}</span>
          <span class="spes-detail-value">{{ formatDate(expense.expenseDate) }}</span>
        </div>
        <div v-if="(isPaystackDetail || isBookkeepingDetail) && sollKonto" class="spes-detail-item">
          <span class="spes-detail-label">{{ t('debitAccounts') }}</span>
          <span class="spes-detail-value">{{ sollKonto }}</span>
        </div>
        <div v-if="expense.payoutMethod != null" class="spes-detail-item">
          <span class="spes-detail-label">{{ t('payoutMethod') }}</span>
          <span class="spes-detail-value">{{ expense.payoutMethod === 'bank' ? t('payoutBank') : t('payoutCash') }}</span>
        </div>
      </div>

      <div v-if="expense.description" class="spes-detail-desc">
        <h3>{{ t('description') }}</h3>
        <p>{{ expense.description }}</p>
      </div>

      <div v-if="receipts.length" class="spes-detail-receipts">
        <h3>{{ t('uploadReceipt') }} ({{ receipts.length }})</h3>
        <div class="spes-receipt-list">
          <div v-for="rec in receipts" :key="rec.id" class="spes-receipt-item">
            <span class="spes-receipt-icon">
              <svg v-if="isImage(rec.mimeType)" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
              <svg v-else width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
            </span>
            <a :href="getReceiptUrl(rec)" target="_blank" class="spes-receipt-link"
               @mouseenter="previewReceipt = rec.id" @mouseleave="previewReceipt = null">
               <span>{{ rec.fileName }}</span>
               <span class="spes-receipt-meta">
                 <span v-if="rec.pageCount" class="spes-receipt-pages">{{ rec.pageCount }} {{ t('pages') }}</span>
                 <span class="spes-receipt-size">{{ formatKb(rec.size) }}</span>
               </span>
            </a>
            <a :href="getReceiptUrl(rec)" :download="rec.fileName" class="spes-receipt-download" :title="t('download')">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21 15v4a2 2 0 01-2 2H5a2 2 0 01-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
            </a>
            <div v-if="previewReceipt === rec.id" class="spes-receipt-preview">
              <img v-if="isImage(rec.mimeType)" :src="getReceiptPreviewUrl(rec)" :alt="rec.fileName" />
              <div v-else-if="rec.mimeType === 'application/pdf'" class="spes-receipt-preview-pdf">
                <span class="spes-receipt-preview-icon">&#128196;</span>
                <span>{{ rec.fileName }}</span>
                <a :href="getReceiptPreviewUrl(rec)" target="_blank" class="spes-receipt-preview-open">{{ t('openPreview') }}</a>
              </div>
              <div v-else class="spes-receipt-preview-unknown">
                <span class="spes-receipt-preview-icon">&#128196;</span>
                <span>{{ rec.fileName }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <HistoryTimeline :history="history" />

      <div v-if="isPaystackDetail && expense.payoutMethod === 'bank' && expense.iban" class="spes-payment-info">
        <h3>{{ t('paymentInfo') }}</h3>
        <div class="spes-payment-body">
          <div class="spes-payment-details">
            <span class="spes-payment-label">{{ t('paymentIban') }}</span>
            <span class="spes-payment-iban">{{ expense.iban }}</span>
            <span class="spes-payment-label">PLZ</span>
            <span class="spes-payment-value">{{ expense.plz }}</span>
            <span class="spes-payment-label">{{ t('cityLabel') }}</span>
            <span class="spes-payment-value">{{ expense.city }}</span>
            <span class="spes-payment-label">{{ t('recipient') }}</span>
            <span class="spes-payment-value">{{ expense.submitterName }}</span>
            <span class="spes-payment-label">{{ t('amount') }}</span>
            <span class="spes-payment-value">CHF {{ formatAmount(expense.amount) }}</span>
          </div>
          <SwissQrCode :iban="expense.iban" :name="expense.submitterName" :amount="expense.amount" :plz="expense.plz" :city="expense.city" :reference="'SpesenNr. ' + expense.id + ': ' + expense.title" />
        </div>
      </div>

      <div v-if="canApprove || canReject || canDone || isPaystackDetail || isBookkeepingDetail || canAddToBookkeeping" class="spes-detail-actions">
        <button v-if="canApprove" class="spes-btn spes-btn-success" @click="handleApprove">{{ t('approve') }}</button>
        <button v-if="canAddToBookkeeping" class="spes-btn spes-btn-bookkeeping" @click="handleAddToBookkeeping">{{ t('addToBookkeeping') }}</button>
        <button v-if="isBookkeepingDetail && expense.payoutMethod === 'bank'" class="spes-btn spes-btn-paystack" @click="handleAddToPaystack">{{ t('addToPaystack') }}</button>
        <button v-if="isBookkeepingDetail && expense.payoutMethod !== 'bank'" class="spes-btn spes-btn-success" @click="handlePay">{{ t('pay') }}</button>
        <button v-if="isBookkeepingDetail" class="spes-btn" @click="handleExportSingle">{{ t('exportCsv') }}</button>
        <button v-if="isPaystackDetail" class="spes-btn spes-btn-success" @click="handlePay">{{ t('pay') }}</button>
        <button v-if="canDone && !isPaystackDetail" class="spes-btn" @click="handleDone">{{ t('done') }}</button>
        <button v-if="canReject" class="spes-btn spes-btn-danger" @click="handleReject">{{ t('reject') }}</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useExpenseStore } from '../store/expenses'
import { useSettingsStore } from '../store/settings'
import { useI18n } from '../i18n'
import { api } from '../api'
import { showError, showSuccess } from '@nextcloud/dialogs'
import StatusBadge from '../components/StatusBadge.vue'
import HistoryTimeline from '../components/HistoryTimeline.vue'
import SwissQrCode from '../components/SwissQrCode.vue'

const route = useRoute()
const router = useRouter()
const store = useExpenseStore()
const settingsStore = useSettingsStore()
const { t } = useI18n()

const backLabel = computed(() => route.query.from === 'approvals' ? t('approvals') : route.query.from === 'paystack' ? t('paystack') : route.query.from === 'bookkeeping' ? t('bookkeeping') : t('dashboard'))

function goBack() {
  if (route.query.from === 'approvals') {
    router.push('/approvals')
  } else if (route.query.from === 'paystack') {
    router.push('/paystack')
  } else if (route.query.from === 'bookkeeping') {
    router.push('/bookkeeping')
  } else {
    router.push('/')
  }
}

const expense = ref(null)
const history = ref([])
const receipts = ref([])
const loading = ref(true)
const previewReceipt = ref(null)

const canEdit = computed(() => {
  if (!expense.value) return false
  return ['draft', 'rejected'].includes(expense.value.status)
})

const canDelete = computed(() => {
  if (!expense.value) return false
  return ['draft', 'rejected'].includes(expense.value.status)
})

const canApprove = computed(() => {
  if (!expense.value) return false
  return store.userIsPresident && expense.value.status === 'submitted' && parseFloat(expense.value.amount) > settingsStore.settings.threshold
})

const canReject = computed(() => {
  if (!expense.value) return false
  if (expense.value.status === 'submitted') {
    return store.userIsPresident && parseFloat(expense.value.amount) > settingsStore.settings.threshold
      || store.userIsTreasurer && parseFloat(expense.value.amount) <= settingsStore.settings.threshold
  }
  if (expense.value.status === 'approved' || expense.value.status === 'bookkeeping' || expense.value.status === 'paystack') {
    return store.userIsTreasurer
  }
  return false
})

const canPay = computed(() => {
  return false
})

const canAddToPaystack = computed(() => {
  return false
})

const canAddToBookkeeping = computed(() => {
  if (!expense.value || !store.userIsTreasurer) return false
  return expense.value.status === 'approved'
    || (expense.value.status === 'submitted' && parseFloat(expense.value.amount) <= settingsStore.settings.threshold)
})

const canDone = computed(() => {
  if (!expense.value) return false
  return expense.value.status === 'paid' && expense.value.userId === store.currentUser
})

const isPaystackDetail = computed(() => {
  if (!expense.value) return false
  return expense.value.status === 'paystack' && store.userIsTreasurer
})

const isBookkeepingDetail = computed(() => {
  if (!expense.value) return false
  return expense.value.status === 'bookkeeping' && store.userIsTreasurer
})

const sollKonto = computed(() => {
  if (!expense.value) return ''
  const accounts = settingsStore.settings.exportAccounts || {}
  return accounts[expense.value.category] || ''
})

const id = computed(() => parseInt(route.params.id))

const categoryValue = ref('')

watch(expense, (val) => {
  if (val) categoryValue.value = val.category
})

onMounted(async () => {
  await settingsStore.loadSettings()
  try {
    const data = await store.getExpense(id.value)
    expense.value = data
    history.value = data.history || []
    receipts.value = data.receipts || []
    categoryValue.value = data.category
  } catch (e) {
    expense.value = null
  } finally {
    loading.value = false
  }
})

function formatAmount(amount, precision = 2) {
  return parseFloat(amount || 0).toFixed(precision)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH')
}

function isImage(mimeType) {
  return mimeType && mimeType.startsWith('image/')
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  return (bytes / 1024).toFixed(0) + ' KB'
}

function formatKb(bytes) {
  return (bytes / 1024).toFixed(1) + ' KB'
}

function getReceiptUrl(rec) {
  return '/index.php/apps/spesenerfassung/api/expenses/' + expense.value.id + '/receipts/' + rec.id + '/download'
}

function getReceiptPreviewUrl(rec) {
  return '/index.php/apps/spesenerfassung/api/expenses/' + expense.value.id + '/receipts/' + rec.id + '/preview'
}

async function handleApprove() {
  try {
    const exp = await api.approve(id.value)
    expense.value = exp
  } catch (e) { showError(e.message) }
}

async function handleReject() {
  const reason = prompt(t('rejectConfirmation'))
  if (!reason) return
  try {
    const exp = await api.reject(id.value, reason)
    expense.value = exp
    goBack()
  } catch (e) { showError(e.message) }
}

async function handlePay() {
  try {
    const exp = await api.pay(id.value)
    expense.value = exp
    if (route.query.from === 'paystack') {
      if (exp.bookingReceipt?.message) showSuccess(exp.bookingReceipt.message)
      router.push('/paystack')
    } else if (route.query.from === 'bookkeeping') {
      if (exp.bookingReceipt?.message) showSuccess(exp.bookingReceipt.message)
      router.push('/bookkeeping')
    }
  } catch (e) { showError(e.message) }
}

async function handleAddToPaystack() {
  try {
    await api.addToPaystack(id.value)
    goBack()
  } catch (e) { showError(e.message) }
}

async function handleAddToBookkeeping() {
  try {
    await api.addToBookkeeping(id.value)
    goBack()
  } catch (e) { showError(e.message) }
}

async function handleDone() {
  try {
    const exp = await api.done(id.value)
    expense.value = exp
  } catch (e) { showError(e.message) }
}

async function handleExportSingle() {
  try {
    await api.exportPaystackSingle(id.value)
  } catch (e) { showError(e.message) }
}

async function handleDelete() {
  if (!confirm(t('confirmDelete'))) return
  await store.deleteExpense(id.value)
  router.push('/')
}

async function onCategoryChange() {
  const oldCat = expense.value.category
  try {
    const data = await api.updateExpenseCategory(id.value, categoryValue.value)
    expense.value = data
    history.value.unshift({
      action: 'category_changed',
      comment: oldCat + ' → ' + categoryValue.value,
      userId: store.currentUser,
      displayName: store.currentUser,
      createdAt: new Date().toISOString(),
    })
  } catch (e) {
    categoryValue.value = oldCat
    showError(e.message)
  }
}
</script>
