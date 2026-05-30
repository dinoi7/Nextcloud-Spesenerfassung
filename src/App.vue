<template>
  <div class="spes-app">
    <header class="spes-header">
      <div class="spes-header-left">
        <router-link to="/" class="spes-logo">
          <svg width="24" height="24" viewBox="0 0 32 32" fill="none">
            <rect width="32" height="32" rx="4" fill="var(--color-primary)"/>
            <path d="M8 10h4v12H8V10zm6-4h4v16h-4V6zm6 6h4v8h-4v-8z" fill="#fff"/>
          </svg>
          <span>Spesenerfassung</span>
        </router-link>
      </div>
      <nav class="spes-nav">
        <router-link to="/" class="spes-nav-link" active-class="spes-nav-link--active">
          <span class="spes-nav-icon">&#9776;</span>
          <span class="spes-nav-label">{{ t('dashboard') }}</span>
          <span v-if="expenseStore.actionCount > 0" class="spes-nav-badge">{{ expenseStore.actionCount }}</span>
        </router-link>
        <router-link v-if="isReviewer" to="/approvals" class="spes-nav-link" active-class="spes-nav-link--active">
          <span class="spes-nav-icon">&#10003;</span>
          <span class="spes-nav-label">{{ t('approvals') }}</span>
          <span v-if="expenseStore.approvalCount > 0" class="spes-nav-badge">{{ expenseStore.approvalCount }}</span>
        </router-link>
        <router-link v-if="isReviewer" to="/evaluation" class="spes-nav-link" active-class="spes-nav-link--active">
          <span class="spes-nav-icon">&#128202;</span>
          <span class="spes-nav-label">{{ t('evaluation') }}</span>
        </router-link>
        <router-link v-if="expenseStore.userIsTreasurer" to="/paystack" class="spes-nav-link" active-class="spes-nav-link--active">
          <span class="spes-nav-icon">&#128179;</span>
          <span class="spes-nav-label">{{ t('paystack') }}</span>
        </router-link>
        <router-link to="/profile" class="spes-nav-link" active-class="spes-nav-link--active">
          <span class="spes-nav-icon">&#128100;</span>
          <span class="spes-nav-label">{{ t('profile') }}</span>
        </router-link>
        <router-link v-if="isAdmin" to="/settings" class="spes-nav-link" active-class="spes-nav-link--active">
          <span class="spes-nav-icon">&#9881;</span>
          <span class="spes-nav-label">{{ t('settings') }}</span>
        </router-link>
      </nav>
    </header>
    <main class="spes-main">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useExpenseStore } from './store/expenses'
import { useI18n } from './i18n'

const { t } = useI18n()
const expenseStore = useExpenseStore()

const isAdmin = computed(() => expenseStore.userIsAdmin)
const isReviewer = computed(() => expenseStore.userIsPresident || expenseStore.userIsTreasurer)

onMounted(() => {
  if (expenseStore.userIsPresident || expenseStore.userIsTreasurer) {
    expenseStore.loadApprovalCount()
  }
})
</script>
