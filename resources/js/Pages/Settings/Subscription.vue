<script setup>
import { Head, router, usePage } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'

const props = defineProps({
    restaurant: Object,
    plans: Array,
    intent: Object,
})

const page = usePage()
const billingCycle = ref('monthly')
const showCancelModal = ref(false)
const processingPlanId = ref(null)
const processingAction = ref(null)
const showSwapModal = ref(false)
const swapTarget = ref(null)
const initiating = ref(false)

const isManual = computed(() => props.restaurant.billing_mode === 'manual')

function initiateSubscription() {
    initiating.value = true
    router.post(route('settings.subscription.initiate'), {}, {
        onFinish: () => { initiating.value = false },
    })
}

const currentPlan = computed(() => {
    if (!props.restaurant.plan) return null
    return props.plans.find(p => p.id === props.restaurant.plan.id) || props.restaurant.plan
})

const pendingPlan = computed(() => {
    if (!props.restaurant.pending_plan) return null
    return props.plans.find(p => p.id === props.restaurant.pending_plan.id) || props.restaurant.pending_plan
})

const isDowngrade = computed(() => {
    if (!swapTarget.value || !currentPlan.value) return false
    return swapTarget.value.orders_limit < currentPlan.value.orders_limit
        || swapTarget.value.max_branches < currentPlan.value.max_branches
})

function isPlanDowngrade(plan) {
    if (!currentPlan.value) return false
    return plan.orders_limit < currentPlan.value.orders_limit
        || plan.max_branches < currentPlan.value.max_branches
}

function isOtherDowngradeBlocked(plan) {
    return pendingPlan.value && isPlanDowngrade(plan) && plan.id !== pendingPlan.value.id
}

const currentBillingCycle = computed(() => {
    if (!currentPlan.value || !props.restaurant.current_stripe_price) return null
    return props.restaurant.current_stripe_price === currentPlan.value.stripe_yearly_price_id ? 'yearly' : 'monthly'
})

function isSamePlanDifferentCycle(plan) {
    return currentPlan.value
        && currentPlan.value.id === plan.id
        && currentBillingCycle.value
        && currentBillingCycle.value !== billingCycle.value
}

const ordersPercent = computed(() => {
    if (!props.restaurant.orders_limit) return 0
    return Math.min(100, Math.round((props.restaurant.orders_count / props.restaurant.orders_limit) * 100))
})

const branchesPercent = computed(() => {
    if (!props.restaurant.max_branches) return 0
    return Math.min(100, Math.round((props.restaurant.branch_count / props.restaurant.max_branches) * 100))
})

const graceDaysLeft = computed(() => {
    if (!props.restaurant.grace_period_ends_at) return 0
    const diff = Math.ceil((new Date(props.restaurant.grace_period_ends_at) - new Date()) / (1000 * 60 * 60 * 24))
    return Math.max(0, diff)
})

const isCanceledButActive = computed(() => {
    return props.restaurant.status === 'canceled'
        && props.restaurant.subscription_ends_at
        && new Date(props.restaurant.subscription_ends_at) > new Date()
})

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
}

function formatPrice(amount) {
    return Number(amount).toLocaleString('es-MX', { style: 'currency', currency: 'MXN', minimumFractionDigits: 0, maximumFractionDigits: 0 })
}

function barClass(percent) {
    if (percent > 90) return 'bg-red-500'
    if (percent > 70) return 'bg-amber-400'
    return 'bg-green-500'
}

function statusBadge(status) {
    const map = {
        active: { label: 'Activo', class: 'bg-green-50 text-green-700' },
        past_due: { label: 'Pago pendiente', class: 'bg-yellow-50 text-yellow-700' },
        grace_period: { label: 'Periodo de gracia', class: 'bg-orange-50 text-orange-700' },
        suspended: { label: 'Suspendido', class: 'bg-red-50 text-red-700' },
        canceled: { label: 'Cancelado', class: 'bg-gray-100 text-gray-500' },
        incomplete: { label: 'Incompleto', class: 'bg-blue-50 text-blue-700' },
    }
    return map[status] || { label: status, class: 'bg-gray-100 text-gray-500' }
}

