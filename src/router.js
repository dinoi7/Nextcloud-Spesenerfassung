import { createRouter, createWebHashHistory } from 'vue-router'
import Dashboard from './views/Dashboard.vue'
import ExpenseForm from './views/ExpenseForm.vue'
import ExpenseDetail from './views/ExpenseDetail.vue'
import ApprovalList from './views/ApprovalList.vue'
import SettingsView from './views/SettingsView.vue'

const routes = [
  { path: '/', name: 'dashboard', component: Dashboard },
  { path: '/new', name: 'expense-new', component: ExpenseForm },
  { path: '/expenses/:id', name: 'expense-detail', component: ExpenseDetail },
  { path: '/expenses/:id/edit', name: 'expense-edit', component: ExpenseForm, props: { edit: true } },
  { path: '/approvals', name: 'approvals', component: ApprovalList },
  { path: '/settings', name: 'settings', component: SettingsView },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

export default router
