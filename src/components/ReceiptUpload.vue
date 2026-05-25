<template>
  <div class="spes-upload">
    <div class="spes-upload-area" @click="$refs.fileInput.click()" @drop.prevent="handleDrop" @dragover.prevent>
      <span class="spes-upload-icon">&#128206;</span>
      <p>{{ t('dropFiles') }}</p>
      <p class="spes-upload-hint">{{ t('fileInfo') }}</p>
      <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*" :capture="isMobile ? 'environment' : null" class="spes-upload-input" @change="handleFileSelect" />
      <button type="button" class="spes-btn spes-btn-sm" @click.stop="$refs.fileInput.click()">{{ t('uploadReceipt') }}</button>
    </div>

    <div v-if="receipts.length" class="spes-receipt-list">
      <div v-for="rec in receipts" :key="rec.id" class="spes-receipt-item">
        <span>{{ rec.fileName }}</span>
        <button class="spes-receipt-delete" @click="$emit('delete', rec.id)">&times;</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { api } from '../api'
import { useI18n } from '../i18n'

const props = defineProps({
  expenseId: { type: Number, required: true },
  receipts: { type: Array, default: () => [] },
})

const emit = defineEmits(['uploaded', 'delete'])
const { t } = useI18n()

const isMobile = computed(() => /Mobi|Android/i.test(navigator.userAgent))

async function handleFileSelect(e) {
  const file = e.target.files?.[0]
  if (file) await uploadFile(file)
  e.target.value = ''
}

async function handleDrop(e) {
  const file = e.dataTransfer?.files?.[0]
  if (file) await uploadFile(file)
}

async function uploadFile(file) {
  try {
    const receipt = await api.uploadReceipt(props.expenseId, file)
    emit('uploaded', receipt)
  } catch (e) {
    alert(e.message)
  }
}
</script>
