<script setup>
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DatePicker from '@/Components/DatePicker.vue'

const isAdmin = computed(() => usePage().props.auth.user?.is_admin === true)

const props = defineProps({
    orders_count: Number,
    preparing_orders_count: Number,
    pos_sales_count: { type: Number, default: 0 },
    monthly_orders_count: Number,
    orders_limit: Number,
    orders_limit_start: String,
    orders_limit_end: String,
    net_profit: { type: Number, default: 0 },
    expenses_total: { type: Number, default: 0 },
    real_profit: { type: Number, default: 0 },
    revenue: { type: Number, default: 0 },
    revenue_breakdown: { type: Object, default: () => ({ orders: 0, pos: 0 }) },
    delivery_revenue: { type: Number, default: 0 },
    revenue_by_payment: { type: Object, default: () => ({ cash: 0, terminal: 0, transfer: 0 }) },
    orders_by_branch: Array,
    recent_orders: Array,
    branches: Array,
    filters: Object,
    channel: { type: String, default: null },
    can_view_cash_metrics: { type: Boolean, default: false },
    can_view_profit_metrics: { type: Boolean, default: false },
})

const from = ref(props.filters.from)
const to = ref(props.filters.to)
const branchId = ref(props.filters.branch_id || '')
const statusFilter = ref(props.filters.status || '')
const minAmount = ref(props.filters.min_amount || '')
const maxAmount = ref(props.filters.max_amount || '')
const channel = ref(props.filters.channel || '')
const showAdvanced = ref(!!(props.filters.status || props.filters.min_amount || props.filters.max_amount))

function applyFilter() {
    const params = { from: from.value, to: to.value }
    if (branchId.value) { params.branch_id = branchId.value }
    if (statusFilter.value) { params.status = statusFilter.value }
    if (minAmount.value) { params.min_amount = minAmount.value }
    if (maxAmount.value) { params.max_amount = maxAmount.value }
    if (channel.value) { params.channel = channel.value }
    router.get(route('dashboard'), params, { preserveState: true, preserveScroll: true })
}

function setChannel(value) {
    channel.value = value
    applyFilter()
}

function localDateStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

function setPreset(preset) {
    const today = new Date()

    if (preset === 'today') {
        from.value = localDateStr(today)
        to.value = localDateStr(today)
    } else if (preset === 'yesterday') {
        const y = new Date(today)
        y.setDate(y.getDate() - 1)
        from.value = localDateStr(y)
        to.value = localDateStr(y)
    } else if (preset === 'week') {
        const w = new Date(today)
        w.setDate(w.getDate() - 6)
        from.value = localDateStr(w)
        to.value = localDateStr(today)
    } else if (preset === 'month') {
        from.value = localDateStr(new Date(today.getFullYear(), today.getMonth(), 1))
        to.value = localDateStr(today)
    }
    applyFilter()
}

const STATUS_OPTIONS = [
    { key: 'received', label: 'Recibido' },
    { key: 'preparing', label: 'Preparacion' },
    { key: 'on_the_way', label: 'En camino' },
    { key: 'delivered', label: 'Entregado' },
    { key: 'cancelled', label: 'Cancelado' },
]

const activeStatuses = computed(() => statusFilter.value ? statusFilter.value.split(',') : [])

function toggleStatus(key) {
    const current = activeStatuses.value
    const idx = current.indexOf(key)
    if (idx === -1) { current.push(key) } else { current.splice(idx, 1) }
    statusFilter.value = current.join(',')
    applyFilter()
}

function clearFilters() {
    statusFilter.value = ''
    minAmount.value = ''
    maxAmount.value = ''
    applyFilter()
}

const hasAdvancedFilters = computed(() => !!(statusFilter.value || minAmount.value || maxAmount.value))

const monthlyPercent = computed(() => Math.min(
    100,
    props.orders_limit ? Math.round((props.monthly_orders_count / props.orders_limit) * 100) : 0,
))

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
    cancelled: 'Cancelado',
    ready: 'Lista',
    paid: 'Cobrada',
}

