<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { ref } from 'vue'

const props = defineProps({
    plans: Array,
    hasPendingSync: Boolean,
})

const syncing = ref(false)

function syncStripe() {
    syncing.value = true
    router.post(route('super.plans.sync-stripe'), {}, {
        onFinish: () => syncing.value = false,
    })
}

function formatPrice(amount) {
    return Number(amount).toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0,
    })
}

const confirmingToggle = ref(null)

function toggleActive(plan) {
    router.patch(route('super.plans.toggle', plan.id), {}, {
        onFinish: () => confirmingToggle.value = null,
    })
}

function tierIcon(plan) {
    if (plan.is_default_grace) return 'volunteer_activism'
    if (plan.orders_limit >= 5000) return 'diamond'
    if (plan.orders_limit >= 1000) return 'rocket_launch'
    return 'storefront'
}

function tierColor(plan) {
    if (plan.is_default_grace) return { bg: 'bg-amber-50', icon: 'text-amber-600', ring: 'ring-amber-200' }
    if (plan.orders_limit >= 5000) return { bg: 'bg-violet-50', icon: 'text-violet-600', ring: 'ring-violet-200' }
    if (plan.orders_limit >= 1000) return { bg: 'bg-blue-50', icon: 'text-blue-600', ring: 'ring-blue-200' }
    return { bg: 'bg-emerald-50', icon: 'text-emerald-600', ring: 'ring-emerald-200' }
}
</script>

