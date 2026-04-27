<script setup>
import { useForm, Head, Link, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'

const flash = computed(() => usePage().props.flash)

const form = useForm({
    email: '',
    password: '',
    remember: false,
})

function submit() {
    form.post('/login', {
        onFinish: () => form.reset('password'),
    })
}
</script>

<template>
    <Head title="Iniciar sesión" />

    <div class="min-h-screen bg-[#FAFAFA] flex items-center justify-center p-4">
        <div class="w-full max-w-md">

            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <img
                    :src="'/images/logo.png'"
                    alt="PideAqui"
                    class="h-20 w-auto mb-3"
                />
                <p class="text-sm text-gray-500 mt-1">Panel de administración</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <!-- Success flash (e.g. after password reset) -->
                <div v-if="flash?.success" class="bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 mb-4">
                    {{ flash.success }}
                </div>

                <h2 class="text-lg font-semibold text-gray-900 mb-6">Iniciar sesion</h2>

                <form @submit.prevent="submit" class="space-y-5">

                    <!-- Email -->
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

                    <!-- Password -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Contraseña
                        </label>
                        <input
                            v-model="form.password"
                            type="password"
                            autocomplete="current-password"
                            required
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-400': form.errors.password }"
                            placeholder="••••••••"
                        />
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <!-- Remember + Forgot -->
                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2 cursor-pointer">
                            <input
                                v-model="form.remember"
                                type="checkbox"
                                class="w-4 h-4 rounded border-gray-300 text-[#FF5722] focus:ring-[#FF5722]/30"
                            />
                            <span class="text-sm text-gray-600">Recordarme</span>
                        </label>
                        <Link :href="route('password.request')" class="text-xs text-[#FF5722] hover:text-[#D84315] font-medium transition-colors">
                            Olvide mi contrasena
                        </Link>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Iniciando sesión…' : 'Iniciar sesión' }}
                    </button>
                </form>

                <div class="text-center text-sm text-gray-600 pt-5 mt-5 border-t border-gray-100">
                    ¿No tienes cuenta?
                    <Link :href="route('register')" class="text-[#FF5722] font-semibold hover:text-[#D84315]">Crear cuenta</Link>
                </div>
            </div>

        </div>
    </div>
</template>
