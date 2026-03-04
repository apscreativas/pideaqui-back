<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    orders_count: Number,
    preparing_orders_count: Number,
    monthly_orders_count: Number,
    orders_limit: Number,
    orders_limit_start: String,
    orders_limit_end: String,
    net_profit: Number,
    revenue: Number,
    orders_by_branch: Array,
    recent_orders: Array,
    filters: Object,
})

const from = ref(props.filters.from)
const to = ref(props.filters.to)

function applyFilter() {
    router.get(route('dashboard'), { from: from.value, to: to.value }, { preserveState: true, preserveScroll: true })
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

const monthlyPercent = Math.min(
    100,
    props.orders_limit ? Math.round((props.monthly_orders_count / props.orders_limit) * 100) : 0,
)

const maxBranchCount = props.orders_by_branch.length
    ? Math.max(...props.orders_by_branch.map((b) => b.count))
    : 1

function branchBarWidth(count) {
    if (maxBranchCount === 0) { return '0%' }
    return Math.round((count / maxBranchCount) * 100) + '%'
}

function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}

function formatDisplayDate(dateStr) {
    if (!dateStr) { return '' }
    return new Date(dateStr + 'T12:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })
}

function formatDateTime(dateStr) {
    return new Date(dateStr).toLocaleString('es-MX', {
        day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit',
    })
}

function orderNumber(id) {
    return '#' + String(id).padStart(4, '0')
}

const STATUS_LABELS = {
    received: 'Recibido',
    preparing: 'En preparación',
    on_the_way: 'En camino',
    delivered: 'Entregado',
}

const STATUS_CLASSES = {
    received: 'bg-orange-100 text-orange-800',
    preparing: 'bg-amber-100 text-amber-800',
    on_the_way: 'bg-blue-100 text-blue-800',
    delivered: 'bg-green-100 text-green-800',
}

const DELIVERY_LABELS = {
    delivery: 'Domicilio',
    pickup: 'Recoger',
    dine_in: 'Restaurante',
}

const isToday = from.value === to.value && from.value === new Date().toISOString().slice(0, 10)
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard">

        <!-- Header + Date filter -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="mt-1 text-sm text-gray-500">Resumen de tu restaurante.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Presets -->
                <div class="flex gap-1">
                    <button
                        @click="setPreset('today')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                        :class="isToday ? 'bg-[#FF5722] text-white' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >Hoy</button>
                    <button
                        @click="setPreset('yesterday')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"
                    >Ayer</button>
                    <button
                        @click="setPreset('week')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"
                    >7 días</button>
                    <button
                        @click="setPreset('month')"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg bg-gray-100 text-gray-600 hover:bg-gray-200 transition-colors"
                    >Mes</button>
                </div>

                <!-- Date inputs -->
                <div class="flex items-center gap-1.5">
                    <input
                        type="date"
                        v-model="from"
                        @change="applyFilter"
                        class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-700 focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                    />
                    <span class="text-gray-400 text-xs">—</span>
                    <input
                        type="date"
                        v-model="to"
                        @change="applyFilter"
                        class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-700 focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                    />
                </div>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <!-- Pedidos en rango -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <span class="material-symbols-outlined text-blue-600">receipt_long</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Pedidos</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ orders_count }}</h3>
            </div>

            <!-- En preparación -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-orange-50 rounded-lg">
                        <span class="material-symbols-outlined text-orange-600">skillet</span>
                    </div>
                    <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">Activo</span>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">En preparación</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ preparing_orders_count }}</h3>
            </div>

            <!-- Ventas -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-emerald-50 rounded-lg">
                        <span class="material-symbols-outlined text-emerald-600">point_of_sale</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Ventas</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ formatPrice(revenue) }}</h3>
            </div>

            <!-- Ganancia neta -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-green-50 rounded-lg">
                        <span class="material-symbols-outlined text-green-600">payments</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Ganancia neta</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ formatPrice(net_profit) }}</h3>
            </div>

        </div>

        <!-- Limit bar + Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- Pedidos por sucursal -->
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Pedidos por sucursal</h3>
                    <Link
                        :href="route('branches.index')"
                        class="text-sm text-[#FF5722] hover:text-[#D84315] font-medium flex items-center gap-1"
                    >
                        Ver sucursales <span class="material-symbols-outlined text-sm">arrow_forward</span>
                    </Link>
                </div>
                <div v-if="orders_by_branch.length" class="space-y-5">
                    <div v-for="branch in orders_by_branch" :key="branch.id">
                        <div class="flex justify-between items-end mb-1">
                            <span class="text-sm font-medium text-gray-700">{{ branch.name }}</span>
                            <span class="text-sm font-bold text-gray-900">{{ branch.count }} pedidos</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3 relative overflow-hidden">
                            <div
                                class="absolute top-0 left-0 h-full bg-[#FF5722] rounded-full transition-all"
                                :style="{ width: branchBarWidth(branch.count) }"
                            ></div>
                        </div>
                    </div>
                </div>
                <p v-else class="text-sm text-gray-400 text-center py-8">Sin datos de sucursales.</p>
            </div>

            <!-- Límite del periodo -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between">
                <h3 class="text-lg font-bold text-gray-900 mb-6">Límite del periodo</h3>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Pedidos del periodo</p>
                        <div class="flex items-baseline gap-2">
                            <p class="text-4xl font-bold text-gray-900">{{ monthly_orders_count }}</p>
                            <span class="text-sm text-gray-400">/ {{ orders_limit }}</span>
                        </div>
                    </div>
                    <div class="w-full bg-gray-100 rounded-full h-2">
                        <div
                            class="h-2 rounded-full transition-all"
                            :class="monthlyPercent >= 90 ? 'bg-red-500' : monthlyPercent >= 70 ? 'bg-amber-500' : 'bg-purple-500'"
                            :style="{ width: monthlyPercent + '%' }"
                        ></div>
                    </div>
                    <p class="text-xs text-gray-400">{{ monthlyPercent }}% utilizado</p>
                    <div v-if="orders_limit_start && orders_limit_end" class="flex justify-between text-xs text-gray-400 mt-1">
                        <span>Inicia: {{ formatDisplayDate(orders_limit_start) }}</span>
                        <span>Termina: {{ formatDisplayDate(orders_limit_end) }}</span>
                    </div>
                </div>
                <Link
                    :href="route('orders.index')"
                    class="mt-6 flex items-center justify-center gap-2 w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors"
                >
                    Ver pedidos
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </Link>
            </div>

        </div>

        <!-- Últimos pedidos -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="p-6 border-b border-gray-100 flex justify-between items-center">
                <h3 class="text-lg font-bold text-gray-900">Últimos pedidos</h3>
                <Link
                    :href="route('orders.index')"
                    class="text-sm text-[#FF5722] hover:text-[#D84315] font-medium"
                >
                    Ver todos
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Pedido</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha/hora</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursal</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Subtotal</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Envío</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Total</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="order in recent_orders"
                            :key="order.id"
                            class="hover:bg-gray-50/50 transition-colors"
                        >
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ orderNumber(order.id) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ formatDateTime(order.created_at) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ order.customer?.name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ order.branch?.name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ DELIVERY_LABELS[order.delivery_type] ?? order.delivery_type }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600 text-right">{{ formatPrice(order.subtotal) }}</td>
                            <td class="px-6 py-4 text-sm text-right" :class="Number(order.delivery_cost) > 0 ? 'text-gray-600' : 'text-gray-300'">
                                {{ Number(order.delivery_cost) > 0 ? formatPrice(order.delivery_cost) : '—' }}
                            </td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900 text-right">{{ formatPrice(order.total) }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="STATUS_CLASSES[order.status]"
                                >
                                    {{ STATUS_LABELS[order.status] ?? order.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <Link
                                    :href="route('orders.show', order.id)"
                                    class="text-gray-400 hover:text-[#FF5722] transition-colors"
                                >
                                    <span class="material-symbols-outlined">open_in_new</span>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!recent_orders.length">
                            <td colspan="10" class="px-6 py-12 text-center text-sm text-gray-400">
                                No hay pedidos en este periodo.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </AppLayout>
</template>
