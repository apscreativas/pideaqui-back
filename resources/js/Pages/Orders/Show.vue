<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    order: Object,
    mapsKey: { type: String, default: '' },
})

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
    return props.order.items?.reduce((sum, item) => sum + itemProfit(item), 0) ?? 0
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
</script>

<template>
    <Head :title="`Pedido ${orderNumber(order.id)}`" />
    <AppLayout :title="`Pedido ${orderNumber(order.id)}`">

        <!-- Breadcrumb + Actions -->
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-6 mb-6">
            <div class="flex flex-col gap-1">
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <Link :href="route('orders.index')" class="hover:text-[#FF5722] flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-lg">arrow_back</span>
                        Pedidos
                    </Link>
                    <span class="text-gray-300">/</span>
                    <span class="text-gray-800 font-medium">Pedido {{ orderNumber(order.id) }}</span>
                </div>
                <div class="flex items-center gap-3 mt-1">
                    <h2 class="text-3xl font-black text-gray-900 tracking-tight">Pedido {{ orderNumber(order.id) }}</h2>
                </div>
                <p class="text-sm text-gray-500">{{ formatDateTime(order.created_at) }} · {{ order.branch?.name }}</p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Cancel button -->
                <button
                    v-if="isCancellable"
                    class="flex items-center justify-center gap-2 border border-red-200 text-red-600 hover:bg-red-50 px-5 py-3 rounded-xl text-sm font-bold transition"
                    @click="openCancelModal"
                >
                    <span class="material-symbols-outlined text-lg">cancel</span>
                    Cancelar pedido
                </button>

                <!-- Advance button -->
                <button
                    v-if="nextStatusLabel"
                    class="flex items-center justify-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition hover:scale-105 active:scale-95"
                    @click="advanceStatus"
                >
                    {{ nextStatusLabel }}
                    <span class="material-symbols-outlined text-lg">arrow_forward</span>
                </button>

                <!-- Delivered badge -->
                <div
                    v-else-if="order.status === 'delivered'"
                    class="flex items-center gap-2 text-green-700 bg-green-50 border border-green-200 px-5 py-3 rounded-xl text-sm font-bold"
                >
                    <span class="material-symbols-outlined">check_circle</span>
                    Pedido entregado
                </div>

                <!-- Cancelled badge -->
                <div
                    v-else-if="isCancelled"
                    class="flex items-center gap-2 text-red-700 bg-red-50 border border-red-200 px-5 py-3 rounded-xl text-sm font-bold"
                >
                    <span class="material-symbols-outlined">cancel</span>
                    Pedido cancelado
                </div>
            </div>
        </div>

        <!-- Status progress bar (normal flow) -->
        <div v-if="!isCancelled" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
            <div class="relative flex items-center justify-between">
                <!-- Track -->
                <div class="absolute left-0 top-5 h-1 w-full bg-gray-100 -translate-y-1/2 z-0"></div>
                <div
                    class="absolute left-0 top-5 h-1 bg-[#FF5722] -translate-y-1/2 z-0 transition-all duration-500"
                    :style="{ width: progressWidth }"
                ></div>

                <div
                    v-for="(step, idx) in STATUS_STEPS"
                    :key="step.key"
                    class="relative z-10 flex flex-col items-center gap-2"
                >
                    <div
                        class="flex size-10 items-center justify-center rounded-full ring-4 ring-white"
                        :class="idx <= currentStepIndex
                            ? 'bg-[#FF5722] text-white'
                            : 'bg-gray-100 text-gray-400'"
                    >
                        <span class="material-symbols-outlined text-lg">{{ step.icon }}</span>
                    </div>
                    <span
                        class="text-xs font-semibold"
                        :class="idx <= currentStepIndex ? 'text-[#FF5722]' : 'text-gray-400'"
                    >
                        {{ step.label }}
                    </span>
                </div>
            </div>
        </div>

        <!-- Cancelled banner -->
        <div v-else class="bg-red-50 border border-red-200 rounded-xl p-6 mb-6">
            <div class="flex items-start gap-4">
                <div class="flex size-10 items-center justify-center rounded-full bg-red-100 text-red-600 shrink-0">
                    <span class="material-symbols-outlined text-xl">cancel</span>
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
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Order items -->
            <div class="lg:col-span-2 flex flex-col gap-6">

                <!-- Detalle del pedido -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">list_alt</span>
                        Detalle del pedido
                    </h3>

                    <div class="divide-y divide-dashed divide-gray-200">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="py-5 flex flex-col gap-3"
                        >
                            <!-- Item header -->
                            <div class="flex justify-between items-start">
                                <h4 class="font-bold text-gray-900">{{ item.quantity }}x {{ item.product_name || item.product?.name || 'Producto' }}</h4>
                                <span class="font-bold text-gray-900 shrink-0 ml-4">{{ formatPrice(itemSaleTotal(item)) }}</span>
                            </div>

                            <!-- Precio de venta unitario -->
                            <div class="flex justify-between text-sm text-gray-500">
                                <span>Precio de venta</span>
                                <span>{{ formatPrice(item.unit_price) }}</span>
                            </div>

                            <!-- Modificadores -->
                            <div v-if="item.modifiers?.length" class="rounded-lg bg-gray-50 px-3 py-2.5 space-y-1.5">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Modificadores</p>
                                <div v-for="mod in item.modifiers" :key="mod.id" class="flex justify-between text-sm">
                                    <span class="text-gray-700">{{ mod.modifier_option_name || mod.modifier_option?.name }}</span>
                                    <div class="flex gap-3 text-xs shrink-0 ml-4">
                                        <span v-if="parseFloat(mod.price_adjustment) > 0" class="text-gray-500">+{{ formatPrice(mod.price_adjustment) }}</span>
                                        <span class="text-red-400">costo {{ formatPrice(mod.production_cost ?? 0) }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Costo de producción -->
                            <div class="rounded-lg bg-red-50/60 px-3 py-2.5 space-y-1">
                                <p class="text-xs font-semibold text-gray-400 uppercase tracking-wide">Costo de producción</p>
                                <div class="flex justify-between text-sm">
                                    <span class="text-gray-600">Producto base</span>
                                    <span class="text-red-600">{{ formatPrice(item.production_cost ?? 0) }}</span>
                                </div>
                                <div v-for="mod in (item.modifiers ?? []).filter(m => parseFloat(m.production_cost ?? 0) > 0)" :key="'cost-' + mod.id" class="flex justify-between text-sm">
                                    <span class="text-gray-600">{{ mod.modifier_option_name || mod.modifier_option?.name }}</span>
                                    <span class="text-red-600">{{ formatPrice(mod.production_cost) }}</span>
                                </div>
                                <div class="flex justify-between text-sm font-bold border-t border-red-100 pt-1 mt-1">
                                    <span class="text-gray-700">Costo total <span v-if="item.quantity > 1" class="font-normal text-gray-400">(x{{ item.quantity }})</span></span>
                                    <span class="text-red-700">{{ formatPrice(itemProductionCost(item)) }}</span>
                                </div>
                            </div>

                            <!-- Ganancia del item -->
                            <div class="flex justify-between items-center rounded-lg bg-green-50/60 px-3 py-2 text-sm font-bold">
                                <span class="text-green-800">Ganancia del item</span>
                                <span class="text-green-700">{{ formatPrice(itemProfit(item)) }}</span>
                            </div>

                            <!-- Notas -->
                            <p v-if="item.notes" class="text-sm text-amber-700 bg-amber-50 px-3 py-2 rounded-lg">
                                Nota: {{ item.notes }}
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Resumen financiero del pedido -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">account_balance</span>
                        Resumen del pedido
                    </h3>

                    <div class="flex flex-col gap-2">
                        <!-- Venta -->
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>Subtotal venta</span>
                            <span>{{ formatPrice(order.subtotal) }}</span>
                        </div>
                        <div v-if="order.delivery_type === 'delivery'" class="flex justify-between text-sm text-gray-500">
                            <span>Costo de envío</span>
                            <span>{{ formatPrice(order.delivery_cost) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-black text-gray-900 border-t border-gray-100 pt-2 mt-1">
                            <span>Total cobrado</span>
                            <span>{{ formatPrice(order.total) }}</span>
                        </div>

                        <!-- Costos -->
                        <div class="flex justify-between text-sm text-red-600 mt-3">
                            <span>Costo total de producción</span>
                            <span class="font-semibold">-{{ formatPrice(totalProductionCost) }}</span>
                        </div>

                        <!-- Ganancia -->
                        <div class="flex justify-between items-center rounded-lg bg-green-50 px-4 py-3 mt-2">
                            <span class="text-green-800 font-bold">Ganancia bruta</span>
                            <span class="text-green-700 text-lg font-black">{{ formatPrice(totalProfit) }}</span>
                        </div>

                        <!-- Método de pago -->
                        <div class="flex justify-between text-sm text-gray-500 mt-3 border-t border-gray-100 pt-3">
                            <span>Método de pago</span>
                            <span class="font-medium text-gray-700">{{ PAYMENT_LABELS[order.payment_method] ?? order.payment_method }}</span>
                        </div>
                        <div v-if="order.payment_method === 'cash' && order.cash_amount" class="flex justify-between text-sm">
                            <span class="text-gray-500">Paga con</span>
                            <span class="font-bold text-gray-900">{{ formatPrice(order.cash_amount) }}</span>
                        </div>
                        <div v-if="order.payment_method === 'cash' && order.cash_amount && parseFloat(order.cash_amount) > parseFloat(order.total)" class="flex justify-between text-sm">
                            <span class="text-gray-500">Cambio</span>
                            <span class="font-bold text-amber-600">{{ formatPrice(parseFloat(order.cash_amount) - parseFloat(order.total)) }}</span>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right: Client + Delivery info -->
            <div class="flex flex-col gap-6">

                <!-- Client card -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">person</span>
                        Cliente
                    </h3>
                    <div class="flex items-center gap-4 mb-4">
                        <div class="flex size-12 items-center justify-center rounded-full bg-[#FF5722]/10 text-[#FF5722]">
                            <span class="material-symbols-outlined">face</span>
                        </div>
                        <div>
                            <p class="font-bold text-gray-900">{{ order.customer?.name ?? '—' }}</p>
                            <p class="text-sm text-gray-500">{{ order.customer?.phone ?? '—' }}</p>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <a
                            v-if="order.customer?.phone"
                            :href="`tel:${order.customer.phone}`"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-gray-100 py-2 text-sm font-bold text-gray-700 hover:bg-gray-200 transition"
                        >
                            <span class="material-symbols-outlined text-lg">call</span>
                            Llamar
                        </a>
                        <a
                            v-if="order.customer?.phone"
                            :href="whatsappHref(order.customer.phone)"
                            target="_blank"
                            rel="noopener"
                            class="flex flex-1 items-center justify-center gap-2 rounded-lg bg-green-50 py-2 text-sm font-bold text-green-700 hover:bg-green-100 transition"
                        >
                            <span class="material-symbols-outlined text-lg">chat</span>
                            WhatsApp
                        </a>
                    </div>
                </div>

                <!-- Delivery card -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">location_on</span>
                        Entrega
                    </h3>
                    <div class="flex flex-col gap-4">
                        <div class="flex items-center gap-2 rounded-lg bg-[#FF5722]/5 px-3 py-2 text-sm font-bold text-[#FF5722]">
                            <span class="material-symbols-outlined">{{ DELIVERY_ICONS[order.delivery_type] }}</span>
                            {{ DELIVERY_LABELS[order.delivery_type] }}
                        </div>

                        <div v-if="order.delivery_type === 'delivery' && order.distance_km" class="flex flex-col gap-1">
                            <p class="text-sm font-bold text-gray-500">Distancia</p>
                            <p class="text-gray-800">{{ parseFloat(order.distance_km).toFixed(2) }} km</p>
                        </div>

                        <div v-if="order.delivery_type === 'delivery'" class="flex flex-col gap-1">
                            <p class="text-sm font-bold text-gray-500">Dirección</p>
                            <p v-if="order.address_street" class="text-gray-800">{{ order.address_street }} #{{ order.address_number }}, Col. {{ order.address_colony }}</p>
                            <p v-if="order.address_references" class="text-sm text-gray-500">{{ order.address_references }}</p>

                            <a
                                v-if="order.latitude && order.longitude"
                                :href="`https://maps.google.com/?q=${order.latitude},${order.longitude}`"
                                target="_blank"
                                rel="noopener"
                                class="block mt-2"
                            >
                                <img
                                    v-if="mapsKey"
                                    :src="`https://maps.googleapis.com/maps/api/staticmap?center=${order.latitude},${order.longitude}&zoom=15&size=400x200&scale=2&markers=color:red|${order.latitude},${order.longitude}&key=${mapsKey}`"
                                    alt="Mapa de ubicación"
                                    class="w-full h-40 object-cover rounded-xl"
                                />
                                <div v-else class="flex items-center gap-2 text-sm text-[#FF5722] mt-1">
                                    <span class="material-symbols-outlined text-base">open_in_new</span>
                                    Ver en Google Maps
                                </div>
                            </a>
                        </div>

                        <div class="flex flex-col gap-1">
                            <p class="text-sm font-bold text-gray-500">Sucursal</p>
                            <p class="text-gray-800">{{ order.branch?.name ?? '—' }}</p>
                        </div>

                        <div v-if="order.scheduled_at" class="flex flex-col gap-1">
                            <p class="text-sm font-bold text-gray-500">Programado para</p>
                            <p class="text-gray-800">{{ formatDateTime(order.scheduled_at) }}</p>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Cancel order modal -->
        <Teleport to="body">
            <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="showCancelModal = false"></div>

                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <!-- Header -->
                        <div class="flex items-start justify-between mb-1">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-full bg-red-100 text-red-600">
                                    <span class="material-symbols-outlined">warning</span>
                                </div>
                                <h2 class="text-xl font-bold text-gray-900">Cancelar pedido</h2>
                            </div>
                            <button @click="showCancelModal = false" class="text-gray-400 hover:text-gray-600 transition-colors ml-4">
                                <span class="material-symbols-outlined">close</span>
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
                                placeholder="Describe brevemente el motivo..."
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
                                class="flex-1 border border-gray-200 text-gray-700 font-semibold rounded-xl py-2.5 text-sm hover:bg-gray-50 transition-colors"
                            >
                                Volver
                            </button>
                            <button
                                @click="submitCancellation"
                                :disabled="!canSubmitCancel || cancelForm.processing"
                                class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                {{ cancelForm.processing ? 'Cancelando...' : 'Confirmar cancelación' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
