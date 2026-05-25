<template>
  <div class="spes-timeline" v-if="historyEntries.length">
    <h3>{{ t('history') }}</h3>
    <div class="spes-timeline-list">
      <div v-for="(entry, idx) in historyEntries" :key="idx" class="spes-timeline-item">
        <span class="spes-timeline-action" :class="'spes-timeline-action--' + entry.action">
          {{ actionLabel(entry.action) }}
        </span>
        <span class="spes-timeline-user">{{ t('byPrefix') }} {{ entry.userId }}</span>
        <span class="spes-timeline-date">{{ formatDate(entry.createdAt) }}</span>
        <span v-if="entry.comment" class="spes-timeline-comment">{{ entry.comment }}</span>
      </div>
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

const historyEntries = computed(() => {
  return [...props.history].reverse()
})

function actionLabel(action) {
  const map = {
    submitted: 'statusSubmitted',
    approved: 'statusApproved',
    rejected: 'statusRejected',
    paid: 'statusPaid',
    done: 'statusDone',
  }
  return t(map[action] || action)
}

function formatDate(dateStr) {
  if (!dateStr) return ''
  const d = new Date(dateStr)
  return d.toLocaleString('de-CH')
}
</script>
