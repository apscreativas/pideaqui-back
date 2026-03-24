<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, watch } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DatePicker from '@/Components/DatePicker.vue'

const props = defineProps({
    orders: Array,
    branches: Array,
    kpis: Object,
    filters: Object,
    allBranches: Array,
    mapsKey: { type: String, default: '' },
})

// ─── Filters ──────────────────────────────────────────────────────────────────

const from = ref(props.filters.from)
const to = ref(props.filters.to)
const branchId = ref(props.filters.branch_id ?? '')
const activeStatuses = ref(
    (props.filters.statuses || 'received,preparing,on_the_way,delivered').split(','),
)

const STATUS_OPTIONS = [
    { key: 'received', label: 'Recibido', color: '#EF4444' },
    { key: 'preparing', label: 'Preparando', color: '#F97316' },
    { key: 'on_the_way', label: 'En camino', color: '#3B82F6' },
    { key: 'delivered', label: 'Entregado', color: '#22C55E' },
    { key: 'cancelled', label: 'Cancelado', color: '#9CA3AF' },
]

const STATUS_MAP = Object.fromEntries(STATUS_OPTIONS.map((s) => [s.key, s]))

const DELIVERY_LABELS = { delivery: 'A domicilio', pickup: 'Para recoger', dine_in: 'Comer aqui' }

function applyFilter() {
    router.get(
        route('map.index'),
        {
            from: from.value,
            to: to.value,
            branch_id: branchId.value || undefined,
            statuses: activeStatuses.value.join(',') || undefined,
        },
        { preserveState: true, preserveScroll: true },
    )
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

function toggleStatus(key) {
    const idx = activeStatuses.value.indexOf(key)
    if (idx >= 0) { activeStatuses.value.splice(idx, 1) } else { activeStatuses.value.push(key) }
    applyFilter()
}

// ─── Formatters ───────────────────────────────────────────────────────────────

function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}

function formatTime(dateStr) {
    return new Date(dateStr).toLocaleString('es-MX', { hour: '2-digit', minute: '2-digit' })
}

function orderNumber(id) {
    return '#' + String(id).padStart(4, '0')
}

// ─── Selection state ──────────────────────────────────────────────────────────

const selectedOrder = ref(null)
const selectedBranch = ref(null)

function closePanel() {
    selectedOrder.value = null
    selectedBranch.value = null
}

function branchOrderCount(branchId) {
    return props.orders.filter((o) => o.branch_id === branchId).length
}

// ─── Google Maps ──────────────────────────────────────────────────────────────

const mapEl = ref(null)
const mapLoaded = ref(false)
const mapError = ref(null)
let googleMap = null
let markers = []

function loadGoogleMapsScript() {
    return new Promise((resolve, reject) => {
        if (window.google?.maps) { resolve(); return }
        const key = props.mapsKey
        if (!key) { reject(new Error('Google Maps API key no configurada')); return }
        const existing = document.querySelector('#google-maps-script')
        if (existing) {
            if (window.google?.maps) { resolve(); return }
            existing.addEventListener('load', resolve)
            existing.addEventListener('error', reject)
            return
        }
        const script = document.createElement('script')
        script.id = 'google-maps-script'
        script.src = `https://maps.googleapis.com/maps/api/js?key=${key}`
        script.async = true
        script.onload = resolve
        script.onerror = () => reject(new Error('Error al cargar Google Maps'))
        document.head.appendChild(script)
    })
}

