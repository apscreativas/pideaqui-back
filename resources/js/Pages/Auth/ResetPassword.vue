<script setup>
import { useForm, Head } from '@inertiajs/vue3'

const props = defineProps({
    token: String,
    email: String,
})

const form = useForm({
    token: props.token,
    email: props.email,
    password: '',
    password_confirmation: '',
})

function submit() {
    form.post('/reset-password', {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Nueva contraseña" />

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
                <h2 class="text-lg font-semibold text-gray-900 mb-6">Establecer nueva contraseña</h2>

                <form @submit.prevent="submit" class="space-y-5">

                    <!-- Email (readonly) -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Correo electrónico</label>
                        <input
                            v-model="form.email"
                            type="email"
                            readonly
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-500 bg-gray-50 cursor-not-allowed"
                        />
                    </div>

                    <!-- New password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nueva contraseña</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-400': form.errors.password }"
                            placeholder="Mínimo 8 caracteres"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <!-- Confirm password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Confirmar contraseña</label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            placeholder="Repite la contraseña"
                        />
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Guardando…' : 'Guardar nueva contraseña' }}
                    </button>
                </form>
            </div>

        </div>
    </div>
</template>
