<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'

const props = defineProps({
    restaurant: Object,
    has_delivery_ranges: Boolean,
})

const form = useForm({
    allows_delivery: props.has_delivery_ranges ? (props.restaurant.allows_delivery ?? true) : false,
    allows_pickup: props.restaurant.allows_pickup ?? true,
    allows_dine_in: props.restaurant.allows_dine_in ?? false,
})

const flash = computed(() => usePage().props.flash)

function submit() {
    form.put(route('settings.delivery-methods.update'))
}

const METHODS = [
    {
        key: 'allows_delivery',
        label: 'Entrega a domicilio',
        description: 'Tus clientes reciben el pedido en su direccion. Se calcula el costo de envio segun la distancia.',
        icon: 'two_wheeler',
    },
    {
        key: 'allows_pickup',
        label: 'Recoger en sucursal',
        description: 'Tus clientes recogen su pedido en la sucursal mas cercana. Sin costo de envio.',
        icon: 'store',
    },
    {
        key: 'allows_dine_in',
        label: 'Comer en el lugar',
        description: 'Tus clientes consumen en el establecimiento. Ideal para pedidos en mesa.',
        icon: 'restaurant',
    },
]

const activeCount = computed(() => {
    return [form.allows_delivery, form.allows_pickup, form.allows_dine_in].filter(Boolean).length
})
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
                <h2 class="text-lg font-bold text-gray-900 mb-1">Metodos de entrega</h2>
                <p class="text-sm text-gray-500 mb-6">Define como pueden recibir los pedidos tus clientes. Los metodos activos apareceran como opciones al momento de ordenar.</p>

                <!-- Success flash -->
                <div
                    v-if="flash?.success"
                    class="flex items-center gap-2 mb-4 px-4 py-2.5 bg-green-50 border border-green-100 rounded-xl"
                    aria-live="polite"
                >
                    <span class="material-symbols-outlined text-green-600 text-lg" aria-hidden="true">check_circle</span>
                    <p class="text-sm text-green-700">{{ flash.success }}</p>
                </div>

                <form @submit.prevent="submit" class="space-y-3">
                    <div
                        v-for="method in METHODS"
                        :key="method.key"
                        class="flex items-start justify-between gap-4 p-4 rounded-xl border transition-colors"
                        :class="[
                            form[method.key]
                                ? 'border-[#FF5722]/20 bg-orange-50/30'
                                : 'border-gray-100 hover:bg-gray-50/50',
                            method.key === 'allows_delivery' && !has_delivery_ranges ? 'opacity-70' : '',
                        ]"
                    >
                        <div class="flex items-start gap-3">
                            <div class="p-2 rounded-lg mt-0.5" :class="form[method.key] ? 'bg-[#FF5722]/10' : 'bg-gray-100'">
                                <span class="material-symbols-outlined" :class="form[method.key] ? 'text-[#FF5722]' : 'text-gray-400'" aria-hidden="true">{{ method.icon }}</span>
                            </div>
                            <div>
                                <div class="flex items-center gap-2">
                                    <p class="text-sm font-semibold text-gray-800">{{ method.label }}</p>
                                    <span
                                        class="text-[10px] font-semibold uppercase tracking-wide px-1.5 py-0.5 rounded-full"
                                        :class="form[method.key]
                                            ? 'bg-green-50 text-green-600'
                                            : 'bg-gray-100 text-gray-400'"
                                    >
                                        {{ form[method.key] ? 'Activo' : 'Inactivo' }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5">{{ method.description }}</p>
                                <p
                                    v-if="method.key === 'allows_delivery' && !has_delivery_ranges"
                                    class="text-xs text-amber-600 mt-1.5 flex items-center gap-1"
                                >
                                    <span class="material-symbols-outlined text-sm" aria-hidden="true">warning</span>
                                    Primero
                                    <Link :href="route('settings.shipping-rates')" class="underline font-medium hover:text-amber-700">configura tus tarifas de envio</Link>
                                    para poder activar esta opcion.
                                </p>
                            </div>
                        </div>
                        <!-- Toggle -->
                        <ToggleSwitch
                            v-model="form[method.key]"
                            :disabled="method.key === 'allows_delivery' && !has_delivery_ranges"
                        />
                    </div>

                    <!-- Validation errors -->
                    <div v-if="form.errors.allows_delivery" class="flex items-center gap-2 px-4 py-2.5 bg-red-50 border border-red-100 rounded-xl" aria-live="polite">
                        <span class="material-symbols-outlined text-red-500 text-lg shrink-0" aria-hidden="true">error</span>
                        <p class="text-sm text-red-600">{{ form.errors.allows_delivery }}</p>
                    </div>

                    <!-- Minimum methods hint -->
                    <p v-if="activeCount === 1" class="text-xs text-amber-600 flex items-center gap-1 px-1">
                        <span class="material-symbols-outlined text-sm" aria-hidden="true">info</span>
                        Solo tienes un metodo activo. Si lo desactivas, no podras recibir pedidos.
                    </p>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition-colors disabled:opacity-60"
                        >
                            {{ form.processing ? 'Guardando…' : 'Guardar cambios' }}
                        </button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
