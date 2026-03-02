<script setup>
import { useForm, Head } from '@inertiajs/vue3'

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/super/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="SuperAdmin — Iniciar sesión" />

    <div class="min-h-screen bg-gray-950 flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <div class="bg-[#FF5722]/10 p-3 rounded-2xl mb-3">
                    <span class="material-symbols-outlined text-[#FF5722] text-4xl" style="font-variation-settings:'FILL' 1">
                        local_fire_department
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-white tracking-tight">GuisoGo</h1>
                <p class="text-sm text-gray-400 mt-1">Panel de administración global</p>
            </div>

            <!-- Card -->
            <div class="bg-gray-900 rounded-2xl border border-gray-800 p-8">
                <h2 class="text-lg font-semibold text-white mb-6">Acceso SuperAdmin</h2>

                <form @submit.prevent="submit" class="space-y-5">

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Correo electrónico</label>
                        <input
                            v-model="form.email"
                            type="email"
                            autocomplete="email"
                            required
                            class="w-full rounded-xl border border-gray-700 bg-gray-800 px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/40 focus:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-500': form.errors.email }"
                            placeholder="superadmin@guisogo.com"
                        />
                        <p v-if="form.errors.email" class="mt-1 text-xs text-red-400">{{ form.errors.email }}</p>
                    </div>

                    <div>
                        <label class="block text-sm font-medium text-gray-300 mb-1.5">Contraseña</label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="w-full rounded-xl border border-gray-700 bg-gray-800 px-4 py-2.5 text-sm text-white placeholder-gray-500 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/40 focus:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-500': form.errors.password }"
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-400">{{ form.errors.password }}</p>
                    </div>

                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Iniciando sesión…' : 'Iniciar sesión' }}
                    </button>
                </form>
            </div>

        </div>
    </div>
</template>
