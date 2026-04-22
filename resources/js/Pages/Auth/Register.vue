<script setup>
import { ref } from 'vue'
import { useForm, Head, Link, usePage } from '@inertiajs/vue3'
import SlugInput from '@/Components/SlugInput.vue'

const form = useForm({
    restaurant_name: '',
    slug: '',
    admin_name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const slugAvailable = ref(false)

const page = usePage()
const menuBaseUrl = page.props.menu_base_url ?? ''
const urlPrefix = menuBaseUrl ? `${menuBaseUrl.replace(/\/$/, '')}/r/` : '/r/'

function submit() {
    form.post(route('register.store'), {
        onFinish: () => form.reset('password', 'password_confirmation'),
    })
}
</script>

<template>
    <Head title="Crear cuenta" />

    <div class="min-h-screen bg-[#FAFAFA] flex items-center justify-center p-4 py-10">
        <div class="w-full max-w-md">
            <!-- Logo -->
            <div class="flex flex-col items-center mb-8">
                <div class="bg-orange-50 p-3 rounded-2xl mb-3">
                    <span class="material-symbols-outlined text-[#FF5722] text-4xl" style="font-variation-settings:'FILL' 1">
                        local_fire_department
                    </span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900 tracking-tight">PideAqui</h1>
                <p class="text-sm text-gray-500 mt-1">Crea tu cuenta en minutos</p>
            </div>

            <!-- Card -->
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-8">
                <h2 class="text-lg font-semibold text-gray-900 mb-6">Crear cuenta</h2>

                <form @submit.prevent="submit" class="space-y-4">
                    <!-- Restaurant name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Nombre del restaurante
                        </label>
                        <input
                            v-model="form.restaurant_name"
                            type="text"
                            autocomplete="organization"
                            required
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-400': form.errors.restaurant_name }"
                            placeholder="Tacos El Rey"
                        />
                        <p v-if="form.errors.restaurant_name" class="mt-1 text-xs text-red-500">{{ form.errors.restaurant_name }}</p>
                    </div>

                    <!-- Slug (public URL) -->
                    <SlugInput
                        v-model="form.slug"
                        :name-source="form.restaurant_name"
                        :url-prefix="urlPrefix"
                        label="URL pública de tu menú"
                        @update:available="slugAvailable = $event"
                    />
                    <p v-if="form.errors.slug" class="mt-1 text-xs text-red-500">{{ form.errors.slug }}</p>

                    <!-- Admin name -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Tu nombre
                        </label>
                        <input
                            v-model="form.admin_name"
                            type="text"
                            autocomplete="name"
                            required
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-400': form.errors.admin_name }"
                            placeholder="Ana Pérez"
                        />
                        <p v-if="form.errors.admin_name" class="mt-1 text-xs text-red-500">{{ form.errors.admin_name }}</p>
                    </div>

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
                            autocomplete="new-password"
                            required
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-400': form.errors.password }"
                            placeholder="••••••••"
                        />
                        <p class="mt-1 text-xs text-gray-500">Mínimo 8 caracteres, con mayúsculas, minúsculas y números.</p>
                        <p v-if="form.errors.password" class="mt-1 text-xs text-red-500">{{ form.errors.password }}</p>
                    </div>

                    <!-- Password confirmation -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">
                            Confirmar contraseña
                        </label>
                        <input
                            v-model="form.password_confirmation"
                            type="password"
                            autocomplete="new-password"
                            required
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm text-gray-900 placeholder-gray-400 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            placeholder="••••••••"
                        />
                    </div>

                    <!-- Grace info -->
                    <div class="bg-orange-50 border border-orange-100 text-sm text-gray-700 rounded-xl px-4 py-3">
                        <p class="font-medium text-gray-900 mb-1">14 días gratis</p>
                        <p class="text-xs text-gray-600">
                            Incluye 50 pedidos y 1 sucursal para que pruebes PideAqui sin compromiso.
                        </p>
                    </div>

                    <!-- Submit -->
                    <button
                        type="submit"
                        :disabled="form.processing || !slugAvailable || !form.slug"
                        class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-60 disabled:cursor-not-allowed"
                    >
                        {{ form.processing ? 'Creando cuenta…' : 'Crear cuenta' }}
                    </button>
                </form>

                <div class="text-center text-sm text-gray-600 pt-5 mt-5 border-t border-gray-100">
                    ¿Ya tienes cuenta?
                    <Link :href="route('login')" class="text-[#FF5722] font-semibold hover:text-[#D84315]">Iniciar sesión</Link>
                </div>
            </div>
        </div>
    </div>
</template>
