import { defineStore } from 'pinia'
import { ref, computed } from 'vue'
import { api } from '../api'

function getInitialData() {
  try {
    const el = document.getElementById('spesenerfassung-initial-data')
    if (el && el.textContent) {
      return JSON.parse(el.textContent)
    }
  } catch {}
  return {}
}

export const useExpenseStore = defineStore('expenses', () => {
  const initial = getInitialData()
  const expenses = ref([])
  const loading = ref(false)
  const error = ref(null)

  const currentUser = ref(initial.currentUser || '')
  const userIsAdmin = ref(initial.isAdmin || false)
  const userIsPresident = ref(initial.isPresident ?? (currentUser.value === (initial.settings?.presidentUid || '') && currentUser.value !== ''))
  const userIsTreasurer = ref(initial.isTreasurer ?? (currentUser.value === (initial.settings?.treasurerUid || '') && currentUser.value !== ''))

  function updateRoles(settings) {
    userIsPresident.value = currentUser.value === settings.presidentUid
    userIsTreasurer.value = currentUser.value === settings.treasurerUid
  }

  async function loadExpenses() {
    loading.value = true
    error.value = null
    try {
      expenses.value = await api.getExpenses()
    } catch (e) {
      error.value = e.message
    } finally {
      loading.value = false
    }
  }

  async function createExpense(data) {
    const exp = await api.createExpense(data)
    expenses.value.unshift(exp)
    return exp
  }

  async function updateExpense(id, data) {
    const exp = await api.updateExpense(id, data)
    const idx = expenses.value.findIndex(e => e.id === id)
    if (idx >= 0) expenses.value[idx] = exp
    return exp
  }

  async function deleteExpense(id) {
    await api.deleteExpense(id)
    expenses.value = expenses.value.filter(e => e.id !== id)
  }

  async function submitExpense(id) {
    const exp = await api.submit(id)
    const idx = expenses.value.findIndex(e => e.id === id)
    if (idx >= 0) expenses.value[idx] = exp
    return exp
  }

  async function getExpense(id) {
    return await api.getExpense(id)
  }

  const filteredExpenses = computed(() => expenses.value)

  const draftExpenses = computed(() =>
    expenses.value.filter(e => e.status === 'draft' || e.status === 'rejected')
  )

  const submittedExpenses = computed(() =>
    expenses.value.filter(e => !['draft', 'rejected', 'done'].includes(e.status))
  )

  const doneExpenses = computed(() =>
    expenses.value.filter(e => e.status === 'done')
  )

  const actionCount = computed(() =>
    expenses.value.filter(e => ['draft', 'rejected', 'paid'].includes(e.status)).length
  )

  const approvalCount = ref(0)
  const bookkeepingCount = ref(0)
  const paystackCount = ref(0)

  async function loadApprovalCount() {
    try {
      const pending = await api.getPendingApprovals()
      approvalCount.value = Array.isArray(pending) ? pending.length : 0
    } catch {
      approvalCount.value = 0
    }
  }

  async function loadBookkeepingCount() {
    try {
      const data = await api.getBookkeeping()
      bookkeepingCount.value = Array.isArray(data) ? data.length : 0
    } catch {
      bookkeepingCount.value = 0
    }
  }

  async function loadPaystackCount() {
    try {
      const data = await api.getPaystack()
      paystackCount.value = Array.isArray(data) ? data.length : 0
    } catch {
      paystackCount.value = 0
    }
  }

  return {
    expenses, loading, error, currentUser,
    userIsPresident, userIsTreasurer, userIsAdmin,
    updateRoles,
    loadExpenses, createExpense, updateExpense, deleteExpense,
    submitExpense, getExpense,
    filteredExpenses, draftExpenses, submittedExpenses, doneExpenses,
    actionCount, approvalCount, bookkeepingCount, paystackCount, loadApprovalCount, loadBookkeepingCount, loadPaystackCount,
  }
})
