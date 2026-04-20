<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DatePicker from '@/Components/DatePicker.vue'

const props = defineProps({
    orders: Object,
    branches: Array,
    filters: Object,
    monthly_count: Number,
    orders_limit: Number,
    limit_reason: { type: String, default: null },
    limit_period: { type: Object, default: () => ({ start: null, end: null }) },
})

function formatPeriodDate(s) {
    if (!s) { return '' }
    return new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(s + 'T12:00:00'))
}

const pageInstance = usePage()
const billing = computed(() => pageInstance.props.billing)
const canOperate = computed(() => billing.value?.can_operate !== false)
const blockMessage = computed(() => billing.value?.block_message ?? '')
const createDisabled = computed(() => props.limit_reason !== null || !canOperate.value)

const newOrderTitle = computed(() => {
    if (!canOperate.value) { return blockMessage.value }
    if (props.limit_reason === 'limit_reached') { return `Has alcanzado el límite del periodo (${props.monthly_count}/${props.orders_limit})` }
    if (props.limit_reason === 'period_expired') { return `El periodo terminó el ${formatPeriodDate(props.limit_period?.end)}` }
    if (props.limit_reason === 'period_not_started') { return `El periodo inicia el ${formatPeriodDate(props.limit_period?.start)}` }
    return 'Crear pedido manual'
})

// --- Optimistic UI: local copy of orders ---
const localOrders = ref(JSON.parse(JSON.stringify(props.orders)))

watch(() => props.orders, (fresh) => {
    localOrders.value = JSON.parse(JSON.stringify(fresh))
}, { deep: true })

// --- Filters ---
const branchId = ref(props.filters?.branch_id ?? '')
const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')
const requiresInvoice = ref(props.filters?.requires_invoice ?? false)
const showCustomRange = ref(false)

function localDateStr(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const today = localDateStr(new Date())

function applyFilters() {
    router.get(route('orders.index'), {
        branch_id: branchId.value || undefined,
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        requires_invoice: requiresInvoice.value || undefined,
    }, {
        preserveState: true,
        replace: true,
    })
}

function setPreset(preset) {
    showCustomRange.value = false
    const now = new Date()
    if (preset === 'today') {
        dateFrom.value = today
        dateTo.value = today
    } else if (preset === 'yesterday') {
        const y = new Date(now)
        y.setDate(y.getDate() - 1)
        dateFrom.value = localDateStr(y)
        dateTo.value = localDateStr(y)
    } else if (preset === 'week') {
        const w = new Date(now)
        w.setDate(w.getDate() - 6)
        dateFrom.value = localDateStr(w)
        dateTo.value = today
    } else if (preset === 'month') {
        dateFrom.value = localDateStr(new Date(now.getFullYear(), now.getMonth(), 1))
        dateTo.value = today
    } else if (preset === 'all') {
        dateFrom.value = ''
        dateTo.value = ''
    }
    applyFilters()
}

const activePreset = computed(() => {
    const from = dateFrom.value
    const to = dateTo.value
    if (!from && !to) { return 'all' }
    if (from === today && to === today) { return 'today' }
    const y = new Date()
    y.setDate(y.getDate() - 1)
    if (from === localDateStr(y) && to === localDateStr(y)) { return 'yesterday' }
    const w = new Date()
    w.setDate(w.getDate() - 6)
    if (from === localDateStr(w) && to === today) { return 'week' }
    const ms = new Date()
    if (from === localDateStr(new Date(ms.getFullYear(), ms.getMonth(), 1)) && to === today) { return 'month' }
    return 'custom'
})

function formatDisplayDate(dateStr) {
    if (!dateStr) { return '' }
    return new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short' }).format(new Date(dateStr + 'T12:00:00'))
}

const dateRangeLabel = computed(() => {
    if (!dateFrom.value && !dateTo.value) { return 'Todas las fechas' }
    if (dateFrom.value === dateTo.value) { return formatDisplayDate(dateFrom.value) }
    return `${formatDisplayDate(dateFrom.value)} – ${formatDisplayDate(dateTo.value)}`
})

// --- Helpers ---
function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}

function formatTime(dateStr) {
    return new Intl.DateTimeFormat('es-MX', { hour: '2-digit', minute: '2-digit' }).format(new Date(dateStr))
}

function orderNumber(id) {
    return '#' + String(id).padStart(4, '0')
}

