<script setup>
import { Head, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
    restaurants: Object,
    filters: Object,
})

const alertLabels = {
    grace_expiring: { label: 'Gracia expira ≤3 días', icon: 'hourglass_bottom', color: 'red' },
    orders_near_limit: { label: '≥80% del límite de pedidos', icon: 'speed', color: 'amber' },
    billing_manual: { label: 'Modo manual', icon: 'settings', color: 'gray' },
    new_this_week: { label: 'Nuevos esta semana', icon: 'fiber_new', color: 'blue' },
    past_due: { label: 'Past due', icon: 'warning', color: 'amber' },
    grace_period: { label: 'En periodo de gracia', icon: 'schedule', color: 'amber' },
    suspended: { label: 'Suspendidos', icon: 'block', color: 'red' },
    no_subscription: { label: 'Sin suscripción', icon: 'help_outline', color: 'blue' },
}

const quickAlerts = ['grace_expiring', 'orders_near_limit', 'billing_manual', 'new_this_week']

const activeAlert = computed(() => props.filters?.alert ?? '')

function applyFilters(overrides = {}) {
    const next = {
        status: props.filters?.status ?? '',
        alert: props.filters?.alert ?? '',
        per_page: props.filters?.per_page ?? 20,
        ...overrides,
    }
    // Remove empty strings / defaults to keep URL clean.
    Object.keys(next).forEach((k) => {
        if (next[k] === '') { delete next[k] }
    })
    if (next.per_page === 20) { delete next.per_page }
    router.get(route('super.restaurants.index'), next, { preserveState: true, preserveScroll: true, replace: true })
}

function toggleActive(restaurant) {
    router.patch(route('super.restaurants.toggle', restaurant.id))
}

function filterByStatus(value) {
    applyFilters({ status: value })
}

function filterByAlert(value) {
    applyFilters({ alert: value })
}

function clearAlert() {
    applyFilters({ alert: '' })
}

function daysLeft(dateStr) {
    if (!dateStr) return null
    const end = new Date(dateStr)
    const now = new Date()
    const diff = Math.ceil((end - now) / (1000 * 60 * 60 * 24))
    return Math.max(0, diff)
}

function isGraceSoon(r) {
    if (r.status !== 'grace_period' || !r.grace_period_ends_at) return false
    const d = daysLeft(r.grace_period_ends_at)
    return d !== null && d <= 3
}

