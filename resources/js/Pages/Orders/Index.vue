<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    orders: Object,   // { received, preparing, on_the_way, delivered }
    branches: Array,
    filters: Object,
    monthly_count: Number,
    max_monthly_orders: Number,
})

// --- Optimistic UI: local copy of orders ---
const localOrders = ref(JSON.parse(JSON.stringify(props.orders)))

watch(() => props.orders, (fresh) => {
    localOrders.value = JSON.parse(JSON.stringify(fresh))
}, { deep: true })

// --- Filters ---
const branchId = ref(props.filters?.branch_id ?? '')
const date = ref(props.filters?.date ?? '')

function applyFilters() {
    router.get(route('orders.index'), { branch_id: branchId.value || undefined, date: date.value || undefined }, {
        preserveState: true,
        replace: true,
    })
}

// --- Helpers ---
function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}

function formatTime(dateStr) {
    return new Date(dateStr).toLocaleTimeString('es-MX', { hour: '2-digit', minute: '2-digit' })
}

function orderNumber(id) {
    return '#' + String(id).padStart(4, '0')
}

const monthlyPercent = computed(() =>
    Math.min(100, Math.round((props.monthly_count / props.max_monthly_orders) * 100)),
)

const COLUMNS = [
    { key: 'received',   label: 'Recibido',        dotClass: 'bg-orange-500', borderClass: 'border-l-orange-500' },
    { key: 'preparing',  label: 'En preparación',   dotClass: 'bg-amber-400',  borderClass: 'border-l-amber-400' },
    { key: 'on_the_way', label: 'En camino',        dotClass: 'bg-blue-500',   borderClass: 'border-l-blue-500' },
    { key: 'delivered',  label: 'Entregado',        dotClass: 'bg-green-500',  borderClass: 'border-l-green-500' },
]

const NEXT_STATUS = { received: 'preparing', preparing: 'on_the_way', on_the_way: 'delivered' }

function itemsSummary(items) {
    if (!items?.length) { return '' }
    return items.map((i) => `${i.quantity}x ${i.product?.name ?? 'producto'}`).join(', ')
}

// --- Drag & Drop ---
const draggingOrder = ref(null)
const draggingFromCol = ref(null)
const dropTargetCol = ref(null)
let didDrag = false

function isValidDropTarget(colKey) {
    return draggingFromCol.value && NEXT_STATUS[draggingFromCol.value] === colKey
}

function onDragStart(order, colKey, event) {
    draggingOrder.value = order
    draggingFromCol.value = colKey
    didDrag = false
    event.dataTransfer.effectAllowed = 'move'
}

function onDragEnd() {
    draggingOrder.value = null
    draggingFromCol.value = null
    dropTargetCol.value = null
}

function onDragOverCol(colKey, event) {
    if (!isValidDropTarget(colKey)) {
        event.dataTransfer.dropEffect = 'none'
        return
    }
    event.preventDefault()
    dropTargetCol.value = colKey
}

function onDragLeaveCol(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) {
        dropTargetCol.value = null
    }
}

function onDrop(targetColKey, event) {
    event.preventDefault()
    dropTargetCol.value = null

    const order = draggingOrder.value
    const fromCol = draggingFromCol.value
    if (!order || !fromCol || !isValidDropTarget(targetColKey)) { return }

    // Optimistic move
    const fromList = localOrders.value[fromCol]
    const idx = fromList.findIndex((o) => o.id === order.id)
    if (idx === -1) { return }
    fromList.splice(idx, 1)
    localOrders.value[targetColKey].unshift(order)

    didDrag = true

    // Persist via backend
    router.put(route('orders.advance-status', order.id), {}, {
        preserveScroll: true,
        onError() {
            // Revert on failure
            localOrders.value = JSON.parse(JSON.stringify(props.orders))
        },
    })
}

function onCardClick(orderId) {
    if (!didDrag) {
        router.visit(route('orders.show', orderId))
    }
}
</script>

