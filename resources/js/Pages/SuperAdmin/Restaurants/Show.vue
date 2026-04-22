<script setup>
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import SlugInput from '@/Components/SlugInput.vue'
import QrCode from '@/Components/QrCode.vue'

const props = defineProps({
    restaurant: Object,
    admin: Object,
    orders_count: Number,
    orders_limit: Number,
    branch_count: Number,
    max_branches: Number,
    plans: Array,
})

const page = usePage()
const menuBaseUrl = computed(() => {
    const v = page.props.menu_base_url ?? ''
    return String(v).replace(/\/$/, '')
})
const publicMenuUrl = computed(() => `${menuBaseUrl.value}/r/${props.restaurant.slug}`)
const urlPrefix = computed(() => `${menuBaseUrl.value}/r/`)

const showSlugRename = ref(false)
const slugAvailable = ref(false)
const qrRef = ref(null)

const slugForm = useForm({
    slug: props.restaurant.slug,
    confirm: false,
})

function copyPublicUrl() {
    navigator.clipboard.writeText(publicMenuUrl.value)
}

function downloadQr() {
    qrRef.value?.download()
}

function submitSlugRename() {
    slugForm.patch(route('super.restaurants.rename-slug', props.restaurant.id), {
        preserveScroll: true,
        onSuccess: () => {
            showSlugRename.value = false
            slugForm.confirm = false
        },
    })
}

const showResetPasswordModal = ref(false)
const editingLimits = ref(false)
const showGraceModal = ref(false)
const showSwitchManualModal = ref(false)

const limitsForm = useForm({
    orders_limit: props.restaurant.orders_limit,
    max_branches: props.restaurant.max_branches,
    orders_limit_start: props.restaurant.orders_limit_start?.split('T')[0] ?? '',
    orders_limit_end: props.restaurant.orders_limit_end?.split('T')[0] ?? '',
})

const graceForm = useForm({ days: 14 })

const isManual = computed(() => props.restaurant.billing_mode === 'manual')
const isSubscription = computed(() => props.restaurant.billing_mode === 'subscription')

const ordersPercent = computed(() => {
    if (!props.orders_limit) return 0
    return Math.min(100, Math.round((props.orders_count / props.orders_limit) * 100))
})

const branchesPercent = computed(() => {
    if (!props.max_branches) return 0
    return Math.min(100, Math.round((props.branch_count / props.max_branches) * 100))
})

const statusMeta = computed(() => {
    const map = {
        active: { label: 'Activo', dot: 'bg-green-500', text: 'text-green-700', bg: 'bg-green-50', border: 'border-green-200' },
        grace_period: { label: 'Periodo de gracia', dot: 'bg-orange-500', text: 'text-orange-700', bg: 'bg-orange-50', border: 'border-orange-200' },
        past_due: { label: 'Pago vencido', dot: 'bg-yellow-500', text: 'text-yellow-700', bg: 'bg-yellow-50', border: 'border-yellow-200' },
        suspended: { label: 'Suspendido', dot: 'bg-red-500', text: 'text-red-700', bg: 'bg-red-50', border: 'border-red-200' },
        canceled: { label: 'Cancelado', dot: 'bg-gray-400', text: 'text-gray-600', bg: 'bg-gray-50', border: 'border-gray-200' },
        incomplete: { label: 'Incompleto', dot: 'bg-blue-500', text: 'text-blue-700', bg: 'bg-blue-50', border: 'border-blue-200' },
        disabled: { label: 'Deshabilitado', dot: 'bg-gray-400', text: 'text-gray-500', bg: 'bg-gray-100', border: 'border-gray-200' },
    }
    return map[props.restaurant.status] ?? { label: props.restaurant.status, dot: 'bg-gray-400', text: 'text-gray-600', bg: 'bg-gray-50', border: 'border-gray-200' }
})