const monthlyPercent = computed(() =>
    Math.min(100, Math.round((props.monthly_count / props.orders_limit) * 100)),
)

const DELIVERY_ICONS = {
    delivery: 'two_wheeler',
    pickup: 'store',
    dine_in: 'restaurant',
}

const PAYMENT_ICONS = {
    cash: 'payments',
    terminal: 'credit_card',
    transfer: 'account_balance',
}

function formatScheduledTime(dateStr) {
    if (!dateStr) { return null }
    return new Intl.DateTimeFormat('es-MX', { hour: 'numeric', minute: '2-digit', hour12: true }).format(new Date(dateStr))
}

const COLUMNS = [
    { key: 'received',   label: 'Recibido',        dotClass: 'bg-orange-500', borderClass: 'border-l-orange-500' },
    { key: 'preparing',  label: 'En preparación',   dotClass: 'bg-amber-400',  borderClass: 'border-l-amber-400' },
    { key: 'on_the_way', label: 'En camino',        dotClass: 'bg-blue-500',   borderClass: 'border-l-blue-500' },
    { key: 'delivered',  label: 'Entregado',        dotClass: 'bg-green-500',  borderClass: 'border-l-green-500' },
]

const NEXT_STATUS = { received: 'preparing', preparing: 'on_the_way', on_the_way: 'delivered' }

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
    // Reset after the current event cycle so the click fired by the browser
    // at the end of the drag sequence is still blocked (preventing accidental
    // navigation), but all subsequent clicks work normally.
    setTimeout(() => { didDrag = false }, 0)
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

    const fromList = localOrders.value[fromCol]
    const idx = fromList.findIndex((o) => o.id === order.id)
    if (idx === -1) { return }
    fromList.splice(idx, 1)
    localOrders.value[targetColKey].unshift(order)

    didDrag = true

    router.put(route('orders.advance-status', order.id), {}, {
        preserveScroll: true,
        onError() {
            localOrders.value = JSON.parse(JSON.stringify(props.orders))
        },
    })
}

function onCardClick(orderId) {
    if (!didDrag) {
        router.visit(route('orders.show', orderId))
    }
}

// --- Real-time via Reverb/Echo ---
const restaurantId = usePage().props.auth.user?.restaurant_id

function removeOrderFromColumns(orderId) {
    for (const key of Object.keys(localOrders.value)) {
        const list = localOrders.value[key]
        const idx = list.findIndex((o) => o.id === orderId)
        if (idx !== -1) {
            list.splice(idx, 1)
            return key
        }
    }
    return null
}

function isInCurrentDateRange(createdAt) {
    if (!dateFrom.value && !dateTo.value) { return true }
    const d = createdAt.slice(0, 10)
    if (dateFrom.value && d < dateFrom.value) { return false }
    if (dateTo.value && d > dateTo.value) { return false }
    return true
}

let echoChannel = null

// --- Sound alert on new orders ---
const SOUND_PREF_KEY = 'orders_sound_enabled'
const soundEnabled = ref(false)

function playNewOrderSound() {
    const AudioContextClass = window.AudioContext || window.webkitAudioContext
    if (!AudioContextClass) { return }
    try {
        const ctx = new AudioContextClass()
        // "Campana de restaurante": dos osciladores a intervalo de quinta
        // producen timbre metálico tipo bell. Filtro low-pass le quita aspereza.
        const strike = (startOffset) => {
            const t0 = ctx.currentTime + startOffset
            const master = ctx.createGain()
            const filter = ctx.createBiquadFilter()
            filter.type = 'lowpass'
            filter.frequency.value = 3200
            filter.Q.value = 0.8
            master.connect(filter)
            filter.connect(ctx.destination)
            master.gain.setValueAtTime(0.0001, t0)
            master.gain.exponentialRampToValueAtTime(0.7, t0 + 0.008)
            master.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.75)

            const makeOsc = (freq, type, amp) => {
                const osc = ctx.createOscillator()
                const g = ctx.createGain()
                osc.type = type
                osc.frequency.value = freq
                g.gain.value = amp
                osc.connect(g)
                g.connect(master)
                osc.start(t0)
                osc.stop(t0 + 0.8)
            }
            makeOsc(988, 'triangle', 1.0)   // B5 fundamental
            makeOsc(1480, 'sine', 0.55)     // F#6 (quinta) → armonía tipo campana
            makeOsc(2960, 'sine', 0.15)     // armonía alta breve para "golpe" inicial
        }
        strike(0)
        strike(0.35)
        setTimeout(() => ctx.close().catch(() => {}), 1500)
    } catch (_) {
        // audio not available; silent fallback
    }
}

