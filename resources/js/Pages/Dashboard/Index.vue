<script setup>
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    today_orders_count: Number,
    yesterday_orders_count: Number,
    preparing_orders_count: Number,
    monthly_orders_count: Number,
    orders_limit: Number,
    net_profit_month: Number,
    orders_by_branch: Array,
    recent_orders: Array,
})

const monthlyPercent = Math.min(
    100,
    Math.round((props.monthly_orders_count / props.orders_limit) * 100),
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

function formatTime(dateStr) {
    return new Date(dateStr).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
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

const todayDiff = props.yesterday_orders_count > 0
    ? Math.round(((props.today_orders_count - props.yesterday_orders_count) / props.yesterday_orders_count) * 100)
    : null
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard">

        <!-- Header -->
        <div class="flex items-start justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="mt-1 text-sm text-gray-500">Resumen de tu restaurante.</p>
            </div>
        </div>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">

            <!-- Pedidos hoy -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <span class="material-symbols-outlined text-blue-600">receipt_long</span>
                    </div>
                    <span
                        v-if="todayDiff !== null"
                        class="flex items-center text-xs font-medium px-2 py-1 rounded-full"
                        :class="todayDiff >= 0 ? 'text-green-600 bg-green-50' : 'text-red-600 bg-red-50'"
                    >
                        <span class="material-symbols-outlined text-sm mr-0.5">{{ todayDiff >= 0 ? 'trending_up' : 'trending_down' }}</span>
                        {{ todayDiff >= 0 ? '+' : '' }}{{ todayDiff }}%
                    </span>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Pedidos de hoy</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ today_orders_count }}</h3>
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

            <!-- Pedidos mensuales -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-purple-50 rounded-lg">
                        <span class="material-symbols-outlined text-purple-600">calendar_month</span>
                    </div>
                    <span class="text-xs font-medium text-gray-500">Meta: {{ orders_limit }}</span>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Pedidos del periodo</p>
                <div class="flex items-baseline gap-2 mb-2">
                    <h3 class="text-3xl font-bold text-gray-900">{{ monthly_orders_count }}</h3>
                    <span class="text-sm text-gray-400">/ {{ orders_limit }}</span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5">
                    <div
                        class="bg-purple-500 h-1.5 rounded-full transition-all"
                        :style="{ width: monthlyPercent + '%' }"
                    ></div>
                </div>
            </div>

            <!-- Ganancia neta -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-green-50 rounded-lg">
                        <span class="material-symbols-outlined text-green-600">payments</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Ganancia neta (mes)</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ formatPrice(net_profit_month) }}</h3>
            </div>

        </div>

        <!-- Charts + Recent orders -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- Pedidos por sucursal -->
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Pedidos por sucursal (últimos 7 días)</h3>
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

            <!-- Pedidos de ayer vs hoy -->
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 flex flex-col justify-between">
                <h3 class="text-lg font-bold text-gray-900 mb-6">Comparativa diaria</h3>
                <div class="space-y-6">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Hoy</p>
                        <p class="text-4xl font-bold text-gray-900">{{ today_orders_count }}</p>
                    </div>
                    <div class="h-px bg-gray-100"></div>
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Ayer</p>
                        <p class="text-4xl font-bold text-gray-400">{{ yesterday_orders_count }}</p>
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
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursal</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Tipo</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Total</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="order in recent_orders"
                            :key="order.id"
                            class="hover:bg-gray-50/50 transition-colors"
                        >
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ orderNumber(order.id) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ order.customer?.name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ order.branch?.name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ DELIVERY_LABELS[order.delivery_type] ?? order.delivery_type }}</td>
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ formatPrice(order.total) }}</td>
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
                            <td colspan="7" class="px-6 py-12 text-center text-sm text-gray-400">
                                Aún no hay pedidos registrados.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </AppLayout>
</template>