const signupSourceLabel = computed(() => {
    if (props.restaurant.signup_source === 'self_signup') return 'Auto-registro'
    if (props.restaurant.signup_source === 'super_admin') return 'SuperAdmin'
    return '—'
})

const graceDaysLeft = computed(() => {
    if (!props.restaurant.grace_period_ends_at) return null
    const end = new Date(props.restaurant.grace_period_ends_at)
    const now = new Date()
    const diff = Math.ceil((end - now) / (1000 * 60 * 60 * 24))
    return Math.max(0, diff)
})

const graceUrgency = computed(() => {
    const d = graceDaysLeft.value
    if (d === null) return null
    if (d <= 1) return 'critical'
    if (d <= 3) return 'high'
    return 'normal'
})

function formatDate(dateStr) {
    if (!dateStr) { return '—' }
    // Handle both ISO datetime ("2026-03-01T00:00:00.000000Z") and date-only ("2026-03-01")
    const date = new Date(dateStr)
    if (isNaN(date.getTime())) { return '—' }
    return date.toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric', timeZone: 'UTC' })
}

function barClass(percent) {
    if (percent > 90) { return 'bg-red-500' }
    if (percent > 70) { return 'bg-amber-400' }
    return 'bg-green-500'
}

function toggleActive() {
    router.patch(route('super.restaurants.toggle', props.restaurant.id))
}

function saveLimits() {
    limitsForm.put(route('super.restaurants.update-limits', props.restaurant.id), {
        onSuccess: () => { editingLimits.value = false; showSwitchManualModal.value = false },
    })
}

function startGrace() {
    graceForm.post(route('super.restaurants.start-grace', props.restaurant.id), {
        onSuccess: () => { showGraceModal.value = false },
    })
}

const passwordForm = useForm({
    password: '',
    password_confirmation: '',
})

function resetAdminPassword() {
    passwordForm.put(route('super.restaurants.reset-password', props.restaurant.id), {
        onSuccess: () => {
            showResetPasswordModal.value = false
            passwordForm.reset()
        },
    })
}

function sendVerification() {
    if (!confirm('¿Enviar correo de verificación al administrador?')) return
    router.post(route('super.restaurants.send-verification', props.restaurant.id), {}, {
        preserveScroll: true,
    })
}
</script>

