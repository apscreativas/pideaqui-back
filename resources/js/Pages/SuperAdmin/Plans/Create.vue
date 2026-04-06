<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { ref } from 'vue'

const showAdvanced = ref(false)

const form = useForm({
    name: '',
    slug: '',
    description: '',
    orders_limit: 500,
    max_branches: 3,
    monthly_price: 0,
    yearly_price: 0,
    sort_order: 0,
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
    form.post(route('super.plans.store'))
}
</script>

<template>
    <Head title="SuperAdmin — Crear Plan" />
    <SuperAdminLayout>
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                <Link :href="route('super.plans.index')" class="hover:text-gray-600 transition-colors">Planes</Link>
                <span class="material-symbols-outlined text-xs">chevron_right</span>
                <span class="text-gray-600">Nuevo plan</span>
            </div>
            <h1 class="text-2xl font-bold text-gray-900">Crear plan</h1>
        </div>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-5xl">

                <!-- Left Column: Plan Details -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Identity -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-900">Información del plan</h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input
                                    v-model="form.name"
                                    @input="generateSlug"
                                    type="text"
                                    placeholder="Ej: Pro"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    placeholder="Breve descripción para la página de planes..."
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50 resize-none"
                                ></textarea>
                                <p v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-900">Precios</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mensual (MXN)</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">$</span>
                                        <input
                                            v-model.number="form.monthly_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full border border-gray-200 rounded-xl pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                        />
                                    </div>
                                    <p v-if="form.errors.monthly_price" class="text-xs text-red-500 mt-1">{{ form.errors.monthly_price }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Anual (MXN)</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">$</span>
                                        <input
                                            v-model.number="form.yearly_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full border border-gray-200 rounded-xl pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                        />
                                    </div>
                                    <p v-if="form.errors.yearly_price" class="text-xs text-red-500 mt-1">{{ form.errors.yearly_price }}</p>
                                </div>
                            </div>
                            <p
                                v-if="form.monthly_price > 0 && form.yearly_price > 0 && form.yearly_price < form.monthly_price * 12"
                                class="text-xs text-emerald-600 mt-2"
                            >
                                El plan anual ahorra {{ Math.round((1 - form.yearly_price / (form.monthly_price * 12)) * 100) }}% respecto al mensual.
                            </p>
                        </div>
                    </div>

                    <!-- Limits -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-900">Límites del plan</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Pedidos por mes</label>
                                    <input
                                        v-model.number="form.orders_limit"
                                        type="number"
                                        min="1"
                                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                    />
                                    <p v-if="form.errors.orders_limit" class="text-xs text-red-500 mt-1">{{ form.errors.orders_limit }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Sucursales</label>
                                    <input
                                        v-model.number="form.max_branches"
                                        type="number"
                                        min="1"
                                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                    />
                                    <p v-if="form.errors.max_branches" class="text-xs text-red-500 mt-1">{{ form.errors.max_branches }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sidebar -->
                <div class="space-y-6">

                    <!-- Actions Card -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors disabled:opacity-60 shadow-sm"
                        >
                            {{ form.processing ? 'Creando...' : 'Crear plan' }}
                        </button>
                        <Link
                            :href="route('super.plans.index')"
                            class="block w-full text-center mt-3 px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
                    </div>

                    <!-- Stripe Info -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-base text-gray-400">link</span>
                            <h3 class="text-sm font-semibold text-gray-900">Integración Stripe</h3>
                        </div>
                        <p class="text-xs text-gray-500 leading-relaxed">
                            Los IDs de Stripe se configuran después de crear el plan. Podrás vincular Product y Prices desde la pantalla de edición.
                        </p>
                    </div>

                    <!-- Advanced -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <button
                            type="button"
                            @click="showAdvanced = !showAdvanced"
                            class="w-full flex items-center justify-between px-6 py-4 text-sm text-gray-600 hover:text-gray-900 transition-colors"
                        >
                            <span class="font-medium">Avanzado</span>
                            <span
                                class="material-symbols-outlined text-lg transition-transform"
                                :class="{ 'rotate-180': showAdvanced }"
                            >expand_more</span>
                        </button>
                        <div v-if="showAdvanced" class="px-6 pb-5 space-y-4 border-t border-gray-100 pt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    placeholder="Se genera automáticamente"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p class="text-xs text-gray-400 mt-1">Identificador URL del plan.</p>
                                <p v-if="form.errors.slug" class="text-xs text-red-500 mt-1">{{ form.errors.slug }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Orden</label>
                                <input
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p class="text-xs text-gray-400 mt-1">Orden ascendente en la página de planes.</p>
                                <p v-if="form.errors.sort_order" class="text-xs text-red-500 mt-1">{{ form.errors.sort_order }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </SuperAdminLayout>
</template>
