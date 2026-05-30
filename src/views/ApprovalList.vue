<template>
  <div class="spes-page">
    <div class="spes-page-header">
      <h1>{{ t('approvals') }}</h1>
    </div>

    <div v-if="loading" class="spes-loading">{{ t('loading') }}</div>
    <div v-else-if="error" class="spes-error">{{ error }}</div>
    <div v-else-if="pending.length === 0" class="spes-empty">
      <p>{{ t('noApprovals') }}</p>
    </div>

    <div v-else class="spes-card-list">
      <div v-for="expense in pending" :key="expense.id" class="spes-card" @click="$router.push(`/expenses/${expense.id}?from=approvals`)">
        <div class="spes-card-header">
          <div class="spes-card-header-left">
            <span class="spes-card-title">{{ expense.title }}</span>
            <span class="spes-card-user">{{ expense.displayName || expense.userId }}</span>
          </div>
          <span v-if="expense.receiptCount > 0" class="spes-card-receipt-badge" :title="expense.receiptCount + ' Belege'"><svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg> {{ expense.receiptCount }}</span>
          <StatusBadge :status="expense.status" />
        </div>
          <div class="spes-card-body">
          <div>
            <div class="spes-card-amount">CHF {{ formatAmount(expense.amount) }}</div>
            <div v-if="expense.foreignCurrency" class="spes-card-foreign-amount">{{ expense.foreignCurrency }} {{ formatAmount(expense.foreignAmount) }}</div>
          </div>
          <div v-if="expense.description" class="spes-card-desc">{{ expense.description }}</div>
          <div class="spes-card-meta">
            <span>{{ expense.category }}</span>
            <span>{{ formatDate(expense.expenseDate) }}</span>
            <span v-if="expense.payoutMethod" class="spes-card-payout">{{ expense.payoutMethod === 'bank' ? t('payoutBank') : t('payoutCash') }}</span>
          </div>
        </div>
        <div class="spes-card-actions" @click.stop>
          <button v-if="userIsPresident && expense.status === 'submitted'" class="spes-btn spes-btn-sm spes-btn-success" @click="approveExpense(expense.id)">{{ t('approve') }}</button>
          <button v-if="canReject(expense)" class="spes-btn spes-btn-sm spes-btn-danger" @click="rejectExpense(expense.id)">{{ t('reject') }}</button>
          <button v-if="canPay(expense)" class="spes-btn spes-btn-sm spes-btn-success" @click="payExpense(expense.id)">{{ t('pay') }}</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useExpenseStore } from '../store/expenses'
import { useSettingsStore } from '../store/settings'
import { useI18n } from '../i18n'
import { api } from '../api'
import StatusBadge from '../components/StatusBadge.vue'

const router = useRouter()
const store = useExpenseStore()
const settingsStore = useSettingsStore()
const { t } = useI18n()

const pending = ref([])
const loading = ref(true)
const error = ref(null)

const userIsPresident = computed(() => store.userIsPresident)
const userIsTreasurer = computed(() => store.userIsTreasurer)

function canReject(expense) {
  if (expense.status === 'submitted') {
    return store.userIsPresident && parseFloat(expense.amount) > settingsStore.settings.threshold
      || store.userIsTreasurer && parseFloat(expense.amount) <= settingsStore.settings.threshold
  }
  if (expense.status === 'approved') return store.userIsTreasurer
  return false
}

function canPay(expense) {
  if (!store.userIsTreasurer) return false
  return expense.status === 'submitted' && parseFloat(expense.amount) <= settingsStore.settings.threshold
    || expense.status === 'approved'
}

function formatAmount(amount) {
  return parseFloat(amount || 0).toFixed(2)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleDateString('de-CH')
}

async function loadPending() {
  loading.value = true
  error.value = null
  try {
    pending.value = await api.getPendingApprovals()
  } catch (e) {
    error.value = e.message
  } finally {
    loading.value = false
  }
}

async function approveExpense(id) {
  try {
    await api.approve(id)
    await loadPending()
  } catch (e) { alert(e.message) }
}

async function rejectExpense(id) {
  const reason = prompt(t('rejectConfirmation'))
  if (!reason) return
  try {
    await api.reject(id, reason)
    await loadPending()
  } catch (e) { alert(e.message) }
}

async function payExpense(id) {
  try {
    await api.pay(id)
    await loadPending()
  } catch (e) { alert(e.message) }
}

onMounted(async () => {
  await settingsStore.loadSettings()
  await loadPending()
})
</script>
