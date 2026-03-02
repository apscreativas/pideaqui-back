<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    restaurants: Object,
    filters: Object,
})

function toggleActive(restaurant) {
    router.patch(route('super.restaurants.toggle', restaurant.id))
}

function filterByStatus(value) {
    router.get(route('super.restaurants.index'), { status: value }, { preserveState: true, replace: true })
}
</script>

<template>
    <Head title="SuperAdmin — Restaurantes" />
    <SuperAdminLayout>
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Restaurantes</h1>
                <p class="mt-1 text-sm text-gray-500">Gestiona todos los restaurantes de la plataforma.</p>
            </div>
            <Link
                :href="route('super.restaurants.create')"
                class="inline-flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors"
            >
                <span class="material-symbols-outlined text-lg">add</span>
                Crear restaurante
            </Link>
        </div>

        <!-- Filters -->
        <div class="flex items-center gap-2 mb-5">
            <button
                @click="filterByStatus('')"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                :class="!filters?.status ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
                Todos
            </button>
            <button
                @click="filterByStatus('1')"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                :class="filters?.status === '1' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
                Activos
            </button>
            <button
                @click="filterByStatus('0')"
                class="px-3 py-1.5 rounded-lg text-sm font-medium transition-colors"
                :class="filters?.status === '0' ? 'bg-gray-900 text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
                Inactivos
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Restaurante</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Pedidos mes</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursales</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Límite mensual</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="restaurants.data.length === 0">
                        <td colspan="6" class="px-6 py-10 text-center text-gray-400 text-sm">Sin restaurantes.</td>
                    </tr>
                    <tr v-for="restaurant in restaurants.data" :key="restaurant.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <p class="font-semibold text-gray-900">{{ restaurant.name }}</p>
                            <p class="text-xs text-gray-400">{{ restaurant.slug }}</p>
                        </td>
                        <td class="px-6 py-4 text-gray-700">{{ restaurant.monthly_orders_count ?? 0 }}</td>
                        <td class="px-6 py-4 text-gray-700">{{ restaurant.active_branch_count ?? 0 }}</td>
                        <td class="px-6 py-4 text-gray-700">{{ restaurant.max_monthly_orders }}</td>
                        <td class="px-6 py-4">
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                :class="restaurant.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ restaurant.is_active ? 'Activo' : 'Inactivo' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-3">
                                <button
                                    @click="toggleActive(restaurant)"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg border transition-colors"
                                    :class="restaurant.is_active
                                        ? 'border-red-200 text-red-600 hover:bg-red-50'
                                        : 'border-green-200 text-green-600 hover:bg-green-50'"
                                >
                                    {{ restaurant.is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                                <Link
                                    :href="route('super.restaurants.show', restaurant.id)"
                                    class="text-xs font-medium px-3 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                                >
                                    Ver detalle
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <!-- Pagination -->
            <div v-if="restaurants.last_page > 1" class="px-6 py-4 border-t border-gray-100 flex items-center justify-between text-sm text-gray-500">
                <span>Página {{ restaurants.current_page }} de {{ restaurants.last_page }}</span>
                <div class="flex gap-2">
                    <Link
                        v-if="restaurants.prev_page_url"
                        :href="restaurants.prev_page_url"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors"
                    >Anterior</Link>
                    <Link
                        v-if="restaurants.next_page_url"
                        :href="restaurants.next_page_url"
                        class="px-3 py-1.5 rounded-lg border border-gray-200 hover:bg-gray-50 transition-colors"
                    >Siguiente</Link>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
