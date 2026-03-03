<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'

const props = defineProps({
    restaurant: Object,
    has_delivery_ranges: Boolean,
})

const form = useForm({
    allows_delivery: props.has_delivery_ranges ? (props.restaurant.allows_delivery ?? true) : false,
    allows_pickup: props.restaurant.allows_pickup ?? true,
    allows_dine_in: props.restaurant.allows_dine_in ?? false,
})

function submit() {
    form.put(route('settings.delivery-methods.update'))
}

const METHODS = [
    {
        key: 'allows_delivery',
        label: 'Entrega a domicilio',
        description: 'El cliente recibe su pedido en su dirección. Activa el flujo de geolocalización y cálculo de envío.',
        icon: 'two_wheeler',
    },
    {
        key: 'allows_pickup',
        label: 'Recoger en sucursal',
        description: 'El cliente recoge su pedido directamente en la sucursal más cercana.',
        icon: 'store',
    },
    {
        key: 'allows_dine_in',
        label: 'Comer en el lugar',
        description: 'El cliente consume en el establecimiento. Ideal para pedidos en mesa.',
        icon: 'restaurant',
    },
]
</script>

<template>
    <Head title="Métodos de Entrega" />
    <AppLayout title="Configuración">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
            <p class="mt-1 text-sm text-gray-500">Gestiona la información y preferencias de tu restaurante.</p>
        </div>

        <SettingsLayout>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2">Métodos de entrega</h2>
                <p class="text-sm text-gray-500 mb-6">Al menos un método debe estar activo para poder recibir pedidos.</p>

                <form @submit.prevent="submit" class="space-y-4">
                    <div
                        v-for="method in METHODS"
                        :key="method.key"
                        class="flex items-start justify-between gap-4 p-4 rounded-xl border border-gray-100 hover:bg-gray-50/50 transition-colors"
                    >
                        <div class="flex items-start gap-3">
                            <div class="p-2 bg-[#FF5722]/10 rounded-lg mt-0.5">
                                <span class="material-symbols-outlined text-[#FF5722]">{{ method.icon }}</span>
                            </div>
                            <div>
                                <p class="text-sm font-semibold text-gray-800">{{ method.label }}</p>
                                <p class="text-xs text-gray-500 mt-0.5">{{ method.description }}</p>
                                <p
                                    v-if="method.key === 'allows_delivery' && !has_delivery_ranges"
                                    class="text-xs text-amber-600 mt-1 flex items-center gap-1"
                                >
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">warning</span>
                                    Configura al menos una tarifa de envío para activar esta opción.
                                </p>
                            </div>
                        </div>
                        <!-- Toggle -->
                        <button
                            type="button"
                            class="relative inline-flex h-6 w-11 shrink-0 rounded-full border-2 border-transparent transition-colors focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            :class="[
                                form[method.key] ? 'bg-[#FF5722]' : 'bg-gray-200',
                                method.key === 'allows_delivery' && !has_delivery_ranges ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
                            ]"
                            :disabled="method.key === 'allows_delivery' && !has_delivery_ranges"
                            @click="form[method.key] = !form[method.key]"
                        >
                            <span
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition duration-200 ease-in-out"
                                :class="form[method.key] ? 'translate-x-5' : 'translate-x-0'"
                            ></span>
                        </button>
                    </div>

                    <p v-if="form.errors.allows_delivery" class="text-xs text-red-500">{{ form.errors.allows_delivery }}</p>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition-colors disabled:opacity-60"
                        >
                            {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                        </button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
