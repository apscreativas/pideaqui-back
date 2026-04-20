<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import OrderTicket from '@/Components/OrderTicket.vue'
import { printTicket } from '@/utils/printTicket'

const props = defineProps({
    order: Object,
    mapsKey: { type: String, default: '' },
    is_admin: { type: Boolean, default: true },
    restaurantName: { type: String, default: '' },
})

const isPrintable = computed(() => props.order.status !== 'cancelled')

function goBack() {
    router.visit(route('orders.index'))
}

const STATUS_STEPS = [
    { key: 'received',   label: 'Recibido',     icon: 'receipt_long' },
    { key: 'preparing',  label: 'Preparación',  icon: 'skillet' },
    { key: 'on_the_way', label: 'En camino',    icon: 'moped' },
    { key: 'delivered',  label: 'Entregado',    icon: 'check_circle' },
]

const NEXT_LABEL = {
    received:   'Marcar en preparación',
    preparing:  'Marcar en camino',
    on_the_way: 'Marcar como entregado',
}

const DELIVERY_LABELS = {
    delivery: 'Entrega a domicilio',
    pickup:   'Recoger en sucursal',
    dine_in:  'En restaurante',
}

const DELIVERY_ICONS = {
    delivery: 'two_wheeler',
    pickup:   'store',
    dine_in:  'restaurant',
}

const PAYMENT_LABELS = {
    cash:     'Efectivo',
    terminal: 'Tarjeta / Terminal',
    transfer: 'Transferencia',
}

const CANCELLATION_REASONS = [
    'El cliente ya no lo necesita',
    'Tiempo de espera demasiado largo',
    'Error en la selección de productos / Pedido duplicado',
    'Falta de disponibilidad (Sin stock)',
    'Otro',
]

const currentStepIndex = computed(() =>
    STATUS_STEPS.findIndex((s) => s.key === props.order.status),
)

const progressWidth = computed(() => {
    const idx = currentStepIndex.value
    if (idx <= 0) { return '0%' }
    return Math.round((idx / (STATUS_STEPS.length - 1)) * 100) + '%'
})

const nextStatusLabel = computed(() => NEXT_LABEL[props.order.status] ?? null)
const isCancellable = computed(() => ['received', 'preparing'].includes(props.order.status))
const isEditable = computed(() => ['received', 'preparing'].includes(props.order.status))
const isCancelled = computed(() => props.order.status === 'cancelled')

// --- Cancel modal ---
const showCancelModal = ref(false)
const selectedReason = ref('')
const customReason = ref('')

const cancelForm = useForm({
    cancellation_reason: '',
})

function openCancelModal() {
    selectedReason.value = ''
    customReason.value = ''
    cancelForm.clearErrors()
    showActionsMenu.value = false
    showCancelModal.value = true
}

function submitCancellation() {
    cancelForm.cancellation_reason = selectedReason.value === 'Otro'
        ? customReason.value
        : selectedReason.value

    cancelForm.put(route('orders.cancel', props.order.id), {
        preserveScroll: true,
        onSuccess: () => { showCancelModal.value = false },
    })
}

const canSubmitCancel = computed(() => {
    if (!selectedReason.value) { return false }
    if (selectedReason.value === 'Otro') { return customReason.value.trim().length > 0 }
    return true
})

// --- Helpers ---
function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}

function formatDateTime(dateStr) {
    return new Date(dateStr).toLocaleString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
    })
}

function orderNumber(id) {
    return '#' + String(id).padStart(4, '0')
}

function itemSaleTotal(item) {
    const modPriceTotal = item.modifiers?.reduce((s, m) => s + parseFloat(m.price_adjustment ?? 0), 0) ?? 0
    return (parseFloat(item.unit_price) + modPriceTotal) * item.quantity
}

function itemProductionCost(item) {
    const baseCost = parseFloat(item.production_cost ?? 0) * item.quantity
    const modCost = item.modifiers?.reduce((s, m) => s + parseFloat(m.production_cost ?? 0) * item.quantity, 0) ?? 0
    return baseCost + modCost
}

function itemProfit(item) {
    return itemSaleTotal(item) - itemProductionCost(item)
}

const totalProductionCost = computed(() => {
    return props.order.items?.reduce((sum, item) => sum + itemProductionCost(item), 0) ?? 0
})

const totalProfit = computed(() => {
    const itemsProfit = props.order.items?.reduce((sum, item) => sum + itemProfit(item), 0) ?? 0
    return itemsProfit - parseFloat(props.order.discount_amount ?? 0)
})

function advanceStatus() {
    router.put(route('orders.advance-status', props.order.id), {}, {
        preserveScroll: true,
    })
}