<template>
    <Head :title="`SuperAdmin — ${restaurant.name}`" />
    <SuperAdminLayout>
        <!-- ═══ HERO ═══ -->
        <div class="mb-6">
            <!-- Breadcrumb -->
            <div class="flex items-center gap-2 text-xs text-gray-400 mb-3">
                <Link :href="route('super.restaurants.index')" class="hover:text-gray-700 transition-colors">Restaurantes</Link>
                <span class="material-symbols-outlined text-sm">chevron_right</span>
                <span class="text-gray-600 font-medium truncate max-w-[300px]">{{ restaurant.name }}</span>
            </div>

            <div class="flex items-start justify-between gap-4 flex-wrap">
                <!-- Title + inline metadata -->
                <div class="min-w-0 flex-1">
                    <div class="flex items-center gap-3 flex-wrap mb-2">
                        <h1 class="text-3xl font-bold text-gray-900 tracking-tight">{{ restaurant.name }}</h1>
                        <!-- Status badge (live dot) -->
                        <span
                            class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-semibold border"
                            :class="[statusMeta.bg, statusMeta.text, statusMeta.border]"
                        >
                            <span class="w-1.5 h-1.5 rounded-full" :class="statusMeta.dot"></span>
                            {{ statusMeta.label }}
                        </span>
                    </div>

                    <!-- Inline pills: slug + mode + plan + origen + creado -->
                    <div class="flex items-center gap-x-4 gap-y-2 flex-wrap text-sm text-gray-500">
                        <span class="inline-flex items-center gap-1.5 font-mono text-xs bg-gray-100 text-gray-700 px-2 py-1 rounded-md">
                            <span class="material-symbols-outlined text-sm">link</span>
                            {{ restaurant.slug }}
                        </span>

                        <span class="inline-flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base text-gray-400">payments</span>
                            <span
                                class="font-semibold"
                                :class="isManual ? 'text-gray-600' : 'text-[#FF5722]'"
                            >{{ isManual ? 'Modo manual' : 'Suscripción' }}</span>
                            <span v-if="restaurant.plan" class="text-gray-700">· {{ restaurant.plan.name }}</span>
                        </span>

                        <span class="inline-flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base text-gray-400">person_add</span>
                            <span>{{ signupSourceLabel }}</span>
                        </span>

                        <span class="inline-flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-base text-gray-400">calendar_today</span>
                            <span>{{ formatDate(restaurant.created_at) }}</span>
                        </span>

                        <span class="inline-flex items-center gap-1.5 text-xs text-gray-400">
                            <span class="material-symbols-outlined text-sm">tag</span>
                            ID {{ restaurant.id }}
                        </span>
                    </div>
                </div>

                <!-- Primary action -->
                <div class="shrink-0">
                    <button
                        @click="toggleActive"
                        class="inline-flex items-center gap-2 px-4 py-2 rounded-xl border text-sm font-semibold transition-colors"
                        :class="restaurant.is_active
                            ? 'border-red-200 text-red-600 hover:bg-red-50'
                            : 'border-green-200 text-green-600 hover:bg-green-50 bg-green-50/50'"
                    >
                        <span class="material-symbols-outlined text-base">
                            {{ restaurant.is_active ? 'toggle_off' : 'toggle_on' }}
                        </span>
                        {{ restaurant.is_active ? 'Desactivar restaurante' : 'Activar restaurante' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══ KPI ROW ═══ -->
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-4 mb-6">
            <!-- Pedidos del mes -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 rounded-lg bg-[#FF5722]/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#FF5722] text-xl" style="font-variation-settings:'FILL' 1">receipt_long</span>
                        </div>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Pedidos del mes</span>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5 mb-2">
                    <span class="text-3xl font-bold text-gray-900 tabular-nums">{{ orders_count }}</span>
                    <span class="text-sm text-gray-400">/ {{ orders_limit }}</span>
                </div>
                <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="barClass(ordersPercent)"
                        :style="{ width: ordersPercent + '%' }"
                    ></div>
                </div>
                <p class="text-xs text-gray-400 mt-1.5">{{ ordersPercent }}% utilizado</p>
            </div>

            <!-- Sucursales -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 rounded-lg bg-blue-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-blue-600 text-xl" style="font-variation-settings:'FILL' 1">store</span>
                        </div>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Sucursales</span>
                    </div>
                </div>
                <div class="flex items-baseline gap-1.5 mb-2">
                    <span class="text-3xl font-bold text-gray-900 tabular-nums">{{ branch_count }}</span>
                    <span class="text-sm text-gray-400">/ {{ max_branches }}</span>
                </div>
                <div class="w-full h-1.5 bg-gray-100 rounded-full overflow-hidden">
                    <div
                        class="h-full rounded-full transition-all"
                        :class="barClass(branchesPercent)"
                        :style="{ width: branchesPercent + '%' }"
                    ></div>
                </div>
                <p class="text-xs text-gray-400 mt-1.5">{{ branchesPercent }}% utilizado</p>
            </div>

            <!-- Gracia / Suscripción -->
            <div
                class="bg-white rounded-xl border shadow-sm p-5"
                :class="graceUrgency === 'critical' ? 'border-red-200' : graceUrgency === 'high' ? 'border-orange-200' : 'border-gray-100'"
            >
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div
                            class="w-9 h-9 rounded-lg flex items-center justify-center"
                            :class="graceUrgency === 'critical' ? 'bg-red-50' : graceUrgency === 'high' ? 'bg-orange-50' : 'bg-amber-50'"
                        >
                            <span
                                class="material-symbols-outlined text-xl"
                                :class="graceUrgency === 'critical' ? 'text-red-600' : graceUrgency === 'high' ? 'text-orange-600' : 'text-amber-600'"
                                style="font-variation-settings:'FILL' 1"
                            >schedule</span>
                        </div>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">
                            {{ restaurant.status === 'grace_period' ? 'Gracia vence' : 'Suscripción' }}
                        </span>
                    </div>
                </div>
                <template v-if="graceDaysLeft !== null">
                    <div class="flex items-baseline gap-1.5 mb-1">
                        <span
                            class="text-3xl font-bold tabular-nums"
                            :class="graceUrgency === 'critical' ? 'text-red-600' : 'text-gray-900'"
                        >{{ graceDaysLeft }}</span>
                        <span class="text-sm text-gray-400">{{ graceDaysLeft === 1 ? 'día' : 'días' }}</span>
                    </div>
                    <p class="text-xs text-gray-500">{{ formatDate(restaurant.grace_period_ends_at) }}</p>
                </template>
                <template v-else-if="restaurant.subscription_ends_at">
                    <div class="flex items-baseline gap-1.5 mb-1">
                        <span class="text-lg font-semibold text-gray-900">Vence</span>
                    </div>
                    <p class="text-xs text-gray-500">{{ formatDate(restaurant.subscription_ends_at) }}</p>
                </template>
                <template v-else>
                    <p class="text-lg font-semibold text-gray-900">—</p>
                    <p class="text-xs text-gray-400 mt-1">Sin periodo activo</p>
                </template>
            </div>

            <!-- Stripe / ID -->
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                <div class="flex items-start justify-between mb-3">
                    <div class="flex items-center gap-2">
                        <div class="w-9 h-9 rounded-lg bg-indigo-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-indigo-600 text-xl">credit_card</span>
                        </div>
                        <span class="text-xs font-semibold text-gray-500 uppercase tracking-wider">Stripe</span>
                    </div>
                </div>
                <template v-if="restaurant.stripe_id">
                    <p class="text-base font-semibold text-gray-900 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span>
                        Conectado
                    </p>
                    <p class="text-xs text-gray-400 font-mono truncate mt-1">{{ restaurant.stripe_id }}</p>
                </template>
                <template v-else>
                    <p class="text-base font-semibold text-gray-900 flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-gray-300"></span>
                        Sin suscripción
                    </p>
                    <p class="text-xs text-gray-400 mt-1">No se ha vinculado cliente Stripe.</p>
                </template>
            </div>
        </div>

        <!-- ═══ MAIN CONTENT GRID ═══ -->
        <div class="grid grid-cols-1 lg:grid-cols-5 gap-6">

            <!-- LEFT — admin info + billing actions (60%) -->
            <div class="lg:col-span-3 space-y-5">

                <!-- Administrador -->
                <section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-gray-400">person</span>
                            <h2 class="text-sm font-semibold text-gray-900">Administrador</h2>
                        </div>
                    </header>
                    <div v-if="admin" class="p-5">
                        <div class="flex items-start gap-4 mb-4">
                            <div class="w-12 h-12 rounded-full bg-[#FF5722]/10 flex items-center justify-center shrink-0">
                                <span class="text-[#FF5722] font-bold text-lg">{{ admin.name.charAt(0).toUpperCase() }}</span>
                            </div>
                            <div class="min-w-0 flex-1">
                                <p class="font-semibold text-gray-900 truncate">{{ admin.name }}</p>
                                <p class="text-sm text-gray-500 truncate">{{ admin.email }}</p>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2">
                            <button
                                @click="showResetPasswordModal = true"
                                class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-2 rounded-xl border border-amber-200 text-amber-700 hover:bg-amber-50 transition-colors"
                            >
                                <span class="material-symbols-outlined text-base">lock_reset</span>
                                Restablecer contraseña
                            </button>
                            <button
                                @click="sendVerification"
                                class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                            >
                                <span class="material-symbols-outlined text-base">mark_email_read</span>
                                Enviar verificación
                            </button>
                        </div>
                    </div>
                    <div v-else class="p-5 text-center text-sm text-gray-400">
                        Sin administrador asignado.
                    </div>
                </section>

                <!-- Plan / Límites -->
                <section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-gray-50 flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-gray-400">tune</span>
                            <h2 class="text-sm font-semibold text-gray-900">Plan y límites</h2>
                        </div>
                        <div class="flex items-center gap-2">
                            <button
                                v-if="isManual && !editingLimits"
                                @click="editingLimits = true"
                                class="text-xs font-semibold text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200 transition-colors"
                            >Editar límites</button>
                            <button
                                v-if="isManual && !editingLimits"
                                @click="showGraceModal = true"
                                class="text-xs font-semibold text-white bg-[#FF5722] hover:bg-[#D84315] px-3 py-1.5 rounded-lg transition-colors"
                            >Iniciar suscripción</button>
                            <button
                                v-if="isSubscription && !editingLimits"
                                @click="showSwitchManualModal = true"
                                class="text-xs font-semibold text-gray-600 hover:bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-200 transition-colors"
                            >Cambiar a manual</button>
                        </div>
                    </header>

                    <div class="p-5">
                        <!-- Manual limits editor -->
                        <form v-if="editingLimits" @submit.prevent="saveLimits" class="space-y-4 bg-gray-50 rounded-xl p-4 mb-4">
                            <div v-if="isSubscription" class="flex items-start gap-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 text-xs">
                                <span class="material-symbols-outlined text-sm mt-0.5">warning</span>
                                <span>Al guardar, la suscripción de Stripe se cancelará y el restaurante pasará a modo manual.</span>
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Límite de pedidos</label>
                                    <input v-model.number="limitsForm.orders_limit" type="number" min="1" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                                    <p v-if="limitsForm.errors.orders_limit" class="text-xs text-red-500 mt-1">{{ limitsForm.errors.orders_limit }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Máx. sucursales</label>
                                    <input v-model.number="limitsForm.max_branches" type="number" min="1" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                                    <p v-if="limitsForm.errors.max_branches" class="text-xs text-red-500 mt-1">{{ limitsForm.errors.max_branches }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Inicio del periodo</label>
                                    <input v-model="limitsForm.orders_limit_start" type="date" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Fin del periodo</label>
                                    <input v-model="limitsForm.orders_limit_end" type="date" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" :disabled="limitsForm.processing" class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2 text-sm transition-colors disabled:opacity-60">
                                    {{ isSubscription ? 'Cambiar a manual' : 'Guardar cambios' }}
                                </button>
                                <button type="button" @click="editingLimits = false" class="px-5 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">Cancelar</button>
                            </div>
                        </form>

                        <!-- Read-only details grid -->
                        <dl v-if="!editingLimits" class="grid grid-cols-2 gap-x-6 gap-y-4 text-sm">
                            <div>
                                <dt class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Modo</dt>
                                <dd class="text-gray-900 font-semibold">{{ isManual ? 'Manual' : 'Suscripción' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Plan</dt>
                                <dd class="text-gray-900 font-semibold">{{ restaurant.plan?.name ?? 'Sin plan' }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Inicio del periodo</dt>
                                <dd class="text-gray-700">{{ formatDate(restaurant.orders_limit_start) }}</dd>
                            </div>
                            <div>
                                <dt class="text-xs text-gray-500 font-medium uppercase tracking-wider mb-1">Fin del periodo</dt>
                                <dd class="text-gray-700">{{ formatDate(restaurant.orders_limit_end) }}</dd>
                            </div>
                        </dl>
                    </div>
                </section>

            </div>

            <!-- RIGHT — Public menu & QR (40%) -->
            <div class="lg:col-span-2 space-y-5">

                <section class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
                    <header class="px-5 py-4 border-b border-gray-50 flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-gray-400">qr_code_2</span>
                            <h2 class="text-sm font-semibold text-gray-900">Menú público</h2>
                        </div>
                        <button
                            v-if="!showSlugRename"
                            @click="showSlugRename = true"
                            class="text-xs font-semibold text-amber-700 hover:bg-amber-50 px-2 py-1 rounded-md transition-colors"
                        >
                            Renombrar slug
                        </button>
                    </header>

                    <div class="p-5">
                        <!-- QR -->
                        <div class="flex justify-center mb-4">
                            <div class="p-3 bg-white rounded-2xl border border-gray-100 shadow-sm">
                                <QrCode
                                    ref="qrRef"
                                    :value="publicMenuUrl"
                                    :size="200"
                                    :file-name="`${restaurant.slug}-qr.png`"
                                />
                            </div>
                        </div>

                        <!-- URL block -->
                        <div class="bg-gray-50 rounded-xl p-3 font-mono text-[11px] text-gray-700 break-all mb-3 border border-gray-100">
                            {{ publicMenuUrl }}
                        </div>

                        <div class="grid grid-cols-2 gap-2">
                            <button
                                @click="copyPublicUrl"
                                class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                            >
                                <span class="material-symbols-outlined text-base">content_copy</span>
                                Copiar URL
                            </button>
                            <button
                                @click="downloadQr"
                                class="inline-flex items-center justify-center gap-1.5 text-sm font-semibold px-3 py-2 rounded-xl bg-[#FF5722] hover:bg-[#D84315] text-white transition-colors"
                            >
                                <span class="material-symbols-outlined text-base">download</span>
                                Descargar QR
                            </button>
                        </div>

                        <!-- Slug rename drawer -->
                        <div v-if="showSlugRename" class="mt-4 border-t border-gray-100 pt-4 space-y-3">
                            <div class="flex items-start gap-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 text-xs">
                                <span class="material-symbols-outlined text-sm mt-0.5">warning</span>
                                <span>Cambiar el slug invalida todos los QR impresos y enlaces compartidos. No hay redirección automática.</span>
                            </div>

                            <SlugInput
                                v-model="slugForm.slug"
                                :url-prefix="urlPrefix"
                                :auto-suggest-from-name="false"
                                :ignore-current-slug="restaurant.slug"
                                label="Nuevo slug"
                                @update:available="slugAvailable = $event"
                            />
                            <p v-if="slugForm.errors.slug" class="text-xs text-red-500">{{ slugForm.errors.slug }}</p>

                            <label class="flex items-start gap-2 text-xs text-gray-700">
                                <input v-model="slugForm.confirm" type="checkbox" class="mt-0.5" />
                                <span>Entiendo que los enlaces y QR anteriores dejarán de funcionar.</span>
                            </label>
                            <p v-if="slugForm.errors.confirm" class="text-xs text-red-500">{{ slugForm.errors.confirm }}</p>

                            <div class="flex gap-2">
                                <button
                                    @click="submitSlugRename"
                                    :disabled="slugForm.processing || !slugAvailable || !slugForm.confirm || slugForm.slug === restaurant.slug"
                                    class="flex-1 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-3 py-2 text-sm transition-colors disabled:opacity-60"
                                >
                                    {{ slugForm.processing ? 'Guardando…' : 'Confirmar cambio' }}
                                </button>
                                <button
                                    @click="showSlugRename = false; slugForm.slug = restaurant.slug"
                                    class="px-3 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                                >
                                    Cancelar
                                </button>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
        <!-- Reset Admin Password Modal -->
        <Teleport to="body">
            <div
                v-if="showResetPasswordModal"
                class="fixed inset-0 z-50 flex items-center justify-center"
            >
                <div class="absolute inset-0 bg-black/40" @click="showResetPasswordModal = false; passwordForm.reset()"></div>
                <div class="relative bg-white rounded-2xl shadow-xl p-6 max-w-md w-full mx-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-amber-600">lock_reset</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Restablecer contraseña</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-5">
                        Asigna una nueva contraseña para el administrador de <strong>{{ restaurant.name }}</strong>.
                    </p>
                    <form @submit.prevent="resetAdminPassword" class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
                            <input
                                v-model="passwordForm.password"
                                type="password"
                                autocomplete="new-password"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                :class="{ 'border-red-400': passwordForm.errors.password }"
                            />
                            <p v-if="passwordForm.errors.password" class="text-xs text-red-500 mt-1">{{ passwordForm.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar contraseña</label>
                            <input
                                v-model="passwordForm.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            />
                        </div>
                        <div class="flex gap-3 justify-end pt-2">
                            <button
                                type="button"
                                @click="showResetPasswordModal = false; passwordForm.reset()"
                                class="px-5 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="passwordForm.processing"
                                class="px-5 py-2 rounded-xl bg-[#FF5722] hover:bg-[#D84315] text-white text-sm font-semibold transition-colors disabled:opacity-60"
                            >
                                {{ passwordForm.processing ? 'Guardando...' : 'Restablecer' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>

        <!-- Grace Period Modal -->
        <Teleport to="body">
            <div v-if="showGraceModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/40" @click="showGraceModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-[#FF5722]/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">schedule</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Iniciar periodo de gracia</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-4">
                        El restaurante pasará a modo suscripción con un periodo de gracia para elegir su plan. Después de ese periodo deberá tener una suscripción activa.
                    </p>
                    <div class="mb-5">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Días de gracia</label>
                        <input v-model.number="graceForm.days" type="number" min="1" max="90" class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        <p v-if="graceForm.errors.days" class="text-xs text-red-500 mt-1">{{ graceForm.errors.days }}</p>
                    </div>
                    <div class="flex justify-end gap-3">
                        <button @click="showGraceModal = false" class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">Cancelar</button>
                        <button @click="startGrace" :disabled="graceForm.processing" class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors disabled:opacity-60">
                            {{ graceForm.processing ? 'Iniciando...' : 'Iniciar gracia' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

        <!-- Switch to Manual Confirmation Modal -->
        <Teleport to="body">
            <div v-if="showSwitchManualModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/40" @click="showSwitchManualModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-amber-600">warning</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Cambiar a modo manual</h3>
                    </div>
                    <div class="space-y-3 mb-5">
                        <p class="text-sm text-gray-600">Al cambiar a modo manual:</p>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-red-400 text-base mt-0.5">close</span>
                                La <strong>suscripción de Stripe se cancelará</strong> de inmediato.
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-blue-400 text-base mt-0.5">tune</span>
                                Los límites dependerán <strong>exclusivamente de la configuración manual</strong>.
                            </li>
                        </ul>
                    </div>
                    <p class="text-sm font-medium text-gray-700 mb-2">Configura los nuevos límites:</p>
                    <form @submit.prevent="saveLimits" class="space-y-3">
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Límite pedidos</label>
                                <input v-model.number="limitsForm.orders_limit" type="number" min="1" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Máx. sucursales</label>
                                <input v-model.number="limitsForm.max_branches" type="number" min="1" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Inicio periodo</label>
                                <input v-model="limitsForm.orders_limit_start" type="date" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-600 mb-1">Fin periodo</label>
                                <input v-model="limitsForm.orders_limit_end" type="date" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                            </div>
                        </div>
                        <div class="flex justify-end gap-3 pt-2">
                            <button type="button" @click="showSwitchManualModal = false" class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">Cancelar</button>
                            <button type="submit" :disabled="limitsForm.processing" class="bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors disabled:opacity-60">
                                {{ limitsForm.processing ? 'Cancelando suscripción...' : 'Cancelar suscripción y cambiar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </Teleport>
    </SuperAdminLayout>
</template>
