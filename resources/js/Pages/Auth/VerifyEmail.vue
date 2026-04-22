<script setup>
import { useForm, Head } from '@inertiajs/vue3'
import { computed } from 'vue'

const props = defineProps({
    status: String,
})

const verificationLinkSent = computed(() => props.status === 'verification-link-sent')

const resendForm = useForm({})
const logoutForm = useForm({})

function resend() {
    resendForm.post(route('verification.send'))
}

function logout() {
    logoutForm.post(route('logout'))
}
</script>

<template>
    <Head title="Verifica tu correo" />

    <div class="min-h-screen bg-[#FAFAFA] flex items-center justify-center p-4">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <div class="bg-orange-50 p-3 rounded-2xl mb-3">
                    <span class="material-symbols-outlined text-[#FF5722] text-4xl" style="font-variation-settings:'FILL' 1">
                        local_fire_department
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">PideAqui</h1>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8 text-center">
                <div class="inline-flex h-16 w-16 items-center justify-center rounded-full bg-[#FF5722]/10 mb-4">
                    <span class="material-symbols-outlined text-[#FF5722] text-4xl">mark_email_read</span>
                </div>

                <h2 class="text-xl font-semibold text-gray-900 mb-2">Verifica tu correo</h2>

                <p class="text-sm text-gray-600 leading-relaxed mb-6">
                    Enviamos un enlace de verificación al correo que registraste.
                    Haz clic en el enlace para activar tu cuenta y acceder al panel.
                </p>

                <div
                    v-if="verificationLinkSent"
                    class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-4"
                >
                    Enlace reenviado. Revisa tu bandeja de entrada.
                </div>

                <button
                    type="button"
                    :disabled="resendForm.processing"
                    class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-60"
                    @click="resend"
                >
                    {{ resendForm.processing ? 'Reenviando…' : 'Reenviar correo' }}
                </button>

                <button
                    type="button"
                    class="mt-4 text-xs text-gray-500 hover:text-gray-700 underline transition-colors"
                    @click="logout"
                >
                    Cerrar sesión
                </button>
            </div>
        </div>
    </div>
</template>
