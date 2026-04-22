<script setup>
import { onMounted, ref, watch } from 'vue'
import QRCode from 'qrcode'

/**
 * Renders a QR code for an arbitrary URL to a <canvas>. Exposes a
 * `download()` method via defineExpose so parents can trigger a PNG
 * download without extra plumbing.
 */
const props = defineProps({
    value: { type: String, required: true },
    size: { type: Number, default: 256 },
    fileName: { type: String, default: 'qr-code.png' },
})

const canvasRef = ref(null)

async function render() {
    if (!canvasRef.value || !props.value) {
        return
    }
    try {
        await QRCode.toCanvas(canvasRef.value, props.value, {
            width: props.size,
            margin: 2,
            color: { dark: '#1a1a1a', light: '#ffffff' },
            errorCorrectionLevel: 'M',
        })
    } catch (err) {
        console.error('QR generation failed', err)
    }
}

function download() {
    if (!canvasRef.value) {
        return
    }
    const link = document.createElement('a')
    link.download = props.fileName
    link.href = canvasRef.value.toDataURL('image/png')
    document.body.appendChild(link)
    link.click()
    document.body.removeChild(link)
}

onMounted(render)
watch(() => [props.value, props.size], render)

defineExpose({ download })
</script>

<template>
    <canvas ref="canvasRef" :width="size" :height="size" class="rounded-lg bg-white" />
</template>
