<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DatePicker from '@/Components/DatePicker.vue'
import Pagination from '@/Components/Pagination.vue'

const props = defineProps({
    orders: Object,
    summary: Object,
    branches: Array,
    filters: Object,
})

const from = ref(props.filters.from)
const to = ref(props.filters.to)
const branchId = ref(props.filters.branch_id ?? '')
const status = ref(props.filters.status ?? 'all')
const perPage = ref(Number(props.filters.per_page ?? 20))

function navigate(params = {}, opts = {}) {
    router.get(route('orders.history'), {
        from: from.value,
        to: to.value,
        branch_id: branchId.value || undefined,
        status: status.value !== 'all' ? status.value : undefined,
        per_page: perPage.value || undefined,
        ...params,
    }, { preserveState: true, preserveScroll: true, replace: true, ...opts })
}

function applyFilter() {
    navigate({ page: 1 })
}

function goToPage(page) {
    if (!page || page === props.orders?.current_page) { return }
    navigate({ page })
}

function onPerPageChange(value) {
    perPage.value = value
    navigate({ page: 1 })
}

const rows = computed(() => props.orders?.data ?? [])

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

function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}

function formatDateTime(dateStr) {
    if (!dateStr) { return '—' }
    return new Date(dateStr).toLocaleString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit',
    })
}
</script>

