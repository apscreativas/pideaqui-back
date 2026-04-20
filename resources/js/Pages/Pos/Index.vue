<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SaleStatusBadge from '@/Components/Pos/SaleStatusBadge.vue'
import NewSaleModal from '@/Components/Pos/NewSaleModal.vue'
import PaymentModal from '@/Components/Pos/PaymentModal.vue'
import CancelSaleModal from '@/Components/CancelSaleModal.vue'
import PosTicket from '@/Components/Pos/PosTicket.vue'
import Pagination from '@/Components/Pagination.vue'
import SortableHeader from '@/Components/SortableHeader.vue'
import '@/../css/print-ticket.css'

const props = defineProps({
    sales: Object, // LengthAwarePaginator: { data, current_page, last_page, per_page, total, from, to, links }
    branches: Array,
    categories: Array,
    paymentMethods: Array,
    cashier: Object,
    restaurantName: String,
    filters: Object,
    totals: Object,
})

const page = usePage()
const billing = computed(() => page.props.billing)
const canOperate = computed(() => billing.value?.can_operate !== false)
const blockMessage = computed(() => billing.value?.block_message ?? '')

function fmt(v) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0) }
function dt(d) {
    if (!d) { return '—' }
    const parsed = new Date(d)
    if (Number.isNaN(parsed.getTime())) { return '—' }
    return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' }).format(parsed)
}
function paidAmount(sale) { return (sale.payments ?? []).reduce((s, p) => s + parseFloat(p.amount), 0) }

// ─── Local list state (drives broadcast mutations on page 1 only) ─────────
// With classic offset pagination, unshifting a new sale only makes sense on
// the first page. On pages >1 we trigger a partial reload of KPIs so totals
// stay in sync, but we don't mutate the visible list to avoid shifting rows
// and creating duplicates when the user pages forward.
const localSales = ref(props.sales?.data ?? [])
const currentPage = computed(() => Number(props.sales?.current_page ?? 1))

watch(() => props.sales, (paginator) => {
    localSales.value = paginator?.data ?? []
})

// ─── Filters ──────────────────────────────────────────────────────────────
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')
const branchId = ref(props.filters?.branch_id ?? '')
const status = ref(props.filters?.status ?? '')
const paymentMethod = ref(props.filters?.payment_method ?? '')
const perPage = ref(Number(props.filters?.per_page ?? 20))
const sortBy = ref(props.filters?.sort_by ?? null)
const sortDir = ref(props.filters?.sort_direction ?? null)

function currentQuery(extra = {}) {
    return {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        branch_id: branchId.value || undefined,
        status: status.value || undefined,
        payment_method: paymentMethod.value || undefined,
        per_page: perPage.value || undefined,
        sort_by: sortBy.value || undefined,
        sort_direction: sortBy.value ? (sortDir.value || 'desc') : undefined,
        ...extra,
    }
}

/**
 * Click cycle: unsorted column → asc → desc → clear (back to backend default).
 * Any change resets to page 1 so the user always lands on the first slice
 * of the new order.
 */
function handleSort(column) {
    if (sortBy.value !== column) {
        sortBy.value = column
        sortDir.value = 'asc'
    } else if (sortDir.value === 'asc') {
        sortDir.value = 'desc'
    } else {
        sortBy.value = null
        sortDir.value = null
    }
    applyFilters({ immediate: true, resetPage: true })
}

let filterDebounce = null
function applyFilters({ immediate = false, resetPage = true } = {}) {
    if (filterDebounce) { clearTimeout(filterDebounce) }
    const fire = () => {
        // Whenever filters change we go back to page 1 — otherwise a user
        // paged into page 5 of a wide range who narrows to "today" would
        // land on an empty page.
        router.get(route('pos.index'), currentQuery(resetPage ? { page: 1 } : {}), {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['sales', 'totals', 'filters'],
        })
    }
    if (immediate) { fire() } else { filterDebounce = setTimeout(fire, 300) }
}

function goToPage(page) {
    // Preserve all filters + per_page when navigating between pages.
    router.get(route('pos.index'), currentQuery({ page }), {
        preserveState: true,
        preserveScroll: true,
        replace: true,
        only: ['sales', 'totals', 'filters'],
    })
}