const STATUS_CLASSES = {
    received: 'bg-orange-100 text-orange-800',
    preparing: 'bg-amber-100 text-amber-800',
    on_the_way: 'bg-blue-100 text-blue-800',
    delivered: 'bg-green-100 text-green-800',
    cancelled: 'bg-red-100 text-red-800',
    ready: 'bg-blue-100 text-blue-800',
    paid: 'bg-green-100 text-green-800',
}

const DELIVERY_LABELS = {
    delivery: 'Domicilio',
    pickup: 'Recoger',
    dine_in: 'Restaurante',
}

const activePreset = computed(() => {
    const f = from.value
    const t = to.value
    const todayStr = localDateStr(new Date())
    if (f === todayStr && t === todayStr) { return 'today' }
    const y = new Date()
    y.setDate(y.getDate() - 1)
    if (f === localDateStr(y) && t === localDateStr(y)) { return 'yesterday' }
    const w = new Date()
    w.setDate(w.getDate() - 6)
    if (f === localDateStr(w) && t === todayStr) { return 'week' }
    const ms = new Date()
    if (f === localDateStr(new Date(ms.getFullYear(), ms.getMonth(), 1)) && t === todayStr) { return 'month' }
    return 'custom'
})
</script>

<template>
    <Head title="Dashboard" />
    <AppLayout title="Dashboard">

        <!-- Header + Date filter -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-4">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
                <p class="mt-1 text-sm text-gray-500">Resumen de tu restaurante.</p>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                <!-- Presets -->
                <div class="flex gap-1">
                    <button
                        v-for="p in [
                            { key: 'today', label: 'Hoy' },
                            { key: 'yesterday', label: 'Ayer' },
                            { key: 'week', label: '7 dias' },
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

                <!-- Channel selector -->
                <div class="flex gap-1">
                    <button
                        v-for="c in [
                            { key: '', label: 'Todos' },
                            { key: 'orders', label: 'Online' },
                            { key: 'pos', label: 'POS' },
                        ]"
                        :key="c.key || 'all'"
                        @click="setChannel(c.key)"
                        class="px-3 py-1.5 text-xs font-medium rounded-lg transition-colors"
                        :class="(channel || '') === c.key
                            ? 'bg-[#FF5722] text-white'
                            : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                    >{{ c.label }}</button>
                </div>

                <!-- Branch selector -->
                <select
                    v-if="branches && branches.length > 0"
                    v-model="branchId"
                    @change="applyFilter"
                    class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-700 bg-white focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                >
                    <option value="">Todas las sucursales</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>

                <!-- Date inputs -->
                <div class="flex items-center gap-1.5">
                    <DatePicker v-model="from" @change="applyFilter" placeholder="Desde" size="sm" />
                    <span class="text-gray-400 text-xs">—</span>
                    <DatePicker v-model="to" @change="applyFilter" placeholder="Hasta" size="sm" />
                </div>

                <!-- Toggle advanced -->
                <button
                    @click="showAdvanced = !showAdvanced"
                    class="flex items-center gap-1 px-2.5 py-1.5 text-xs font-medium rounded-lg transition-colors"
                    :class="hasAdvancedFilters ? 'bg-[#FF5722]/10 text-[#FF5722]' : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                >
                    <span class="material-symbols-outlined text-sm">tune</span>
                    Filtros
                    <span v-if="hasAdvancedFilters" class="w-1.5 h-1.5 rounded-full bg-[#FF5722]"></span>
                </button>
            </div>
        </div>

        <!-- Advanced filters panel -->
        <Transition name="slide">
            <div v-if="showAdvanced" class="bg-white border border-gray-100 rounded-xl p-4 mb-6 space-y-4">
                <!-- Status filter -->
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Estatus</p>
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="s in STATUS_OPTIONS"
                            :key="s.key"
                            @click="toggleStatus(s.key)"
                            class="px-3 py-1.5 text-xs font-medium rounded-full border transition-colors"
                            :class="activeStatuses.includes(s.key)
                                ? 'bg-[#FF5722] text-white border-[#FF5722]'
                                : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                        >{{ s.label }}</button>
                    </div>
                </div>

                <!-- Amount filter -->
                <div>
                    <p class="text-xs font-semibold text-gray-500 uppercase tracking-wide mb-2">Monto del pedido</p>
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                            <input
                                v-model="minAmount"
                                type="number"
                                min="0"
                                step="1"
                                placeholder="Minimo"
                                @change="applyFilter"
                                class="w-full pl-7 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                            />
                        </div>
                        <span class="text-gray-400 text-xs">—</span>
                        <div class="relative flex-1">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                            <input
                                v-model="maxAmount"
                                type="number"
                                min="0"
                                step="1"
                                placeholder="Maximo"
                                @change="applyFilter"
                                class="w-full pl-7 pr-3 py-1.5 text-xs border border-gray-200 rounded-lg focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                            />
                        </div>
                    </div>
                </div>

                <!-- Clear -->
                <div v-if="hasAdvancedFilters" class="flex justify-end">
                    <button
                        @click="clearFilters"
                        class="text-xs text-gray-500 hover:text-red-500 font-medium flex items-center gap-1 transition-colors"
                    >
                        <span class="material-symbols-outlined text-sm">close</span>
                        Limpiar filtros
                    </button>
                </div>
            </div>
        </Transition>

        <!-- KPI Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">

            <!-- Pedidos online en rango -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <span class="material-symbols-outlined text-blue-600">receipt_long</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Pedidos online</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ orders_count }}</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">cuentan al límite del plan</p>
            </div>

            <!-- Ventas POS -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <div class="p-2 bg-purple-50 rounded-lg">
                        <span class="material-symbols-outlined text-purple-600">point_of_sale</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Ventas POS</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ pos_sales_count }}</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">no cuentan al límite del plan</p>
            </div>

            <!-- En preparación -->
            <div class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <div class="p-2 bg-orange-50 rounded-lg">
                        <span class="material-symbols-outlined text-orange-600">skillet</span>
                    </div>
                    <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded-full">Activo</span>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">En preparación</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ preparing_orders_count }}</h3>
            </div>

            <!-- Ingresos totales — visible para admin + operator (métrica de caja) -->
            <div v-if="can_view_cash_metrics" class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <div class="p-2 bg-emerald-50 rounded-lg">
                        <span class="material-symbols-outlined text-emerald-600">trending_up</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Ingresos totales</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ formatPrice(revenue) }}</h3>
                <p class="text-[11px] text-gray-400 mt-0.5 truncate">
                    Online {{ formatPrice(revenue_breakdown.orders) }} · POS {{ formatPrice(revenue_breakdown.pos) }}
                </p>
                <p v-if="delivery_revenue > 0" class="text-[11px] text-sky-600 mt-0.5 truncate" title="Cobrado al cliente por envíos. No es ganancia del restaurante.">
                    Incluye envíos: {{ formatPrice(delivery_revenue) }}
                </p>
            </div>

            <!-- Utilidad real — admin only (después de gastos) -->
            <div v-if="can_view_profit_metrics" class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <div class="p-2 bg-green-50 rounded-lg">
                        <span class="material-symbols-outlined text-green-600">payments</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Utilidad real</p>
                <h3 class="text-3xl font-bold" :class="real_profit < 0 ? 'text-red-600' : 'text-gray-900'">
                    {{ formatPrice(real_profit) }}
                </h3>
                <p class="text-[11px] text-gray-400 mt-0.5">después de gastos</p>
            </div>

            <!-- Efectivo cobrado — reemplaza "Ganancia neta" para operator (cash-only replacement) -->
            <div v-if="can_view_cash_metrics && !can_view_profit_metrics" class="bg-white p-5 rounded-xl shadow-sm border border-gray-100 hover:shadow-md transition-shadow">
                <div class="flex justify-between items-start mb-3">
                    <div class="p-2 bg-green-50 rounded-lg">
                        <span class="material-symbols-outlined text-green-600">payments</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Efectivo cobrado</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ formatPrice(revenue_by_payment.cash) }}</h3>
                <p class="text-[11px] text-gray-400 mt-0.5">Monto físico por entregar</p>
            </div>

        </div>

        <!-- Desglose financiero (admin) — flujo ingresos → costos → gastos → utilidad real -->
        <div v-if="can_view_profit_metrics" class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <div class="flex items-center justify-between mb-5">
                <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                    <span class="material-symbols-outlined text-[#FF5722]">account_balance_wallet</span>
                    Resumen del periodo
                </h3>
                <Link
                    :href="route('expenses.index')"
                    class="text-xs text-[#FF5722] hover:text-[#D84315] font-medium flex items-center gap-1"
                >
                    Ver gastos <span class="material-symbols-outlined text-sm">arrow_forward</span>
                </Link>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-5 gap-4">
                <div class="p-4 rounded-xl border border-gray-100 bg-gray-50/50">
                    <p class="text-xs font-medium text-gray-500">Ingresos totales</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ formatPrice(revenue) }}</p>
                </div>
                <div class="p-4 rounded-xl border border-sky-100 bg-sky-50/50">
                    <p class="text-xs font-medium text-sky-700">Envíos cobrados</p>
                    <p class="text-xl font-bold text-sky-900 mt-1">{{ formatPrice(delivery_revenue) }}</p>
                    <p class="text-[11px] text-sky-600 mt-0.5">no es ganancia del restaurante</p>
                </div>
                <div class="p-4 rounded-xl border border-gray-100 bg-gray-50/50">
                    <p class="text-xs font-medium text-gray-500">Utilidad bruta</p>
                    <p class="text-xl font-bold text-gray-900 mt-1">{{ formatPrice(net_profit) }}</p>
                    <p class="text-[11px] text-gray-400 mt-0.5">tras costo de producción</p>
                </div>
                <div class="p-4 rounded-xl border border-amber-100 bg-amber-50/50">
                    <p class="text-xs font-medium text-amber-700">Gastos del periodo</p>
                    <p class="text-xl font-bold text-amber-900 mt-1">− {{ formatPrice(expenses_total) }}</p>
                </div>
                <div
                    class="p-4 rounded-xl border"
                    :class="real_profit < 0 ? 'border-red-100 bg-red-50/50' : 'border-green-100 bg-green-50/50'"
                >
                    <p class="text-xs font-medium" :class="real_profit < 0 ? 'text-red-700' : 'text-green-700'">Utilidad real</p>
                    <p class="text-xl font-bold mt-1" :class="real_profit < 0 ? 'text-red-900' : 'text-green-900'">
                        {{ formatPrice(real_profit) }}
                    </p>
                    <p class="text-[11px] mt-0.5" :class="real_profit < 0 ? 'text-red-500' : 'text-green-600'">
                        utilidad bruta − gastos
                    </p>
                </div>
            </div>
        </div>

        <!-- Cobros por metodo de pago -->
        <div v-if="revenue_by_payment" class="bg-white p-6 rounded-xl shadow-sm border border-gray-100 mb-8">
            <h3 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                <span class="material-symbols-outlined text-[#FF5722]">payments</span>
                Cobros por metodo de pago
            </h3>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div class="flex items-center gap-4 p-4 bg-green-50/50 rounded-xl border border-green-100">
                    <div class="w-10 h-10 rounded-lg bg-green-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-green-700">payments</span>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Efectivo</p>
                        <p class="text-xl font-bold text-gray-900">{{ formatPrice(revenue_by_payment.cash) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-4 bg-blue-50/50 rounded-xl border border-blue-100">
                    <div class="w-10 h-10 rounded-lg bg-blue-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-blue-700">credit_card</span>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Terminal / Tarjeta</p>
                        <p class="text-xl font-bold text-gray-900">{{ formatPrice(revenue_by_payment.terminal) }}</p>
                    </div>
                </div>
                <div class="flex items-center gap-4 p-4 bg-purple-50/50 rounded-xl border border-purple-100">
                    <div class="w-10 h-10 rounded-lg bg-purple-100 flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined text-purple-700">account_balance</span>
                    </div>
                    <div>
                        <p class="text-xs font-medium text-gray-500">Transferencia</p>
                        <p class="text-xl font-bold text-gray-900">{{ formatPrice(revenue_by_payment.transfer) }}</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Limit bar + Charts -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">

            <!-- Pedidos por sucursal -->
            <div class="lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-center mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Pedidos por sucursal</h3>
                    <Link
                        v-if="isAdmin"
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
                <div class="mb-6">
                    <h3 class="text-lg font-bold text-gray-900">Límite del periodo</h3>
                    <p class="text-xs text-gray-400 mt-1">
                        Solo pedidos de la app externa. Las ventas POS no cuentan al plan.
                    </p>
                </div>
                <div class="space-y-4">
                    <div>
                        <p class="text-sm text-gray-500 mb-1">Pedidos del plan</p>
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
                <div>
                    <h3 class="text-lg font-bold text-gray-900">Últimos pedidos</h3>
                    <p class="text-xs text-gray-500 mt-0.5">Mostrando los 20 más recientes</p>
                </div>
                <Link
                    :href="route('orders.index')"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl text-sm font-semibold text-[#FF5722] bg-orange-50 hover:bg-orange-100 transition-colors"
                >
                    Ver todos
                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                </Link>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse">
                    <thead class="bg-gray-50">
                        <tr>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Referencia</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Canal</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha/hora</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente / Cajero</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursal</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Total</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-4"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="row in recent_orders"
                            :key="row.channel + '-' + row.id"
                            class="hover:bg-gray-50/50 transition-colors"
                        >
                            <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ row.reference }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full border"
                                    :class="row.channel === 'pos'
                                        ? 'bg-purple-50 text-purple-700 border-purple-200'
                                        : 'bg-blue-50 text-blue-700 border-blue-200'"
                                >
                                    <span class="material-symbols-outlined text-sm">{{ row.channel === 'pos' ? 'point_of_sale' : 'receipt_long' }}</span>
                                    {{ row.channel === 'pos' ? 'POS' : 'Online' }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-sm text-gray-500 whitespace-nowrap">{{ formatDateTime(row.created_at) }}</td>
                            <td class="px-6 py-4 text-sm text-gray-700">{{ row.who ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm text-gray-600">{{ row.branch?.name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm font-semibold text-gray-900 text-right">{{ formatPrice(row.total) }}</td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium"
                                    :class="STATUS_CLASSES[row.status] ?? 'bg-gray-100 text-gray-600'"
                                >
                                    {{ STATUS_LABELS[row.status] ?? row.status }}
                                </span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <Link
                                    :href="row.channel === 'pos' ? route('pos.sales.show', row.id) : route('orders.show', row.id)"
                                    class="text-gray-400 hover:text-[#FF5722] transition-colors"
                                >
                                    <span class="material-symbols-outlined">open_in_new</span>
                                </Link>
                            </td>
                        </tr>
                        <tr v-if="!recent_orders.length">
                            <td colspan="8" class="px-6 py-12 text-center text-sm text-gray-400">
                                No hay actividad en este periodo.
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </AppLayout>
</template>

<style scoped>
.slide-enter-active, .slide-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}
.slide-enter-from, .slide-leave-to {
    opacity: 0;
    max-height: 0;
    margin-bottom: 0;
}
.slide-enter-to, .slide-leave-from {
    opacity: 1;
    max-height: 300px;
}
</style>