function whatsappHref(phone) {
    const clean = phone.replace(/\D/g, '')
    return `https://wa.me/${clean}`
}

// --- Collapsible item details ---
const expandedItems = ref(new Set())

function toggleItem(itemId) {
    if (expandedItems.value.has(itemId)) {
        expandedItems.value.delete(itemId)
    } else {
        expandedItems.value.add(itemId)
    }
}

const allExpanded = computed(() =>
    props.order.items?.length > 0 && props.order.items.every((i) => expandedItems.value.has(i.id))
)

function toggleAllItems() {
    if (allExpanded.value) {
        expandedItems.value.clear()
    } else {
        props.order.items?.forEach((i) => expandedItems.value.add(i.id))
    }
}

// --- Collapsible map ---
const showMap = ref(false)

// --- Actions dropdown ---
const showActionsMenu = ref(false)
const actionsMenuRef = ref(null)

function toggleActionsMenu() {
    showActionsMenu.value = !showActionsMenu.value
}

function onClickOutside(e) {
    if (actionsMenuRef.value && !actionsMenuRef.value.contains(e.target)) {
        showActionsMenu.value = false
    }
}

onMounted(() => document.addEventListener('click', onClickOutside, true))
onUnmounted(() => document.removeEventListener('click', onClickOutside, true))

// --- Copy order ---
const copied = ref(false)

function buildOrderText(order) {
    const num = orderNumber(order.id)
    const fmt = (v) => '$' + Number(v).toFixed(2)
    const paymentLabel = PAYMENT_LABELS[order.payment_method] ?? order.payment_method

    const lines = [
        `Pedido ${num} — ${props.restaurantName}`,
        '',
        `👤 Cliente: ${order.customer?.name ?? '—'} | ${order.customer?.phone ?? '—'}`,
        '',
        '🛒 Pedido:',
    ]

    for (const item of order.items) {
        const modTotal = item.modifiers?.reduce((s, m) => s + parseFloat(m.price_adjustment ?? 0), 0) ?? 0
        const total = (parseFloat(item.unit_price) + modTotal) * item.quantity
        lines.push(`• ${item.quantity}x ${item.product_name ?? 'Producto'} - ${fmt(total)}`)

        for (const mod of (item.modifiers ?? [])) {
            const adj = parseFloat(mod.price_adjustment ?? 0) > 0 ? ` (+${fmt(mod.price_adjustment)})` : ''
            lines.push(`  ↳ ${mod.modifier_option_name || mod.modifier_option?.name}${adj}`)
        }

        if (item.notes) { lines.push(`  📝 ${item.notes}`) }
    }

    lines.push('')

    if (order.delivery_type === 'delivery') {
        lines.push('🚗 Tipo: A domicilio')
        if (order.address_street) {
            let address = `${order.address_street} ${order.address_number}`
            if (order.address_references) { address += ` — ${order.address_references}` }
            lines.push(`📍 Dirección: ${address}`)
        }
        if (order.branch?.name) { lines.push(`🏪 Sucursal: ${order.branch.name}`) }
        if (order.distance_km) { lines.push(`📏 Distancia: ${Number(order.distance_km)} km`) }
        if (order.latitude && order.longitude) {
            lines.push(`📌 Ubicación: https://maps.google.com/?q=${order.latitude},${order.longitude}`)
        }
    } else if (order.delivery_type === 'pickup') {
        lines.push('🏪 Tipo: Recoger en sucursal')
        if (order.branch?.name) { lines.push(`🏪 Sucursal: ${order.branch.name}`) }
    } else if (order.delivery_type === 'dine_in') {
        lines.push('🍽 Tipo: Comer en restaurante')
    }

    lines.push('')

    if (order.scheduled_at) {
        const d = new Date(order.scheduled_at)
        const pad = (n) => String(n).padStart(2, '0')
        const h12 = d.getHours() % 12 || 12
        const ampm = d.getHours() >= 12 ? 'pm' : 'am'
        lines.push(`🕐 Programado para: ${pad(d.getDate())}/${pad(d.getMonth() + 1)}/${d.getFullYear()}, ${pad(h12)}:${pad(d.getMinutes())} ${ampm}`)
    }

    lines.push(`💳 Pago: ${paymentLabel}`)
    if (order.payment_method === 'cash' && order.cash_amount) {
        lines.push(`💵 Paga con: ${fmt(order.cash_amount)}`)
    }

    lines.push('')
    lines.push(`Subtotal: ${fmt(order.subtotal)}`)
    if (order.delivery_type === 'delivery') {
        lines.push(`Envío: ${fmt(order.delivery_cost)}`)
    }
    if (parseFloat(order.discount_amount) > 0) {
        lines.push(`🏷 Cupón (${order.coupon_code}): -${fmt(order.discount_amount)}`)
    }
    lines.push(`Total: ${fmt(order.total)}`)

    if (order.requires_invoice) {
        lines.push('📋 Requiere factura')
    }

    return lines.join('\n')
}

