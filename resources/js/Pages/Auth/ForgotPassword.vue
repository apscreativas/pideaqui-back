<script setup>
import { useForm, Head } from '@inertiajs/vue3'

defineProps({
    status: String,
})

const form = useForm({
    email: '',
})

function submit() {
    form.post('/forgot-password')
}
</script>

<template>
    <Head title="Recuperar contraseña" />

    <div class="min-h-screen bg-[#FAFAFA] flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <div class="bg-orange-50 p-3 rounded-2xl mb-3">
                    <span class="material-symbols-outlined text-[#FF5722] text-4xl" style="font-variation-settings:'FILL' 1">
                        local_fire_department
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">GuisoGo</h1>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-2">Recuperar contraseña</h2>
                <p class="text-sm text-gray-500 mb-6">
                    Escribe tu correo y te enviaremos un link para restablecer tu contraseña.
                </p>

                <!-- Status message -->
                <div v-if="status" class="mb-5 rounded-xl bg-green-50 border border-green-100 px-4 py-3 text-sm text-green-700">
                    {{ status }}
                </div>

                <form @submit.prevent="submit" class="space-y-5">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Correo electrónico
                        </label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-400': form.errors.email }"
                            placeholder="admin@restaurante.com"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-500">{{ form.errors.email }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Enviando…' : 'Enviar link de recuperación' }}
                    </button>

                    <div class="text-center">
                        <a href="/login" class="text-sm text-gray-500 hover:text-gray-700 transition-colors">
                            ← Volver al inicio de sesión
                        </a>
                    </div>
                </form>
            </div>

        </div>
    </div>
</template>