<template>
    <Head title="Historial de pedidos" />
    <AppLayout title="Historial de pedidos">

        <!-- Header + Filters -->
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4 mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Historial de pedidos</h1>
                <p class="mt-1 text-sm text-gray-500">Reporte de pedidos por rango de fechas con sumatoria de venta, costo y utilidad.</p>
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

                <div class="flex items-center gap-1.5">
                    <DatePicker v-model="from" @change="applyFilter" placeholder="Desde" size="sm" />
                    <span class="text-gray-400 text-xs">&mdash;</span>
                    <DatePicker v-model="to" @change="applyFilter" placeholder="Hasta" size="sm" />
                </div>

                <select
                    v-model="branchId"
                    @change="applyFilter"
                    class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-700 focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                >
                    <option value="">Todas las sucursales</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>

                <select
                    v-model="status"
                    @change="applyFilter"
                    class="text-xs border border-gray-200 rounded-lg px-2.5 py-1.5 text-gray-700 focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                >
                    <option value="all">Todos los estatus</option>
                    <option value="delivered">Entregados</option>
                    <option value="cancelled">Cancelados</option>
                </select>
            </div>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-blue-50 rounded-lg">
                        <span class="material-symbols-outlined text-blue-600">receipt_long</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Pedidos</p>
                <h3 class="text-3xl font-bold text-gray-900">{{ summary.count }}</h3>
                <p class="text-xs text-gray-400 mt-1">en el rango filtrado</p>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-emerald-50 rounded-lg">
                        <span class="material-symbols-outlined text-emerald-600">payments</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Total vendido</p>
                <h3 class="text-3xl font-bold text-gray-900 tabular-nums">{{ formatPrice(summary.sum_total) }}</h3>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-red-50 rounded-lg">
                        <span class="material-symbols-outlined text-red-600">trending_down</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Costo</p>
                <h3 class="text-3xl font-bold text-gray-900 tabular-nums">{{ formatPrice(summary.sum_cost) }}</h3>
            </div>

            <div class="bg-white p-6 rounded-xl shadow-sm border border-gray-100">
                <div class="flex justify-between items-start mb-4">
                    <div class="p-2 bg-amber-50 rounded-lg">
                        <span class="material-symbols-outlined text-amber-600">trending_up</span>
                    </div>
                </div>
                <p class="text-sm font-medium text-gray-500 mb-1">Utilidad</p>
                <h3 class="text-3xl font-bold tabular-nums" :class="summary.sum_profit >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ formatPrice(summary.sum_profit) }}</h3>
            </div>
        </div>

        <!-- Tabla -->
        <div class="bg-white rounded-xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="min-w-full">
                    <thead class="bg-gray-50 border-b border-gray-100">
                        <tr class="text-left">
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Pedido</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Fecha / Hora</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Cliente</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Teléfono</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursal</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Total</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Costo</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Utilidad</th>
                            <th class="px-6 py-4 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right"></th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        <tr
                            v-for="row in rows"
                            :key="row.id"
                            class="transition-colors"
                            :class="row.status === 'cancelled'
                                ? 'bg-red-50/50 hover:bg-red-50'
                                : 'hover:bg-gray-50/50'"
                        >
                            <td class="px-6 py-4 text-sm font-medium whitespace-nowrap" :class="row.status === 'cancelled' ? 'text-red-700' : 'text-gray-900'">
                                {{ row.reference }}
                                <span v-if="row.status === 'cancelled'" class="ml-1 text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-700 uppercase tracking-wider">Cancelado</span>
                            </td>
                            <td class="px-6 py-4 text-sm whitespace-nowrap" :class="row.status === 'cancelled' ? 'text-red-600' : 'text-gray-500'">{{ formatDateTime(row.created_at) }}</td>
                            <td class="px-6 py-4 text-sm" :class="row.status === 'cancelled' ? 'text-red-700' : 'text-gray-700'">{{ row.customer_name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm tabular-nums" :class="row.status === 'cancelled' ? 'text-red-600' : 'text-gray-600'">{{ row.customer_phone ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm" :class="row.status === 'cancelled' ? 'text-red-600' : 'text-gray-600'">{{ row.branch_name ?? '—' }}</td>
                            <td class="px-6 py-4 text-sm font-semibold text-right tabular-nums" :class="row.status === 'cancelled' ? 'text-red-700' : 'text-gray-900'">{{ formatPrice(row.total) }}</td>
                            <td class="px-6 py-4 text-sm text-right tabular-nums" :class="row.status === 'cancelled' ? 'text-red-600' : 'text-gray-600'">{{ formatPrice(row.production_cost) }}</td>
                            <td class="px-6 py-4 text-sm font-semibold text-right tabular-nums" :class="row.status === 'cancelled' ? 'text-red-700' : (row.profit >= 0 ? 'text-emerald-600' : 'text-red-600')">{{ formatPrice(row.profit) }}</td>
                            <td class="px-6 py-4 text-right">
                                <a
                                    :href="route('orders.show', row.id)"
                                    target="_blank"
                                    rel="noopener"
                                    class="text-gray-400 hover:text-[#FF5722] transition-colors"
                                    title="Ver detalle en nueva pestaña"
                                >
                                    <span class="material-symbols-outlined">open_in_new</span>
                                </a>
                            </td>
                        </tr>
                        <tr v-if="!rows.length">
                            <td colspan="9" class="px-6 py-12 text-center text-sm text-gray-400">
                                No hay pedidos en este rango.
                            </td>
                        </tr>
                    </tbody>
                    <tfoot v-if="rows.length" class="bg-gray-50 border-t border-gray-200">
                        <tr>
                            <td colspan="5" class="px-6 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                Sumatoria del rango ({{ summary.count }} pedidos)
                            </td>
                            <td class="px-6 py-3 text-sm font-bold text-right tabular-nums text-gray-900">{{ formatPrice(summary.sum_total) }}</td>
                            <td class="px-6 py-3 text-sm font-bold text-right tabular-nums text-gray-900">{{ formatPrice(summary.sum_cost) }}</td>
                            <td class="px-6 py-3 text-sm font-bold text-right tabular-nums" :class="summary.sum_profit >= 0 ? 'text-emerald-600' : 'text-red-600'">{{ formatPrice(summary.sum_profit) }}</td>
                            <td></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
            <Pagination
                :paginator="orders"
                label="pedidos"
                @page="goToPage"
                @per-page="onPerPageChange"
            />
        </div>
        <p class="mt-2 text-[11px] text-gray-400">
            Las sumatorias incluyen todos los pedidos del rango y filtro de estatus seleccionados (no solo la página visible). Los pedidos cancelados se muestran en rojo. Usa el filtro "Entregados" si quieres ver únicamente la venta real.
        </p>

    </AppLayout>
</template>
