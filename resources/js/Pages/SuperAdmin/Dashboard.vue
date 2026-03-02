<script setup>
import { Head, Link } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineProps({
    active_restaurants: Number,
    new_restaurants_this_month: Number,
    total_monthly_orders: Number,
    recent_restaurants: Array,
})
</script>

<template>
    <Head title="SuperAdmin — Dashboard" />
    <SuperAdminLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Vista global de la plataforma GuisoGo.</p>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-3 gap-5 mb-8">
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-green-600" style="font-variation-settings:'FILL' 1">storefront</span>
                    </div>
                    <span class="text-sm font-medium text-gray-500">Restaurantes activos</span>
                </div>
                <p class="text-3xl font-bold text-gray-900">{{ active_restaurants }}</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-blue-600" style="font-variation-settings:'FILL' 1">receipt_long</span>
                    </div>
                    <span class="text-sm font-medium text-gray-500">Pedidos totales del mes</span>
                </div>
                <p class="text-3xl font-bold text-gray-900">{{ total_monthly_orders }}</p>
            </div>

            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center gap-3 mb-3">
                    <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center">
                        <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">add_business</span>
                    </div>
                    <span class="text-sm font-medium text-gray-500">Nuevos restaurantes (mes)</span>
                </div>
                <p class="text-3xl font-bold text-gray-900">{{ new_restaurants_this_month }}</p>
            </div>
        </div>

        <!-- Recent restaurants -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
            <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">Restaurantes recientes</h2>
                <Link :href="route('super.restaurants.index')" class="text-sm text-[#FF5722] hover:underline font-medium">Ver todos</Link>
            </div>
            <div class="divide-y divide-gray-50">
                <div v-if="recent_restaurants.length === 0" class="px-6 py-8 text-center text-sm text-gray-400">
                    Sin restaurantes registrados.
                </div>
                <div
                    v-for="restaurant in recent_restaurants"
                    :key="restaurant.id"
                    class="px-6 py-4 flex items-center justify-between"
                >
                    <div class="flex items-center gap-3">
                        <div class="w-9 h-9 rounded-xl bg-gray-100 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-gray-500 text-lg">storefront</span>
                        </div>
                        <div>
                            <p class="text-sm font-semibold text-gray-900">{{ restaurant.name }}</p>
                            <p class="text-xs text-gray-400">{{ restaurant.slug }}</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-3">
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                            :class="restaurant.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ restaurant.is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                        <Link
                            :href="route('super.restaurants.show', restaurant.id)"
                            class="text-sm text-[#FF5722] hover:underline font-medium"
                        >Ver</Link>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
