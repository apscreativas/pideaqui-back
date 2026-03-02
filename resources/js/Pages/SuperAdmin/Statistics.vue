<script setup>
import { Head } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

defineProps({
    orders_by_day: Object,
    top_restaurants: Array,
})
</script>

<template>
    <Head title="SuperAdmin — Estadísticas" />
    <SuperAdminLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Estadísticas</h1>
            <p class="mt-1 text-sm text-gray-500">Vista global de pedidos en los últimos 30 días.</p>
        </div>

        <!-- Orders by day -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-900 mb-5">Pedidos por día (últimos 30 días)</h2>

            <div v-if="Object.keys(orders_by_day).length === 0" class="text-center py-8 text-sm text-gray-400">
                Sin datos disponibles.
            </div>
            <div v-else class="overflow-x-auto">
                <div class="flex items-end gap-1 h-32 min-w-max">
                    <div
                        v-for="(count, date) in orders_by_day"
                        :key="date"
                        class="flex flex-col items-center gap-1 group"
                    >
                        <div class="relative">
                            <div
                                class="w-6 bg-[#FF5722]/80 rounded-t transition-all group-hover:bg-[#FF5722]"
                                :style="{ height: Math.max(4, count * 4) + 'px' }"
                            ></div>
                            <div class="absolute -top-7 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-xs px-2 py-1 rounded opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none">
                                {{ count }} pedidos
                            </div>
                        </div>
                        <span class="text-xs text-gray-400 rotate-45 origin-left mt-1 w-6">{{ date.slice(5) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Top restaurants -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
            <div class="px-6 py-4 border-b border-gray-100">
                <h2 class="text-base font-semibold text-gray-900">Top restaurantes del mes</h2>
            </div>
            <div class="divide-y divide-gray-50">
                <div v-if="top_restaurants.length === 0" class="px-6 py-8 text-center text-sm text-gray-400">
                    Sin datos disponibles.
                </div>
                <div
                    v-for="(restaurant, index) in top_restaurants"
                    :key="restaurant.id"
                    class="px-6 py-4 flex items-center gap-4"
                >
                    <span class="w-7 h-7 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 shrink-0">
                        {{ index + 1 }}
                    </span>
                    <div class="flex-1 min-w-0">
                        <p class="text-sm font-semibold text-gray-900">{{ restaurant.name }}</p>
                        <p class="text-xs text-gray-400">{{ restaurant.slug }}</p>
                    </div>
                    <div class="text-right">
                        <p class="text-sm font-bold text-gray-900">{{ restaurant.monthly_orders_count }}</p>
                        <p class="text-xs text-gray-400">pedidos</p>
                    </div>
                    <span
                        class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                        :class="restaurant.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                    >
                        {{ restaurant.is_active ? 'Activo' : 'Inactivo' }}
                    </span>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
