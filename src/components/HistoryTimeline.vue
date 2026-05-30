<template>
  <div class="spes-history" v-if="historyEntries.length">
    <h3>{{ t('history') }}</h3>
    <div class="spes-history-table-wrap">
      <table class="spes-history-table">
        <thead>
          <tr>
            <th>{{ t('status') }}</th>
            <th>{{ t('byPrefix') }}</th>
            <th>{{ t('onDate') }}</th>
            <th>{{ t('reason') }}</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(entry, idx) in historyEntries" :key="idx">
            <td><span class="spes-timeline-action" :class="'spes-timeline-action--' + entry.action">{{ actionLabel(entry.action) }}</span></td>
            <td>{{ entry.displayName || entry.userId }}</td>
            <td class="spes-history-date">{{ formatDate(entry.createdAt) }}</td>
            <td class="spes-history-comment">{{ entry.comment || '—' }}</td>
          </tr>
        </tbody>
      </table>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from '../i18n'

const props = defineProps({
  history: { type: Array, default: () => [] },
})

const { t } = useI18n()

const historyEntries = computed(() => [...props.history].reverse())

function actionLabel(action) {
  const map = {
    submitted: 'statusSubmitted',
    approved: 'statusApproved',
    rejected: 'statusRejected',
    paid: 'statusPaid',
    paystack: 'toPaystack',
    done: 'statusDone',
    category_changed: 'categoryChanged',
  }
  return t(map[action] || action)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleString('de-CH')
}
</script>