function onPerPageChange(value) {
    perPage.value = value
    applyFilters({ immediate: true, resetPage: true })
}

function localDateStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const today = localDateStr(new Date())

function setPreset(preset) {
    if (preset === 'today') { dateFrom.value = today; dateTo.value = today }
    else if (preset === 'yesterday') {
        const y = new Date(); y.setDate(y.getDate() - 1)
        dateFrom.value = localDateStr(y); dateTo.value = localDateStr(y)
    } else if (preset === 'week') {
        const w = new Date(); w.setDate(w.getDate() - 6)
        dateFrom.value = localDateStr(w); dateTo.value = today
    } else if (preset === 'month') {
        const ms = new Date()
        dateFrom.value = localDateStr(new Date(ms.getFullYear(), ms.getMonth(), 1))
        dateTo.value = today
    }
    applyFilters({ immediate: true })
}

const activePreset = computed(() => {
    if (dateFrom.value === today && dateTo.value === today) { return 'today' }
    const y = new Date(); y.setDate(y.getDate() - 1)
    if (dateFrom.value === localDateStr(y) && dateTo.value === localDateStr(y)) { return 'yesterday' }
    const w = new Date(); w.setDate(w.getDate() - 6)
    if (dateFrom.value === localDateStr(w) && dateTo.value === today) { return 'week' }
    const ms = new Date()
    if (dateFrom.value === localDateStr(new Date(ms.getFullYear(), ms.getMonth(), 1)) && dateTo.value === today) { return 'month' }
    return 'custom'
})

// ─── New sale modal ───────────────────────────────────────────────────────
const showNewSale = ref(false)
const printableSale = ref(null)
const showPostCreatePrint = ref(false)

function openNewSale() {
    // Server-side is authoritative; this is UX defense-in-depth.
    if (!canOperate.value) { return }
    showNewSale.value = true
}
function onSaleCreated(snapshot) {
    showNewSale.value = false
    printableSale.value = snapshot
    showPostCreatePrint.value = true
    // Refresh first page so the new sale appears with full relations.
    router.reload({ only: ['sales', 'totals'], preserveScroll: true })
}

// ─── Pay sale ─────────────────────────────────────────────────────────────
const payingSale = ref(null)
const showPayment = ref(false)
function openPayment(sale, e) {
    if (e) { e.stopPropagation() }
    payingSale.value = sale
    showPayment.value = true
}
const showPostPayPrint = ref(false)
function onPaid(updated) {
    showPayment.value = false
    payingSale.value = null
    printableSale.value = updated
    showPostPayPrint.value = true
    router.reload({ only: ['sales', 'totals'], preserveScroll: true })
}

// ─── Cancel sale ──────────────────────────────────────────────────────────
const cancellingSale = ref(null)
const showCancel = ref(false)
function openCancel(sale, e) {
    if (e) { e.stopPropagation() }
    cancellingSale.value = sale
    showCancel.value = true
}
function onCancelled() {
    showCancel.value = false
    cancellingSale.value = null
    router.reload({ only: ['sales', 'totals'], preserveScroll: true })
}

// ─── Print ────────────────────────────────────────────────────────────────
const printRequested = ref(false)
function reprint(sale, e) {
    if (e) { e.stopPropagation() }
    printableSale.value = sale
    printRequested.value = true
    setTimeout(() => {
        window.print()
        setTimeout(() => { printRequested.value = false }, 300)
    }, 100)
}
function printFromConfirm() {
    showPostCreatePrint.value = false
    showPostPayPrint.value = false
    printRequested.value = true
    setTimeout(() => {
        window.print()
        setTimeout(() => { printRequested.value = false }, 300)
    }, 100)
}

// ─── Real-time ────────────────────────────────────────────────────────────
// Mutate locally on incoming broadcasts instead of reloading the whole
// page. With many cashiers active, a per-event router.reload would trigger
// ramps of HTTP requests and jank on every client.
const restaurantId = usePage().props.auth.user?.restaurant_id
let channel = null

function matchesFilters(sale) {
    if (branchId.value && String(sale.branch?.id) !== String(branchId.value)) { return false }
    if (status.value && sale.status !== status.value) { return false }
    // payment_method and date range are authoritative server-side; when they
    // change, the list is re-fetched anyway.
    return true
}

