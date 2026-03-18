<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'
import TimePicker from '@/Components/TimePicker.vue'

const DAY_LABELS = ['D', 'L', 'M', 'Mi', 'J', 'V', 'S']

const imagePreview = ref(null)

const form = useForm({
    name: '',
    description: '',
    price: '',
    production_cost: '',
    image: null,
    is_active: true,
    active_days: [0, 1, 2, 3, 4, 5, 6],
    starts_at: '',
    ends_at: '',
    modifier_groups: [],
})

const allDay = computed({
    get() {
        return !form.starts_at && !form.ends_at
    },
    set(value) {
        if (value) {
            form.starts_at = ''
            form.ends_at = ''
        } else {
            form.starts_at = '09:00'
            form.ends_at = '21:00'
        }
    },
})

const IMAGE_MAX_MB = 5
const IMAGE_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp'

function handleImageChange(event) {
    const file = event.target.files[0]
    if (!file) { return }
    form.clearErrors('image')

    if (file.size > IMAGE_MAX_MB * 1024 * 1024) {
        form.setError('image', `La imagen no debe pesar mas de ${IMAGE_MAX_MB} MB.`)
        event.target.value = ''
        return
    }

    form.image = file
    imagePreview.value = URL.createObjectURL(file)
}

function toggleDay(dayIndex) {
    const pos = form.active_days.indexOf(dayIndex)
    if (pos === -1) {
        form.active_days.push(dayIndex)
    } else {
        form.active_days.splice(pos, 1)
    }
}

function addModifierGroup() {
    form.modifier_groups.push({
        id: null,
        name: '',
        selection_type: 'single',
        is_required: false,
        options: [{ id: null, name: '', price_adjustment: 0, production_cost: 0 }],
    })
}

function removeModifierGroup(index) {
    form.modifier_groups.splice(index, 1)
}

function addOption(groupIndex) {
    form.modifier_groups[groupIndex].options.push({ id: null, name: '', price_adjustment: 0, production_cost: 0 })
}

function removeOption(groupIndex, optionIndex) {
    form.modifier_groups[groupIndex].options.splice(optionIndex, 1)
}

function submit() {
    form.post(route('promotions.store'), {
        forceFormData: true,
    })
}
</script>

