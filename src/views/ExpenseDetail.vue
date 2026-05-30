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
        <div v-if="expense.payoutMethod" class="spes-detail-item">
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
            <a :href="getReceiptUrl(rec)" target="_blank" class="spes-receipt-link">
              <span>{{ rec.fileName }}</span>
              <span class="spes-receipt-size">{{ formatSize(rec.size) }}</span>
            </a>
          </div>
        </div>
      </div>

      <HistoryTimeline :history="history" />

      <div v-if="canPay && expense.payoutMethod === 'bank' && expense.iban" class="spes-payment-info">
        <h3>{{ t('paymentInfo') }}</h3>
        <div class="spes-payment-body">
          <div class="spes-payment-details">
            <span class="spes-payment-label">{{ t('paymentIban') }}</span>
            <span class="spes-payment-iban">{{ expense.iban }}</span>
            <span class="spes-payment-label">{{ t('recipient') }}</span>
            <span class="spes-payment-value">{{ expense.submitterName }}</span>
            <span class="spes-payment-label">{{ t('amount') }}</span>
            <span class="spes-payment-value">CHF {{ formatAmount(expense.amount) }}</span>
          </div>
          <SwissQrCode :iban="expense.iban" :name="expense.submitterName" :amount="expense.amount" :reference="'SpesenNr. ' + expense.id + ': ' + expense.title" />
        </div>
      </div>

      <div v-if="canApprove || canReject || canPay || canDone" class="spes-detail-actions">
        <button v-if="canApprove" class="spes-btn spes-btn-success" @click="handleApprove">{{ t('approve') }}</button>
        <button v-if="canReject" class="spes-btn spes-btn-danger" @click="handleReject">{{ t('reject') }}</button>
        <button v-if="canPay" class="spes-btn spes-btn-success" @click="handlePay">{{ t('pay') }}</button>
        <button v-if="canDone" class="spes-btn" @click="handleDone">{{ t('done') }}</button>
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
import StatusBadge from '../components/StatusBadge.vue'
import HistoryTimeline from '../components/HistoryTimeline.vue'
import SwissQrCode from '../components/SwissQrCode.vue'

const route = useRoute()
const router = useRouter()
const store = useExpenseStore()
const settingsStore = useSettingsStore()
const { t } = useI18n()

const backLabel = computed(() => route.query.from === 'approvals' ? t('approvals') : t('dashboard'))

function goBack() {
  if (route.query.from === 'approvals') {
    router.push('/approvals')
  } else {
    router.push('/')
  }
}

const expense = ref(null)
const history = ref([])
const receipts = ref([])
const loading = ref(true)

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
  if (expense.value.status === 'approved') {
    return store.userIsTreasurer
  }
  return false
})

const canPay = computed(() => {
  if (!expense.value) return false
  return store.userIsTreasurer && (expense.value.status === 'submitted' && parseFloat(expense.value.amount) <= settingsStore.settings.threshold
    || expense.value.status === 'approved')
})

const canDone = computed(() => {
  if (!expense.value) return false
  return expense.value.status === 'paid'
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

function formatAmount(amount) {
  return parseFloat(amount || 0).toFixed(2)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH')
}

function formatSize(bytes) {
  if (bytes < 1024) return bytes + ' B'
  return (bytes / 1024).toFixed(0) + ' KB'
}

function getReceiptUrl(rec) {
  return '/index.php/apps/spesenerfassung/api/expenses/' + expense.value.id + '/receipts/' + rec.id + '/download'
}

async function handleApprove() {
  try {
    const exp = await api.approve(id.value)
    expense.value = exp
  } catch (e) { alert(e.message) }
}

async function handleReject() {
  const reason = prompt(t('rejectConfirmation'))
  if (!reason) return
  try {
    const exp = await api.reject(id.value, reason)
    expense.value = exp
    router.push('/')
  } catch (e) { alert(e.message) }
}

async function handlePay() {
  try {
    const exp = await api.pay(id.value)
    expense.value = exp
  } catch (e) { alert(e.message) }
}

async function handleDone() {
  try {
    const exp = await api.done(id.value)
    expense.value = exp
  } catch (e) { alert(e.message) }
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
    alert(e.message)
  }
}
</script>