function applyBroadcastCreated(payload) {
    const sale = payload?.sale ?? payload
    if (!sale?.id) { return }
    if (!matchesFilters(sale)) { return }

    // With classic offset pagination, only mutating the visible list when the
    // user is on page 1 keeps things consistent: inserting a row on page 5
    // would shift every later row down and create duplicates on page 6.
    if (currentPage.value === 1) {
        if (localSales.value.some((s) => s.id === sale.id)) { return }
        localSales.value.unshift({
            ...sale,
            items: sale.items ?? [],
            payments: sale.payments ?? [],
        })
        // Trim to the configured page size so the visible list doesn't grow
        // past per_page while broadcasts keep arriving.
        if (localSales.value.length > perPage.value) {
            localSales.value.length = perPage.value
        }
    }

    // Always refresh the KPI totals so the top cards reflect the new sale,
    // regardless of which page the user is on.
    router.reload({ only: ['totals'], preserveScroll: true })
}

function applyBroadcastUpdated(payload) {
    const sale = payload?.sale ?? payload
    if (!sale?.id) { return }
    const idx = localSales.value.findIndex((s) => s.id === sale.id)
    if (idx !== -1) {
        // The row is visible on the current page — mutate in place so the
        // status badge / totals update without a full reload. This is safe
        // on any page since the row position doesn't change.
        localSales.value[idx] = { ...localSales.value[idx], ...sale }
    }
    router.reload({ only: ['totals'], preserveScroll: true })
}

onMounted(() => {
    const echo = window.getEcho?.()
    if (!restaurantId || !echo) { return }
    channel = echo.private(`restaurant.${restaurantId}.pos`)
        .listen('PosSaleCreated', applyBroadcastCreated)
        .listen('PosSaleStatusChanged', applyBroadcastUpdated)
        .listen('PosSaleCancelled', applyBroadcastUpdated)
})
onUnmounted(() => {
    if (filterDebounce) { clearTimeout(filterDebounce) }
    if (channel) {
        window.getEcho?.()?.leave(`restaurant.${restaurantId}.pos`)
        channel = null
    }
})
</script>

