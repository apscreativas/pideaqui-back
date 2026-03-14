<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    cancelled_count: Number,
    total_orders_count: Number,
    cancellation_rate: Number,
    top_reason: String,
    reasons_breakdown: Array,
    by_branch: Array,
    by_day: Array,
    cancelled_orders: Array,
    branches: Array,
    filters: Object,
})

const from = ref(props.filters.from)
const to = ref(props.filters.to)
const branchId = ref(props.filters.branch_id ?? '')

function applyFilter() {
    router.get(route('cancellations.index'), {
        from: from.value,
        to: to.value,
        branch_id: branchId.value || undefined,
    }, { preserveState: true, preserveScroll: true })
}

function setPreset(preset) {
    const today = new Date()
    const fmt = (d) => d.toISOString().slice(0, 10)

    if (preset === 'today') {
        from.value = fmt(today)
        to.value = fmt(today)
    } else if (preset === 'yesterday') {
        const y = new Date(today)
        y.setDate(y.getDate() - 1)
        from.value = fmt(y)
        to.value = fmt(y)
    } else if (preset === 'week') {
        const w = new Date(today)
        w.setDate(w.getDate() - 6)
        from.value = fmt(w)
        to.value = fmt(today)
    } else if (preset === 'month') {
        from.value = fmt(new Date(today.getFullYear(), today.getMonth(), 1))
        to.value = fmt(today)
    }
    applyFilter()
}

const activePreset = computed(() => {
    const f = from.value
    const t = to.value
    const todayStr = new Date().toISOString().slice(0, 10)
    if (f === todayStr && t === todayStr) { return 'today' }
    const y = new Date()
    y.setDate(y.getDate() - 1)
    const yd = y.toISOString().slice(0, 10)
    if (f === yd && t === yd) { return 'yesterday' }
    const w = new Date()
    w.setDate(w.getDate() - 6)
    if (f === w.toISOString().slice(0, 10) && t === todayStr) { return 'week' }
    const m = new Date().toISOString().slice(0, 8) + '01'
    if (f === m && t === todayStr) { return 'month' }
    return 'custom'
})

const maxReasonCount = computed(() =>
    props.reasons_breakdown.length ? Math.max(...props.reasons_breakdown.map((r) => r.count)) : 1,
)

const maxBranchCount = computed(() =>
    props.by_branch.length ? Math.max(...props.by_branch.map((b) => b.count)) : 1,
)

const maxDayCount = computed(() =>
    props.by_day.length ? Math.max(...props.by_day.map((d) => d.count)) : 1,
)

function barWidth(count, max) {
    if (max === 0) { return '0%' }
    return Math.round((count / max) * 100) + '%'
}

function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}

function formatDateTime(dateStr) {
    if (!dateStr) { return '—' }
    return new Date(dateStr).toLocaleString('es-MX', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
    })
}

function formatDate(dateStr) {
    if (!dateStr) { return '—' }
    return new Date(dateStr + 'T12:00:00').toLocaleDateString('es-MX', {
        day: 'numeric', month: 'short',
    })
}

function orderNumber(id) {
    return '#' + String(id).padStart(4, '0')
}

function rateColor(rate) {
    if (rate >= 20) { return 'text-red-600' }
    if (rate >= 10) { return 'text-amber-600' }
    return 'text-green-600'
}
</script>

