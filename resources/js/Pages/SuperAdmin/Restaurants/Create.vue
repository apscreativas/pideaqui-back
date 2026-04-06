<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const form = useForm({
    name: '',
    slug: '',
    admin_name: '',
    admin_email: '',
    password: '',
    password_confirmation: '',
})

function generateSlug() {
    form.slug = form.name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
}

function submit() {
    form.post(route('super.restaurants.store'))
}
</script>

<template>
    <Head title="SuperAdmin — Crear Restaurante" />
    <SuperAdminLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Crear Restaurante</h1>
            <p class="mt-1 text-sm text-gray-500">Registra un nuevo restaurante en la plataforma.</p>
        </div>

        <div class="max-w-2xl">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <form @submit.prevent="submit" class="space-y-5">

                    <!-- Datos del restaurante -->
                    <div>
                        <p class="text-sm font-semibold text-gray-900 mb-4">Datos del restaurante</p>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del restaurante</label>
                                <input
                                    v-model="form.name"
                                    @input="generateSlug"
                                    type="text"
                                    placeholder="El Fogón del Norte"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Slug (URL amigable)</label>
                                <div class="flex items-center">
                                    <span class="inline-flex items-center px-3 py-2.5 border border-r-0 border-gray-200 rounded-l-xl bg-gray-50 text-sm text-gray-500">pideaqui.com/</span>
                                    <input
                                        v-model="form.slug"
                                        type="text"
                                        placeholder="el-fogon-del-norte"
                                        class="flex-1 border border-gray-200 rounded-r-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                    />
                                </div>
                                <p v-if="form.errors.slug" class="text-xs text-red-500 mt-1">{{ form.errors.slug }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="h-px bg-gray-100"></div>

                    <!-- Admin del restaurante -->
                    <div>
                        <p class="text-sm font-semibold text-gray-900 mb-4">Administrador del restaurante</p>

                        <div class="space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                                <input
                                    v-model="form.admin_name"
                                    type="text"
                                    placeholder="Juan Pérez"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.admin_name" class="text-xs text-red-500 mt-1">{{ form.errors.admin_name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                                <input
                                    v-model="form.admin_email"
                                    type="email"
                                    placeholder="admin@restaurante.com"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.admin_email" class="text-xs text-red-500 mt-1">{{ form.errors.admin_email }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña inicial</label>
                                <input
                                    v-model="form.password"
                                    type="password"
                                    autocomplete="new-password"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña</label>
                                <input
                                    v-model="form.password_confirmation"
                                    type="password"
                                    autocomplete="new-password"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                            </div>
                        </div>
                    </div>

                    <div class="h-px bg-gray-100"></div>

                    <!-- Plan info -->
                    <div class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
                        <div class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-blue-500 text-lg mt-0.5">info</span>
                            <p class="text-sm text-blue-700">
                                El restaurante iniciará con el <strong>plan de gracia</strong> y tendrá un periodo limitado para elegir su plan y suscribirse.
                                Los límites de pedidos y sucursales se definen por el plan asignado.
                            </p>
                        </div>
                    </div>

                    <div class="flex justify-end gap-3 pt-2">
                        <a
                            :href="route('super.restaurants.index')"
                            class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                        >Cancelar</a>
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition-colors disabled:opacity-60"
                        >
                            {{ form.processing ? 'Creando...' : 'Crear restaurante' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </SuperAdminLayout>
</template>