async function copyOrder() {
    showActionsMenu.value = false
    const text = buildOrderText(props.order)
    try {
        await navigator.clipboard.writeText(text)
        copied.value = true
        setTimeout(() => { copied.value = false }, 2000)
    } catch {
        const ta = document.createElement('textarea')
        ta.value = text
        document.body.appendChild(ta)
        ta.select()
        document.execCommand('copy')
        document.body.removeChild(ta)
        copied.value = true
        setTimeout(() => { copied.value = false }, 2000)
    }
}
</script>

<template>
    <Head :title="`Pedido ${orderNumber(order.id)}`" />
    <AppLayout :title="`Pedido ${orderNumber(order.id)}`">

        <!-- Breadcrumb + Actions -->
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 mb-8">
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <button @click="goBack" class="hover:text-[#FF5722] flex items-center gap-1 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 rounded">
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_back</span>
                        Regresar
                    </button>
                    <span class="text-gray-300">/</span>
                    <span class="text-gray-800 font-medium">Pedido {{ orderNumber(order.id) }}</span>
                </div>
                <div class="flex items-center gap-3 mt-1">
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight text-balance">Pedido {{ orderNumber(order.id) }}</h2>
                    <span v-if="order.edit_count > 0" class="text-xs font-semibold text-amber-600 bg-amber-50 border border-amber-200 px-2 py-1 rounded-lg">editado ({{ order.edit_count }})</span>
                </div>
                <p class="text-sm text-gray-500">{{ formatDateTime(order.created_at) }} · {{ order.branch?.name }}</p>
            </div>

            <div class="flex items-center gap-3 shrink-0">
                <!-- Actions dropdown (secondary actions) -->
                <div ref="actionsMenuRef" class="relative">
                    <button
                        @click="toggleActionsMenu"
                        class="flex items-center justify-center size-11 border border-gray-200 text-gray-500 hover:bg-gray-50 hover:text-gray-700 rounded-xl transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 no-print"
                        aria-label="Más acciones"
                    >
                        <span class="material-symbols-outlined text-xl" aria-hidden="true">more_vert</span>
                    </button>

                    <!-- Dropdown menu -->
                    <Transition
                        enter-active-class="transition duration-100 ease-out"
                        enter-from-class="opacity-0 scale-95"
                        enter-to-class="opacity-100 scale-100"
                        leave-active-class="transition duration-75 ease-in"
                        leave-from-class="opacity-100 scale-100"
                        leave-to-class="opacity-0 scale-95"
                    >
                        <div
                            v-if="showActionsMenu"
                            class="absolute right-0 top-full mt-2 w-52 bg-white rounded-xl border border-gray-200 shadow-lg py-1.5 z-30"
                        >
                            <!-- Copy -->
                            <button
                                @click="copyOrder"
                                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left"
                            >
                                <span class="material-symbols-outlined text-lg text-gray-400" aria-hidden="true">{{ copied ? 'check' : 'content_copy' }}</span>
                                {{ copied ? 'Copiado' : 'Copiar comanda' }}
                            </button>

                            <!-- Print -->
                            <button
                                v-if="isPrintable"
                                @click="printTicket(); showActionsMenu = false"
                                class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors text-left no-print"
                            >
                                <span class="material-symbols-outlined text-lg text-gray-400" aria-hidden="true">print</span>
                                Imprimir ticket
                            </button>

                            <!-- Edit -->
                            <Link
                                v-if="isEditable"
                                :href="route('orders.edit', order.id)"
                                class="flex items-center gap-2.5 px-4 py-2.5 text-sm text-gray-700 hover:bg-gray-50 transition-colors"
                            >
                                <span class="material-symbols-outlined text-lg text-gray-400" aria-hidden="true">edit</span>
                                Editar pedido
                            </Link>

                            <!-- Cancel (destructive — separated) -->
                            <template v-if="isCancellable">
                                <div class="my-1.5 border-t border-gray-100"></div>
                                <button
                                    @click="openCancelModal"
                                    class="w-full flex items-center gap-2.5 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors text-left"
                                >
                                    <span class="material-symbols-outlined text-lg" aria-hidden="true">cancel</span>
                                    Cancelar pedido
                                </button>
                            </template>
                        </div>
                    </Transition>
                </div>

                <!-- Primary action: Advance status -->
                <button
                    v-if="nextStatusLabel"
                    class="flex items-center justify-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 focus-visible:ring-offset-2 motion-reduce:transition-none"
                    @click="advanceStatus"
                >
                    {{ nextStatusLabel }}
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_forward</span>
                </button>

                <!-- Delivered badge -->
                <div
                    v-else-if="order.status === 'delivered'"
                    class="flex items-center gap-2 text-green-700 bg-green-50 border border-green-200 px-5 py-3 rounded-xl text-sm font-bold"
                >
                    <span class="material-symbols-outlined" aria-hidden="true">check_circle</span>
                    Pedido entregado
                </div>

                <!-- Cancelled badge -->
                <div
                    v-else-if="isCancelled"
                    class="flex items-center gap-2 text-red-700 bg-red-50 border border-red-200 px-5 py-3 rounded-xl text-sm font-bold"
                >
                    <span class="material-symbols-outlined" aria-hidden="true">cancel</span>
                    Pedido cancelado
                </div>
            </div>
        </div>

        <!-- Status progress bar (normal flow) -->
        <div v-if="!isCancelled" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sm:p-8 mb-8">
            <div class="relative flex items-center justify-between">
                <!-- Track -->
                <div class="absolute left-0 top-[22px] h-1 w-full bg-gray-100 -translate-y-1/2 z-0 rounded-full"></div>
                <div
                    class="absolute left-0 top-[22px] h-1 bg-[#FF5722] -translate-y-1/2 z-0 rounded-full transition-[width] duration-500 ease-in-out"
                    :style="{ width: progressWidth }"
                ></div>

                <div
                    v-for="(step, idx) in STATUS_STEPS"
                    :key="step.key"
                    class="relative z-10 flex flex-col items-center gap-2.5"
                >
                    <div
                        class="flex size-11 items-center justify-center rounded-full ring-4 ring-white transition-all duration-300"
                        :class="[
                            idx <= currentStepIndex
                                ? 'bg-[#FF5722] text-white shadow-md'
                                : 'bg-gray-100 text-gray-400',
                            idx === currentStepIndex ? 'ring-[#FF5722]/15 ring-[6px]' : '',
                        ]"
                    >
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">{{ step.icon }}</span>
                    </div>
                    <span
                        class="text-xs font-semibold transition-colors duration-300"
                        :class="idx <= currentStepIndex ? 'text-[#FF5722]' : 'text-gray-400'"
                    >
                        {{ step.label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Cancelled banner -->
        <div v-else class="bg-red-50 border border-red-200 rounded-xl p-6 mb-8">
            <div class="flex items-start gap-4">
                <div class="flex size-10 items-center justify-center rounded-full bg-red-100 text-red-600 shrink-0">
                    <span class="material-symbols-outlined text-xl" aria-hidden="true">cancel</span>
                </div>
                <div>
                    <h3 class="font-bold text-red-800 mb-1">Pedido cancelado</h3>
                    <p class="text-sm text-red-700 mb-2">{{ order.cancellation_reason }}</p>
                    <p v-if="order.cancelled_at" class="text-xs text-red-500">
                        Cancelado el {{ formatDateTime(order.cancelled_at) }}
                    </p>
                </div>
            </div>
        </div>

        <!-- Two-column layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            <!-- Left: Order items -->
            <div class="lg:col-span-2 flex flex-col gap-8">

                <!-- Detalle del pedido -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sm:p-7">
                    <div class="flex items-center justify-between mb-5">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#FF5722]" aria-hidden="true">list_alt</span>
                            Detalle del pedido
                        </h3>
                        <button
                            v-if="order.items?.length > 1"
                            @click="toggleAllItems"
                            class="text-xs font-semibold text-[#FF5722] hover:text-[#D84315] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 rounded px-1"
                        >
                            {{ allExpanded ? 'Colapsar todo' : 'Expandir todo' }}
                        </button>
                    </div>

                    <div class="divide-y divide-dashed divide-gray-200">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="py-5 first:pt-0 last:pb-0"
                        >
                            <!-- Item summary row — always visible, clickable -->
                            <button
                                class="w-full flex items-center justify-between gap-3 text-left group focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 rounded-lg -mx-1 px-1"
                                @click="toggleItem(item.id)"
                            >
                                <div class="flex items-center gap-2 min-w-0">
                                    <span
                                        class="material-symbols-outlined text-lg text-gray-400 transition-transform duration-200 shrink-0"
                                        :class="expandedItems.has(item.id) ? 'rotate-180' : ''"
                                        aria-hidden="true"
                                    >expand_more</span>
                                    <h4 class="font-bold text-gray-900 truncate">{{ item.quantity }}x {{ item.product_name || item.product?.name || 'Producto' }}</h4>
                                    <span v-if="item.modifiers?.length" class="text-xs text-gray-400 shrink-0">+{{ item.modifiers.length }} mod.</span>
                                </div>
                                <span class="font-bold text-gray-900 shrink-0 tabular-nums">{{ formatPrice(itemSaleTotal(item)) }}</span>
                            </button>

                            <!-- Notes — always visible when present -->
                            <p v-if="item.notes" class="text-sm text-amber-700 bg-amber-50 px-3 py-2 rounded-lg mt-2.5 ml-7">
                                Nota: {{ item.notes }}
                            </p>

                            <!-- Collapsible detail content -->
                            <div
                                class="grid transition-[grid-template-rows] duration-200 ease-in-out"
                                :class="expandedItems.has(item.id) ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                            >
                                <div class="overflow-hidden">
                                    <div class="flex flex-col gap-3 pt-3 ml-7">

                                        <!-- Precio de venta unitario -->
                                        <div class="flex justify-between text-sm text-gray-500">
                                            <span>Precio de venta</span>
                                            <span class="tabular-nums">{{ formatPrice(item.unit_price) }}</span>
                                        </div>

                                        <!-- Modificadores -->
                                        <div v-if="item.modifiers?.length" class="rounded-lg bg-gray-50 px-3 py-2.5 space-y-1.5">
                                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Modificadores</p>
                                            <div v-for="mod in item.modifiers" :key="mod.id" class="flex justify-between text-sm">
                                                <span class="text-gray-700">{{ mod.modifier_option_name || mod.modifier_option?.name }}</span>
                                                <div class="flex gap-3 text-xs shrink-0 ml-4 tabular-nums">
                                                    <span v-if="parseFloat(mod.price_adjustment) > 0" class="text-gray-500">+{{ formatPrice(mod.price_adjustment) }}</span>
                                                    <span v-if="is_admin" class="text-red-400">costo {{ formatPrice(mod.production_cost ?? 0) }}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <!-- Costo de producción (admin only) -->
                                        <div v-if="is_admin" class="rounded-lg bg-red-50/60 px-3 py-2.5 space-y-1">
                                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Costo de producción</p>
                                            <div class="flex justify-between text-sm">
                                                <span class="text-gray-600">Producto base</span>
                                                <span class="text-red-600 tabular-nums">{{ formatPrice(item.production_cost ?? 0) }}</span>
                                            </div>
                                            <div v-for="mod in (item.modifiers ?? []).filter(m => parseFloat(m.production_cost ?? 0) > 0)" :key="'cost-' + mod.id" class="flex justify-between text-sm">
                                                <span class="text-gray-600">{{ mod.modifier_option_name || mod.modifier_option?.name }}</span>
                                                <span class="text-red-600 tabular-nums">{{ formatPrice(mod.production_cost) }}</span>
                                            </div>
                                            <div class="flex justify-between text-sm font-bold border-t border-red-100 pt-1 mt-1">
                                                <span class="text-gray-700">Costo total <span v-if="item.quantity > 1" class="font-normal text-gray-400">(x{{ item.quantity }})</span></span>
                                                <span class="text-red-700 tabular-nums">{{ formatPrice(itemProductionCost(item)) }}</span>
                                            </div>
                                        </div>

                                        <!-- Ganancia del item (admin only) -->
                                        <div v-if="is_admin" class="flex justify-between items-center rounded-lg bg-green-50/60 px-3 py-2 text-sm font-bold">
                                            <span class="text-green-800">Ganancia del item</span>
                                            <span class="text-green-700 tabular-nums">{{ formatPrice(itemProfit(item)) }}</span>
                                        </div>

                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Resumen financiero del pedido -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sm:p-7">
                    <h3 class="text-lg font-bold text-gray-900 mb-5 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]" aria-hidden="true">account_balance</span>
                        Resumen del pedido
                    </h3>

                    <div class="flex flex-col gap-2.5">
                        <!-- Venta -->
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>Subtotal venta</span>
                            <span class="tabular-nums">{{ formatPrice(order.subtotal) }}</span>
                        </div>
                        <div v-if="parseFloat(order.discount_amount) > 0" class="flex justify-between text-sm text-green-600">
                            <span class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-base" aria-hidden="true">confirmation_number</span>
                                Cupón {{ order.coupon_code }}
                            </span>
                            <span class="tabular-nums">-{{ formatPrice(order.discount_amount) }}</span>
                        </div>
                        <div v-if="order.delivery_type === 'delivery'" class="flex justify-between text-sm text-gray-500">
                            <span>Costo de envío</span>
                            <span class="tabular-nums">{{ formatPrice(order.delivery_cost) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-black text-gray-900 border-t border-gray-100 pt-3 mt-1">
                            <span>Total cobrado</span>
                            <span class="tabular-nums">{{ formatPrice(order.total) }}</span>
                        </div>

                        <!-- Costos + Ganancia (admin only) -->
                        <template v-if="is_admin">
                            <div class="flex justify-between text-sm text-red-600 mt-3">
                                <span>Costo total de producción</span>
                                <span class="font-semibold tabular-nums">-{{ formatPrice(totalProductionCost) }}</span>
                            </div>
                            <div class="flex justify-between items-center rounded-xl bg-green-50 px-4 py-3.5 mt-2">
                                <span class="text-green-800 font-bold">Ganancia bruta</span>
                                <span class="text-green-700 text-lg font-black tabular-nums">{{ formatPrice(totalProfit) }}</span>
                            </div>
                        </template>

                        <!-- Método de pago -->
                        <div class="flex justify-between text-sm text-gray-500 mt-3 border-t border-gray-100 pt-3">
                            <span>Método de pago</span>
                            <span class="font-medium text-gray-700">{{ PAYMENT_LABELS[order.payment_method] ?? order.payment_method }}</span>
                        </div>
                        <div v-if="order.payment_method === 'cash' && order.cash_amount" class="flex justify-between text-sm">
                            <span class="text-gray-500">Paga con</span>
                            <span class="font-bold text-gray-900 tabular-nums">{{ formatPrice(order.cash_amount) }}</span>
                        </div>
                        <div v-if="order.payment_method === 'cash' && order.cash_amount && parseFloat(order.cash_amount) > parseFloat(order.total)" class="flex justify-between text-sm">
                            <span class="text-gray-500">Cambio</span>
                            <span class="font-bold text-amber-600 tabular-nums">{{ formatPrice(parseFloat(order.cash_amount) - parseFloat(order.total)) }}</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right: Client + Delivery info -->
            <div class="flex flex-col gap-8">

                <!-- Client card -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sm:p-7">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]" aria-hidden="true">person</span>
                        Cliente
                    </h3>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="flex size-12 items-center justify-center rounded-full bg-[#FF5722]/10 text-[#FF5722]">
                            <span class="material-symbols-outlined" aria-hidden="true">face</span>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">{{ order.customer?.name ?? '—' }}</p>
                            <p class="text-sm text-gray-500 tabular-nums">{{ order.customer?.phone ?? '—' }}</p>
                        </div>
                    </div>
                    <div v-if="order.requires_invoice" class="flex items-center gap-2 px-3 py-2 rounded-lg bg-amber-50 border border-amber-200 text-amber-700 text-sm font-semibold mb-3">
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">receipt_long</span>
                        Requiere factura
                    </div>
                    <div class="flex gap-3">
                        <a
                            v-if="order.customer?.phone"
                            :href="`tel:${order.customer.phone}`"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-gray-100 py-2.5 text-sm font-bold text-gray-700 hover:bg-gray-200 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50"
                        >
                            <span class="material-symbols-outlined text-lg" aria-hidden="true">call</span>
                            Llamar
                        </a>
                        <a
                            v-if="order.customer?.phone"
                            :href="whatsappHref(order.customer.phone)"
                            target="_blank"
                            rel="noopener"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-green-50 py-2.5 text-sm font-bold text-green-700 hover:bg-green-100 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-green-500/50"
                        >
                            <span class="material-symbols-outlined text-lg" aria-hidden="true">chat</span>
                            WhatsApp
                        </a>
                    </div>
                </div>

                <!-- Delivery card -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sm:p-7">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]" aria-hidden="true">location_on</span>
                        Entrega
                    </h3>
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 rounded-lg bg-[#FF5722]/5 px-3 py-2.5 text-sm font-bold text-[#FF5722]">
                            <span class="material-symbols-outlined" aria-hidden="true">{{ DELIVERY_ICONS[order.delivery_type] }}</span>
                            {{ DELIVERY_LABELS[order.delivery_type] }}
                        </div>

                        <div v-if="order.delivery_type === 'delivery' && order.distance_km" class="flex flex-col gap-1">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Distancia</p>
                            <p class="text-gray-800 font-medium tabular-nums">{{ parseFloat(order.distance_km).toFixed(2) }} km</p>
                        </div>

                        <div v-if="order.delivery_type === 'delivery'" class="flex flex-col gap-1">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Dirección</p>
                            <p v-if="order.address_street" class="text-gray-800">{{ order.address_street }} #{{ order.address_number }}, Col. {{ order.address_colony }}</p>
                            <p v-if="order.address_references" class="text-sm text-gray-500">{{ order.address_references }}</p>

                            <!-- Collapsible map -->
                            <div v-if="order.latitude && order.longitude" class="mt-2">
                                <button
                                    @click="showMap = !showMap"
                                    class="flex items-center gap-1.5 text-sm text-[#FF5722] font-medium hover:text-[#D84315] transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 rounded"
                                >
                                    <span class="material-symbols-outlined text-base" aria-hidden="true">{{ showMap ? 'expand_less' : 'map' }}</span>
                                    {{ showMap ? 'Ocultar mapa' : 'Ver mapa' }}
                                </button>
                                <div
                                    class="grid transition-[grid-template-rows] duration-200 ease-in-out"
                                    :class="showMap ? 'grid-rows-[1fr]' : 'grid-rows-[0fr]'"
                                >
                                    <div class="overflow-hidden">
                                        <a
                                            :href="`https://maps.google.com/?q=${order.latitude},${order.longitude}`"
                                            target="_blank"
                                            rel="noopener"
                                            class="block mt-2"
                                        >
                                            <img
                                                v-if="mapsKey"
                                                :src="`https://maps.googleapis.com/maps/api/staticmap?center=${order.latitude},${order.longitude}&zoom=15&size=400x200&scale=2&markers=color:red|${order.latitude},${order.longitude}&key=${mapsKey}`"
                                                alt="Mapa de ubicación"
                                                width="400"
                                                height="200"
                                                loading="lazy"
                                                class="w-full h-40 object-cover rounded-xl"
                                            />
                                            <div v-else class="flex items-center gap-2 text-sm text-[#FF5722]">
                                                <span class="material-symbols-outlined text-base" aria-hidden="true">open_in_new</span>
                                                Ver en Google Maps
                                            </div>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="flex flex-col gap-1">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Sucursal</p>
                            <p class="text-gray-800 font-medium">{{ order.branch?.name ?? '—' }}</p>
                        </div>

                        <div v-if="order.scheduled_at" class="flex flex-col gap-1">
                            <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Programado para</p>
                            <p class="text-gray-800 font-medium">{{ formatDateTime(order.scheduled_at) }}</p>
                        </div>
                    </div>
                </div>

                <!-- Historial de acciones (audit trail) -->
                <div v-if="order.events?.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sm:p-7">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]" aria-hidden="true">history</span>
                        Historial
                    </h3>
                    <div class="space-y-4">
                        <div
                            v-for="event in order.events"
                            :key="event.id"
                            class="flex items-start gap-3"
                        >
                            <div class="mt-0.5 shrink-0">
                                <div
                                    class="w-7 h-7 rounded-full flex items-center justify-center text-white text-xs"
                                    :class="{
                                        'bg-blue-500': event.action === 'created',
                                        'bg-[#FF5722]': event.action === 'status_changed',
                                        'bg-red-500': event.action === 'cancelled',
                                    }"
                                >
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">
                                        {{ event.action === 'created' ? 'add' : event.action === 'cancelled' ? 'cancel' : 'arrow_forward' }}
                                    </span>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">
                                    <template v-if="event.action === 'created'">Pedido creado</template>
                                    <template v-else-if="event.action === 'cancelled'">Pedido cancelado</template>
                                    <template v-else>
                                        {{ event.from_status }} → {{ event.to_status }}
                                    </template>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ event.user ? event.user.name : 'Cliente (API)' }}
                                    · {{ formatDateTime(event.created_at) }}
                                </p>
                                <p v-if="event.metadata?.reason" class="text-xs text-red-500 mt-0.5">
                                    Motivo: {{ event.metadata.reason }}
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Historial de cambios (edit audit trail) -->
                <div v-if="order.audits?.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sm:p-7">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]" aria-hidden="true">edit_note</span>
                        Historial de cambios
                    </h3>
                    <div class="space-y-4">
                        <div
                            v-for="audit in order.audits"
                            :key="audit.id"
                            class="flex items-start gap-3"
                        >
                            <div class="mt-0.5 shrink-0">
                                <div class="w-7 h-7 rounded-full flex items-center justify-center bg-amber-500 text-white text-xs">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">edit</span>
                                </div>
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-gray-900">
                                    <template v-if="audit.action === 'items_modified'">Productos modificados</template>
                                    <template v-else-if="audit.action === 'address_modified'">Dirección actualizada</template>
                                    <template v-else-if="audit.action === 'location_modified'">Ubicación corregida</template>
                                    <template v-else-if="audit.action === 'payment_method_changed'">Método de pago cambiado</template>
                                    <template v-else>{{ audit.action }}</template>
                                </p>
                                <p class="text-xs text-gray-500">
                                    {{ audit.user?.name ?? 'Sistema' }}
                                    · {{ formatDateTime(audit.created_at) }}
                                </p>
                                <p v-if="audit.reason" class="text-xs text-gray-500 mt-0.5">
                                    Motivo: {{ audit.reason }}
                                </p>
                                <div v-if="audit.old_total !== audit.new_total && audit.old_total !== null" class="text-xs text-amber-600 mt-0.5 tabular-nums">
                                    Total: {{ formatPrice(audit.old_total) }} → {{ formatPrice(audit.new_total) }}
                                </div>

                                <!-- Item changes detail -->
                                <div v-if="audit.action === 'items_modified' && audit.changes" class="mt-1.5 text-xs space-y-0.5">
                                    <div v-for="(added, ai) in (audit.changes.added ?? [])" :key="'add-'+ai" class="text-green-600">
                                        + {{ added.quantity }}x {{ added.product_name }}
                                    </div>
                                    <div v-for="(removed, ri) in (audit.changes.removed ?? [])" :key="'rem-'+ri" class="text-red-500">
                                        - {{ removed.quantity }}x {{ removed.product_name }}
                                    </div>
                                    <div v-for="(mod, mi) in (audit.changes.modified ?? [])" :key="'mod-'+mi" class="text-amber-600">
                                        ~ {{ mod.product_name }}: {{ mod.field }} {{ mod.old }} → {{ mod.new }}
                                    </div>
                                </div>

                                <!-- Address/payment changes detail -->
                                <div v-if="['address_modified', 'location_modified', 'payment_method_changed'].includes(audit.action) && audit.changes" class="mt-1.5 text-xs space-y-0.5">
                                    <div v-for="(change, field) in audit.changes" :key="field" class="text-gray-500">
                                        {{ field }}: {{ change.old ?? '—' }} → {{ change.new ?? '—' }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Cancel order modal -->
        <Teleport to="body">
            <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="showCancelModal = false"></div>

                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto overscroll-contain">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-1">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-full bg-red-100 text-red-600">
                                    <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                                </div>
                                <h2 class="text-xl font-bold text-gray-900">Cancelar pedido</h2>
                            </div>
                            <button @click="showCancelModal = false" class="text-gray-400 hover:text-gray-600 transition-colors ml-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 rounded" aria-label="Cerrar">
                                <span class="material-symbols-outlined" aria-hidden="true">close</span>
                            </button>
                        </div>
                        <p class="text-sm text-gray-500 mb-5 ml-[52px]">
                            Esta acción no se puede deshacer. Selecciona el motivo de la cancelación.
                        </p>

                        <!-- Reasons -->
                        <div class="space-y-2 mb-5">
                            <label
                                v-for="reason in CANCELLATION_REASONS"
                                :key="reason"
                                class="flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all"
                                :class="selectedReason === reason
                                    ? 'border-red-300 bg-red-50'
                                    : 'border-gray-100 bg-gray-50 hover:border-gray-200'"
                            >
                                <input
                                    type="radio"
                                    name="cancel_reason"
                                    :value="reason"
                                    v-model="selectedReason"
                                    class="accent-red-600"
                                />
                                <span class="text-sm font-medium text-gray-800">{{ reason }}</span>
                            </label>
                        </div>

                        <!-- Custom reason textarea -->
                        <div v-if="selectedReason === 'Otro'" class="mb-5">
                            <textarea
                                v-model="customReason"
                                placeholder="Describe brevemente el motivo…"
                                rows="3"
                                class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-red-300"
                            ></textarea>
                        </div>

                        <!-- Validation error -->
                        <p v-if="cancelForm.errors.cancellation_reason" class="text-xs text-red-500 mb-4">
                            {{ cancelForm.errors.cancellation_reason }}
                        </p>

                        <!-- Buttons -->
                        <div class="flex gap-3">
                            <button
                                type="button"
                                @click="showCancelModal = false"
                                class="flex-1 border border-gray-200 text-gray-700 font-semibold rounded-xl py-2.5 text-sm hover:bg-gray-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-300"
                            >
                                Volver
                            </button>
                            <button
                                @click="submitCancellation"
                                :disabled="!canSubmitCancel || cancelForm.processing"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                            >
                                {{ cancelForm.processing ? 'Cancelando…' : 'Confirmar cancelación' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>

    <!-- Print-only ticket (hidden in DOM, visible only via @media print) -->
    <OrderTicket :order="order" :restaurant-name="restaurantName" />
</template>