<template>
    <Head title="Cancelaciones" />
    <AppLayout title="Cancelaciones">

        <!-- Header + Filters -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Cancelaciones</h1>
                <p class="mt-1 text-sm text-gray-500">Reporte de pedidos cancelados.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Presets -->
                <div class="flex gap-1">
                    <button
                        v-for="p in [
                            { key: 'today', label: 'Hoy' },
                            { key: 'yesterday', label: 'Ayer' },
                            { key: 'week', label: '7 días' },
                            { key: 'month', label: 'Mes' },
                        ]"
                        :key="p.key"
                        @click="setPreset(p.key)"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                        :class="activePreset === p.key
                            ? 'bg-[#FF5722] text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >{{ p.label }}</button>
                </div>

                <!-- Date inputs -->
                <div class="flex items-center gap-1.5">
                    <input
                        type="date"
                        v-model="from"
                        @change="applyFilter"
                        class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-700 focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                    />
                    <span class="text-gray-400 text-xs">&mdash;</span>
                    <input
                        type="date"
                        v-model="to"
                        @change="applyFilter"
                        class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-700 focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                    />
                </div>

                <!-- Branch filter -->
                <select
                    v-model="branchId"
                    @change="applyFilter"
                    class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-700 focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                >
                    <option value="">Todas las sucursales</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">

            <!-- Pedidos cancelados -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-red-50 rounded-lg">
                        <span class="material-symbols-outlined text-red-600">cancel</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Pedidos cancelados</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ cancelled_count }}</h3>
                <p class="text-xs text-gray-400 mt-1">de {{ total_orders_count }} pedidos totales</p>
            </div>

            <!-- Tasa de cancelacion -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-amber-50 rounded-lg">
                        <span class="material-symbols-outlined text-amber-600">percent</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Tasa de cancelacion</p>
                <h3 class="text-3xl font-bold" :class="rateColor(cancellation_rate)">{{ cancellation_rate }}%</h3>
                <p class="text-xs text-gray-400 mt-1">cancelados / total del periodo</p>
            </div>

            <!-- Motivo mas frecuente -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <span class="material-symbols-outlined text-blue-600">info</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Motivo mas frecuente</p>
                <h3 class="text-lg font-bold text-gray-900 leading-snug">{{ top_reason ?? 'Sin datos' }}</h3>
            </div>

        </div>

        <!-- Reasons + Branch breakdown -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

            <!-- Motivos de cancelacion -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-6">Motivos de cancelacion</h3>
                <div v-if="reasons_breakdown.length" class="space-y-4">
                    <div v-for="item in reasons_breakdown" :key="item.reason">
                        <div class="flex justify-between items-end mb-1">
                            <span class="text-sm font-medium text-gray-700 line-clamp-1">{{ item.reason }}</span>
                            <span class="text-sm font-bold text-gray-900 shrink-0 ml-2">{{ item.count }} <span class="text-gray-400 font-normal">({{ item.percentage }}%)</span></span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3 relative overflow-hidden">
                            <div
                                class="absolute top-0 left-0 h-full bg-red-500 rounded-full transition-all"
                                :style="{ width: barWidth(item.count, maxReasonCount) }"
                            ></div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-8">Sin cancelaciones en este periodo.</p>
            </div>

            <!-- Cancelaciones por sucursal -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <h3 class="text-lg font-bold text-gray-900 mb-6">Cancelaciones por sucursal</h3>
                <div v-if="by_branch.length" class="space-y-4">
                    <div v-for="branch in by_branch" :key="branch.id">
                        <div class="flex justify-between items-end mb-1">
                            <span class="text-sm font-medium text-gray-700">{{ branch.name }}</span>
                            <span class="text-sm font-bold text-gray-900">{{ branch.count }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3 relative overflow-hidden">
                            <div
                                class="absolute top-0 left-0 h-full bg-[#FF5722] rounded-full transition-all"
                                :style="{ width: barWidth(branch.count, maxBranchCount) }"
                            ></div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-8">Sin datos de sucursales.</p>
            </div>

        </div>

        <!-- Cancelaciones por dia -->
        <div v-if="by_day.length" class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <h3 class="text-lg font-bold text-gray-900 mb-6">Cancelaciones por dia</h3>
            <div class="space-y-3">
                <div v-for="day in by_day" :key="day.date" class="flex items-center gap-4">
                    <span class="text-sm text-gray-500 w-20 shrink-0">{{ formatDate(day.date) }}</span>
                    <div class="flex-1 bg-gray-100 rounded-full h-3 relative overflow-hidden">
                        <div
                            class="absolute top-0 left-0 h-full bg-red-400 rounded-full transition-all"
                            :style="{ width: barWidth(day.count, maxDayCount) }"
                        ></div>
                    </div>
                    <span class="text-sm font-bold text-gray-900 w-8 text-right shrink-0">{{ day.count }}</span>
                </div>
            </div>
        </div>

        <!-- Tabla de pedidos cancelados -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Pedidos cancelados</h3>
                <Link
                    :href="route('orders.index')"
                    class="text-sm text-[#FF5722] hover:text-[#D84315] font-medium"
                >
                    Ver Kanban
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Pedido</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cancelado</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursal</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Total</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Motivo</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="order in cancelled_orders"
                            :key="order.id"
                            class="hover:bg-gray-50/50 transition-colors"
                        >
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ orderNumber(order.id) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ formatDateTime(order.cancelled_at) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ order.customer?.name ?? '&mdash;' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ order.branch?.name ?? '&mdash;' }}</td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900 text-right">{{ formatPrice(order.total) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600 max-w-xs truncate" :title="order.cancellation_reason">{{ order.cancellation_reason ?? '&mdash;' }}</td>
                            <td class="px-6 py-4 text-right">
                                <Link
                                    :href="route('orders.show', order.id)"
                                    class="text-gray-400 hover:text-[#FF5722] transition-colors"
                                >
                                    <span class="material-symbols-outlined">open_in_new</span>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!cancelled_orders.length">
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-400">
                                No hay pedidos cancelados en este periodo.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </AppLayout>
</template>
