<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'

const props = defineProps({
    ranges: Array,
})

// Add range form
const addForm = useForm({
    min_km: '',
    max_km: '',
    price: '',
})

function addRange() {
    addForm.post(route('settings.shipping-rates.store'), {
        onSuccess: () => addForm.reset(),
    })
}

// Inline editing
const editing = ref(null)
const editForm = useForm({ min_km: '', max_km: '', price: '' })

function startEdit(range) {
    editing.value = range.id
    editForm.min_km = range.min_km
    editForm.max_km = range.max_km
    editForm.price = range.price
}

function saveEdit(rangeId) {
    editForm.put(route('settings.shipping-rates.update', rangeId), {
        onSuccess: () => { editing.value = null },
    })
}

function cancelEdit() {
    editing.value = null
}

function deleteRange(rangeId) {
    if (!confirm('¿Eliminar este rango?')) { return }
    router.delete(route('settings.shipping-rates.destroy', rangeId))
}

function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}
</script>

<template>
    <Head title="Tarifas de Envío" />
    <AppLayout title="Configuración">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
            <p class="mt-1 text-sm text-gray-500">Gestiona la información y preferencias de tu restaurante.</p>
        </div>

        <SettingsLayout>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-2">Tarifas de envío</h2>
                <p class="text-sm text-gray-500 mb-6">
                    Define rangos de distancia con precio fijo. El último rango define la cobertura máxima.
                </p>

                <!-- Ranges table -->
                <div class="rounded-xl border border-gray-100 overflow-hidden mb-6">
                    <table class="w-full text-left">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Desde (km)</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Hasta (km)</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider">Precio</th>
                                <th class="px-4 py-3 text-xs font-semibold text-gray-500 uppercase tracking-wider text-right">Acciones</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <tr v-for="range in ranges" :key="range.id" class="hover:bg-gray-50/50">
                                <!-- Editing row -->
                                <template v-if="editing === range.id">
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="editForm.min_km"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-24 border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="editForm.max_km"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-24 border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                        />
                                    </td>
                                    <td class="px-4 py-3">
                                        <input
                                            v-model="editForm.price"
                                            type="number"
                                            step="0.01"
                                            min="0"
                                            class="w-24 border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                        />
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-2">
                                            <button @click="saveEdit(range.id)" class="text-xs font-semibold text-[#FF5722] hover:underline">Guardar</button>
                                            <button @click="cancelEdit" class="text-xs font-semibold text-gray-400 hover:text-gray-600">Cancelar</button>
                                        </div>
                                    </td>
                                </template>
                                <!-- Normal row -->
                                <template v-else>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ range.min_km }} km</td>
                                    <td class="px-4 py-3 text-sm text-gray-700">{{ range.max_km }} km</td>
                                    <td class="px-4 py-3 text-sm font-medium text-gray-900">{{ formatPrice(range.price) }}</td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="flex items-center justify-end gap-3">
                                            <button @click="startEdit(range)" class="text-gray-400 hover:text-[#FF5722] transition-colors">
                                                <span class="material-symbols-outlined text-lg">edit</span>
                                            </button>
                                            <button @click="deleteRange(range.id)" class="text-gray-400 hover:text-red-500 transition-colors">
                                                <span class="material-symbols-outlined text-lg">delete</span>
                                            </button>
                                        </div>
                                    </td>
                                </template>
                            </tr>
                            <tr v-if="!ranges.length">
                                <td colspan="4" class="px-4 py-8 text-center text-sm text-gray-400">
                                    Sin rangos configurados. El envío no tendrá costo ni límite de distancia.
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Add range form -->
                <div class="border-t border-gray-100 pt-6">
                    <h3 class="text-sm font-semibold text-gray-700 mb-4">Agregar rango</h3>
                    <form @submit.prevent="addRange" class="flex flex-wrap items-start gap-4">
                        <div class="relative pb-5">
                            <label class="block text-xs text-gray-500 mb-1">Desde (km)</label>
                            <input
                                v-model="addForm.min_km"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="0"
                                class="w-28 border rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                :class="addForm.errors.min_km ? 'border-red-300' : 'border-gray-200'"
                            />
                            <p v-if="addForm.errors.min_km" class="absolute bottom-0 left-0 text-xs text-red-500 truncate max-w-[7rem]" :title="addForm.errors.min_km">{{ addForm.errors.min_km }}</p>
                        </div>
                        <div class="relative pb-5">
                            <label class="block text-xs text-gray-500 mb-1">Hasta (km)</label>
                            <input
                                v-model="addForm.max_km"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="5"
                                class="w-28 border rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                :class="addForm.errors.max_km ? 'border-red-300' : 'border-gray-200'"
                            />
                            <p v-if="addForm.errors.max_km" class="absolute bottom-0 left-0 text-xs text-red-500 truncate max-w-[7rem]" :title="addForm.errors.max_km">{{ addForm.errors.max_km }}</p>
                        </div>
                        <div class="relative pb-5">
                            <label class="block text-xs text-gray-500 mb-1">Precio ($)</label>
                            <input
                                v-model="addForm.price"
                                type="number"
                                step="0.01"
                                min="0"
                                placeholder="30"
                                class="w-28 border rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                :class="addForm.errors.price ? 'border-red-300' : 'border-gray-200'"
                            />
                            <p v-if="addForm.errors.price" class="absolute bottom-0 left-0 text-xs text-red-500 truncate max-w-[7rem]" :title="addForm.errors.price">{{ addForm.errors.price }}</p>
                        </div>
                        <div class="pt-5">
                            <button
                                type="submit"
                                :disabled="addForm.processing"
                                class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors disabled:opacity-60"
                            >
                                <span class="material-symbols-outlined text-lg">add</span>
                                Agregar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
