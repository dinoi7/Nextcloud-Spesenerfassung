<template>
  <div class="spes-upload" :class="{ 'spes-upload--loading': uploading || localUploading }">
    <div v-if="uploading || localUploading" class="spes-upload-spinner-overlay">
      <div class="spes-upload-spinner"></div>
      <span class="spes-upload-spinner-text">{{ t('uploading') }}</span>
    </div>
    <div v-if="receipts.length >= 5" class="spes-upload-max">
      <p>{{ t('maxReceipts') }}</p>
    </div>
    <div v-else class="spes-upload-area" @click="$refs.fileInput.click()" @drop.prevent="handleDrop" @dragover.prevent>
      <span class="spes-upload-icon"><svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="var(--color-primary, #0082c9)" stroke-width="2" stroke-linecap="round"><path d="M21.44 11.05l-9.19 9.19a6 6 0 01-8.49-8.49l9.19-9.19a4 4 0 015.66 5.66l-9.2 9.19a2 2 0 01-2.83-2.83l8.49-8.48"/></svg></span>
      <p>{{ t('dropFiles') }}</p>
      <p class="spes-upload-hint">{{ t('fileInfo') }}</p>
      <input ref="fileInput" type="file" accept=".pdf,.jpg,.jpeg,.png,image/*" :capture="isMobile ? 'environment' : null" class="spes-upload-input" @change="handleFileSelect" />
      <button type="button" class="spes-btn spes-btn-sm" @click.stop="$refs.fileInput.click()">{{ t('uploadReceipt') }} ({{ receipts.length }}/5)</button>
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
import { ref, computed, watch, nextTick } from 'vue'
import { useI18n } from '../i18n'

const props = defineProps({
  expenseId: { type: Number, default: null },
  receipts: { type: Array, default: () => [] },
  uploading: { type: Boolean, default: false },
})

const emit = defineEmits(['uploaded', 'delete', 'file'])
const { t } = useI18n()

const isMobile = computed(() => /Mobi|Android/i.test(navigator.userAgent))

const localUploading = ref(false)

watch(() => props.receipts.length, () => {
  localUploading.value = false
})

watch(() => props.uploading, (val) => {
  if (!val) localUploading.value = false
})

async function handleFileSelect(e) {
  const file = e.target.files?.[0]
  if (file) {
    localUploading.value = true
    await nextTick()
    await new Promise(r => requestAnimationFrame(r))
    emit('file', file)
  }
  e.target.value = ''
}

async function handleDrop(e) {
  const file = e.dataTransfer?.files?.[0]
  if (file) {
    localUploading.value = true
    await nextTick()
    await new Promise(r => requestAnimationFrame(r))
    emit('file', file)
  }
}
</script>
