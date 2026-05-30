import { createRouter, createWebHashHistory } from 'vue-router'
import Dashboard from './views/Dashboard.vue'
import ExpenseForm from './views/ExpenseForm.vue'
import ExpenseDetail from './views/ExpenseDetail.vue'
import ApprovalList from './views/ApprovalList.vue'
import SettingsView from './views/SettingsView.vue'
import UserSettings from './views/UserSettings.vue'
import EvaluationView from './views/EvaluationView.vue'

const routes = [
  { path: '/', name: 'dashboard', component: Dashboard },
  { path: '/new', name: 'expense-new', component: ExpenseForm },
  { path: '/expenses/:id', name: 'expense-detail', component: ExpenseDetail },
  { path: '/expenses/:id/edit', name: 'expense-edit', component: ExpenseForm, props: { edit: true } },
  { path: '/approvals', name: 'approvals', component: ApprovalList },
  { path: '/settings', name: 'settings', component: SettingsView },
  { path: '/profile', name: 'profile', component: UserSettings },
  { path: '/evaluation', name: 'evaluation', component: EvaluationView },
]

const router = createRouter({
  history: createWebHashHistory(),
  routes,
})

export default router
