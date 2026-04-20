<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import PosTicket from '@/Components/Pos/PosTicket.vue'
import PaymentModal from '@/Components/Pos/PaymentModal.vue'
import SaleStatusBadge from '@/Components/Pos/SaleStatusBadge.vue'
import CancelSaleModal from '@/Components/CancelSaleModal.vue'
import { printTicket } from '@/utils/printTicket'

const props = defineProps({
    sale: Object,
    restaurantName: String,
    paymentMethods: { type: Array, default: () => [] },
    can_view_financials: { type: Boolean, default: false },
})

const PAYMENT_LABELS = { cash: 'Efectivo', terminal: 'Tarjeta', transfer: 'Transferencia' }
const PAYMENT_ICONS = { cash: 'payments', terminal: 'credit_card', transfer: 'account_balance' }

function fmt(v) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0) }
function dt(d) {
    if (!d) { return '—' }
    const parsed = new Date(d)
    if (Number.isNaN(parsed.getTime())) { return '—' }
    return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(parsed)
}
function itemTotal(item) {
    const mod = item.modifiers?.reduce((s, m) => s + parseFloat(m.price_adjustment), 0) ?? 0
    return (parseFloat(item.unit_price) + mod) * item.quantity
}

function itemProductionCost(item) {
    const baseCost = parseFloat(item.production_cost ?? 0) * item.quantity
    const modCost = item.modifiers?.reduce((s, m) => s + parseFloat(m.production_cost ?? 0) * item.quantity, 0) ?? 0
    return baseCost + modCost
}

function itemProfit(item) {
    return itemTotal(item) - itemProductionCost(item)
}

const totalProfit = computed(() => {
    return props.sale.items?.reduce((sum, item) => sum + itemProfit(item), 0) ?? 0
})

const totalProductionCost = computed(() => {
    return props.sale.items?.reduce((sum, item) => sum + itemProductionCost(item), 0) ?? 0
})

const totalPaid = computed(() => (props.sale.payments ?? []).reduce((s, p) => s + parseFloat(p.amount), 0))
const pending = computed(() => Math.max(0, parseFloat(props.sale.total) - totalPaid.value))
const isOpen = computed(() => ['preparing', 'ready'].includes(props.sale.status))
const itemCount = computed(() => props.sale.items?.reduce((s, i) => s + i.quantity, 0) ?? 0)

// ─── Print ────────────────────────────────────────────────────────────────
const printRequested = ref(false)
function reprint() {
    printRequested.value = true
    setTimeout(() => {
        printTicket()
        setTimeout(() => { printRequested.value = false }, 300)
    }, 100)
}

// ─── Cancel ───────────────────────────────────────────────────────────────
const showCancelModal = ref(false)

// ─── Payment modal (collect) ───────────────────────────────────────────────
const showPayment = ref(false)
const showPostPayPrint = ref(false)
function onPaid() {
    showPayment.value = false
    showPostPayPrint.value = true
}

function back() { router.visit(route('pos.index')) }
</script>