<template>
    <Head title="Historial POS" />
    <AppLayout title="Historial POS">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Historial POS</h1>
                <p class="mt-1 text-sm text-gray-500">Ventas de mostrador. Crea, cobra e imprime desde aquí.</p>
            </div>
            <button
                type="button"
                @click="openNewSale"
                :disabled="!canOperate"
                :title="canOperate ? '' : blockMessage"
                class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-5 py-3 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition disabled:opacity-50 disabled:cursor-not-allowed disabled:hover:bg-[#FF5722]"
            >
                <span class="material-symbols-outlined text-lg">add_shopping_cart</span>
                Nueva venta
            </button>
        </div>

        <!-- Operational gate banner -->
        <div v-if="!canOperate" class="mb-5 p-4 rounded-xl border border-red-200 bg-red-50 flex items-start gap-3">
            <span class="material-symbols-outlined text-red-600">block</span>
            <div class="flex-1">
                <p class="text-sm font-semibold text-red-900">{{ blockMessage }}</p>
                <p class="text-xs text-red-700 mt-1">Puedes cerrar ventas en curso pero no crear nuevas.</p>
            </div>
            <a :href="route('settings.subscription')" class="text-sm font-bold text-red-700 hover:underline whitespace-nowrap">
                Ir a mi plan
            </a>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3 mb-5">
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="material-symbols-outlined text-gray-400 text-base">receipt</span>
                    <p class="text-xs font-bold text-gray-500 uppercase">Tickets</p>
                </div>
                <p class="text-2xl font-black text-gray-900">{{ totals.tickets }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="material-symbols-outlined text-green-500 text-base">payments</span>
                    <p class="text-xs font-bold text-gray-500 uppercase">Cobradas</p>
                </div>
                <p class="text-2xl font-black text-gray-900">{{ fmt(totals.revenue) }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="material-symbols-outlined text-amber-500 text-base">cooking</span>
                    <p class="text-xs font-bold text-gray-500 uppercase">Abiertas</p>
                </div>
                <p class="text-2xl font-black text-gray-900">{{ totals.open_count }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="material-symbols-outlined text-gray-400 text-base">cancel</span>
                    <p class="text-xs font-bold text-gray-500 uppercase">Canceladas</p>
                </div>
                <p class="text-2xl font-black text-gray-900">{{ totals.cancelled_count }}</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-4">
            <!-- Branch -->
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-lg">store</span>
                <select v-model="branchId" @change="applyFilters({ immediate: true })" class="pl-9 pr-8 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 appearance-none min-w-[180px]">
                    <option value="">Todas las sucursales</option>
                    <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                </select>
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-lg pointer-events-none">arrow_drop_down</span>
            </div>

            <!-- Status -->
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-lg">flag</span>
                <select v-model="status" @change="applyFilters({ immediate: true })" class="pl-9 pr-8 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 appearance-none">
                    <option value="">Todos los estados</option>
                    <option value="preparing">Creadas</option>
                    <option value="paid">Cobradas</option>
                    <option value="cancelled">Canceladas</option>
                </select>
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-lg pointer-events-none">arrow_drop_down</span>
            </div>

            <!-- Payment method -->
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-lg">payments</span>
                <select v-model="paymentMethod" @change="applyFilters({ immediate: true })" class="pl-9 pr-8 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 appearance-none">
                    <option value="">Todos los métodos</option>
                    <option value="cash">Efectivo</option>
                    <option value="terminal">Tarjeta</option>
                    <option value="transfer">Transferencia</option>
                </select>
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-lg pointer-events-none">arrow_drop_down</span>
            </div>

            <!-- Date presets -->
            <div class="flex items-center gap-1.5 bg-white border border-gray-200 rounded-xl p-1">
                <button
                    v-for="p in [{key:'today',label:'Hoy'},{key:'yesterday',label:'Ayer'},{key:'week',label:'7 días'},{key:'month',label:'Mes'}]"
                    :key="p.key"
                    @click="setPreset(p.key)"
                    type="button"
                    class="px-3 py-1.5 rounded-lg text-xs font-semibold transition"
                    :class="activePreset === p.key ? 'bg-[#FF5722] text-white shadow-sm' : 'text-gray-600 hover:bg-gray-50'"
                >{{ p.label }}</button>
            </div>
        </div>

        <!-- Sales table -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <SortableHeader
                            column-key="ticket_number"
                            label="Ticket"
                            :active-key="sortBy"
                            :direction="sortDir"
                            align="left"
                            @sort="handleSort"
                        />
                        <SortableHeader
                            column-key="created_at"
                            label="Hora"
                            :active-key="sortBy"
                            :direction="sortDir"
                            align="left"
                            @sort="handleSort"
                        />
                        <th class="px-4 py-3 text-left font-semibold">Cajero</th>
                        <th class="px-4 py-3 text-left font-semibold">Sucursal</th>
                        <th class="px-4 py-3 text-right font-semibold">Items</th>
                        <SortableHeader
                            column-key="total"
                            label="Total"
                            :active-key="sortBy"
                            :direction="sortDir"
                            align="right"
                            @sort="handleSort"
                        />
                        <th class="px-4 py-3 text-center font-semibold">Estado</th>
                        <th class="px-4 py-3 text-right font-semibold">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="sale in localSales" :key="sale.id" class="border-t border-gray-100 hover:bg-gray-50/50 transition cursor-pointer" @click="router.visit(route('pos.sales.show', sale.id))">
                        <td class="px-4 py-3 font-bold text-gray-900">{{ sale.ticket_number }}</td>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap">{{ dt(sale.created_at) }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ sale.cashier?.name }}</td>
                        <td class="px-4 py-3 text-gray-700">{{ sale.branch?.name }}</td>
                        <td class="px-4 py-3 text-right text-gray-500">{{ sale.items?.reduce((s, i) => s + i.quantity, 0) ?? 0 }}</td>
                        <td class="px-4 py-3 text-right">
                            <p class="font-bold text-gray-900">{{ fmt(sale.total) }}</p>
                            <p v-if="['preparing','ready'].includes(sale.status) && paidAmount(sale) > 0" class="text-[10px] text-amber-600">Abonado {{ fmt(paidAmount(sale)) }}</p>
                        </td>
                        <td class="px-4 py-3 text-center"><SaleStatusBadge :status="sale.status" /></td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    v-if="['preparing','ready'].includes(sale.status)"
                                    @click="openPayment(sale, $event)"
                                    type="button"
                                    class="text-green-600 hover:bg-green-50 p-1.5 rounded-lg transition"
                                    title="Cobrar"
                                >
                                    <span class="material-symbols-outlined text-base">payments</span>
                                </button>
                                <button
                                    v-if="['preparing','ready'].includes(sale.status)"
                                    @click="openCancel(sale, $event)"
                                    type="button"
                                    class="text-gray-400 hover:text-red-600 hover:bg-red-50 p-1.5 rounded-lg transition"
                                    title="Cancelar"
                                >
                                    <span class="material-symbols-outlined text-base">close</span>
                                </button>
                                <button
                                    @click="reprint(sale, $event)"
                                    type="button"
                                    class="text-gray-400 hover:text-gray-700 hover:bg-gray-100 p-1.5 rounded-lg transition"
                                    title="Reimprimir ticket"
                                >
                                    <span class="material-symbols-outlined text-base">print</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="localSales.length === 0">
                        <td colspan="8" class="text-center py-16">
                            <span class="material-symbols-outlined text-5xl text-gray-200">receipt_long</span>
                            <p class="text-sm text-gray-400 mt-2">No hay ventas POS en este periodo</p>
                            <button
                                @click="openNewSale"
                                type="button"
                                :disabled="!canOperate"
                                class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[#FF5722] hover:text-[#D84315] disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <span class="material-symbols-outlined text-base">add_circle</span>
                                Crear primera venta
                            </button>
                        </td>
                    </tr>
                </tbody>
            </table>
            <Pagination
                :paginator="sales"
                label="ventas"
                @page="goToPage"
                @per-page="onPerPageChange"
            />
        </div>
        <p class="mt-2 text-[11px] text-gray-400">
            Los KPIs de arriba resumen todo el periodo filtrado. La tabla muestra solo la página actual.
        </p>

        <!-- New sale modal -->
        <NewSaleModal
            :show="showNewSale"
            :branches="branches"
            :categories="categories"
            :cashier="cashier"
            @close="showNewSale = false"
            @created="onSaleCreated"
        />

        <!-- Payment modal — only show active payment types -->
        <PaymentModal
            v-if="payingSale"
            :sale="payingSale"
            :show="showPayment"
            :available-types="paymentMethods.map((m) => m.type)"
            @close="showPayment = false; payingSale = null"
            @paid="onPaid"
        />

        <!-- Cancel modal -->
        <CancelSaleModal
            v-if="cancellingSale"
            :show="showCancel"
            title="Cancelar venta"
            :subtitle="cancellingSale.ticket_number"
            :url="route('pos.sales.cancel', cancellingSale.id)"
            submit-label="Cancelar venta"
            @close="showCancel = false; cancellingSale = null"
            @cancelled="onCancelled"
        />

        <!-- Post-create / post-pay print confirmation -->
        <Teleport to="body">
            <div v-if="showPostCreatePrint || showPostPayPrint" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
                    <div class="flex justify-center mb-3">
                        <div class="size-12 rounded-full bg-orange-100 text-[#FF5722] flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">{{ showPostPayPrint ? 'check_circle' : 'print' }}</span>
                        </div>
                    </div>
                    <h2 class="text-lg font-bold mb-1 text-gray-900">{{ showPostPayPrint ? 'Venta cobrada' : 'Venta creada' }}</h2>
                    <p class="text-sm text-gray-500 mb-4">¿Imprimir ticket{{ showPostPayPrint ? ' de venta' : ' de cocina' }}?</p>
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="showPostCreatePrint = false; showPostPayPrint = false" class="border border-gray-200 hover:bg-gray-50 py-2.5 rounded-xl text-sm font-semibold">No, gracias</button>
                        <button @click="printFromConfirm" class="bg-[#FF5722] hover:bg-[#D84315] text-white py-2.5 rounded-xl text-sm font-bold">Sí, imprimir</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Hidden printable ticket -->
        <PosTicket v-if="printRequested && printableSale" :sale="printableSale" :restaurant-name="restaurantName" />
    </AppLayout>
</template>