<template>
    <Head title="SuperAdmin — Planes" />
    <SuperAdminLayout>
        <!-- Header -->
        <div class="flex items-center justify-between mb-8">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Planes</h1>
                <p class="mt-1 text-sm text-gray-500">Configura los planes de suscripción disponibles para los restaurantes.</p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    @click="syncStripe"
                    :disabled="syncing"
                    class="inline-flex items-center gap-2 border border-gray-200 text-gray-600 hover:bg-gray-50 hover:border-gray-300 font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors disabled:opacity-60"
                >
                    <span
                        class="material-symbols-outlined text-lg"
                        :class="{ 'animate-spin': syncing }"
                    >{{ syncing ? 'progress_activity' : 'sync' }}</span>
                    {{ syncing ? 'Sincronizando...' : 'Sincronizar Stripe' }}
                </button>
                <Link
                    :href="route('super.plans.create')"
                    class="inline-flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors shadow-sm"
                >
                    <span class="material-symbols-outlined text-lg">add</span>
                    Nuevo plan
                </Link>
            </div>
        </div>

        <!-- Stripe Sync Banner -->
        <div
            v-if="hasPendingSync"
            class="flex items-center gap-3 bg-amber-50 border border-amber-200 rounded-xl px-4 py-3 mb-6"
        >
            <span class="material-symbols-outlined text-amber-600 text-xl" style="font-variation-settings:'FILL' 1">warning</span>
            <p class="text-sm text-amber-800 flex-1">
                Hay planes sin vincular a Stripe. El checkout no funcionará para esos planes hasta que se sincronicen.
            </p>
            <button
                @click="syncStripe"
                :disabled="syncing"
                class="text-sm font-semibold text-amber-700 hover:text-amber-900 whitespace-nowrap disabled:opacity-60"
            >
                {{ syncing ? 'Sincronizando...' : 'Sincronizar ahora' }}
            </button>
        </div>

        <!-- Plans Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
            <div
                v-for="plan in plans"
                :key="plan.id"
                class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden flex flex-col transition-shadow hover:shadow-md"
                :class="{ 'ring-1': plan.is_default_grace, [tierColor(plan).ring]: plan.is_default_grace }"
            >
                <!-- Card Header -->
                <div class="p-6 pb-4">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex items-center gap-3">
                            <div
                                class="w-10 h-10 rounded-xl flex items-center justify-center"
                                :class="tierColor(plan).bg"
                            >
                                <span
                                    class="material-symbols-outlined text-xl"
                                    :class="tierColor(plan).icon"
                                    style="font-variation-settings:'FILL' 1"
                                >{{ tierIcon(plan) }}</span>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900">{{ plan.name }}</h3>
                                <p v-if="plan.is_default_grace" class="text-xs text-amber-600 font-medium">Plan de gracia</p>
                                <p v-else-if="plan.description" class="text-xs text-gray-400 line-clamp-1">{{ plan.description }}</p>
                            </div>
                        </div>
                        <span
                            class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold"
                            :class="plan.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
                        >
                            {{ plan.is_active ? 'Activo' : 'Inactivo' }}
                        </span>
                    </div>

                    <!-- Pricing -->
                    <div v-if="!plan.is_default_grace" class="mb-4">
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-gray-900">{{ formatPrice(plan.monthly_price) }}</span>
                            <span class="text-sm text-gray-400">/mes</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-0.5">{{ formatPrice(plan.yearly_price) }}/año</p>
                    </div>
                    <div v-else class="mb-4">
                        <div class="flex items-baseline gap-1">
                            <span class="text-3xl font-bold text-gray-900">Gratis</span>
                        </div>
                        <p class="text-xs text-amber-600 mt-0.5">Temporal — mientras eligen plan</p>
                    </div>

                    <!-- Limits -->
                    <div class="space-y-2.5">
                        <div class="flex items-center gap-2.5 text-sm">
                            <span class="material-symbols-outlined text-base text-gray-400">receipt_long</span>
                            <span class="text-gray-700">
                                <strong class="text-gray-900">{{ plan.orders_limit.toLocaleString('es-MX') }}</strong> pedidos/mes
                            </span>
                        </div>
                        <div class="flex items-center gap-2.5 text-sm">
                            <span class="material-symbols-outlined text-base text-gray-400">store</span>
                            <span class="text-gray-700">
                                <strong class="text-gray-900">{{ plan.max_branches }}</strong>
                                {{ plan.max_branches === 1 ? 'sucursal' : 'sucursales' }}
                            </span>
                        </div>
                    </div>
                </div>

                <!-- Card Footer -->
                <div class="mt-auto border-t border-gray-50 px-6 py-4 bg-gray-50/50">
                    <div class="flex items-center justify-between">
                        <!-- Stats -->
                        <div class="flex items-center gap-4 text-xs text-gray-400">
                            <div class="flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">restaurant</span>
                                <span>{{ plan.restaurants_count ?? 0 }}</span>
                            </div>
                            <div v-if="!plan.is_default_grace" class="flex items-center gap-1">
                                <span
                                    class="w-1.5 h-1.5 rounded-full"
                                    :class="plan.stripe_product_id ? 'bg-green-500' : 'bg-gray-300'"
                                ></span>
                                <span>Stripe</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <div class="flex items-center gap-2">
                            <button
                                v-if="!plan.is_default_grace"
                                @click="confirmingToggle === plan.id ? toggleActive(plan) : (confirmingToggle = plan.id)"
                                class="text-xs font-semibold px-2.5 py-1.5 rounded-lg border transition-colors"
                                :class="confirmingToggle === plan.id
                                    ? (plan.is_active ? 'border-red-300 bg-red-50 text-red-700' : 'border-green-300 bg-green-50 text-green-700')
                                    : (plan.is_active ? 'border-gray-200 text-gray-500 hover:border-red-200 hover:text-red-600' : 'border-gray-200 text-gray-500 hover:border-green-200 hover:text-green-600')"
                            >
                                {{ confirmingToggle === plan.id
                                    ? (plan.is_active ? 'Confirmar' : 'Confirmar')
                                    : (plan.is_active ? 'Desactivar' : 'Activar') }}
                            </button>
                            <Link
                                :href="route('super.plans.edit', plan.id)"
                                class="inline-flex items-center gap-1 text-xs font-semibold px-2.5 py-1.5 rounded-lg border border-gray-200 text-gray-600 hover:bg-white hover:border-gray-300 transition-colors"
                            >
                                <span class="material-symbols-outlined text-sm">edit</span>
                                Editar
                            </Link>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Empty State -->
        <div v-if="plans.length === 0" class="bg-white rounded-xl border border-gray-100 shadow-sm p-16 text-center">
            <div class="w-14 h-14 rounded-2xl bg-orange-50 flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-[#FF5722] text-2xl" style="font-variation-settings:'FILL' 1">payments</span>
            </div>
            <h3 class="text-base font-semibold text-gray-900 mb-1">Sin planes registrados</h3>
            <p class="text-sm text-gray-500 mb-5">Crea tu primer plan para que los restaurantes puedan suscribirse.</p>
            <Link
                :href="route('super.plans.create')"
                class="inline-flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors"
            >
                <span class="material-symbols-outlined text-lg">add</span>
                Crear primer plan
            </Link>
        </div>
    </SuperAdminLayout>
</template>