<template>
    <Head title="Pedidos" />
    <AppLayout title="Pedidos">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tablero de Pedidos</h1>
                <p class="mt-1 text-sm text-gray-500">Gestión visual de tus órdenes.</p>
            </div>

            <!-- Monthly usage -->
            <div class="flex items-center gap-4 bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-3 min-w-[220px]">
                <div class="flex-1">
                    <div class="flex justify-between items-center mb-1">
                        <span class="text-xs font-semibold text-gray-700">Órdenes del mes</span>
                        <span class="text-xs text-[#FF5722] font-medium">{{ monthly_count }}/{{ max_monthly_orders }}</span>
                    </div>
                    <div class="h-1.5 w-full bg-gray-100 rounded-full overflow-hidden">
                        <div
                            class="h-full bg-[#FF5722] rounded-full transition-all"
                            :style="{ width: monthlyPercent + '%' }"
                        ></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-4 mb-6">
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-xl">store</span>
                <select
                    v-model="branchId"
                    class="pl-10 pr-8 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50 appearance-none min-w-[200px]"
                    @change="applyFilters"
                >
                    <option value="">Todas las sucursales</option>
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                </select>
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-xl pointer-events-none">arrow_drop_down</span>
            </div>
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-xl">calendar_today</span>
                <input
                    v-model="date"
                    type="date"
                    class="pl-10 pr-4 py-2.5 bg-gray-50 border border-gray-200 rounded-lg text-sm font-medium text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                    @change="applyFilters"
                />
            </div>
            <button
                v-if="branchId || date"
                class="text-sm text-gray-400 hover:text-gray-600 underline"
                @click="branchId = ''; date = ''; applyFilters()"
            >
                Limpiar filtros
            </button>
        </div>

        <!-- Kanban columns -->
        <div class="flex gap-6 overflow-x-auto pb-4">
            <div
                v-for="col in COLUMNS"
                :key="col.key"
                class="flex flex-col w-80 shrink-0"
            >
                <!-- Column header -->
                <div class="flex items-center gap-2 mb-4 px-1">
                    <div class="size-2.5 rounded-full" :class="col.dotClass"></div>
                    <h3 class="font-semibold text-gray-700">{{ col.label }}</h3>
                    <span class="bg-gray-200 text-gray-600 text-xs font-bold px-2 py-0.5 rounded-full">
                        {{ localOrders[col.key]?.length ?? 0 }}
                    </span>
                </div>

                <!-- Cards (drop zone) -->
                <div
                    class="flex flex-col gap-3 min-h-[80px] rounded-xl p-1 transition-all"
                    :class="dropTargetCol === col.key && isValidDropTarget(col.key) ? 'ring-2 ring-[#FF5722] ring-dashed bg-orange-50/50' : ''"
                    @dragover="onDragOverCol(col.key, $event)"
                    @dragleave="onDragLeaveCol($event)"
                    @drop.prevent="onDrop(col.key, $event)"
                >
                    <div
                        v-for="order in localOrders[col.key]"
                        :key="order.id"
                        :draggable="col.key !== 'delivered'"
                        class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-all group border-l-4 select-none"
                        :class="[
                            col.borderClass,
                            col.key === 'delivered' ? 'opacity-75 hover:opacity-100' : 'cursor-grab active:cursor-grabbing',
                            draggingOrder?.id === order.id ? 'opacity-40' : '',
                        ]"
                        @dragstart="onDragStart(order, col.key, $event)"
                        @dragend="onDragEnd"
                        @click="onCardClick(order.id)"
                    >
                        <div class="flex justify-between items-start mb-3">
                            <div class="flex items-center gap-1">
                                <span
                                    v-if="col.key !== 'delivered'"
                                    class="material-symbols-outlined text-gray-300 text-base"
                                >drag_indicator</span>
                                <span class="text-xs font-bold text-gray-400">{{ orderNumber(order.id) }}</span>
                            </div>
                            <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                {{ formatTime(order.created_at) }}
                            </span>
                        </div>
                        <h4 class="font-bold text-gray-800 mb-1" :class="col.key === 'delivered' ? 'line-through decoration-gray-400 text-gray-600' : ''">
                            {{ order.customer?.name ?? '—' }}
                        </h4>
                        <p class="text-sm text-gray-500 mb-3 line-clamp-2">{{ order.branch?.name }}</p>
                        <div class="flex items-center justify-between mt-auto">
                            <span class="font-bold text-gray-900">{{ formatPrice(order.total) }}</span>
                            <span
                                v-if="col.key === 'delivered'"
                                class="flex items-center gap-1 text-green-600 text-xs font-bold"
                            >
                                <span class="material-symbols-outlined text-sm">check_circle</span> Completado
                            </span>
                            <span
                                v-else
                                class="text-[#FF5722] text-sm font-medium flex items-center gap-1 group-hover:translate-x-1 transition-transform"
                            >
                                Ver <span class="material-symbols-outlined text-base">arrow_forward</span>
                            </span>
                        </div>
                    </div>

                    <!-- Empty state -->
                    <div
                        v-if="!localOrders[col.key]?.length"
                        class="bg-gray-50 border border-dashed border-gray-200 rounded-xl p-6 text-center text-sm text-gray-400"
                    >
                        Sin pedidos
                    </div>
                </div>
            </div>
        </div>

    </AppLayout>
</template>
