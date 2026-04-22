<script setup>
import { Head, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    mrr: Number,
    active_subscriptions: Number,
    total_restaurants: Number,
    new_this_month: Number,
    canceled_this_month: Number,
    total_monthly_orders: Number,
    by_status: Object,
    by_plan: Array,
    monthly_vs_annual: Object,
    new_subs_by_day: Object,
    recent_plan_changes: Array,
    alerts: Object,
    recent_events: Array,
    at_risk_restaurants: Array,
})

const activeTab = ref('overview')

const tabs = [
    { key: 'overview', label: 'Resumen', icon: 'dashboard' },
    { key: 'revenue', label: 'Ingresos', icon: 'payments' },
    { key: 'subscriptions', label: 'Suscripciones', icon: 'card_membership' },
    { key: 'alerts', label: 'Alertas', icon: 'notifications' },
]

// ─── Formatters ───

function formatCurrency(amount) {
    return Number(amount || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function formatCurrencyFull(amount) {
    return Number(amount || 0).toLocaleString('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 2, maximumFractionDigits: 2 })
}

function relativeTime(dateStr) {
    if (!dateStr) return ''
    const now = new Date()
    const date = new Date(dateStr)
    const diffMs = now - date
    const diffSec = Math.floor(diffMs / 1000)
    const diffMin = Math.floor(diffSec / 60)
    const diffHr = Math.floor(diffMin / 60)
    const diffDay = Math.floor(diffHr / 24)

    if (diffSec < 60) return 'hace un momento'
    if (diffMin < 60) return `hace ${diffMin} ${diffMin === 1 ? 'minuto' : 'minutos'}`
    if (diffHr < 24) return `hace ${diffHr} ${diffHr === 1 ? 'hora' : 'horas'}`
    if (diffDay < 30) return `hace ${diffDay} ${diffDay === 1 ? 'día' : 'días'}`
    return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
}

function formatShortDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('es-MX', { day: 'numeric', month: 'short' })
}

function graceDaysLeft(dateStr) {
    if (!dateStr) return 0
    const diff = Math.ceil((new Date(dateStr) - new Date()) / (1000 * 60 * 60 * 24))
    return Math.max(0, diff)
}

// ─── Event Icons ───

const eventIconMap = {
    subscription_started: { icon: 'add_circle', color: 'text-green-600', bg: 'bg-green-50' },
    payment_succeeded: { icon: 'check_circle', color: 'text-green-600', bg: 'bg-green-50' },
    payment_failed: { icon: 'error', color: 'text-red-600', bg: 'bg-red-50' },
    plan_changed: { icon: 'swap_horiz', color: 'text-blue-600', bg: 'bg-blue-50' },
    subscription_canceled: { icon: 'cancel', color: 'text-gray-500', bg: 'bg-gray-100' },
    suspended: { icon: 'block', color: 'text-red-600', bg: 'bg-red-50' },
    reactivated: { icon: 'restart_alt', color: 'text-green-600', bg: 'bg-green-50' },
    grace_period_started: { icon: 'schedule', color: 'text-orange-600', bg: 'bg-orange-50' },
    restaurant_created: { icon: 'store', color: 'text-blue-600', bg: 'bg-blue-50' },
    enabled: { icon: 'toggle_on', color: 'text-green-600', bg: 'bg-green-50' },
    disabled: { icon: 'toggle_off', color: 'text-gray-500', bg: 'bg-gray-100' },
    grace_period_extended: { icon: 'more_time', color: 'text-orange-600', bg: 'bg-orange-50' },
}

const defaultEventIcon = { icon: 'info', color: 'text-gray-500', bg: 'bg-gray-100' }

function getEventIcon(action) {
    return eventIconMap[action] || defaultEventIcon
}

function eventLabel(action) {
    const labels = {
        subscription_started: 'Suscripción iniciada',
        payment_succeeded: 'Pago exitoso',
        payment_failed: 'Pago fallido',
        plan_changed: 'Cambio de plan',
        subscription_canceled: 'Suscripción cancelada',
        suspended: 'Suspendido',
        reactivated: 'Reactivado',
        grace_period_started: 'Periodo de gracia',
        restaurant_created: 'Restaurante creado',
        enabled: 'Habilitado',
        disabled: 'Deshabilitado',
        grace_period_extended: 'Gracia extendida',
    }
    return labels[action] || action
}

// ─── Status Badges ───

function statusBadge(status) {
    const map = {
        active: { label: 'Activo', class: 'bg-green-50 text-green-700' },
        past_due: { label: 'Pago pendiente', class: 'bg-yellow-50 text-yellow-700' },
        grace_period: { label: 'Periodo de gracia', class: 'bg-orange-50 text-orange-700' },
        suspended: { label: 'Suspendido', class: 'bg-red-50 text-red-700' },
        canceled: { label: 'Cancelado', class: 'bg-gray-100 text-gray-500' },
        no_subscription: { label: 'Sin suscripción', class: 'bg-blue-50 text-blue-600' },
    }
    return map[status] || { label: status, class: 'bg-gray-100 text-gray-500' }
}

// ─── Bar Chart (new_subs_by_day) ───

const chartEntries = computed(() =>
    Object.entries(props.new_subs_by_day || {}).map(([date, count]) => [date, parseInt(count, 10) || 0]),
)
const maxSubsCount = computed(() => Math.max(1, ...chartEntries.value.map(([, c]) => c)))

const yTicks = computed(() => {
    const m = maxSubsCount.value
    if (m <= 5) { return Array.from({ length: m + 1 }, (_, i) => i) }
    const step = Math.ceil(m / 4)
    const ticks = []
    for (let i = 0; i <= m; i += step) { ticks.push(i) }
    if (ticks[ticks.length - 1] < m) { ticks.push(m) }
    return ticks
})

function barHeight(count) {
    return Math.max(3, (count / maxSubsCount.value) * 100)
}

function formatChartDate(dateStr) {
    const [, m, d] = dateStr.split('-')
    return `${d}/${m}`
}

function showChartLabel(index) {
    const total = chartEntries.value.length
    if (total <= 15) { return true }
    if (total <= 20) { return index % 2 === 0 }
    return index % 3 === 0
}

const totalNewSubs = computed(() => chartEntries.value.reduce((s, [, c]) => s + c, 0))

// ─── Plan Distribution Chart ───

const maxPlanCount = computed(() => {
    if (!props.by_plan || props.by_plan.length === 0) return 1
    return Math.max(1, ...props.by_plan.map(p => p.count))
})

// ─── Computed: ARR ───

const arr = computed(() => (props.mrr || 0) * 12)

// ─── Computed: Alerts total ───

const totalAlerts = computed(() => {
    if (!props.alerts) return 0
    return (props.alerts.past_due || 0) + (props.alerts.grace_period || 0) + (props.alerts.suspended || 0) + (props.alerts.no_subscription || 0)
})
</script>

<template>
    <Head title="SuperAdmin — Dashboard" />
    <SuperAdminLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Dashboard</h1>
            <p class="mt-1 text-sm text-gray-500">Panel de control de la plataforma PideAqui.</p>
        </div>

        <!-- ─── Tab Selector ─── -->

        <div class="mb-6">
            <div class="inline-flex bg-gray-100 rounded-xl p-1">
                <button
                    v-for="tab in tabs"
                    :key="tab.key"
                    @click="activeTab = tab.key"
                    class="flex items-center gap-2 px-5 py-2 rounded-lg text-sm font-semibold transition-all"
                    :class="activeTab === tab.key
                        ? 'bg-white text-gray-900 shadow-sm'
                        : 'text-gray-500 hover:text-gray-700'"
                >
                    <span class="material-symbols-outlined text-lg" :style="activeTab === tab.key ? 'font-variation-settings:\'FILL\' 1' : ''">{{ tab.icon }}</span>
                    <span>{{ tab.label }}</span>
                    <span
                        v-if="tab.key === 'alerts' && totalAlerts > 0"
                        class="inline-flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-bold bg-red-500 text-white"
                    >{{ totalAlerts > 9 ? '9+' : totalAlerts }}</span>
                </button>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- TAB 1: RESUMEN                                     -->
        <!-- ═══════════════════════════════════════════════════ -->

        <div v-if="activeTab === 'overview'">

            <!-- KPI Cards -->
            <div class="grid grid-cols-4 gap-5 mb-8">
                <!-- MRR (prominent) -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-[#FF5722]/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">trending_up</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">MRR</span>
                    </div>
                    <p class="text-3xl font-bold text-[#FF5722]">{{ formatCurrency(mrr) }}</p>
                    <p class="text-xs text-gray-400 mt-1">Ingreso mensual recurrente</p>
                </div>

                <!-- Suscripciones activas -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-green-600" style="font-variation-settings:'FILL' 1">card_membership</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Suscripciones activas</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ active_subscriptions }}</p>
                </div>

                <!-- Nuevos del mes -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600" style="font-variation-settings:'FILL' 1">group_add</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Nuevos del mes</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ new_this_month }}</p>
                </div>

                <!-- Pedidos del mes -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-purple-600" style="font-variation-settings:'FILL' 1">receipt_long</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Pedidos del mes</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ (total_monthly_orders || 0).toLocaleString('es-MX') }}</p>
                </div>
            </div>

            <!-- 2-column grid: Distribution + Activity -->
            <div class="grid grid-cols-2 gap-5">

                <!-- Distribución por plan -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-5">Distribución por plan</h2>

                    <div v-if="!by_plan || by_plan.length === 0" class="text-center py-8 text-sm text-gray-400">
                        Sin planes registrados.
                    </div>
                    <div v-else class="space-y-4">
                        <div v-for="plan in by_plan" :key="plan.name" class="group">
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-sm font-semibold text-gray-900">{{ plan.name }}</span>
                                <span class="text-sm font-bold text-gray-700">{{ plan.count }} <span class="text-xs font-normal text-gray-400">{{ plan.count === 1 ? 'restaurante' : 'restaurantes' }}</span></span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                                <div
                                    class="h-full rounded-full bg-[#FF5722]/70 group-hover:bg-[#FF5722] transition-colors"
                                    :style="{ width: Math.max(3, (plan.count / maxPlanCount) * 100) + '%' }"
                                ></div>
                            </div>
                            <p class="text-xs text-gray-400 mt-1">{{ formatCurrency(plan.revenue) }}/mes</p>
                        </div>
                    </div>
                </div>

                <!-- Actividad reciente -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                    <div class="px-6 py-4 border-b border-gray-100">
                        <h2 class="text-base font-semibold text-gray-900">Actividad reciente</h2>
                    </div>
                    <div class="divide-y divide-gray-50 max-h-[420px] overflow-y-auto">
                        <div v-if="!recent_events || recent_events.length === 0" class="px-6 py-8 text-center text-sm text-gray-400">
                            Sin actividad reciente.
                        </div>
                        <div
                            v-for="(event, idx) in (recent_events || []).slice(0, 10)"
                            :key="idx"
                            class="px-6 py-3.5 flex items-start gap-3"
                        >
                            <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5" :class="getEventIcon(event.action).bg">
                                <span class="material-symbols-outlined text-lg" :class="getEventIcon(event.action).color" style="font-variation-settings:'FILL' 1">{{ getEventIcon(event.action).icon }}</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-gray-900">
                                    <span class="font-semibold">{{ event.restaurant || 'Sistema' }}</span>
                                    <span class="text-gray-500"> — {{ eventLabel(event.action) }}</span>
                                </p>
                                <p class="text-xs text-gray-400 mt-0.5">{{ relativeTime(event.date) }}</p>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- TAB 2: INGRESOS                                    -->
        <!-- ═══════════════════════════════════════════════════ -->

        <div v-if="activeTab === 'revenue'">

            <!-- KPI Cards -->
            <div class="grid grid-cols-2 gap-5 mb-8">
                <!-- MRR -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-[#FF5722]/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">trending_up</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">MRR</span>
                    </div>
                    <p class="text-3xl font-bold text-[#FF5722]">{{ formatCurrency(mrr) }}</p>
                    <p class="text-xs text-gray-400 mt-1">Ingreso mensual recurrente</p>
                </div>

                <!-- ARR -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-green-600" style="font-variation-settings:'FILL' 1">account_balance</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">ARR</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ formatCurrency(arr) }}</p>
                    <p class="text-xs text-gray-400 mt-1">Ingreso anual recurrente (MRR x 12)</p>
                </div>
            </div>

            <!-- Ingresos por plan -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm mb-6">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Ingresos por plan</h2>
                </div>
                <div v-if="!by_plan || by_plan.length === 0" class="px-6 py-8 text-center text-sm text-gray-400">
                    Sin planes registrados.
                </div>
                <table v-else class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Suscriptores</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Precio mensual</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Precio anual</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Contribución MRR</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="plan in by_plan" :key="plan.name" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="text-sm font-semibold text-gray-900">{{ plan.name }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-sm font-bold text-gray-900">{{ plan.count }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-sm text-gray-600">{{ formatCurrencyFull(plan.monthly_price) }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-sm text-gray-600">{{ formatCurrencyFull(plan.yearly_price) }}</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <span class="text-sm font-bold text-[#FF5722]">{{ formatCurrency(plan.revenue) }}</span>
                            </td>
                        </tr>
                    </tbody>
                    <tfoot>
                        <tr class="border-t border-gray-200 bg-gray-50/50">
                            <td class="px-6 py-3 text-sm font-bold text-gray-900">Total</td>
                            <td class="px-6 py-3 text-right text-sm font-bold text-gray-900">{{ by_plan.reduce((s, p) => s + p.count, 0) }}</td>
                            <td class="px-6 py-3"></td>
                            <td class="px-6 py-3"></td>
                            <td class="px-6 py-3 text-right text-sm font-bold text-[#FF5722]">{{ formatCurrency(mrr) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <!-- Facturación: mensual vs anual -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-base font-semibold text-gray-900 mb-4">Facturación</h2>
                <div class="flex items-center gap-8">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600" style="font-variation-settings:'FILL' 1">calendar_month</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ monthly_vs_annual?.monthly || 0 }}</p>
                            <p class="text-xs text-gray-400">Suscripciones mensuales</p>
                        </div>
                    </div>
                    <div class="h-12 w-px bg-gray-200"></div>
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl bg-purple-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-purple-600" style="font-variation-settings:'FILL' 1">date_range</span>
                        </div>
                        <div>
                            <p class="text-2xl font-bold text-gray-900">{{ monthly_vs_annual?.yearly || 0 }}</p>
                            <p class="text-xs text-gray-400">Suscripciones anuales</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- TAB 3: SUSCRIPCIONES                               -->
        <!-- ═══════════════════════════════════════════════════ -->

        <div v-if="activeTab === 'subscriptions'">

            <!-- KPI Cards -->
            <div class="grid grid-cols-4 gap-5 mb-8">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-green-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-green-600" style="font-variation-settings:'FILL' 1">check_circle</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Activas</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ by_status?.active || 0 }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-orange-600" style="font-variation-settings:'FILL' 1">schedule</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">En grace period</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ by_status?.grace_period || 0 }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-yellow-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-yellow-600" style="font-variation-settings:'FILL' 1">warning</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Past due</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ by_status?.past_due || 0 }}</p>
                </div>

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-gray-500" style="font-variation-settings:'FILL' 1">cancel</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Canceladas del mes</span>
                    </div>
                    <p class="text-3xl font-bold text-gray-900">{{ canceled_this_month || 0 }}</p>
                </div>
            </div>

            <!-- Bar Chart: Nuevas suscripciones -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
                <h2 class="text-base font-semibold text-gray-900 mb-6">Nuevas suscripciones (30 días)</h2>

                <div v-if="chartEntries.length === 0" class="text-center py-12 text-sm text-gray-400">
                    Sin datos disponibles.
                </div>
                <div v-else class="relative">
                    <!-- Chart area -->
                    <div class="flex">
                        <!-- Y axis -->
                        <div class="flex flex-col justify-between h-56 pr-3 shrink-0">
                            <span v-for="tick in [...yTicks].reverse()" :key="tick" class="text-xs text-gray-400 text-right tabular-nums leading-none">
                                {{ tick }}
                            </span>
                        </div>

                        <!-- Bars area -->
                        <div class="flex-1 min-w-0 relative">
                            <!-- Horizontal grid lines -->
                            <div class="absolute inset-0 flex flex-col justify-between pointer-events-none">
                                <div v-for="tick in yTicks" :key="'g'+tick" class="border-t border-gray-100 w-full" :class="{ 'border-gray-200': tick === 0 }"></div>
                            </div>

                            <!-- Bars -->
                            <div class="relative flex items-end gap-[3px] h-56 overflow-x-auto no-scrollbar">
                                <div
                                    v-for="([date, count], index) in chartEntries"
                                    :key="date"
                                    class="flex-1 min-w-[14px] max-w-[28px] flex flex-col items-center group relative"
                                    style="height: 100%;"
                                >
                                    <!-- Tooltip -->
                                    <div class="absolute -top-8 left-1/2 -translate-x-1/2 bg-gray-900 text-white text-xs px-2 py-1 rounded-lg opacity-0 group-hover:opacity-100 transition-opacity whitespace-nowrap pointer-events-none z-10">
                                        {{ formatChartDate(date) }}: <strong>{{ count }}</strong> {{ count === 1 ? 'suscripción' : 'suscripciones' }}
                                    </div>

                                    <!-- Bar wrapper -->
                                    <div class="flex-1"></div>
                                    <div
                                        class="w-full rounded-t-sm bg-[#FF5722]/70 group-hover:bg-[#FF5722] transition-colors cursor-default"
                                        :style="{ height: barHeight(count) + '%' }"
                                    ></div>
                                </div>
                            </div>

                            <!-- X axis labels -->
                            <div class="flex gap-[3px] mt-2">
                                <div
                                    v-for="([date], index) in chartEntries"
                                    :key="'l'+date"
                                    class="flex-1 min-w-[14px] max-w-[28px] text-center"
                                >
                                    <span v-if="showChartLabel(index)" class="text-[10px] text-gray-400 tabular-nums">
                                        {{ formatChartDate(date) }}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="flex items-center gap-8 mt-4 pt-4 border-t border-gray-100">
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Total nuevas</p>
                            <p class="text-xl font-bold text-gray-900">{{ totalNewSubs }}</p>
                        </div>
                        <div>
                            <p class="text-xs text-gray-400 mb-0.5">Día con más altas</p>
                            <p class="text-xl font-bold text-gray-900">{{ maxSubsCount }} <span class="text-xs font-normal text-gray-400">suscripciones</span></p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Cambios de plan recientes -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Cambios de plan recientes</h2>
                </div>
                <div class="divide-y divide-gray-50">
                    <div v-if="!recent_plan_changes || recent_plan_changes.length === 0" class="px-6 py-8 text-center text-sm text-gray-400">
                        Sin cambios de plan recientes.
                    </div>
                    <div
                        v-for="(change, idx) in recent_plan_changes"
                        :key="idx"
                        class="px-6 py-4 flex items-center gap-4"
                    >
                        <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-blue-600 text-lg" style="font-variation-settings:'FILL' 1">swap_horiz</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <p class="text-sm font-semibold text-gray-900">{{ change.restaurant }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-gray-100 text-gray-600 text-xs font-medium">{{ change.old_plan }}</span>
                                <span class="material-symbols-outlined text-gray-400 text-sm align-middle mx-1">arrow_forward</span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-[#FF5722]/10 text-[#FF5722] text-xs font-medium">{{ change.new_plan }}</span>
                            </p>
                        </div>
                        <span class="text-xs text-gray-400 shrink-0">{{ formatShortDate(change.date) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════ -->
        <!-- TAB 4: ALERTAS                                     -->
        <!-- ═══════════════════════════════════════════════════ -->

        <div v-if="activeTab === 'alerts'">

            <!-- ─── Alertas accionables (Paquete A) ─── -->
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Alertas accionables</h3>
            <div class="grid grid-cols-4 gap-5 mb-8">
                <!-- Gracia expira pronto -->
                <Link
                    :href="route('super.restaurants.index', { alert: 'grace_expiring' })"
                    class="bg-white rounded-xl border-l-4 border-l-red-500 border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow group"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-red-600" style="font-variation-settings:'FILL' 1">hourglass_bottom</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Gracia expira ≤3 días</span>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-gray-900">{{ alerts?.grace_expiring_soon || 0 }}</p>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-[#FF5722] transition-colors">arrow_forward</span>
                    </div>
                </Link>

                <!-- Cerca del límite -->
                <Link
                    :href="route('super.restaurants.index', { alert: 'orders_near_limit' })"
                    class="bg-white rounded-xl border-l-4 border-l-amber-500 border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow group"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-amber-600" style="font-variation-settings:'FILL' 1">speed</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">≥80% del límite</span>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-gray-900">{{ alerts?.orders_near_limit || 0 }}</p>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-[#FF5722] transition-colors">arrow_forward</span>
                    </div>
                </Link>

                <!-- Modo manual -->
                <Link
                    :href="route('super.restaurants.index', { alert: 'billing_manual' })"
                    class="bg-white rounded-xl border-l-4 border-l-gray-400 border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow group"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-gray-600" style="font-variation-settings:'FILL' 1">settings</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Modo manual</span>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-gray-900">{{ alerts?.billing_manual || 0 }}</p>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-[#FF5722] transition-colors">arrow_forward</span>
                    </div>
                </Link>

                <!-- Nuevos esta semana -->
                <Link
                    :href="route('super.restaurants.index', { alert: 'new_this_week' })"
                    class="bg-white rounded-xl border-l-4 border-l-blue-400 border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow group"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600" style="font-variation-settings:'FILL' 1">fiber_new</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Nuevos en 7 días</span>
                    </div>
                    <div class="flex items-end justify-between">
                        <div>
                            <p class="text-3xl font-bold text-gray-900">{{ alerts?.new_this_week?.total || 0 }}</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                <span class="font-medium">{{ alerts?.new_this_week?.self_signup || 0 }}</span> self +
                                <span class="font-medium">{{ alerts?.new_this_week?.super_admin || 0 }}</span> admin
                            </p>
                        </div>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-[#FF5722] transition-colors">arrow_forward</span>
                    </div>
                </Link>
            </div>

            <!-- ─── Estado general ─── -->
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wider mb-3">Estado general</h3>
            <div class="grid grid-cols-4 gap-5 mb-8">
                <Link
                    :href="route('super.restaurants.index', { alert: 'past_due' })"
                    class="bg-white rounded-xl border-l-4 border-l-yellow-400 border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow group"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-yellow-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-yellow-600" style="font-variation-settings:'FILL' 1">warning</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Past due</span>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-gray-900">{{ alerts?.past_due || 0 }}</p>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-[#FF5722] transition-colors">arrow_forward</span>
                    </div>
                </Link>

                <Link
                    :href="route('super.restaurants.index', { alert: 'grace_period' })"
                    class="bg-white rounded-xl border-l-4 border-l-orange-400 border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow group"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-orange-600" style="font-variation-settings:'FILL' 1">schedule</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Grace period</span>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-gray-900">{{ alerts?.grace_period || 0 }}</p>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-[#FF5722] transition-colors">arrow_forward</span>
                    </div>
                </Link>

                <Link
                    :href="route('super.restaurants.index', { alert: 'suspended' })"
                    class="bg-white rounded-xl border-l-4 border-l-red-400 border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow group"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-red-600" style="font-variation-settings:'FILL' 1">block</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Suspendidos</span>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-gray-900">{{ alerts?.suspended || 0 }}</p>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-[#FF5722] transition-colors">arrow_forward</span>
                    </div>
                </Link>

                <Link
                    :href="route('super.restaurants.index', { alert: 'no_subscription' })"
                    class="bg-white rounded-xl border-l-4 border-l-blue-400 border border-gray-100 shadow-sm p-6 hover:shadow-md transition-shadow group"
                >
                    <div class="flex items-center gap-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600" style="font-variation-settings:'FILL' 1">help_outline</span>
                        </div>
                        <span class="text-sm font-medium text-gray-500">Sin suscripción</span>
                    </div>
                    <div class="flex items-end justify-between">
                        <p class="text-3xl font-bold text-gray-900">{{ alerts?.no_subscription || 0 }}</p>
                        <span class="material-symbols-outlined text-gray-300 group-hover:text-[#FF5722] transition-colors">arrow_forward</span>
                    </div>
                </Link>
            </div>

            <!-- Restaurantes en riesgo -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                <div class="px-6 py-4 border-b border-gray-100">
                    <h2 class="text-base font-semibold text-gray-900">Restaurantes en riesgo</h2>
                </div>

                <div v-if="!at_risk_restaurants || at_risk_restaurants.length === 0" class="px-6 py-8 text-center text-sm text-gray-400">
                    No hay restaurantes en riesgo actualmente.
                </div>

                <table v-else class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Restaurante</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Estado</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Plan</th>
                            <th class="px-6 py-3 text-left text-xs font-semibold text-gray-500 uppercase tracking-wider">Gracia vence</th>
                            <th class="px-6 py-3 text-right text-xs font-semibold text-gray-500 uppercase tracking-wider">Acción</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr v-for="restaurant in at_risk_restaurants" :key="restaurant.id" class="hover:bg-gray-50/50 transition-colors">
                            <td class="px-6 py-4">
                                <span class="text-sm font-semibold text-gray-900">{{ restaurant.name }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span
                                    class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                                    :class="statusBadge(restaurant.status).class"
                                >{{ statusBadge(restaurant.status).label }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <span class="text-sm text-gray-600">{{ restaurant.plan || '—' }}</span>
                            </td>
                            <td class="px-6 py-4">
                                <template v-if="restaurant.grace_ends">
                                    <span class="text-sm text-gray-900">{{ formatShortDate(restaurant.grace_ends) }}</span>
                                    <span class="text-xs text-gray-400 ml-1.5">({{ graceDaysLeft(restaurant.grace_ends) }} días)</span>
                                </template>
                                <span v-else class="text-sm text-gray-400">—</span>
                            </td>
                            <td class="px-6 py-4 text-right">
                                <Link
                                    :href="route('super.restaurants.show', restaurant.id)"
                                    class="inline-flex items-center gap-1.5 text-sm font-semibold px-3 py-1.5 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                                >
                                    Ver detalle
                                    <span class="material-symbols-outlined text-base">arrow_forward</span>
                                </Link>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </SuperAdminLayout>
</template>