function displayPrice(plan) {
    return billingCycle.value === 'monthly' ? plan.monthly_price : plan.yearly_price
}

function savingsPercent(plan) {
    if (!plan.monthly_price || !plan.yearly_price || plan.monthly_price <= 0) return 0
    return Math.round((1 - plan.yearly_price / (plan.monthly_price * 12)) * 100)
}

function choosePlan(plan) {
    processingPlanId.value = plan.id
    processingAction.value = 'checkout'
    router.post(route('settings.subscription.checkout'), {
        plan_id: plan.id,
        billing_cycle: billingCycle.value,
    }, {
        onFinish: () => { processingPlanId.value = null; processingAction.value = null },
    })
}

function confirmSwap(plan) {
    swapTarget.value = plan
    showSwapModal.value = true
}

function swapPlan() {
    const plan = swapTarget.value
    processingPlanId.value = plan.id
    processingAction.value = 'swap'
    router.put(route('settings.subscription.swap'), {
        plan_id: plan.id,
        billing_cycle: billingCycle.value,
    }, {
        onSuccess: () => { showSwapModal.value = false; swapTarget.value = null },
        onFinish: () => { processingPlanId.value = null; processingAction.value = null },
    })
}

function cancelSubscription() {
    processingAction.value = 'cancel'
    router.post(route('settings.subscription.cancel'), {}, {
        onSuccess: () => { showCancelModal.value = false },
        onFinish: () => { processingAction.value = null },
    })
}

function resumeSubscription() {
    processingAction.value = 'resume'
    router.post(route('settings.subscription.resume'), {}, {
        onFinish: () => { processingAction.value = null },
    })
}

function cancelPendingDowngrade() {
    processingAction.value = 'cancel-pending'
    router.delete(route('settings.subscription.cancel-pending'), {
        onFinish: () => { processingAction.value = null },
    })
}

function managePayment() {
    processingAction.value = 'portal'
    window.location.href = route('settings.subscription.portal')
}
</script>

