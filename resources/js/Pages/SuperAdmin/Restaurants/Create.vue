<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const form = useForm({
    name: '',
    admin_name: '',
    admin_email: '',
    password: '',
    password_confirmation: '',
    billing_mode: 'grace',
    orders_limit: null,
    max_branches: 1,
    orders_limit_start: null,
    orders_limit_end: null,
})

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
                                    type="text"
                                    placeholder="El Fogón del Norte"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
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

                    <!-- Billing mode -->
                    <div>
                        <p class="text-sm font-semibold text-gray-900 mb-4">Modo de facturación</p>

                        <div class="space-y-3">
                            <label class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-colors" :class="form.billing_mode === 'grace' ? 'border-[#FF5722] bg-orange-50/50' : 'border-gray-200 hover:bg-gray-50'">
                                <input v-model="form.billing_mode" type="radio" value="grace" class="mt-0.5 text-[#FF5722] focus:ring-[#FF5722]" />
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Periodo de gracia</p>
                                    <p class="text-xs text-gray-500 mt-0.5">El restaurante tendrá 14 días para elegir un plan y suscribirse vía Stripe. Inicia con 50 pedidos de prueba.</p>
                                </div>
                            </label>

                            <label class="flex items-start gap-3 p-4 border rounded-xl cursor-pointer transition-colors" :class="form.billing_mode === 'manual' ? 'border-[#FF5722] bg-orange-50/50' : 'border-gray-200 hover:bg-gray-50'">
                                <input v-model="form.billing_mode" type="radio" value="manual" class="mt-0.5 text-[#FF5722] focus:ring-[#FF5722]" />
                                <div>
                                    <p class="text-sm font-semibold text-gray-900">Límites manuales</p>
                                    <p class="text-xs text-gray-500 mt-0.5">Tú defines los límites de pedidos, sucursales y el periodo. Sin suscripción de Stripe.</p>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Manual limits (only if manual) -->
                    <div v-if="form.billing_mode === 'manual'" class="space-y-4 bg-gray-50 rounded-xl p-5">
                        <p class="text-sm font-semibold text-gray-700">Configurar límites</p>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Límite de pedidos</label>
                                <input
                                    v-model.number="form.orders_limit"
                                    type="number"
                                    min="1"
                                    placeholder="500"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.orders_limit" class="text-xs text-red-500 mt-1">{{ form.errors.orders_limit }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Máx. sucursales</label>
                                <input
                                    v-model.number="form.max_branches"
                                    type="number"
                                    min="1"
                                    placeholder="1"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.max_branches" class="text-xs text-red-500 mt-1">{{ form.errors.max_branches }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Inicio del periodo</label>
                                <input
                                    v-model="form.orders_limit_start"
                                    type="date"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.orders_limit_start" class="text-xs text-red-500 mt-1">{{ form.errors.orders_limit_start }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Fin del periodo</label>
                                <input
                                    v-model="form.orders_limit_end"
                                    type="date"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.orders_limit_end" class="text-xs text-red-500 mt-1">{{ form.errors.orders_limit_end }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Grace info (only if grace) -->
                    <div v-if="form.billing_mode === 'grace'" class="bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
                        <div class="flex items-start gap-2">
                            <span class="material-symbols-outlined text-blue-500 text-lg mt-0.5">info</span>
                            <p class="text-sm text-blue-700">
                                El restaurante iniciará con el <strong>plan de gracia</strong> (50 pedidos, 1 sucursal) y tendrá <strong>14 días</strong> para elegir su plan y suscribirse.
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
                            :disabled="form.processing || !slugAvailable || !form.slug"
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
