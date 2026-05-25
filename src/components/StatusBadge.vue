<template>
  <span class="spes-badge" :class="'spes-badge--' + status">
    {{ label }}
  </span>
</template>

<script setup>
import { computed } from 'vue'
import { useI18n } from '../i18n'

const props = defineProps({
  status: { type: String, required: true },
})

const { t } = useI18n()

const label = computed(() => {
  const map = {
    draft: 'statusDraft',
    submitted: 'statusSubmitted',
    approved: 'statusApproved',
    rejected: 'statusRejected',
    paid: 'statusPaid',
    done: 'statusDone',
  }
  return t(map[props.status] || props.status)
})
</script>
