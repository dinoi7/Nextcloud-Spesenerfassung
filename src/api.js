const BASE = '/index.php/apps/spesenerfassung/api'

function getHeaders() {
  const headers = {
    'Content-Type': 'application/json',
    'Accept': 'application/json',
    'X-Requested-With': 'XMLHttpRequest',
  }
  const token = document.querySelector('head meta[name="csrf-token"]')?.getAttribute('content')
  if (token) {
    headers['requesttoken'] = token
  }
  return headers
}

async function request(method, url, body = null) {
  const opts = {
    method,
    headers: getHeaders(),
  }
  if (body) {
    if (body instanceof FormData) {
      delete opts.headers['Content-Type']
      opts.body = body
    } else {
      opts.body = JSON.stringify(body)
    }
  }
  const response = await fetch(BASE + url, opts)

  let data = null
  try {
    data = await response.json()
  } catch {
    data = { error: 'Server error: ' + response.status }
  }

  if (!response.ok) {
    throw new Error(data?.error || data?.message || 'Request failed (HTTP ' + response.status + ')')
  }
  return data
}

export const api = {
  getExpenses: () => request('GET', '/expenses'),
  getExpense: (id) => request('GET', `/expenses/${id}`),
  createExpense: (data) => request('POST', '/expenses', data),
  updateExpense: (id, data) => request('PUT', `/expenses/${id}`, data),
  deleteExpense: (id) => request('DELETE', `/expenses/${id}`),

  uploadReceipt: (id, file) => {
    const fd = new FormData()
    fd.append('receipt', file)
    return request('POST', `/expenses/${id}/receipts`, fd)
  },
  deleteReceipt: (expenseId, receiptId) => request('DELETE', `/expenses/${expenseId}/receipts/${receiptId}`),

  submit: (id) => request('POST', `/expenses/${id}/submit`),
  approve: (id) => request('POST', `/expenses/${id}/approve`),
  reject: (id, reason) => request('POST', `/expenses/${id}/reject`, { reason }),
  pay: (id) => request('POST', `/expenses/${id}/pay`),
  done: (id) => request('POST', `/expenses/${id}/done`),

  getPendingApprovals: () => request('GET', '/approvals/pending'),
  getSettings: () => request('GET', '/settings'),
  updateSettings: (data) => request('PUT', '/settings', data),
  getUserSettings: () => request('GET', '/settings/user'),
  updateUserSettings: (data) => request('PUT', '/settings/user', data),
  getCategories: () => request('GET', '/categories'),
  createCategory: (name) => request('POST', '/categories', { name }),
  updateCategory: (id, name) => request('PUT', `/categories/${id}`, { name }),
  deleteCategory: (id) => request('DELETE', `/categories/${id}`),
  getUsers: () => request('GET', '/users'),
}
