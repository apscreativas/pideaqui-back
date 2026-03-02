<script setup>
import { Head } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'

const props = defineProps({
    access_token: String,
    restaurant_name: String,
})

// Use qrserver.com to generate the QR image (free, no npm package needed)
const qrDataUrl = `https://api.qrserver.com/v1/create-qr-code/?size=300x300&data=${encodeURIComponent(props.access_token)}`

const copied = ref(false)

function copyToken() {
    navigator.clipboard.writeText(props.access_token).then(() => {
        copied.value = true
        setTimeout(() => { copied.value = false }, 2000)
    })
}

function downloadQr() {
    const link = document.createElement('a')
    link.href = qrDataUrl
    link.download = `qr-${props.restaurant_name}.png`
    link.click()
}
</script>

<template>
    <Head title="Código QR" />
    <AppLayout title="Configuración">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
            <p class="mt-1 text-sm text-gray-500">Gestiona la información y preferencias de tu restaurante.</p>
        </div>

        <SettingsLayout>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2">Código QR y link público</h2>
                <p class="text-sm text-gray-500 mb-8">
                    Comparte este QR o link para que tus clientes accedan al menú de tu restaurante.
                </p>

                <div class="flex flex-col md:flex-row gap-10 items-start">

                    <!-- QR Image -->
                    <div class="flex flex-col items-center gap-4">
                        <div class="p-4 border border-gray-100 rounded-2xl shadow-sm bg-white">
                            <img
                                :src="qrDataUrl"
                                alt="Código QR del restaurante"
                                class="w-48 h-48"
                            />
                        </div>
                        <button
                            class="flex items-center gap-2 border border-gray-200 text-gray-700 font-semibold rounded-xl px-5 py-2.5 text-sm hover:bg-gray-50 transition-colors"
                            @click="downloadQr"
                        >
                            <span class="material-symbols-outlined text-lg">download</span>
                            Descargar QR
                        </button>
                    </div>

                    <!-- Token + Copy -->
                    <div class="flex-1">
                        <p class="text-sm font-semibold text-gray-700 mb-2">Token de acceso</p>
                        <p class="text-xs text-gray-500 mb-3">
                            Este token identifica tu restaurante en la API pública. Compártelo con tu proveedor de tecnología si integran el menú.
                        </p>

                        <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
                            <code class="flex-1 text-sm text-gray-700 font-mono break-all">{{ access_token }}</code>
                            <button
                                class="shrink-0 flex items-center gap-1.5 text-sm font-semibold transition-colors"
                                :class="copied ? 'text-green-600' : 'text-[#FF5722] hover:text-[#D84315]'"
                                @click="copyToken"
                            >
                                <span class="material-symbols-outlined text-lg">{{ copied ? 'check' : 'content_copy' }}</span>
                                {{ copied ? 'Copiado' : 'Copiar' }}
                            </button>
                        </div>

                        <div class="mt-6 p-4 bg-amber-50 border border-amber-100 rounded-xl text-sm text-amber-800">
                            <p class="font-semibold mb-1">Nota de seguridad</p>
                            <p>El token de acceso es confidencial. No lo publiques en redes sociales ni lo incluyas en URLs visibles.</p>
                        </div>
                    </div>

                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