function createOrderPinUrl(color) {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="30" height="40" viewBox="0 0 30 40">
        <path d="M15 39C15 39 29 25 29 14C29 6.27 22.73 0 15 0C7.27 0 1 6.27 1 14C1 25 15 39 15 39Z"
              fill="${color}" stroke="white" stroke-width="1.5"/>
        <circle cx="15" cy="13" r="6.5" fill="white" fill-opacity="0.95"/>
    </svg>`
    return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg)
}

function createBranchPinUrl() {
    const svg = `<svg xmlns="http://www.w3.org/2000/svg" width="38" height="46" viewBox="0 0 38 46">
        <path d="M19 45C19 45 37 29 37 17C37 8.16 29.84 1 19 1C8.16 1 1 8.16 1 17C1 29 19 45 19 45Z"
              fill="#FF5722" stroke="white" stroke-width="2"/>
        <rect x="11" y="11" width="16" height="12" rx="1.5" fill="white"/>
        <path d="M11 14.5h16" stroke="#FF5722" stroke-width="1"/>
        <rect x="16" y="17" width="6" height="6" rx="0.5" fill="#FF5722" fill-opacity="0.25"
              stroke="#FF5722" stroke-width="0.5"/>
    </svg>`
    return 'data:image/svg+xml;charset=UTF-8,' + encodeURIComponent(svg)
}

function clearMarkers() {
    markers.forEach((m) => m.setMap(null))
    markers = []
}

function renderMarkers() {
    if (!googleMap || !window.google) { return }
    clearMarkers()

    const bounds = new window.google.maps.LatLngBounds()
    let hasPoints = false

    // Branch markers
    props.branches.forEach((branch) => {
        const lat = parseFloat(branch.latitude)
        const lng = parseFloat(branch.longitude)
        if (!lat || !lng) { return }

        const marker = new window.google.maps.Marker({
            position: { lat, lng },
            map: googleMap,
            icon: {
                url: createBranchPinUrl(),
                scaledSize: new window.google.maps.Size(38, 46),
                anchor: new window.google.maps.Point(19, 46),
            },
            title: branch.name,
            zIndex: 1000,
        })

        marker.addListener('click', () => {
            selectedOrder.value = null
            selectedBranch.value = branch
            googleMap.panTo({ lat, lng })
        })

        markers.push(marker)
        bounds.extend({ lat, lng })
        hasPoints = true
    })

    // Order markers
    props.orders.forEach((order) => {
        const lat = parseFloat(order.latitude)
        const lng = parseFloat(order.longitude)
        if (!lat || !lng) { return }

        const status = STATUS_MAP[order.status]
        const color = status?.color || '#9CA3AF'

        const marker = new window.google.maps.Marker({
            position: { lat, lng },
            map: googleMap,
            icon: {
                url: createOrderPinUrl(color),
                scaledSize: new window.google.maps.Size(30, 40),
                anchor: new window.google.maps.Point(15, 40),
                labelOrigin: new window.google.maps.Point(15, 14),
            },
            label: {
                text: String(order.id % 100).padStart(2, '0'),
                color: color,
                fontSize: '9px',
                fontWeight: '800',
                fontFamily: 'Inter, system-ui, sans-serif',
            },
            title: `Pedido ${orderNumber(order.id)}`,
            zIndex: order.status === 'delivered' ? 100 : order.status === 'cancelled' ? 50 : 500,
        })

        marker.addListener('click', () => {
            selectedBranch.value = null
            selectedOrder.value = order
            googleMap.panTo({ lat, lng })
        })

        markers.push(marker)
        bounds.extend({ lat, lng })
        hasPoints = true
    })

    if (hasPoints) {
        googleMap.fitBounds(bounds, { top: 100, right: 50, bottom: 60, left: 50 })
        const listener = window.google.maps.event.addListener(googleMap, 'idle', () => {
            if (googleMap.getZoom() > 16) { googleMap.setZoom(16) }
            window.google.maps.event.removeListener(listener)
        })
    }
}

async function initMap() {
    try {
        await loadGoogleMapsScript()

        googleMap = new window.google.maps.Map(mapEl.value, {
            center: { lat: 23.6345, lng: -102.5528 },
            zoom: 5,
            disableDefaultUI: true,
            zoomControl: true,
            mapTypeControl: false,
            streetViewControl: false,
            fullscreenControl: true,
            styles: [
                { featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] },
                { featureType: 'transit', elementType: 'labels', stylers: [{ visibility: 'off' }] },
            ],
        })

        googleMap.addListener('click', () => closePanel())

        mapLoaded.value = true
        renderMarkers()
    } catch (err) {
        mapError.value = err.message
    }
}

watch(
    () => [props.orders, props.branches],
    () => {
        closePanel()
        renderMarkers()
    },
    { deep: true },
)

onMounted(() => initMap())
onUnmounted(() => {
    clearMarkers()
    googleMap = null
})
</script>

<template>
    <Head title="Mapa operativo" />
    <AppLayout flush>
        <div class="relative w-full overflow-hidden" style="height: 100vh">

            <!-- Google Map (full viewport) -->
            <div ref="mapEl" class="absolute inset-0 z-0"></div>

            <!-- ═══ FLOATING CONTROL BAR ═══ -->
            <div class="absolute top-4 left-4 right-4 z-20 pointer-events-none">
                <div class="bg-white/[0.97] backdrop-blur-md rounded-2xl shadow-lg border border-white/80 pointer-events-auto px-5 py-4">

                    <!-- Row 1: Title + Date filters + Branch -->
                    <div class="flex items-center gap-3 flex-wrap">
                        <h1 class="text-base font-bold text-gray-900 shrink-0">Mapa operativo</h1>

                        <div class="h-5 w-px bg-gray-200 shrink-0"></div>

                        <!-- Date presets -->
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
                                class="px-2.5 py-1 text-xs font-medium rounded-lg transition-colors"
                                :class="activePreset === p.key
                                    ? 'bg-[#FF5722] text-white'
                                    : 'bg-gray-100 text-gray-600 hover:bg-gray-200'"
                            >{{ p.label }}</button>
                        </div>

                        <!-- Date inputs -->
                        <div class="flex items-center gap-1">
                            <DatePicker v-model="from" @change="applyFilter" placeholder="Desde" size="sm" />
                            <span class="text-gray-300 text-xs">&mdash;</span>
                            <DatePicker v-model="to" @change="applyFilter" placeholder="Hasta" size="sm" />
                        </div>

                        <!-- Branch selector -->
                        <select
                            v-model="branchId"
                            @change="applyFilter"
                            class="text-xs border border-gray-200 rounded-lg px-2 py-1 text-gray-700 focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                        >
                            <option value="">Todas las sucursales</option>
                            <option v-for="b in allBranches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>

                    <!-- Row 2: Status pills + Compact KPIs -->
                    <div class="flex items-center justify-between gap-3 mt-3 pt-3 border-t border-gray-100/80 flex-wrap">
                        <!-- Status toggle pills -->
                        <div class="flex items-center gap-1.5 flex-wrap">
                            <button
                                v-for="s in STATUS_OPTIONS"
                                :key="s.key"
                                @click="toggleStatus(s.key)"
                                class="flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium transition-all border"
                                :class="activeStatuses.includes(s.key)
                                    ? 'border-transparent text-white shadow-sm'
                                    : 'border-gray-200 text-gray-400 hover:border-gray-300 hover:text-gray-500'"
                                :style="activeStatuses.includes(s.key) ? { backgroundColor: s.color } : {}"
                            >
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :style="{ backgroundColor: activeStatuses.includes(s.key) ? '#fff' : s.color }"
                                ></span>
                                {{ s.label }}
                            </button>
                        </div>

                        <!-- Compact KPIs -->
                        <div class="flex items-center gap-4 text-xs text-gray-500 shrink-0 flex-wrap">
                            <span><strong class="text-gray-900 text-sm">{{ kpis.total }}</strong> pedidos</span>
                            <span><strong class="text-orange-600 text-sm">{{ kpis.active }}</strong> activos</span>
                            <span><strong class="text-green-600 text-sm">{{ kpis.delivered }}</strong> entregados</span>
                            <span class="font-semibold text-gray-900 text-sm">{{ formatPrice(kpis.revenue) }}</span>
                        </div>
                    </div>

                </div>
            </div>

            <!-- ═══ SELECTED ORDER PANEL ═══ -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-x-4"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-4"
            >
                <div v-if="selectedOrder" class="absolute top-36 right-4 z-20 w-80">
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <!-- Header -->
                        <div class="px-5 py-4 border-b border-gray-100 flex items-center justify-between">
                            <div class="flex items-center gap-2.5">
                                <span class="text-lg font-bold text-gray-900">{{ orderNumber(selectedOrder.id) }}</span>
                                <span
                                    class="px-2 py-0.5 rounded-full text-[10px] font-semibold uppercase tracking-wide text-white"
                                    :style="{ backgroundColor: STATUS_MAP[selectedOrder.status]?.color }"
                                >{{ STATUS_MAP[selectedOrder.status]?.label }}</span>
                            </div>
                            <button
                                @click="closePanel"
                                class="text-gray-400 hover:text-gray-600 transition-colors p-1 -m-1 rounded-lg hover:bg-gray-100"
                            >
                                <span class="material-symbols-outlined text-lg">close</span>
                            </button>
                        </div>

                        <!-- Body -->
                        <div class="px-5 py-4 space-y-3">
                            <!-- Customer -->
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-gray-100 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-gray-500 text-lg">person</span>
                                </div>
                                <div>
                                    <p class="text-sm font-medium text-gray-900">{{ selectedOrder.customer?.name || '—' }}</p>
                                    <p class="text-xs text-gray-400">Cliente</p>
                                </div>
                            </div>

                            <!-- Info grid -->
                            <div class="grid grid-cols-2 gap-2.5">
                                <div class="bg-gray-50 rounded-xl px-3 py-2.5">
                                    <p class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Sucursal</p>
                                    <p class="text-sm font-semibold text-gray-900 truncate">{{ selectedOrder.branch?.name || '—' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl px-3 py-2.5">
                                    <p class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Hora</p>
                                    <p class="text-sm font-semibold text-gray-900">{{ formatTime(selectedOrder.created_at) }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl px-3 py-2.5">
                                    <p class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Tipo</p>
                                    <p class="text-sm font-semibold text-gray-900">{{ DELIVERY_LABELS[selectedOrder.delivery_type] || selectedOrder.delivery_type }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-xl px-3 py-2.5">
                                    <p class="text-[10px] font-medium text-gray-400 uppercase tracking-wide mb-0.5">Total</p>
                                    <p class="text-sm font-bold text-gray-900">{{ formatPrice(selectedOrder.total) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- Footer -->
                        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100">
                            <Link
                                :href="route('orders.show', selectedOrder.id)"
                                class="flex items-center justify-center gap-1.5 w-full px-4 py-2 bg-[#FF5722] text-white text-sm font-semibold rounded-xl hover:bg-[#D84315] transition-colors"
                            >
                                Ver detalle completo
                                <span class="material-symbols-outlined text-sm">arrow_forward</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ═══ SELECTED BRANCH PANEL ═══ -->
            <Transition
                enter-active-class="transition-all duration-300 ease-out"
                enter-from-class="opacity-0 translate-x-4"
                enter-to-class="opacity-100 translate-x-0"
                leave-active-class="transition-all duration-200 ease-in"
                leave-from-class="opacity-100 translate-x-0"
                leave-to-class="opacity-0 translate-x-4"
            >
                <div v-if="selectedBranch" class="absolute top-36 right-4 z-20 w-72">
                    <div class="bg-white rounded-2xl shadow-xl border border-gray-100 overflow-hidden">
                        <div class="px-5 py-4 flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-[#FF5722]/10 flex items-center justify-center shrink-0">
                                    <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">store</span>
                                </div>
                                <div>
                                    <p class="text-base font-bold text-gray-900">{{ selectedBranch.name }}</p>
                                    <p class="text-xs text-gray-400">Sucursal</p>
                                </div>
                            </div>
                            <button
                                @click="closePanel"
                                class="text-gray-400 hover:text-gray-600 transition-colors p-1 -m-1 rounded-lg hover:bg-gray-100"
                            >
                                <span class="material-symbols-outlined text-lg">close</span>
                            </button>
                        </div>
                        <div class="px-5 py-3 bg-gray-50 border-t border-gray-100 flex items-center justify-between">
                            <span class="text-xs text-gray-500">Pedidos en mapa</span>
                            <span class="text-sm font-bold text-gray-900">{{ branchOrderCount(selectedBranch.id) }}</span>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- ═══ LEGEND (bottom-left) ═══ -->
            <div class="absolute bottom-5 left-4 z-20">
                <div class="bg-white/[0.95] backdrop-blur-sm rounded-xl shadow-md border border-white/80 px-4 py-2.5">
                    <div class="flex items-center gap-3 flex-wrap">
                        <div v-for="s in STATUS_OPTIONS" :key="'l-' + s.key" class="flex items-center gap-1.5">
                            <span class="w-2.5 h-2.5 rounded-full shrink-0" :style="{ backgroundColor: s.color }"></span>
                            <span class="text-[11px] text-gray-500">{{ s.label }}</span>
                        </div>
                        <div class="w-px h-3.5 bg-gray-200"></div>
                        <div class="flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[#FF5722] text-sm" style="font-variation-settings:'FILL' 1">store</span>
                            <span class="text-[11px] text-gray-500">Sucursal</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- ═══ GEO COUNT BADGE (bottom-right) ═══ -->
            <div v-if="mapLoaded && kpis.geolocated !== kpis.total" class="absolute bottom-5 right-4 z-20">
                <div class="bg-white/[0.95] backdrop-blur-sm rounded-lg shadow-md border border-white/80 px-3 py-2">
                    <p class="text-[11px] text-gray-500">
                        <strong class="text-gray-700">{{ kpis.geolocated }}</strong> de {{ kpis.total }} con ubicacion
                    </p>
                </div>
            </div>

            <!-- ═══ LOADING STATE ═══ -->
            <div v-if="!mapLoaded && !mapError" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-gray-100">
                <div class="w-10 h-10 rounded-full border-4 border-gray-200 border-t-[#FF5722] animate-spin mb-4"></div>
                <p class="text-sm text-gray-400 font-medium">Cargando mapa...</p>
            </div>

            <!-- ═══ ERROR STATE ═══ -->
            <div v-if="mapError" class="absolute inset-0 z-10 flex flex-col items-center justify-center bg-gray-50">
                <span class="material-symbols-outlined text-gray-300 text-6xl mb-4">map</span>
                <p class="text-base text-gray-500 font-medium mb-1">Mapa no disponible</p>
                <p class="text-sm text-gray-400">{{ mapError }}</p>
            </div>

            <!-- ═══ EMPTY STATE ═══ -->
            <div
                v-if="mapLoaded && orders.length === 0"
                class="absolute inset-0 z-10 pointer-events-none flex items-center justify-center"
            >
                <div class="bg-white/90 backdrop-blur-sm rounded-2xl shadow-lg border border-gray-200/50 px-8 py-6 text-center pointer-events-auto">
                    <span class="material-symbols-outlined text-gray-300 text-5xl mb-3">location_off</span>
                    <p class="text-base font-semibold text-gray-700 mb-1">No hay pedidos geolocalizados</p>
                    <p class="text-sm text-gray-400">Solo los pedidos de delivery tienen ubicacion en el mapa</p>
                </div>
            </div>

        </div>
    </AppLayout>
</template>
