<template>
  <div v-if="iban && amount" class="spes-qr-code">
    <span class="spes-qr-label">{{ t('scanQr') }}</span>
    <img v-if="qrDataUrl" :src="qrDataUrl" alt="QR-Bill" class="spes-qr-image" />
  </div>
</template>

<script setup>
import { ref, computed, watch, onMounted } from 'vue'
import QRCode from 'qrcode'
import { useI18n } from '../i18n'

const props = defineProps({
  iban: { type: String, required: true },
  name: { type: String, default: '' },
  amount: { type: [Number, String], required: true },
  reference: { type: String, default: '' },
})

const { t } = useI18n()
const qrDataUrl = ref('')

function buildSwissQrPayload() {
  const iban = props.iban.replace(/\s+/g, '')
  const amt = parseFloat(props.amount || 0).toFixed(2)
  const ref = props.reference || ''
  const name = props.name || ''

  return [
    'SPC',
    '0200',
    '1',
    iban,
    'K',
    name,
    'Adresse nicht erfasst',
    '0000 Ort',
    '',
    '',
    'CH',
    '',
    '',
    '',
    '',
    '',
    '',
    '',
    amt,
    'CHF',
    'K',
    'SpesenErfassung',
    '',
    '0000 Ort',
    '',
    '',
    'CH',
    'NON',
    '',
    ref,
    'EPD',
  ].join('\n')
}

async function generate() {
  try {
    const payload = buildSwissQrPayload()
    qrDataUrl.value = await QRCode.toDataURL(payload, {
      width: 256,
      margin: 2,
      color: { dark: '#000000', light: '#ffffff' },
    })
  } catch (e) {
    console.error('QR generation failed', e)
  }
}

onMounted(generate)
watch(() => [props.iban, props.name, props.amount, props.reference], generate)
</script>
