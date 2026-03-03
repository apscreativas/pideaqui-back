<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'

const props = defineProps({
    payment_methods: Array,
})

const TYPE_LABELS = {
    cash: 'Efectivo',
    terminal: 'Terminal física',
    transfer: 'Transferencia bancaria',
}

const TYPE_ICONS = {
    cash: 'payments',
    terminal: 'credit_card',
    transfer: 'account_balance',
}

const TYPE_DESCRIPTIONS = {
    cash: 'El cliente paga en efectivo al momento de la entrega o al recoger su pedido.',
    terminal: 'El repartidor lleva la terminal para cobrar con tarjeta al entregar.',
    transfer: 'El cliente realiza una transferencia bancaria antes o al confirmar su pedido.',
}

// One form per payment method
const forms = Object.fromEntries(
    props.payment_methods.map((pm) => [
        pm.id,
        useForm({
            is_active: pm.is_active,
            bank_name: pm.bank_name ?? '',
            account_holder: pm.account_holder ?? '',
            clabe: pm.clabe ?? '',
            alias: pm.alias ?? '',
        }),
    ]),
)

function save(pm) {
    forms[pm.id].put(route('settings.payment-methods.update', pm.id))
}
</script>

<template>
    <Head title="Métodos de Pago" />
    <AppLayout title="Configuración">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
            <p class="mt-1 text-sm text-gray-500">Gestiona la información y preferencias de tu restaurante.</p>
        </div>

        <SettingsLayout>
            <div class="space-y-4">
                <div
                    v-for="pm in payment_methods"
                    :key="pm.id"
                    class="bg-white rounded-xl border border-gray-100 shadow-sm p-6"
                >
                    <!-- Header row -->
                    <div class="flex items-center justify-between mb-1">
                        <div class="flex items-center gap-3">
                            <div class="p-2 bg-gray-50 rounded-lg">
                                <span class="material-symbols-outlined text-gray-600">{{ TYPE_ICONS[pm.type] }}</span>
                            </div>
                            <div>
                                <p class="text-sm font-bold text-gray-800">{{ TYPE_LABELS[pm.type] }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ TYPE_DESCRIPTIONS[pm.type] }}</p>
                            </div>
                        </div>
                        <!-- Toggle -->
                        <button
                            type="button"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            :class="forms[pm.id].is_active ? 'bg-[#FF5722]' : 'bg-gray-200'"
                            @click="forms[pm.id].is_active = !forms[pm.id].is_active"
                        >
                            <span
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                :class="forms[pm.id].is_active ? 'translate-x-5' : 'translate-x-0'"
                            ></span>
                        </button>
                    </div>

                    <!-- Error: cannot deactivate last method -->
                    <p v-if="forms[pm.id].errors.is_active" class="text-xs text-red-500 mt-2">{{ forms[pm.id].errors.is_active }}</p>

                    <!-- Bank fields (transfer only, shown when active) -->
                    <div
                        v-if="pm.type === 'transfer' && forms[pm.id].is_active"
                        class="mt-5 border-t border-gray-100 pt-5 grid grid-cols-1 sm:grid-cols-2 gap-4"
                    >
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Banco *</label>
                            <input
                                v-model="forms[pm.id].bank_name"
                                type="text"
                                placeholder="BBVA, Banamex, HSBC..."
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            />
                            <p v-if="forms[pm.id].errors.bank_name" class="text-xs text-red-500 mt-1">{{ forms[pm.id].errors.bank_name }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Titular de la cuenta *</label>
                            <input
                                v-model="forms[pm.id].account_holder"
                                type="text"
                                placeholder="Nombre completo"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            />
                            <p v-if="forms[pm.id].errors.account_holder" class="text-xs text-red-500 mt-1">{{ forms[pm.id].errors.account_holder }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">CLABE interbancaria (16 o 18 dígitos) *</label>
                            <input
                                v-model="forms[pm.id].clabe"
                                type="text"
                                maxlength="18"
                                placeholder="0000000000000000"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            />
                            <p v-if="forms[pm.id].errors.clabe" class="text-xs text-red-500 mt-1">{{ forms[pm.id].errors.clabe }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-600 mb-1">Alias / Oxxo Pay (opcional)</label>
                            <input
                                v-model="forms[pm.id].alias"
                                type="text"
                                placeholder="Número de teléfono o alias"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            />
                        </div>
                    </div>

                    <div class="flex justify-end mt-4">
                        <button
                            type="button"
                            :disabled="forms[pm.id].processing"
                            class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2 text-sm transition-colors disabled:opacity-60"
                            @click="save(pm)"
                        >
                            {{ forms[pm.id].processing ? 'Guardando...' : 'Guardar' }}
                        </button>
                    </div>
                </div>

                <p v-if="!payment_methods.length" class="text-sm text-gray-400 text-center py-8">
                    No hay métodos de pago configurados.
                </p>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