function isNearLimit(r) {
    if (!r.orders_limit || r.orders_limit <= 0) return false
    return (r.period_orders_count ?? 0) / r.orders_limit >= 0.8
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

        <!-- Active alert banner -->
        <div
            v-if="activeAlert && alertLabels[activeAlert]"
            class="mb-4 flex items-center justify-between rounded-xl border px-4 py-3"
            :class="{
                'bg-red-50 border-red-200 text-red-800': alertLabels[activeAlert].color === 'red',
                'bg-amber-50 border-amber-200 text-amber-800': alertLabels[activeAlert].color === 'amber',
                'bg-gray-50 border-gray-200 text-gray-700': alertLabels[activeAlert].color === 'gray',
                'bg-blue-50 border-blue-200 text-blue-800': alertLabels[activeAlert].color === 'blue',
            }"
        >
            <div class="flex items-center gap-3">
                <span class="material-symbols-outlined" style="font-variation-settings:'FILL' 1">
                    {{ alertLabels[activeAlert].icon }}
                </span>
                <div>
                    <p class="text-sm font-semibold">Filtrando por: {{ alertLabels[activeAlert].label }}</p>
                    <p class="text-xs">{{ restaurants.total }} restaurantes coinciden con esta alerta.</p>
                </div>
            </div>
            <button
                @click="clearAlert"
                class="text-xs font-semibold underline hover:no-underline"
            >
                Limpiar filtro
            </button>
        </div>

        <!-- Status filters -->
        <div class="flex items-center gap-2 mb-3">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider mr-1">Estado:</span>
            <button
                @click="filterByStatus('')"
                class="px-3 py-1.5 rounded-xl text-sm font-semibold transition-colors"
                :class="!filters?.status ? 'bg-[#FF5722] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
                Todos
            </button>
            <button
                @click="filterByStatus('1')"
                class="px-3 py-1.5 rounded-xl text-sm font-semibold transition-colors"
                :class="filters?.status === '1' ? 'bg-[#FF5722] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
                Activos
            </button>
            <button
                @click="filterByStatus('0')"
                class="px-3 py-1.5 rounded-xl text-sm font-semibold transition-colors"
                :class="filters?.status === '0' ? 'bg-[#FF5722] text-white' : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
                Inactivos
            </button>
        </div>

        <!-- Alert quick filters (solo los accionables; los de estado llegan via click-through del dashboard) -->
        <div class="flex flex-wrap items-center gap-2 mb-5">
            <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider mr-1">Alertas:</span>
            <button
                v-for="key in quickAlerts"
                :key="key"
                @click="filterByAlert(key)"
                class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl text-sm font-semibold transition-colors border"
                :class="filters?.alert === key
                    ? 'bg-[#FF5722] text-white border-[#FF5722]'
                    : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
                <span class="material-symbols-outlined text-base">{{ alertLabels[key].icon }}</span>
                {{ alertLabels[key].label }}
            </button>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="border-b border-gray-100 bg-gray-50">
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Restaurante</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursales</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Pedidos / Limite</th>
                        <th class="text-left px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-if="restaurants.data.length === 0">
                        <td colspan="5" class="px-6 py-10 text-center text-gray-400 text-sm">Sin restaurantes.</td>
                    </tr>
                    <tr v-for="restaurant in restaurants.data" :key="restaurant.id" class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-2 flex-wrap">
                                <p class="font-semibold text-gray-900">{{ restaurant.name }}</p>
                                <!-- Inline alert badges -->
                                <span
                                    v-if="isGraceSoon(restaurant)"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-red-50 text-red-700 border border-red-200"
                                >
                                    <span class="material-symbols-outlined text-xs">hourglass_bottom</span>
                                    Gracia {{ daysLeft(restaurant.grace_period_ends_at) }}d
                                </span>
                                <span
                                    v-if="isNearLimit(restaurant)"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200"
                                >
                                    <span class="material-symbols-outlined text-xs">speed</span>
                                    80%+
                                </span>
                                <span
                                    v-if="restaurant.billing_mode === 'manual'"
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-gray-100 text-gray-600 border border-gray-200"
                                >
                                    Manual
                                </span>
                            </div>
                            <p class="text-xs text-gray-400 mt-0.5">{{ restaurant.slug }}</p>
                        </td>
                        <td class="px-6 py-4 text-gray-700">{{ restaurant.active_branch_count ?? 0 }}</td>
                        <td class="px-6 py-4">
                            <template v-if="restaurant.orders_limit">
                                <div class="flex items-center gap-2">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-baseline gap-1 mb-1">
                                            <span class="text-sm font-semibold text-gray-900">{{ restaurant.period_orders_count ?? 0 }}</span>
                                            <span class="text-xs text-gray-400">/ {{ restaurant.orders_limit }}</span>
                                        </div>
                                        <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                                            <div
                                                class="h-full rounded-full transition-all"
                                                :class="[
                                                    (restaurant.period_orders_count ?? 0) >= restaurant.orders_limit
                                                        ? 'bg-red-500'
                                                        : (restaurant.period_orders_count ?? 0) >= restaurant.orders_limit * 0.8
                                                            ? 'bg-amber-500'
                                                            : 'bg-[#FF5722]'
                                                ]"
                                                :style="{ width: Math.min(100, ((restaurant.period_orders_count ?? 0) / restaurant.orders_limit) * 100) + '%' }"
                                            ></div>
                                        </div>
                                    </div>
                                </div>
                            </template>
                            <span v-else class="text-xs text-gray-400">Sin limite</span>
                        </td>
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
                                    class="text-sm font-semibold px-3 py-1.5 rounded-xl border transition-colors"
                                    :class="restaurant.is_active
                                        ? 'border-red-200 text-red-600 hover:bg-red-50'
                                        : 'border-green-200 text-green-600 hover:bg-green-50'"
                                >
                                    {{ restaurant.is_active ? 'Desactivar' : 'Activar' }}
                                </button>
                                <Link
                                    :href="route('super.restaurants.show', restaurant.id)"
                                    class="text-sm font-semibold px-3 py-1.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                                >
                                    Ver detalle
                                </Link>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination
                v-if="restaurants.data.length > 0"
                :paginator="restaurants"
                label="restaurantes"
                @page="(p) => applyFilters({ page: p })"
                @per-page="(n) => applyFilters({ per_page: n, page: 1 })"
            />
        </div>
    </SuperAdminLayout>
</template>
