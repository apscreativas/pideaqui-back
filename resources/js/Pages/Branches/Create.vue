<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import MapPicker from '@/Components/MapPicker.vue'

const form = useForm({
    name: '',
    address: '',
    latitude: '',
    longitude: '',
    whatsapp: '',
    is_active: true,
})

function submit() {
    form.post(route('branches.store'))
}
</script>

<template>
    <Head title="Nueva Sucursal" />
    <AppLayout title="Nueva Sucursal">

        <h1 class="text-2xl font-bold text-gray-900 mb-6">Nueva Sucursal</h1>

        <div class="max-w-2xl pb-24">
            <div class="bg-white rounded-2xl border border-gray-100 p-6">
                <form @submit.prevent="submit" class="space-y-5">

                    <!-- Nombre -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre de la sucursal *</label>
                        <input
                            v-model="form.name"
                            type="text"
                            required
                            placeholder="Ej: Sucursal Centro"
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-400': form.errors.name }"
                        />
                        <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                    </div>

                    <!-- Dirección -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Dirección</label>
                        <input
                            v-model="form.address"
                            type="text"
                            placeholder="Ej: Av. Reforma 123, Centro Histórico, CDMX"
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                        />
                    </div>

                    <!-- WhatsApp -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">WhatsApp *</label>
                        <input
                            v-model="form.whatsapp"
                            type="tel"
                            inputmode="numeric"
                            pattern="[0-9]{10}"
                            maxlength="10"
                            required
                            name="whatsapp"
                            autocomplete="tel-national"
                            placeholder="5512345678"
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/30 focus-visible:border-[#FF5722] transition-colors"
                            :class="{ 'border-red-400': form.errors.whatsapp }"
                        />
                        <p v-if="form.errors.whatsapp" class="mt-1 text-xs text-red-500">{{ form.errors.whatsapp }}</p>
                        <p class="text-xs text-gray-400 mt-1">10 dígitos sin espacios ni guiones. Ej: 5512345678</p>
                    </div>

                    <!-- Coordenadas -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Ubicación en el mapa</label>
                        <MapPicker
                            :lat="form.latitude"
                            :lng="form.longitude"
                            @update:lat="form.latitude = $event"
                            @update:lng="form.longitude = $event"
                        />
                        <div class="grid grid-cols-2 gap-4 mt-3">
                            <div>
                                <input
                                    v-model="form.latitude"
                                    type="number"
                                    step="any"
                                    placeholder="Latitud (ej: 19.4326)"
                                    class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                    :class="{ 'border-red-400': form.errors.latitude }"
                                />
                                <p v-if="form.errors.latitude" class="mt-1 text-xs text-red-500">{{ form.errors.latitude }}</p>
                            </div>
                            <div>
                                <input
                                    v-model="form.longitude"
                                    type="number"
                                    step="any"
                                    placeholder="Longitud (ej: -99.1332)"
                                    class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                    :class="{ 'border-red-400': form.errors.longitude }"
                                />
                                <p v-if="form.errors.longitude" class="mt-1 text-xs text-red-500">{{ form.errors.longitude }}</p>
                            </div>
                        </div>
                        <div class="flex items-start gap-2 mt-2 bg-blue-50 rounded-xl px-3 py-2">
                            <span class="material-symbols-outlined text-blue-500 text-base mt-0.5" style="font-variation-settings:'FILL' 1">info</span>
                            <p class="text-xs text-blue-700">Haz clic en el mapa o arrastra el pin para ajustar la ubicación. También puedes escribir las coordenadas manualmente.</p>
                        </div>
                    </div>

                    <!-- Estado -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado inicial</label>
                        <div
                            class="flex items-center gap-3 border border-gray-200 rounded-xl px-4 py-3 cursor-pointer hover:border-gray-300 transition-colors"
                            @click="form.is_active = !form.is_active"
                        >
                            <span class="text-sm text-gray-700">{{ form.is_active ? 'Sucursal activa (visible para clientes)' : 'Sucursal inactiva (oculta para clientes)' }}</span>
                            <div class="ml-auto w-10 h-6 rounded-full transition-colors relative"
                                :class="form.is_active ? 'bg-[#FF5722]' : 'bg-gray-200'">
                                <div class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all"
                                    :class="form.is_active ? 'left-5' : 'left-1'" />
                            </div>
                        </div>
                    </div>

                </form>
            </div>
        </div>

        <!-- Bottom action bar -->
        <div class="fixed bottom-0 left-[260px] right-0 bg-white border-t border-gray-100 px-6 py-4 flex items-center justify-between z-10">
            <Link :href="route('branches.index')" class="text-sm text-gray-500 hover:text-gray-700 font-medium">Cancelar</Link>
            <button
                @click="submit"
                :disabled="form.processing"
                class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-6 py-2.5 text-sm disabled:opacity-60"
            >
                {{ form.processing ? 'Guardando...' : 'Guardar' }}
            </button>
        </div>

    </AppLayout>
</template>
