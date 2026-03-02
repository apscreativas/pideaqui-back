<script setup>
import { Head } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'

const props = defineProps({
    monthly_orders_count: Number,
    max_monthly_orders: Number,
    branch_count: Number,
    max_branches: Number,
})

const ordersPercent = computed(() =>
    Math.min(100, Math.round((props.monthly_orders_count / props.max_monthly_orders) * 100)),
)

const branchesPercent = computed(() =>
    Math.min(100, Math.round((props.branch_count / props.max_branches) * 100)),
)

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
                <h2 class="text-lg font-bold text-gray-900 mb-2">Mis límites del plan</h2>
                <p class="text-sm text-gray-500 mb-8">
                    Los límites son configurados por el administrador de GuisoGo. Contáctanos si necesitas ampliarlos.
                </p>

                <div class="space-y-8">

                    <!-- Pedidos mensuales -->
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-gray-500">receipt_long</span>
                                <span class="text-sm font-semibold text-gray-700">Pedidos mensuales</span>
                            </div>
                            <span class="text-sm font-bold text-gray-900">{{ monthly_orders_count }} / {{ max_monthly_orders }}</span>
                        </div>
                        <div class="w-full bg-gray-100 rounded-full h-3 overflow-hidden">
                            <div
                                class="h-full rounded-full transition-all"
                                :class="barClass(ordersPercent)"
                                :style="{ width: ordersPercent + '%' }"
                            ></div>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">Se reinicia automáticamente el día 1 de cada mes.</p>
                        <div
                            v-if="alertClass(ordersPercent)"
                            class="mt-3 px-4 py-3 rounded-xl border text-sm font-medium"
                            :class="alertClass(ordersPercent)"
                        >
                            <span v-if="ordersPercent > 90">⚠️ Estás muy cerca del límite mensual. Contacta a soporte para ampliarlo.</span>
                            <span v-else>Estás superando el 70% de tu límite mensual.</span>
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
                            <span v-if="branchesPercent >= 100">Has alcanzado el límite de sucursales. Contacta a soporte para ampliar tu plan.</span>
                            <span v-else>Estás muy cerca del límite de sucursales.</span>
                        </div>
                    </div>

                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
