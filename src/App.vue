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
        </router-link>
        <router-link v-if="isReviewer" to="/approvals" class="spes-nav-link" active-class="spes-nav-link--active">
          <span class="spes-nav-icon">&#10003;</span>
          <span class="spes-nav-label">{{ t('approvals') }}</span>
        </router-link>
        <router-link v-if="isAdmin" to="/settings" class="spes-nav-link" active-class="spes-nav-link--active">
          <span class="spes-nav-icon">&#9881;</span>
          <span class="spes-nav-label">{{ t('settings') }}</span>
        </router-link>
      </nav>
      <div class="spes-header-right">
        <button class="spes-lang-btn" @click="toggleLang" :title="lang === 'de' ? 'Switch to English' : 'Zu Deutsch wechseln'">
          {{ lang.toUpperCase() }}
        </button>
      </div>
    </header>
    <main class="spes-main">
      <router-view />
    </main>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useExpenseStore } from './store/expenses'
import { useSettingsStore } from './store/settings'
import { useI18n } from './i18n'

const { t, lang } = useI18n()
const expenseStore = useExpenseStore()
const settingsStore = useSettingsStore()

const isAdmin = computed(() => expenseStore.userIsAdmin)
const isReviewer = computed(() => expenseStore.userIsPresident || expenseStore.userIsTreasurer)

function toggleLang() {
  localStorage.setItem('spesenerfassung_lang', lang.value === 'de' ? 'en' : 'de')
  location.reload()
}
</script>
