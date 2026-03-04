<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
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

const currentStepIndex = computed(() =>
    STATUS_STEPS.findIndex((s) => s.key === props.order.status),
)

const progressWidth = computed(() => {
    const idx = currentStepIndex.value
    if (idx <= 0) { return '0%' }
    return Math.round((idx / (STATUS_STEPS.length - 1)) * 100) + '%'
})

const nextStatusLabel = computed(() => NEXT_LABEL[props.order.status] ?? null)

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

function itemTotal(item) {
    const modTotal = item.modifiers?.reduce((s, m) => s + parseFloat(m.price_adjustment ?? 0), 0) ?? 0
    return (parseFloat(item.unit_price) + modTotal) * item.quantity
}

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

            <button
                v-if="nextStatusLabel"
                class="flex items-center justify-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition hover:scale-105 active:scale-95"
                @click="advanceStatus"
            >
                {{ nextStatusLabel }}
                <span class="material-symbols-outlined text-lg">arrow_forward</span>
            </button>
            <div
                v-else-if="order.status === 'delivered'"
                class="flex items-center gap-2 text-green-700 bg-green-50 border border-green-200 px-5 py-3 rounded-xl text-sm font-bold"
            >
                <span class="material-symbols-outlined">check_circle</span>
                Pedido entregado
            </div>
        </div>

        <!-- Status progress bar -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 mb-6">
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

        <!-- Two-column layout -->
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Order items -->
            <div class="lg:col-span-2 flex flex-col gap-6">

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">list_alt</span>
                        Detalle del pedido
                    </h3>

                    <div class="divide-y divide-dashed divide-gray-200">
                        <div
                            v-for="item in order.items"
                            :key="item.id"
                            class="py-4 flex flex-col gap-1"
                        >
                            <div class="flex justify-between">
                                <h4 class="font-bold text-gray-800">{{ item.quantity }}x {{ item.product?.name ?? 'Producto' }}</h4>
                                <span class="font-bold text-gray-900">{{ formatPrice(itemTotal(item)) }}</span>
                            </div>
                            <div v-if="item.modifiers?.length" class="text-sm text-gray-500 space-y-0.5">
                                <p v-for="mod in item.modifiers" :key="mod.id">
                                    · {{ mod.modifier_option?.name }}
                                    <span v-if="parseFloat(mod.price_adjustment) > 0" class="text-gray-400">
                                        (+{{ formatPrice(mod.price_adjustment) }})
                                    </span>
                                </p>
                            </div>
                            <p v-if="item.notes" class="text-sm text-amber-700 bg-amber-50 px-2 py-1 rounded mt-1">
                                Nota: {{ item.notes }}
                            </p>
                        </div>
                    </div>

                    <!-- Totals -->
                    <div class="mt-6 border-t border-gray-200 pt-6 flex flex-col gap-2">
                        <div class="flex justify-between text-sm text-gray-500">
                            <span>Subtotal</span>
                            <span>{{ formatPrice(order.subtotal) }}</span>
                        </div>
                        <div v-if="order.delivery_type === 'delivery'" class="flex justify-between text-sm text-gray-500">
                            <span>Envío</span>
                            <span>{{ formatPrice(order.delivery_cost) }}</span>
                        </div>
                        <div class="flex justify-between text-xl font-black text-gray-900 mt-2">
                            <span>Total</span>
                            <span>{{ formatPrice(order.total) }}</span>
                        </div>
                        <p class="text-right text-xs text-gray-400 mt-1">
                            Pago: {{ PAYMENT_LABELS[order.payment_method] ?? order.payment_method }}
                        </p>
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

    </AppLayout>
</template>
