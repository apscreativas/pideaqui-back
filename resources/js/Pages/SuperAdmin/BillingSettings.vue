<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    settings: Object,
})

const form = useForm({
    initial_grace_period_days: props.settings.initial_grace_period_days ?? 14,
    payment_grace_period_days: props.settings.payment_grace_period_days ?? 7,
    reminder_days_before_expiry: String(props.settings.reminder_days_before_expiry ?? '3,1'),
})

function submit() {
    form.put(route('super.billing-settings.update'))
}
</script>

<template>
    <Head title="SuperAdmin — Configuración de Billing" />
    <SuperAdminLayout>
        <!-- Header -->
        <div class="mb-8">
            <h1 class="text-2xl font-bold text-gray-900">Configuración de Billing</h1>
            <p class="mt-1 text-sm text-gray-500">Parámetros globales del sistema de suscripciones.</p>
        </div>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-5xl">

                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Grace Periods -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-base text-gray-400">schedule</span>
                                <h2 class="text-sm font-semibold text-gray-900">Periodos de gracia</h2>
                            </div>
                        </div>
                        <div class="p-6 space-y-6">
                            <div class="grid grid-cols-2 gap-6">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Gracia inicial</label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model.number="form.initial_grace_period_days"
                                            type="number"
                                            min="1"
                                            max="90"
                                            class="w-24 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                        />
                                        <span class="text-sm text-gray-500">días</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">
                                        Tiempo que un restaurante nuevo puede operar antes de elegir un plan y pagar.
                                    </p>
                                    <p v-if="form.errors.initial_grace_period_days" class="text-xs text-red-500 mt-1">{{ form.errors.initial_grace_period_days }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Gracia por impago</label>
                                    <div class="flex items-center gap-2">
                                        <input
                                            v-model.number="form.payment_grace_period_days"
                                            type="number"
                                            min="1"
                                            max="30"
                                            class="w-24 border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-center focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                        />
                                        <span class="text-sm text-gray-500">días</span>
                                    </div>
                                    <p class="text-xs text-gray-400 mt-2 leading-relaxed">
                                        Días adicionales después de que Stripe agota reintentos de cobro, antes de suspender la cuenta.
                                    </p>
                                    <p v-if="form.errors.payment_grace_period_days" class="text-xs text-red-500 mt-1">{{ form.errors.payment_grace_period_days }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reminders -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <div class="flex items-center gap-2">
                                <span class="material-symbols-outlined text-base text-gray-400">notifications</span>
                                <h2 class="text-sm font-semibold text-gray-900">Recordatorios por email</h2>
                            </div>
                        </div>
                        <div class="p-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Días de anticipación</label>
                                <input
                                    v-model="form.reminder_days_before_expiry"
                                    type="text"
                                    placeholder="7,3,1"
                                    class="w-full max-w-xs border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p class="text-xs text-gray-400 mt-2 leading-relaxed">
                                    Se enviará un email de recordatorio X días antes de que venza el periodo de gracia.
                                    Separa con comas para múltiples recordatorios.
                                </p>
                                <div class="mt-3 bg-gray-50 rounded-lg px-3 py-2">
                                    <p class="text-xs text-gray-500">
                                        Ejemplo: <code class="bg-white px-1.5 py-0.5 rounded text-gray-700 font-mono text-xs">7,3,1</code>
                                        — envía recordatorios a 7, 3 y 1 día del vencimiento.
                                    </p>
                                </div>
                                <p v-if="form.errors.reminder_days_before_expiry" class="text-xs text-red-500 mt-1">{{ form.errors.reminder_days_before_expiry }}</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column -->
                <div class="space-y-6">
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors disabled:opacity-60 shadow-sm"
                        >
                            {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                        </button>
                    </div>

                    <!-- Info -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                        <div class="flex items-center gap-2 mb-3">
                            <span class="material-symbols-outlined text-base text-gray-400">info</span>
                            <h3 class="text-sm font-semibold text-gray-900">Sobre estos ajustes</h3>
                        </div>
                        <div class="space-y-3 text-xs text-gray-500 leading-relaxed">
                            <p>
                                La <strong class="text-gray-700">gracia inicial</strong> aplica a todos los restaurantes nuevos creados desde este panel.
                            </p>
                            <p>
                                La <strong class="text-gray-700">gracia por impago</strong> se activa automáticamente cuando Stripe no puede cobrar después de varios reintentos.
                            </p>
                            <p>
                                Los <strong class="text-gray-700">recordatorios</strong> se envían por email a los administradores del restaurante antes de que su periodo de gracia expire.
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </SuperAdminLayout>
</template>