function toggleSound() {
    soundEnabled.value = !soundEnabled.value
    try { localStorage.setItem(SOUND_PREF_KEY, soundEnabled.value ? '1' : '0') } catch (_) {}
    if (soundEnabled.value) {
        // Play once on activation — also unlocks browser autoplay policy.
        playNewOrderSound()
    }
}

// Branch IDs this user can see (from server-filtered branches prop).
const allowedBranchIdSet = new Set(props.branches.map((b) => b.id))

function isAllowedBranch(order) {
    // If user is NOT admin and has branch restrictions, filter.
    if (usePage().props.auth.user?.is_admin === false && allowedBranchIdSet.size > 0) {
        return allowedBranchIdSet.has(order.branch?.id ?? order.branch_id)
    }
    return true
}

function isVisibleEvent(order) {
    if (!isInCurrentDateRange(order.created_at)) { return false }
    if (branchId.value && (order.branch?.id ?? order.branch_id) !== Number(branchId.value)) { return false }
    if (!isAllowedBranch(order)) { return false }
    return true
}

onMounted(() => {
    try { soundEnabled.value = localStorage.getItem(SOUND_PREF_KEY) === '1' } catch (_) {}

    const echo = window.getEcho?.()
    if (!restaurantId || !echo) { return }

    echoChannel = echo.private(`restaurant.${restaurantId}`)
        .listen('OrderCreated', (e) => {
            if (!isVisibleEvent(e.order)) { return }
            const exists = localOrders.value.received?.some((o) => o.id === e.order.id)
            if (!exists) {
                localOrders.value.received.unshift(e.order)
                if (soundEnabled.value) { playNewOrderSound() }
            }
        })
        .listen('OrderStatusChanged', (e) => {
            if (!isAllowedBranch(e.order)) { return }
            removeOrderFromColumns(e.order.id)
            const col = e.order.status
            if (localOrders.value[col]) {
                localOrders.value[col].unshift(e.order)
            }
        })
        .listen('OrderCancelled', (e) => {
            if (!isAllowedBranch(e.order)) { return }
            removeOrderFromColumns(e.order.id)
        })
        .listen('OrderUpdated', (e) => {
            if (!isAllowedBranch(e.order)) { return }
            // Update the order data in place within its current column
            for (const key of Object.keys(localOrders.value)) {
                const list = localOrders.value[key]
                const idx = list.findIndex((o) => o.id === e.order.id)
                if (idx !== -1) {
                    Object.assign(list[idx], e.order)
                    break
                }
            }
        })
})

onUnmounted(() => {
    if (echoChannel) {
        window.getEcho?.()?.leave(`restaurant.${restaurantId}`)
        echoChannel = null
    }
})
</script>

