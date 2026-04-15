<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'

const props = defineProps({
    orders_count: Number,
    orders_limit: Number,
    orders_limit_start: String,
    orders_limit_end: String,
    branch_count: Number,
    max_branches: Number,
    plan_name: String,
    billing_mode: String,
})

const ordersPercent = computed(() =>
    Math.min(100, Math.round((props.orders_count / props.orders_limit) * 100)),
)

const branchesPercent = computed(() =>
    Math.min(100, Math.round((props.branch_count / props.max_branches) * 100)),
)

function formatDate(dateStr) {
    if (!dateStr) { return '—' }
    return new Date(dateStr + 'T12:00:00').toLocaleDateString('es-MX', { day: 'numeric', month: 'short', year: 'numeric' })
}

function barClass(percent) {
    if (percent > 90) { return 'bg-red-500' }
    if (percent > 70) { return 'bg-amber-400' }
    return 'bg-green-500'
}

function alertClass(percent) {
    if (percent > 90) { return 'bg-red-50 border-red-200 text-red-700' }
    if (percent > 70) { return 'bg-amber-50 border-amber-200 text-amber-700' }
    return null
}
</script>

<template>
    <Head title="Mis Límites" />
    <AppLayout title="Configuración">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
            <p class="mt-1 text-sm text-gray-500">Gestiona la información y preferencias de tu restaurante.</p>
        </div>

        <SettingsLayout>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <div class="flex items-center justify-between mb-2">
                    <h2 class="text-lg font-bold text-gray-900">Mis límites</h2>
                    <span
                        class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium"
                        :class="billing_mode === 'subscription' ? 'bg-[#FF5722]/10 text-[#FF5722]' : 'bg-gray-100 text-gray-600'"
                    >
                        {{ billing_mode === 'subscription' ? (plan_name || 'Suscripción') : 'Límites manuales' }}
                    </span>
                </div>
                <p class="text-sm text-gray-500 mb-8">
                    {{ billing_mode === 'subscription'
                        ? 'Los límites están definidos por tu plan de suscripción.'
                        : 'Los límites son configurados por el administrador de PideAqui. Contáctanos si necesitas ampliarlos.' }}
                </p>

                <div class="space-y-8">

                    <!-- Pedidos del periodo -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-gray-500">receipt_long</span>
                                <span class="text-sm font-semibold text-gray-700">Pedidos del plan</span>
                            </div>
                            <span class="text-sm font-bold text-gray-900">{{ orders_count }} / {{ orders_limit }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="barClass(ordersPercent)"
                                :style="{ width: ordersPercent + '%' }"
                            ></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Periodo: {{ formatDate(orders_limit_start) }} — {{ formatDate(orders_limit_end) }}</p>
                        <div class="mt-3 px-3 py-2 rounded-lg bg-blue-50/50 border border-blue-100 flex items-start gap-2">
                            <span class="material-symbols-outlined text-blue-600 text-sm mt-0.5">info</span>
                            <p class="text-xs text-blue-800 leading-snug">
                                Este conteo considera <span class="font-semibold">solo los pedidos creados desde la app externa / menú digital</span>.
                                Las ventas generadas en el POS del restaurante <span class="font-semibold">son ilimitadas</span> y no cuentan al plan.
                            </p>
                        </div>
                        <div
                            v-if="alertClass(ordersPercent)"
                            class="mt-3 px-4 py-3 rounded-xl border text-sm font-medium"
                            :class="alertClass(ordersPercent)"
                        >
                            <span v-if="ordersPercent > 90">
                                Estás muy cerca del límite de pedidos.
                                {{ billing_mode === 'subscription' ? 'Considera actualizar tu plan.' : 'Contacta a soporte para ampliarlo.' }}
                            </span>
                            <span v-else>Estás superando el 70% de tu límite de pedidos.</span>
                        </div>
                    </div>

                    <div class="h-px bg-gray-100"></div>

                    <!-- Sucursales -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-gray-500">storefront</span>
                                <span class="text-sm font-semibold text-gray-700">Sucursales activas</span>
                            </div>
                            <span class="text-sm font-bold text-gray-900">{{ branch_count }} / {{ max_branches }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="barClass(branchesPercent)"
                                :style="{ width: branchesPercent + '%' }"
                            ></div>
                        </div>
                        <div
                            v-if="alertClass(branchesPercent)"
                            class="mt-3 px-4 py-3 rounded-xl border text-sm font-medium"
                            :class="alertClass(branchesPercent)"
                        >
                            <span v-if="branchesPercent >= 100">
                                Has alcanzado el límite de sucursales.
                                {{ billing_mode === 'subscription' ? 'Considera actualizar tu plan.' : 'Contacta a soporte para ampliarlo.' }}
                            </span>
                            <span v-else>Estás muy cerca del límite de sucursales.</span>
                        </div>
                    </div>

                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
