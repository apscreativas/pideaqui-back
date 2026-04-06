<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    restaurant: Object,
    admin: Object,
    orders_count: Number,
    orders_limit: Number,
    branch_count: Number,
    max_branches: Number,
    plans: Array,
})

const showToken = ref(false)
const showRegenerateModal = ref(false)
const regenerating = ref(false)
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

function copyToken() {
    navigator.clipboard.writeText(props.restaurant.access_token)
}

function regenerateToken() {
    regenerating.value = true
    router.post(route('super.restaurants.regenerate-token', props.restaurant.id), {}, {
        onFinish: () => {
            regenerating.value = false
            showRegenerateModal.value = false
            showToken.value = false
        },
    })
}
</script>

<template>
    <Head :title="`SuperAdmin — ${restaurant.name}`" />
    <SuperAdminLayout>
        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <a :href="route('super.restaurants.index')" class="hover:text-gray-600">Restaurantes</a>
                    <span>/</span>
                    <span class="text-gray-900 font-medium">{{ restaurant.name }}</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">{{ restaurant.name }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ restaurant.slug }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                    :class="restaurant.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                >
                    {{ restaurant.is_active ? 'Activo' : 'Inactivo' }}
                </span>
                <button
                    @click="toggleActive"
                    class="px-4 py-2 rounded-xl border text-sm font-semibold transition-colors"
                    :class="restaurant.is_active
                        ? 'border-red-200 text-red-600 hover:bg-red-50'
                        : 'border-green-200 text-green-600 hover:bg-green-50'"
                >
                    {{ restaurant.is_active ? 'Desactivar' : 'Activar' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-6">

            <!-- Left column -->
            <div class="col-span-2 space-y-6">

                <!-- Plan & Uso -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-5">
                        <div>
                            <h2 class="text-base font-semibold text-gray-900">Plan y uso</h2>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span
                                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                    :class="isManual ? 'bg-gray-100 text-gray-600' : 'bg-[#FF5722]/10 text-[#FF5722]'"
                                >
                                    {{ isManual ? 'Modo manual' : 'Suscripción' }}
                                </span>
                                <span v-if="restaurant.plan" class="text-sm text-gray-500">
                                    Plan: <strong class="text-gray-900">{{ restaurant.plan.name }}</strong>
                                </span>
                            </div>
                        </div>
                        <div class="flex items-center gap-3">
                            <button
                                v-if="isManual && !editingLimits"
                                @click="editingLimits = true"
                                class="text-sm text-gray-500 hover:underline font-medium"
                            >Editar límites</button>
                            <button
                                v-if="isManual && !editingLimits"
                                @click="showGraceModal = true"
                                class="text-sm text-[#FF5722] hover:underline font-medium"
                            >Iniciar suscripción</button>
                            <button
                                v-if="isSubscription && !editingLimits"
                                @click="showSwitchManualModal = true"
                                class="text-sm text-gray-500 hover:underline font-medium"
                            >Cambiar a manual</button>
                        </div>
                    </div>

                    <!-- Manual limits editor -->
                    <div v-if="editingLimits" class="mb-5">
                        <form @submit.prevent="saveLimits" class="space-y-4 bg-gray-50 rounded-xl p-4">
                            <p class="text-sm font-semibold text-gray-700">Límites manuales</p>
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
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Inicio del periodo</label>
                                    <input v-model="limitsForm.orders_limit_start" type="date" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                                    <p v-if="limitsForm.errors.orders_limit_start" class="text-xs text-red-500 mt-1">{{ limitsForm.errors.orders_limit_start }}</p>
                                </div>
                                <div>
                                    <label class="block text-xs font-medium text-gray-600 mb-1">Fin del periodo</label>
                                    <input v-model="limitsForm.orders_limit_end" type="date" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                                    <p v-if="limitsForm.errors.orders_limit_end" class="text-xs text-red-500 mt-1">{{ limitsForm.errors.orders_limit_end }}</p>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" :disabled="limitsForm.processing" class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2 text-sm transition-colors disabled:opacity-60">
                                    {{ isSubscription ? 'Cambiar a manual' : 'Guardar' }}
                                </button>
                                <button type="button" @click="editingLimits = false" class="px-5 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors">Cancelar</button>
                            </div>
                        </form>
                    </div>

                    <!-- Usage bars -->
                    <div class="space-y-5">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Pedidos del mes</span>
                                <span class="text-sm font-bold text-gray-900">{{ orders_count }} / {{ orders_limit }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="barClass(ordersPercent)"
                                    :style="{ width: ordersPercent + '%' }"
                                ></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Sucursales</span>
                                <span class="text-sm font-bold text-gray-900">{{ branch_count }} / {{ max_branches }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="barClass(branchesPercent)"
                                    :style="{ width: branchesPercent + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right column -->
            <div class="space-y-6">

                <!-- Admin info -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Administrador</h2>
                    <div v-if="admin" class="space-y-3">
                        <div class="space-y-2">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-gray-400 text-lg">person</span>
                                <span class="text-sm text-gray-700">{{ admin.name }}</span>
                            </div>
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-gray-400 text-lg">mail</span>
                                <span class="text-sm text-gray-700">{{ admin.email }}</span>
                            </div>
                        </div>
                        <button
                            @click="showResetPasswordModal = true"
                            class="w-full text-sm font-semibold px-3 py-2 rounded-xl border border-amber-200 text-amber-600 hover:bg-amber-50 transition-colors flex items-center justify-center gap-1"
                        >
                            <span class="material-symbols-outlined text-base">lock_reset</span>
                            Restablecer contraseña
                        </button>
                    </div>
                    <p v-else class="text-sm text-gray-400">Sin administrador asignado.</p>
                </div>

                <!-- Access token -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3">Access Token (API)</h2>
                    <div class="bg-gray-50 rounded-xl p-3 font-mono text-xs text-gray-700 break-all mb-3">
                        {{ showToken ? restaurant.access_token : '••••••••••••••••' }}
                    </div>
                    <div class="flex gap-2 mb-2">
                        <button
                            @click="showToken = !showToken"
                            class="flex-1 text-sm font-semibold px-3 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                        >
                            {{ showToken ? 'Ocultar' : 'Mostrar' }}
                        </button>
                        <button
                            @click="copyToken"
                            class="flex-1 text-sm font-semibold px-3 py-2 rounded-xl border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors flex items-center justify-center gap-1"
                        >
                            <span class="material-symbols-outlined text-base">content_copy</span>
                            Copiar
                        </button>
                    </div>
                    <button
                        @click="showRegenerateModal = true"
                        class="w-full text-sm font-semibold px-3 py-2 rounded-xl border border-amber-200 text-amber-600 hover:bg-amber-50 transition-colors flex items-center justify-center gap-1"
                    >
                        <span class="material-symbols-outlined text-base">refresh</span>
                        Regenerar token
                    </button>
                </div>

                <!-- Status & Billing -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3">Billing</h2>
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Status</span>
                            <span
                                class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                                :class="{
                                    'bg-green-50 text-green-700': restaurant.status === 'active',
                                    'bg-amber-50 text-amber-700': restaurant.status === 'past_due' || restaurant.status === 'grace_period',
                                    'bg-red-50 text-red-700': restaurant.status === 'suspended' || restaurant.status === 'disabled',
                                    'bg-gray-100 text-gray-600': restaurant.status === 'canceled' || restaurant.status === 'incomplete',
                                }"
                            >{{ restaurant.status }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Plan</span>
                            <span class="font-medium text-gray-900">{{ restaurant.plan?.name ?? 'Sin plan' }}</span>
                        </div>
                        <div v-if="restaurant.grace_period_ends_at" class="flex justify-between">
                            <span class="text-gray-500">Gracia vence</span>
                            <span>{{ formatDate(restaurant.grace_period_ends_at) }}</span>
                        </div>
                        <div v-if="restaurant.stripe_id" class="flex justify-between">
                            <span class="text-gray-500">Stripe</span>
                            <span class="font-mono text-xs">{{ restaurant.stripe_id }}</span>
                        </div>
                    </div>
                </div>

                <!-- Metadata -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3">Información</h2>
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex justify-between">
                            <span class="text-gray-500">ID</span>
                            <span class="font-mono">{{ restaurant.id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Creado</span>
                            <span>{{ new Date(restaurant.created_at).toLocaleDateString('es-MX') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Regenerate Token Modal -->
        <Teleport to="body">
            <div
                v-if="showRegenerateModal"
                class="fixed inset-0 z-50 flex items-center justify-center"
            >
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/40" @click="showRegenerateModal = false"></div>
                <!-- Modal -->
                <div class="relative bg-white rounded-2xl shadow-xl p-6 max-w-md w-full mx-4">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-amber-50 flex items-center justify-center">
                            <span class="material-symbols-outlined text-amber-600">warning</span>
                        </div>
                        <h3 class="text-lg font-bold text-gray-900">Regenerar token</h3>
                    </div>
                    <p class="text-sm text-gray-600 mb-6">
                        Esta acción generará un nuevo token de acceso. El token anterior dejará de funcionar inmediatamente y las integraciones activas perderán acceso. Esta acción no se puede deshacer.
                    </p>
                    <div class="flex gap-3 justify-end">
                        <button
                            @click="showRegenerateModal = false"
                            class="px-5 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="regenerateToken"
                            :disabled="regenerating"
                            class="px-5 py-2 rounded-xl bg-amber-500 hover:bg-amber-600 text-white text-sm font-semibold transition-colors disabled:opacity-60"
                        >
                            {{ regenerating ? 'Regenerando...' : 'Regenerar' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

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