<template>
    <Head title="Pedidos" />
    <AppLayout title="Pedidos">
      <div class="flex flex-col h-[calc(100vh-4rem)]">

        <!-- Operational gate banner -->
        <div v-if="!canOperate" class="mb-4 p-4 rounded-xl border border-red-200 bg-red-50 flex items-start gap-3 shrink-0">
            <span class="material-symbols-outlined text-red-600">block</span>
            <div class="flex-1">
                <p class="text-sm font-semibold text-red-900">{{ blockMessage }}</p>
                <p class="text-xs text-red-700 mt-1">Los pedidos en curso se pueden gestionar normalmente. No se aceptarán pedidos nuevos.</p>
            </div>
            <a :href="route('settings.subscription')" class="text-sm font-bold text-red-700 hover:underline whitespace-nowrap">
                Ir a mi plan
            </a>
        </div>

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-4 shrink-0">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Tablero de Pedidos</h1>
                <p class="mt-1 text-sm text-gray-500">Gestiona tus pedidos arrastrando las tarjetas entre columnas.</p>
            </div>

            <div class="flex items-center gap-3">
                <!-- Sound alert toggle -->
                <button
                    type="button"
                    @click="toggleSound"
                    class="flex items-center gap-2 px-4 py-3 rounded-xl text-sm font-semibold border transition-colors"
                    :class="soundEnabled
                        ? 'bg-green-50 border-green-200 text-green-700 hover:bg-green-100'
                        : 'bg-white border-gray-200 text-gray-500 hover:bg-gray-50'"
                    :title="soundEnabled ? 'Sonido activado — click para silenciar' : 'Sonido desactivado — click para activar'"
                >
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">{{ soundEnabled ? 'notifications_active' : 'notifications_off' }}</span>
                    {{ soundEnabled ? 'Sonido ON' : 'Sonido OFF' }}
                </button>

                <!-- New manual order button -->
                <button
                    type="button"
                    :disabled="createDisabled"
                    @click="router.visit(route('orders.create'))"
                    class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-5 py-3 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    :title="newOrderTitle"
                >
                    <span class="material-symbols-outlined text-lg" aria-hidden="true">add_shopping_cart</span>
                    Nuevo pedido
                </button>

                <!-- Monthly usage -->
                <div class="flex items-center gap-4 bg-white rounded-xl border border-gray-100 shadow-sm px-5 py-3 min-w-[280px]">
                    <div class="flex-1 min-w-0">
                        <div class="flex justify-between items-center mb-1.5">
                            <span class="text-sm font-semibold text-gray-700">Pedidos del periodo</span>
                            <span class="text-sm text-[#FF5722] font-bold">{{ monthly_count }}/{{ orders_limit }}</span>
                        </div>
                        <div class="h-2 w-full bg-gray-100 rounded-full overflow-hidden">
                            <div
                                class="h-full bg-[#FF5722] rounded-full transition-[width] duration-300"
                                :style="{ width: monthlyPercent + '%' }"
                            ></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-3 mb-4 shrink-0">
            <!-- Branch selector -->
            <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-xl" aria-hidden="true">store</span>
                <select
                    v-model="branchId"
                    aria-label="Filtrar por sucursal"
                    name="branch_id"
                    class="pl-10 pr-8 py-2 bg-white border border-gray-200 rounded-xl text-sm font-medium text-gray-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 appearance-none min-w-[200px]"
                    @change="applyFilters"
                >
                    <option value="">Todas las sucursales</option>
                    <option v-for="branch in branches" :key="branch.id" :value="branch.id">{{ branch.name }}</option>
                </select>
                <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-xl pointer-events-none" aria-hidden="true">arrow_drop_down</span>
            </div>

            <!-- Invoice filter -->
            <button
                @click="requiresInvoice = !requiresInvoice; applyFilters()"
                class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-sm font-semibold border transition-colors"
                :class="requiresInvoice
                    ? 'bg-amber-50 border-amber-300 text-amber-700'
                    : 'bg-white border-gray-200 text-gray-600 hover:bg-gray-50'"
            >
                <span class="material-symbols-outlined text-base" aria-hidden="true">receipt_long</span>
                Factura
            </button>

            <!-- Date presets -->
            <div class="flex items-center gap-1.5 bg-white border border-gray-200 rounded-xl p-1">
                <button
                    v-for="p in [
                        { key: 'today', label: 'Hoy', icon: 'today' },
                        { key: 'yesterday', label: 'Ayer', icon: null },
                        { key: 'week', label: '7 d\u00EDas', icon: null },
                        { key: 'month', label: 'Mes', icon: null },
                        { key: 'all', label: 'Todo', icon: null },
                    ]"
                    :key="p.key"
                    @click="setPreset(p.key)"
                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                    :class="activePreset === p.key
                        ? 'bg-[#FF5722] text-white shadow-sm'
                        : 'text-gray-600 hover:bg-gray-50'"
                >
                    <span v-if="p.icon" class="material-symbols-outlined text-sm" aria-hidden="true">{{ p.icon }}</span>
                    {{ p.label }}
                </button>
                <button
                    @click="showCustomRange = !showCustomRange"
                    class="flex items-center gap-1 px-3 py-1.5 rounded-lg text-xs font-semibold transition-colors"
                    :class="activePreset === 'custom'
                        ? 'bg-[#FF5722] text-white shadow-sm'
                        : showCustomRange
                            ? 'bg-gray-100 text-gray-700'
                            : 'text-gray-600 hover:bg-gray-50'"
                    aria-label="Seleccionar rango personalizado"
                >
                    <span class="material-symbols-outlined text-sm" aria-hidden="true">date_range</span>
                    Rango
                </button>
            </div>

            <!-- Date range label -->
            <span class="text-sm text-gray-500 font-medium hidden sm:inline">
                <span class="material-symbols-outlined text-base align-middle mr-0.5" aria-hidden="true">calendar_today</span>
                {{ dateRangeLabel }}
            </span>
        </div>

        <!-- Custom date range (collapsible) -->
        <div
            v-if="showCustomRange"
            class="flex flex-wrap items-center gap-3 mb-4 shrink-0 bg-white border border-gray-200 rounded-xl p-3"
        >
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Desde</label>
            <DatePicker v-model="dateFrom" @change="applyFilters" placeholder="Desde" size="sm" />
            <label class="text-xs font-semibold text-gray-500 uppercase tracking-wide">Hasta</label>
            <DatePicker v-model="dateTo" @change="applyFilters" placeholder="Hasta" size="sm" />
            <button
                class="text-xs text-[#FF5722] font-semibold hover:underline"
                @click="dateFrom = ''; dateTo = ''; applyFilters()"
            >
                Limpiar
            </button>
        </div>

        <!-- Kanban columns -->
        <div class="flex gap-4 flex-1 min-h-0 overflow-hidden">
            <div
                v-for="col in COLUMNS"
                :key="col.key"
                class="flex flex-col flex-1 min-w-0"
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
                    class="flex flex-col gap-3 flex-1 min-h-0 overflow-y-auto rounded-xl p-1 transition-colors"
                    :class="dropTargetCol === col.key && isValidDropTarget(col.key) ? 'ring-2 ring-[#FF5722] ring-dashed bg-orange-50/50' : ''"
                    @dragover="onDragOverCol(col.key, $event)"
                    @dragleave="onDragLeaveCol($event)"
                    @drop.prevent="onDrop(col.key, $event)"
                >
                    <div
                        v-for="order in localOrders[col.key]"
                        :key="order.id"
                        :draggable="col.key !== 'delivered'"
                        class="bg-white p-4 rounded-xl border border-gray-200 shadow-sm hover:shadow-md transition-shadow group border-l-4 select-none touch-manipulation"
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
                                    aria-hidden="true"
                                >drag_indicator</span>
                                <span class="text-xs font-bold text-gray-400">{{ orderNumber(order.id) }}</span>
                            </div>
                            <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2 py-1 rounded">
                                {{ formatTime(order.created_at) }}
                            </span>
                        </div>
                        <h4 class="font-bold text-gray-800 mb-1 truncate" :class="col.key === 'delivered' ? 'line-through decoration-gray-400 text-gray-600' : ''">
                            {{ order.customer?.name ?? '\u2014' }}
                        </h4>
                        <div class="flex items-center gap-1.5 text-sm text-gray-500 mb-3">
                            <span class="material-symbols-outlined text-base" aria-hidden="true">{{ DELIVERY_ICONS[order.delivery_type] }}</span>
                            <span class="truncate">{{ order.branch?.name }}</span>
                            <span v-if="order.requires_invoice" class="material-symbols-outlined text-base text-amber-500" aria-hidden="true" title="Requiere factura">receipt_long</span>
                            <span class="material-symbols-outlined text-base ml-auto" aria-hidden="true" :title="order.payment_method">{{ PAYMENT_ICONS[order.payment_method] ?? 'help' }}</span>
                        </div>
                        <div class="flex items-center justify-between mt-auto">
                            <div class="flex items-center gap-2">
                                <span class="font-bold text-gray-900">{{ formatPrice(order.total) }}</span>
                                <span v-if="order.edit_count > 0" class="text-xs font-semibold text-amber-600 bg-amber-50 border border-amber-200 px-1.5 py-0.5 rounded">editado</span>
                                <span v-if="order.scheduled_at" class="flex items-center gap-0.5 text-xs text-indigo-600 font-medium">
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">schedule</span>
                                    {{ formatScheduledTime(order.scheduled_at) }}
                                </span>
                            </div>
                            <span
                                v-if="col.key === 'delivered'"
                                class="flex items-center gap-1 text-green-600 text-xs font-bold"
                            >
                                <span class="material-symbols-outlined text-sm" aria-hidden="true">check_circle</span> Completado
                            </span>
                            <span
                                v-else
                                class="text-[#FF5722] text-sm font-medium flex items-center gap-1 group-hover:translate-x-1 motion-reduce:transform-none transition-transform"
                            >
                                Ver <span class="material-symbols-outlined text-base" aria-hidden="true">arrow_forward</span>
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

      </div>
    </AppLayout>
</template>