<template>
    <Head title="Nueva Promocion" />
    <AppLayout title="Nueva Promocion">

        <h1 class="text-2xl font-bold text-gray-900 mb-6">Nueva Promocion</h1>

        <div class="grid grid-cols-3 gap-6 pb-24">

            <!-- Left column (2/3) -->
            <div class="col-span-2 space-y-5">

                <!-- Informacion basica -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">info</span>
                        <h2 class="font-semibold text-gray-900">Informacion de la Promocion</h2>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Ej: 3 Tacos por $70"
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                :class="{ 'border-red-400': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Descripcion</label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                placeholder="Describe la promocion..."
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors resize-none"
                            />
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Precio de venta</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <input
                                        v-model="form.price"
                                        type="number"
                                        min="0.01"
                                        step="0.01"
                                        required
                                        placeholder="0.00"
                                        class="w-full rounded-xl border border-gray-200 pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                        :class="{ 'border-red-400': form.errors.price }"
                                    />
                                </div>
                                <p v-if="form.errors.price" class="mt-1 text-xs text-red-500">{{ form.errors.price }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">Costo de produccion</label>
                                <div class="relative">
                                    <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                    <input
                                        v-model="form.production_cost"
                                        type="number"
                                        min="0"
                                        step="0.01"
                                        placeholder="0.00"
                                        class="w-full rounded-xl border border-gray-200 pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                        :class="{ 'border-red-400': form.errors.production_cost }"
                                    />
                                </div>
                                <p v-if="form.errors.production_cost" class="mt-1 text-xs text-red-500">{{ form.errors.production_cost }}</p>
                                <p class="mt-1 text-xs text-gray-400">Opcional. Para calcular ganancia.</p>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Modificadores -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <div class="flex items-center justify-between mb-4">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">tune</span>
                            <h2 class="font-semibold text-gray-900">Modificadores</h2>
                        </div>
                        <button
                            type="button"
                            @click="addModifierGroup"
                            class="flex items-center gap-1.5 text-sm font-medium text-[#FF5722] hover:text-[#D84315] transition-colors"
                        >
                            <span class="material-symbols-outlined text-lg">add</span>
                            Agregar grupo
                        </button>
                    </div>

                    <p v-if="form.modifier_groups.length === 0" class="text-sm text-gray-400">
                        No hay grupos de modificadores. Agrega uno para ofrecer opciones como extras, tamaño, etc.
                    </p>

                    <div v-else class="space-y-4">
                        <div
                            v-for="(group, gi) in form.modifier_groups"
                            :key="gi"
                            class="border border-gray-200 rounded-xl p-4"
                        >
                            <div class="flex items-start gap-3 mb-3">
                                <div class="flex-1 space-y-3">
                                    <input
                                        v-model="group.name"
                                        type="text"
                                        placeholder="Nombre del grupo (ej: Tipo de salsa)"
                                        class="w-full rounded-xl border px-4 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                        :class="form.errors[`modifier_groups.${gi}.name`] ? 'border-red-400' : 'border-gray-200'"
                                    />
                                    <p v-if="form.errors[`modifier_groups.${gi}.name`]" class="text-xs text-red-500 mt-1">{{ form.errors[`modifier_groups.${gi}.name`] }}</p>
                                    <div class="flex items-center gap-4">
                                        <select
                                            v-model="group.selection_type"
                                            class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                        >
                                            <option value="single">Seleccion unica</option>
                                            <option value="multiple">Seleccion multiple</option>
                                        </select>
                                        <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer">
                                            <input
                                                type="checkbox"
                                                v-model="group.is_required"
                                                class="rounded border-gray-300 text-[#FF5722] focus:ring-[#FF5722]/30"
                                            />
                                            Obligatorio
                                        </label>
                                    </div>
                                </div>
                                <button
                                    type="button"
                                    @click="removeModifierGroup(gi)"
                                    class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors shrink-0"
                                    title="Eliminar grupo"
                                >
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                            </div>

                            <div class="space-y-2 ml-2">
                                <p v-if="form.errors[`modifier_groups.${gi}.options`]" class="text-xs text-red-500 mb-1">{{ form.errors[`modifier_groups.${gi}.options`] }}</p>
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Opciones</p>
                                <div class="flex items-center gap-2 mb-1">
                                    <span class="flex-1 text-xs text-gray-400">Nombre</span>
                                    <span class="w-24 text-xs text-gray-400">Precio</span>
                                    <span class="w-24 text-xs text-gray-400">Costo prod.</span>
                                    <span class="w-6"></span>
                                </div>
                                <div
                                    v-for="(option, oi) in group.options"
                                    :key="oi"
                                    class="flex items-center gap-2"
                                >
                                    <div class="flex-1">
                                        <input
                                            v-model="option.name"
                                            type="text"
                                            placeholder="Nombre de opcion"
                                            class="w-full rounded-lg border px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                            :class="form.errors[`modifier_groups.${gi}.options.${oi}.name`] ? 'border-red-400' : 'border-gray-200'"
                                        />
                                        <p v-if="form.errors[`modifier_groups.${gi}.options.${oi}.name`]" class="text-xs text-red-500 mt-0.5">{{ form.errors[`modifier_groups.${gi}.options.${oi}.name`] }}</p>
                                    </div>
                                    <div class="relative w-24">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                        <input
                                            v-model.number="option.price_adjustment"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full rounded-lg border border-gray-200 pl-6 pr-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                        />
                                    </div>
                                    <div class="relative w-24">
                                        <span class="absolute left-2.5 top-1/2 -translate-y-1/2 text-[#FF5722] text-xs">$</span>
                                        <input
                                            v-model.number="option.production_cost"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full rounded-lg border border-gray-200 pl-6 pr-2 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                        />
                                    </div>
                                    <button
                                        v-if="group.options.length > 1"
                                        type="button"
                                        @click="removeOption(gi, oi)"
                                        class="p-1 text-gray-400 hover:text-red-500 transition-colors shrink-0"
                                    >
                                        <span class="material-symbols-outlined text-base">close</span>
                                    </button>
                                    <div v-else class="w-6" />
                                </div>
                                <button
                                    type="button"
                                    @click="addOption(gi)"
                                    class="text-xs font-medium text-[#FF5722] hover:text-[#D84315] transition-colors mt-1"
                                >
                                    + Agregar opcion
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Right column (1/3) -->
            <div class="space-y-5">

                <!-- Imagen -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">Imagen</h2>
                    <div
                        class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:border-[#FF5722]/40 transition-colors cursor-pointer bg-orange-50/30"
                        @click="$refs.imageInput.click()"
                    >
                        <img v-if="imagePreview" :src="imagePreview" class="mx-auto h-28 w-28 object-cover rounded-xl mb-2" />
                        <div v-else class="flex flex-col items-center">
                            <span class="material-symbols-outlined text-gray-300 text-4xl mb-2" style="font-variation-settings:'FILL' 1">add_photo_alternate</span>
                            <p class="text-sm font-medium text-gray-600">Subir imagen</p>
                        </div>
                        <p class="text-xs text-gray-400 mt-1">JPG, PNG, GIF o WebP · Max 5 MB</p>
                        <input ref="imageInput" type="file" :accept="IMAGE_ACCEPT" class="hidden" @change="handleImageChange" />
                    </div>
                    <p v-if="form.errors.image" class="mt-1 text-xs text-red-500">{{ form.errors.image }}</p>
                </div>

                <!-- Horario -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">Horario de Activacion</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-2">Dias activos</label>
                            <div class="flex items-center gap-1.5">
                                <button
                                    v-for="(label, index) in DAY_LABELS"
                                    :key="index"
                                    type="button"
                                    @click="toggleDay(index)"
                                    class="w-9 h-9 rounded-full text-xs font-semibold transition-colors"
                                    :class="form.active_days.includes(index)
                                        ? 'bg-[#FF5722] text-white'
                                        : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                >
                                    {{ label }}
                                </button>
                            </div>
                            <p v-if="form.errors.active_days" class="mt-1 text-xs text-red-500">{{ form.errors.active_days }}</p>
                        </div>

                        <label class="flex items-center gap-2 cursor-pointer">
                            <input type="checkbox" v-model="allDay" class="rounded border-gray-300 text-[#FF5722] focus:ring-[#FF5722]/30" />
                            <span class="text-sm text-gray-700">Todo el dia</span>
                        </label>

                        <div v-if="!allDay" class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Hora inicio</label>
                                <TimePicker v-model="form.starts_at" placeholder="Inicio" />
                            </div>
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Hora fin</label>
                                <TimePicker v-model="form.ends_at" placeholder="Fin" />
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Estado -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">Estado</h2>
                    <div class="flex items-center gap-3 border border-gray-200 rounded-xl px-4 py-2.5">
                        <span class="text-sm text-gray-700">{{ form.is_active ? 'Promocion activa' : 'Promocion inactiva' }}</span>
                        <div class="ml-auto">
                            <ToggleSwitch v-model="form.is_active" />
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Bottom action bar -->
        <div class="fixed bottom-0 left-[260px] right-0 bg-white border-t border-gray-100 px-6 py-4 flex items-center justify-between z-10">
            <Link :href="route('promotions.index')" class="text-sm text-gray-500 hover:text-gray-700 font-medium">Cancelar</Link>
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