<template>
    <Head :title="`Venta ${sale.ticket_number}`" />
    <AppLayout :title="`Venta ${sale.ticket_number}`">

        <!-- Header -->
        <div class="flex flex-col lg:flex-row lg:items-start justify-between gap-4 mb-6">
            <div class="min-w-0">
                <button @click="back" class="text-sm text-gray-500 hover:text-[#FF5722] flex items-center gap-1 mb-2 transition">
                    <span class="material-symbols-outlined text-lg">arrow_back</span>
                    Historial POS
                </button>
                <div class="flex items-center gap-3 flex-wrap">
                    <h1 class="text-2xl font-black text-gray-900">{{ sale.ticket_number }}</h1>
                    <SaleStatusBadge :status="sale.status" />
                </div>
                <p class="text-sm text-gray-500 mt-1.5">
                    {{ dt(sale.created_at) }}
                    <span class="text-gray-300 mx-1.5">·</span>
                    Cajero <span class="font-semibold text-gray-700">{{ sale.cashier?.name }}</span>
                    <span class="text-gray-300 mx-1.5">·</span>
                    Sucursal <span class="font-semibold text-gray-700">{{ sale.branch?.name }}</span>
                </p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <button
                    type="button"
                    @click="reprint"
                    class="flex items-center gap-1.5 border border-gray-200 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-semibold transition"
                >
                    <span class="material-symbols-outlined text-lg">print</span>
                    Imprimir
                </button>
                <button
                    v-if="isOpen"
                    type="button"
                    @click="showCancelModal = true"
                    class="flex items-center gap-1.5 border border-red-200 text-red-600 hover:bg-red-50 px-4 py-2.5 rounded-xl text-sm font-semibold transition"
                >
                    <span class="material-symbols-outlined text-lg">close</span>
                    Cancelar
                </button>
            </div>
        </div>

        <!-- Cancellation banner -->
        <div v-if="sale.status === 'cancelled'" class="mb-6 bg-red-50 border border-red-200 rounded-xl p-4 flex items-start gap-3">
            <span class="material-symbols-outlined text-red-500 text-2xl shrink-0">cancel</span>
            <div class="text-sm">
                <p class="font-bold text-red-800">Venta cancelada · {{ dt(sale.cancelled_at) }}</p>
                <p v-if="sale.cancellation_reason" class="text-red-700 italic mt-0.5">"{{ sale.cancellation_reason }}"</p>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: items + general note -->
            <div class="lg:col-span-2 flex flex-col gap-5">

                <!-- Items -->
                <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-5 py-3.5 border-b border-gray-100 flex items-center justify-between">
                        <h3 class="text-sm font-bold text-gray-900 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[#FF5722] text-base">list_alt</span>
                            Productos
                        </h3>
                        <span class="text-xs font-semibold text-gray-500">{{ itemCount }} item(s)</span>
                    </div>
                    <div class="divide-y divide-gray-100">
                        <div v-for="item in sale.items" :key="item.id" class="px-5 py-4">
                            <!-- Item header -->
                            <div class="flex justify-between gap-3 items-start">
                                <div class="flex items-start gap-3 min-w-0 flex-1">
                                    <div class="size-10 rounded-lg bg-orange-50 text-[#FF5722] flex items-center justify-center font-black text-sm shrink-0">
                                        {{ item.quantity }}×
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-bold text-gray-900 truncate">{{ item.product_name }}</p>
                                        <p v-if="item.notes" class="text-xs text-amber-700 italic mt-1 bg-amber-50 inline-block px-2 py-0.5 rounded">📝 {{ item.notes }}</p>
                                    </div>
                                </div>
                                <p class="font-black text-gray-900 text-base shrink-0">{{ fmt(itemTotal(item)) }}</p>
                            </div>

                            <!-- Full breakdown: mirrors Orders/Show.vue exactly -->
                            <div class="flex flex-col gap-3 pt-3 ml-[52px]">

                                <!-- Precio de venta unitario -->
                                <div class="flex justify-between text-sm text-gray-500">
                                    <span>Precio de venta</span>
                                    <span class="tabular-nums">{{ fmt(item.unit_price) }}</span>
                                </div>

                                <!-- Modificadores -->
                                <div v-if="item.modifiers?.length" class="rounded-lg bg-gray-50 px-3 py-2.5 space-y-1.5">
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Modificadores</p>
                                    <div v-for="mod in item.modifiers" :key="mod.id" class="flex justify-between text-sm">
                                        <span class="text-gray-700">{{ mod.modifier_option_name }}</span>
                                        <div class="flex gap-3 text-xs shrink-0 ml-4 tabular-nums">
                                            <span v-if="parseFloat(mod.price_adjustment) > 0" class="text-gray-500">+{{ fmt(mod.price_adjustment) }}</span>
                                            <span v-if="can_view_financials" class="text-red-400">costo {{ fmt(mod.production_cost ?? 0) }}</span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Costo de producción (admin only) -->
                                <div v-if="can_view_financials" class="rounded-lg bg-red-50/60 px-3 py-2.5 space-y-1">
                                    <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Costo de producción</p>
                                    <div class="flex justify-between text-sm">
                                        <span class="text-gray-600">Producto base</span>
                                        <span class="text-red-600 tabular-nums">{{ fmt(item.production_cost ?? 0) }}</span>
                                    </div>
                                    <div v-for="mod in (item.modifiers ?? []).filter(m => parseFloat(m.production_cost ?? 0) > 0)" :key="'cost-' + mod.id" class="flex justify-between text-sm">
                                        <span class="text-gray-600">{{ mod.modifier_option_name }}</span>
                                        <span class="text-red-600 tabular-nums">{{ fmt(mod.production_cost) }}</span>
                                    </div>
                                    <div class="flex justify-between text-sm font-bold border-t border-red-100 pt-1 mt-1">
                                        <span class="text-gray-700">Costo total <span v-if="item.quantity > 1" class="font-normal text-gray-400">(x{{ item.quantity }})</span></span>
                                        <span class="text-red-700 tabular-nums">{{ fmt(itemProductionCost(item)) }}</span>
                                    </div>
                                </div>

                                <!-- Ganancia del item (admin only) -->
                                <div v-if="can_view_financials" class="flex justify-between items-center rounded-lg bg-green-50/60 px-3 py-2 text-sm font-bold">
                                    <span class="text-green-800">Ganancia del item</span>
                                    <span class="text-green-700 tabular-nums">{{ fmt(itemProfit(item)) }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- General note -->
                <div v-if="sale.notes" class="bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 flex items-start gap-2.5">
                    <span class="material-symbols-outlined text-amber-600 text-lg shrink-0">sticky_note_2</span>
                    <div class="text-sm">
                        <p class="font-bold text-amber-900">Nota general</p>
                        <p class="text-amber-800 mt-0.5">{{ sale.notes }}</p>
                    </div>
                </div>
            </div>

            <!-- Right sidebar: totals + payments + actions -->
            <div class="flex flex-col gap-4">

                <!-- Resumen financiero (mirrors Orders/Show.vue layout) -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h3 class="text-base font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]" aria-hidden="true">account_balance</span>
                        Resumen de la venta
                    </h3>

                    <div class="flex flex-col gap-2.5">
                        <!-- Venta -->
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>Subtotal venta</span>
                            <span class="tabular-nums">{{ fmt(sale.subtotal) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-black text-gray-900 border-t border-gray-100 pt-3 mt-1">
                            <span>Total cobrado</span>
                            <span class="tabular-nums">{{ fmt(sale.total) }}</span>
                        </div>

                        <!-- Costos + Ganancia (admin only) -->
                        <template v-if="can_view_financials">
                            <div class="flex justify-between text-sm text-red-600 mt-3">
                                <span>Costo total de producción</span>
                                <span class="font-semibold tabular-nums">-{{ fmt(totalProductionCost) }}</span>
                            </div>
                            <div class="flex justify-between items-center rounded-xl bg-green-50 px-4 py-3.5 mt-2">
                                <span class="text-green-800 font-bold">Ganancia bruta</span>
                                <span class="text-green-700 text-lg font-black tabular-nums">{{ fmt(totalProfit) }}</span>
                            </div>
                        </template>

                        <!-- Pagos -->
                        <template v-if="sale.payments?.length || sale.status === 'paid'">
                            <div class="flex justify-between text-sm text-gray-500 mt-3 border-t border-gray-100 pt-3">
                                <span>Pagado</span>
                                <span class="font-semibold text-green-600 tabular-nums">{{ fmt(totalPaid) }}</span>
                            </div>
                            <div v-if="pending > 0" class="flex justify-between text-sm">
                                <span class="text-amber-700 font-semibold">Pendiente</span>
                                <span class="font-bold text-amber-700 tabular-nums">{{ fmt(pending) }}</span>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Payments breakdown -->
                <div v-if="sale.payments?.length" class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">
                    <div class="px-5 py-3 border-b border-gray-100">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Pagos registrados</p>
                    </div>
                    <ul class="divide-y divide-gray-100">
                        <li v-for="p in sale.payments" :key="p.id" class="px-5 py-3 flex items-center justify-between">
                            <div class="flex items-center gap-2.5 min-w-0">
                                <span class="material-symbols-outlined text-gray-400">{{ PAYMENT_ICONS[p.payment_method_type] }}</span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ PAYMENT_LABELS[p.payment_method_type] }}</p>
                                    <p v-if="p.cash_received" class="text-[10px] text-gray-500">
                                        Recibió {{ fmt(p.cash_received) }} · cambio {{ fmt(p.change_given) }}
                                    </p>
                                </div>
                            </div>
                            <span class="text-sm font-bold text-gray-900 shrink-0">{{ fmt(p.amount) }}</span>
                        </li>
                    </ul>
                </div>

                <!-- Actions (only when open) -->
                <div v-if="isOpen" class="space-y-2">
                    <button
                        @click="showPayment = true"
                        class="w-full bg-green-600 hover:bg-green-700 text-white py-3 rounded-xl text-sm font-bold flex items-center justify-center gap-2 shadow-md shadow-green-200 transition"
                    >
                        <span class="material-symbols-outlined">payments</span>
                        Cobrar venta
                    </button>
                </div>
            </div>
        </div>

        <!-- Cancel modal (shared) -->
        <CancelSaleModal
            :show="showCancelModal"
            title="Cancelar venta"
            :subtitle="sale.ticket_number"
            :url="route('pos.sales.cancel', sale.id)"
            submit-label="Cancelar venta"
            @close="showCancelModal = false"
            @cancelled="showCancelModal = false"
        />

        <!-- Payment modal -->
        <PaymentModal
            v-if="isOpen"
            :sale="sale"
            :show="showPayment"
            :available-types="paymentMethods.map((m) => m.type)"
            @close="showPayment = false"
            @paid="onPaid"
        />

        <!-- Print confirm after pay -->
        <Teleport to="body">
            <div v-if="showPostPayPrint" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
                    <div class="flex justify-center mb-3">
                        <div class="size-12 rounded-full bg-green-100 text-green-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">check_circle</span>
                        </div>
                    </div>
                    <h2 class="text-lg font-bold mb-1 text-gray-900">Venta cobrada</h2>
                    <p class="text-sm text-gray-500 mb-4">¿Imprimir ticket de venta?</p>
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="showPostPayPrint = false" class="border border-gray-200 hover:bg-gray-50 py-2.5 rounded-xl text-sm font-semibold">No, gracias</button>
                        <button @click="showPostPayPrint = false; reprint()" class="bg-[#FF5722] hover:bg-[#D84315] text-white py-2.5 rounded-xl text-sm font-bold">Sí, imprimir</button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Hidden printable ticket -->
        <PosTicket v-if="printRequested" :sale="sale" :restaurant-name="restaurantName" />
    </AppLayout>
</template>