<template>
    <Head title="Suscripción" />
    <AppLayout title="Configuración">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
            <p class="mt-1 text-sm text-gray-500">Gestiona la información y preferencias de tu restaurante.</p>
        </div>

        <SettingsLayout>
            <div class="space-y-6">

                <!-- ─── Manual Mode CTA ─── -->
                <div v-if="isManual" class="bg-white rounded-xl border border-gray-100 shadow-sm p-8 text-center">
                    <div class="w-14 h-14 rounded-2xl bg-[#FF5722]/10 flex items-center justify-center mx-auto mb-4">
                        <span class="material-symbols-outlined text-[#FF5722] text-3xl" style="font-variation-settings:'FILL' 1">rocket_launch</span>
                    </div>
                    <h2 class="text-xl font-bold text-gray-900 mb-2">Activa tu suscripción</h2>
                    <p class="text-sm text-gray-500 mb-6 max-w-md mx-auto">
                        Actualmente operas con límites manuales. Activa una suscripción para acceder a planes con más pedidos, sucursales y facturación automática.
                    </p>
                    <button
                        @click="initiateSubscription"
                        :disabled="initiating"
                        class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-8 py-3 text-sm transition-colors disabled:opacity-60 inline-flex items-center gap-2"
                    >
                        <span v-if="initiating" class="material-symbols-outlined text-lg animate-spin">progress_activity</span>
                        {{ initiating ? 'Activando...' : 'Iniciar periodo de prueba gratuito' }}
                    </button>
                    <p class="text-xs text-gray-400 mt-3">Tendrás un periodo de gracia para elegir tu plan.</p>
                </div>

                <template v-if="!isManual">

                <!-- ─── Status Banners ─── -->

                <div
                    v-if="restaurant.status === 'grace_period'"
                    class="flex items-start gap-3 bg-orange-50 border border-orange-200 text-orange-800 rounded-xl px-5 py-4 text-sm"
                >
                    <span class="material-symbols-outlined text-orange-500 text-xl mt-0.5" style="font-variation-settings:'FILL' 1">schedule</span>
                    <div>
                        <p class="font-semibold">Tu periodo de gracia está activo</p>
                        <p class="mt-1">
                            Tienes <strong>{{ graceDaysLeft }} días</strong> para elegir un plan.
                            <template v-if="restaurant.grace_period_ends_at">Vence el {{ formatDate(restaurant.grace_period_ends_at) }}.</template>
                        </p>
                    </div>
                </div>

                <div
                    v-if="restaurant.status === 'suspended'"
                    class="flex items-start gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-5 py-4 text-sm"
                >
                    <span class="material-symbols-outlined text-red-500 text-xl mt-0.5" style="font-variation-settings:'FILL' 1">block</span>
                    <div>
                        <p class="font-semibold">Tu cuenta está suspendida</p>
                        <p class="mt-1">Tus clientes no pueden realizar pedidos. Elige un plan para reactivar.</p>
                    </div>
                </div>

                <div
                    v-if="isCanceledButActive"
                    class="flex items-start gap-3 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-5 py-4 text-sm"
                >
                    <span class="material-symbols-outlined text-amber-500 text-xl mt-0.5" style="font-variation-settings:'FILL' 1">event_busy</span>
                    <div>
                        <p class="font-semibold">Tu suscripción fue cancelada</p>
                        <p class="mt-1">
                            Tu plan sigue activo hasta el <strong>{{ formatDate(restaurant.subscription_ends_at) }}</strong>.
                            Después de esa fecha tu cuenta será suspendida.
                        </p>
                    </div>
                </div>

                <div
                    v-if="page.url.includes('success=1')"
                    class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-xl px-5 py-4 text-sm"
                >
                    <span class="material-symbols-outlined text-green-600 text-xl" style="font-variation-settings:'FILL' 1">check_circle</span>
                    <p class="font-semibold">Suscripción activada correctamente.</p>
                </div>

                <!-- ─── Current Plan Card ─── -->

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-base font-semibold text-gray-900">Tu plan actual</h2>
                        <span
                            class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold"
                            :class="statusBadge(restaurant.status).class"
                        >{{ statusBadge(restaurant.status).label }}</span>
                    </div>

                    <div v-if="currentPlan" class="space-y-5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-[#FF5722]/10 flex items-center justify-center">
                                <span class="material-symbols-outlined text-[#FF5722] text-xl" style="font-variation-settings:'FILL' 1">workspace_premium</span>
                            </div>
                            <div>
                                <p class="text-lg font-bold text-gray-900">{{ currentPlan.name }}</p>
                                <p v-if="currentPlan.description" class="text-sm text-gray-500">{{ currentPlan.description }}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div class="bg-gray-50 rounded-xl p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-500">Pedidos</span>
                                    <span class="text-xs font-bold text-gray-900">{{ restaurant.orders_count }} / {{ restaurant.orders_limit }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                    <div class="h-full rounded-full transition-all" :class="barClass(ordersPercent)" :style="{ width: ordersPercent + '%' }"></div>
                                </div>
                            </div>
                            <div class="bg-gray-50 rounded-xl p-4">
                                <div class="flex items-center justify-between mb-2">
                                    <span class="text-xs font-medium text-gray-500">Sucursales</span>
                                    <span class="text-xs font-bold text-gray-900">{{ restaurant.branch_count }} / {{ restaurant.max_branches }}</span>
                                </div>
                                <div class="w-full bg-gray-200 rounded-full h-2 overflow-hidden">
                                    <div class="h-full rounded-full transition-all" :class="barClass(branchesPercent)" :style="{ width: branchesPercent + '%' }"></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div v-else class="flex items-center gap-3 py-2">
                        <div class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                            <span class="material-symbols-outlined text-gray-400 text-xl">help_outline</span>
                        </div>
                        <p class="text-sm text-gray-500">No tienes un plan activo. Elige uno a continuación.</p>
                    </div>

                    <!-- Pending downgrade banner -->
                    <div
                        v-if="pendingPlan"
                        class="flex items-start gap-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-4 py-3 mt-4 text-sm"
                    >
                        <span class="material-symbols-outlined text-blue-500 text-xl mt-0.5" style="font-variation-settings:'FILL' 1">schedule</span>
                        <div class="flex-1">
                            <p class="font-semibold">Cambio de plan programado</p>
                            <p class="mt-0.5">
                                Tu plan cambiará a <strong>{{ pendingPlan.name }}</strong> el <strong>{{ formatDate(restaurant.pending_plan_effective_at) }}</strong>.
                                Sigues gozando de los beneficios de {{ currentPlan?.name }} hasta entonces.
                            </p>
                        </div>
                        <button
                            @click="cancelPendingDowngrade"
                            :disabled="processingAction === 'cancel-pending'"
                            class="shrink-0 text-sm font-semibold text-blue-700 hover:text-blue-900 transition-colors disabled:opacity-50"
                        >
                            {{ processingAction === 'cancel-pending' ? 'Cancelando...' : 'Cancelar cambio' }}
                        </button>
                    </div>

                    <!-- Actions footer -->
                    <div v-if="restaurant.has_subscription" class="mt-5 pt-4 border-t border-gray-100">
                        <!-- Resume CTA (canceled but still active) -->
                        <div v-if="restaurant.on_grace_period" class="flex items-center justify-between">
                            <p class="text-sm text-gray-500">
                                Se cancelará el {{ formatDate(restaurant.subscription_ends_at) }}
                            </p>
                            <button
                                @click="resumeSubscription"
                                :disabled="processingAction === 'resume'"
                                class="inline-flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2 text-sm transition-colors disabled:opacity-60"
                            >
                                <span v-if="processingAction === 'resume'" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                                <span v-else class="material-symbols-outlined text-base">replay</span>
                                Reanudar suscripción
                            </button>
                        </div>

                        <!-- Normal actions -->
                        <div v-else class="flex items-center gap-5">
                            <button
                                @click="managePayment"
                                :disabled="processingAction === 'portal'"
                                class="text-sm font-semibold text-[#FF5722] hover:text-[#D84315] transition-colors disabled:opacity-50"
                            >
                                {{ processingAction === 'portal' ? 'Redirigiendo...' : 'Gestionar método de pago' }}
                            </button>
                            <span class="text-gray-200">|</span>
                            <button
                                @click="showCancelModal = true"
                                class="text-sm font-semibold text-gray-500 hover:text-red-600 transition-colors"
                            >
                                Cancelar suscripción
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ─── Billing Cycle Selector ─── -->

                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-semibold text-gray-900">Periodo de facturación</p>
                            <p class="text-xs text-gray-500 mt-0.5">
                                {{ billingCycle === 'monthly' ? 'Precios de pago mensual' : 'Precios de pago anual — pagas una vez al año' }}
                            </p>
                        </div>

                        <div class="inline-flex bg-gray-100 rounded-xl p-1">
                            <button
                                @click="billingCycle = 'monthly'"
                                class="px-5 py-2 rounded-lg text-sm font-semibold transition-all"
                                :class="billingCycle === 'monthly'
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'"
                            >
                                Mensual
                            </button>
                            <button
                                @click="billingCycle = 'yearly'"
                                class="px-5 py-2 rounded-lg text-sm font-semibold transition-all flex items-center gap-2"
                                :class="billingCycle === 'yearly'
                                    ? 'bg-white text-gray-900 shadow-sm'
                                    : 'text-gray-500 hover:text-gray-700'"
                            >
                                Anual
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-green-100 text-green-700">
                                    Ahorra
                                </span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- ─── Plans Grid ─── -->

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div
                        v-for="plan in plans"
                        :key="plan.id"
                        class="bg-white rounded-xl border shadow-sm flex flex-col transition-shadow hover:shadow-md"
                        :class="currentPlan && currentPlan.id === plan.id
                            ? 'border-[#FF5722] ring-2 ring-[#FF5722]/20'
                            : 'border-gray-100'"
                    >
                        <div class="p-6 flex-1">
                            <div class="flex items-center justify-between mb-4">
                                <h3 class="text-lg font-bold text-gray-900">{{ plan.name }}</h3>
                                <div class="flex items-center gap-2">
                                    <span
                                        v-if="pendingPlan && pendingPlan.id === plan.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700"
                                    >Próximo</span>
                                    <span
                                        v-if="currentPlan && currentPlan.id === plan.id"
                                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-[#FF5722]/10 text-[#FF5722]"
                                    >Actual{{ isSamePlanDifferentCycle(plan) ? ` (${currentBillingCycle === 'monthly' ? 'mensual' : 'anual'})` : '' }}</span>
                                </div>
                            </div>

                            <p v-if="plan.description" class="text-sm text-gray-500 mb-5">{{ plan.description }}</p>

                            <!-- Price block -->
                            <div class="mb-5">
                                <div class="flex items-baseline gap-1.5">
                                    <span class="text-3xl font-bold text-gray-900">{{ formatPrice(displayPrice(plan)) }}</span>
                                    <span class="text-sm text-gray-400">/ {{ billingCycle === 'monthly' ? 'mes' : 'año' }}</span>
                                </div>

                                <!-- Annual context: show monthly equivalent + savings -->
                                <div v-if="billingCycle === 'yearly'" class="mt-1.5 flex items-center gap-2">
                                    <span class="text-xs text-gray-400 line-through">{{ formatPrice(plan.monthly_price * 12) }}/año</span>
                                    <span
                                        v-if="savingsPercent(plan) > 0"
                                        class="inline-flex items-center px-1.5 py-0.5 rounded-md text-[10px] font-bold bg-green-100 text-green-700"
                                    >
                                        -{{ savingsPercent(plan) }}%
                                    </span>
                                </div>
                                <p v-if="billingCycle === 'yearly'" class="text-xs text-gray-500 mt-1">
                                    Equivale a {{ formatPrice(Math.round(plan.yearly_price / 12)) }}/mes
                                </p>

                                <!-- Monthly context -->
                                <p v-if="billingCycle === 'monthly'" class="text-xs text-gray-400 mt-1">
                                    Facturación mensual
                                </p>
                            </div>

                            <!-- Features -->
                            <ul class="space-y-2.5">
                                <li class="flex items-center gap-2.5 text-sm text-gray-700">
                                    <span class="material-symbols-outlined text-green-500 text-base" style="font-variation-settings:'FILL' 1">check_circle</span>
                                    Hasta <strong class="mx-0.5">{{ plan.orders_limit.toLocaleString('es-MX') }}</strong> pedidos/mes
                                </li>
                                <li class="flex items-center gap-2.5 text-sm text-gray-700">
                                    <span class="material-symbols-outlined text-green-500 text-base" style="font-variation-settings:'FILL' 1">check_circle</span>
                                    Hasta <strong class="mx-0.5">{{ plan.max_branches }}</strong> {{ plan.max_branches === 1 ? 'sucursal' : 'sucursales' }}
                                </li>
                                <li class="flex items-center gap-2.5 text-sm text-gray-700">
                                    <span class="material-symbols-outlined text-green-500 text-base" style="font-variation-settings:'FILL' 1">check_circle</span>
                                    Todas las funcionalidades
                                </li>
                            </ul>
                        </div>

                        <!-- CTA -->
                        <div class="px-6 pb-6">
                            <!-- Pending plan: cancel button -->
                            <button
                                v-if="pendingPlan && pendingPlan.id === plan.id"
                                @click="cancelPendingDowngrade"
                                :disabled="processingAction === 'cancel-pending'"
                                class="w-full border border-blue-300 text-blue-700 hover:bg-blue-50 font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
                            >
                                <span v-if="processingAction === 'cancel-pending'" class="material-symbols-outlined text-lg animate-spin">progress_activity</span>
                                {{ processingAction === 'cancel-pending' ? 'Cancelando...' : 'Cancelar cambio' }}
                            </button>
                            <!-- Current plan: same cycle -->
                            <div
                                v-else-if="currentPlan && currentPlan.id === plan.id && !isSamePlanDifferentCycle(plan)"
                                class="w-full text-center text-sm font-semibold text-[#FF5722] bg-[#FF5722]/5 rounded-xl px-4 py-2.5"
                            >
                                Tu plan actual
                            </div>
                            <!-- Current plan: different cycle (e.g. monthly → yearly) -->
                            <button
                                v-else-if="isSamePlanDifferentCycle(plan)"
                                @click="confirmSwap(plan)"
                                :disabled="processingPlanId === plan.id"
                                class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
                            >
                                <span v-if="processingPlanId === plan.id" class="material-symbols-outlined text-lg animate-spin">progress_activity</span>
                                {{ processingPlanId === plan.id ? 'Cambiando...' : `Cambiar a ${billingCycle === 'yearly' ? 'anual' : 'mensual'}` }}
                            </button>
                            <!-- Blocked: another downgrade while pending exists -->
                            <button
                                v-else-if="isOtherDowngradeBlocked(plan)"
                                disabled
                                class="w-full bg-gray-100 text-gray-400 font-semibold rounded-xl px-4 py-2.5 text-sm cursor-not-allowed"
                                title="Cancela el cambio pendiente primero"
                            >
                                Cambio pendiente
                            </button>
                            <!-- Normal: choosePlan or confirmSwap -->
                            <button
                                v-else
                                @click="restaurant.has_subscription ? confirmSwap(plan) : choosePlan(plan)"
                                :disabled="processingPlanId === plan.id"
                                class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors disabled:opacity-60 flex items-center justify-center gap-2"
                            >
                                <span v-if="processingPlanId === plan.id" class="material-symbols-outlined text-lg animate-spin">progress_activity</span>
                                <template v-if="processingPlanId === plan.id">
                                    {{ processingAction === 'checkout' ? 'Redirigiendo a pago...' : 'Cambiando plan...' }}
                                </template>
                                <template v-else>
                                    {{ restaurant.has_subscription ? 'Cambiar a este plan' : (billingCycle === 'monthly' ? 'Elegir plan mensual' : 'Elegir plan anual') }}
                                </template>
                            </button>
                        </div>
                    </div>
                </div>

                </template><!-- end v-if !isManual -->

            </div>
        </SettingsLayout>

        <!-- ─── Cancel Modal ─── -->

        <Teleport to="body">
            <div v-if="showCancelModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/40" @click="showCancelModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-red-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-red-600">warning</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Cancelar suscripción</h3>
                    </div>

                    <div class="space-y-3 mb-6">
                        <p class="text-sm text-gray-600">
                            Al cancelar tu suscripción:
                        </p>
                        <ul class="space-y-2 text-sm text-gray-600">
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-gray-400 text-base mt-0.5">check</span>
                                Tu plan seguirá <strong>activo hasta el final del periodo</strong> que ya pagaste.
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-red-400 text-base mt-0.5">close</span>
                                Después de esa fecha, tu cuenta será <strong>suspendida</strong>.
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-red-400 text-base mt-0.5">close</span>
                                Tus clientes <strong>no podrán realizar pedidos</strong> una vez suspendida.
                            </li>
                        </ul>
                        <p class="text-xs text-gray-400 bg-gray-50 rounded-lg px-3 py-2">
                            Puedes reanudar tu suscripción en cualquier momento antes de que venza el periodo actual.
                        </p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            @click="showCancelModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                        >Mantener plan</button>
                        <button
                            @click="cancelSubscription"
                            :disabled="processingAction === 'cancel'"
                            class="bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors disabled:opacity-60 flex items-center gap-2"
                        >
                            <span v-if="processingAction === 'cancel'" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                            {{ processingAction === 'cancel' ? 'Cancelando...' : 'Sí, cancelar suscripción' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
        <!-- ─── Swap Confirmation Modal ─── -->

        <Teleport to="body">
            <div v-if="showSwapModal && swapTarget" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="fixed inset-0 bg-black/40" @click="showSwapModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl max-w-md w-full mx-4 p-6">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-[#FF5722]/10 flex items-center justify-center">
                            <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">swap_horiz</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Cambiar de plan</h3>
                    </div>

                    <div class="space-y-4 mb-6">
                        <div class="flex items-center gap-3 bg-gray-50 rounded-xl p-4">
                            <div class="flex-1 text-center">
                                <p class="text-xs text-gray-500 mb-1">Plan actual</p>
                                <p class="font-semibold text-gray-900">{{ currentPlan?.name }}</p>
                            </div>
                            <span class="material-symbols-outlined text-gray-300">arrow_forward</span>
                            <div class="flex-1 text-center">
                                <p class="text-xs text-gray-500 mb-1">Nuevo plan</p>
                                <p class="font-semibold text-[#FF5722]">{{ swapTarget.name }}</p>
                            </div>
                        </div>

                        <ul class="space-y-2 text-sm text-gray-600">
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-green-500 text-base mt-0.5" style="font-variation-settings:'FILL' 1">check_circle</span>
                                <span>Hasta <strong>{{ swapTarget.orders_limit.toLocaleString('es-MX') }}</strong> pedidos/mes</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-green-500 text-base mt-0.5" style="font-variation-settings:'FILL' 1">check_circle</span>
                                <span>Hasta <strong>{{ swapTarget.max_branches }}</strong> {{ swapTarget.max_branches === 1 ? 'sucursal' : 'sucursales' }}</span>
                            </li>
                            <li class="flex items-start gap-2">
                                <span class="material-symbols-outlined text-blue-500 text-base mt-0.5" style="font-variation-settings:'FILL' 1">info</span>
                                <span>Precio: <strong>{{ formatPrice(displayPrice(swapTarget)) }}/{{ billingCycle === 'monthly' ? 'mes' : 'año' }}</strong></span>
                            </li>
                        </ul>

                        <p v-if="isDowngrade" class="text-xs text-blue-600 bg-blue-50 rounded-lg px-3 py-2">
                            Tu plan cambiará el <strong>{{ formatDate(restaurant.pending_plan_effective_at || restaurant.period_end) }}</strong>.
                            Seguirás gozando de los beneficios de {{ currentPlan?.name }} hasta entonces.
                        </p>
                        <p v-else class="text-xs text-gray-400 bg-gray-50 rounded-lg px-3 py-2">
                            El cambio se aplicará de inmediato. Stripe prorrateará la diferencia automáticamente usando tu método de pago actual.
                        </p>
                    </div>

                    <div class="flex justify-end gap-3">
                        <button
                            @click="showSwapModal = false"
                            class="px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                        >Cancelar</button>
                        <button
                            @click="swapPlan"
                            :disabled="processingAction === 'swap'"
                            class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors disabled:opacity-60 flex items-center gap-2"
                        >
                            <span v-if="processingAction === 'swap'" class="material-symbols-outlined text-base animate-spin">progress_activity</span>
                            {{ processingAction === 'swap' ? 'Cambiando...' : (isDowngrade ? 'Programar cambio' : 'Confirmar cambio') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
