<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'
import { useDragSort } from '@/Composables/useDragSort'

const dnd = useDragSort()

const props = defineProps({
    templates: Array,
})

const showModal = ref(false)
const editingTemplate = ref(null)

const form = useForm({
    name: '',
    selection_type: 'single',
    is_required: false,
    max_selections: null,
    is_active: true,
    options: [{ id: null, name: '', price_adjustment: 0, production_cost: 0, is_active: true }],
})

function openCreate() {
    editingTemplate.value = null
    form.reset()
    form.clearErrors()
    form.options = [{ id: null, name: '', price_adjustment: 0, production_cost: 0, is_active: true }]
    showModal.value = true
}

function openEdit(template) {
    editingTemplate.value = template
    form.clearErrors()
    form.name = template.name
    form.selection_type = template.selection_type
    form.is_required = template.is_required
    form.max_selections = template.max_selections
    form.is_active = template.is_active
    form.options = (template.options || []).map(o => ({
        id: o.id,
        name: o.name,
        price_adjustment: parseFloat(o.price_adjustment) || 0,
        production_cost: parseFloat(o.production_cost) || 0,
        is_active: o.is_active !== false,
    }))
    showModal.value = true
}

function addOption() {
    form.options.push({ id: null, name: '', price_adjustment: 0, production_cost: 0, is_active: true })
}

function removeOption(index) {
    form.options.splice(index, 1)
}

function submit() {
    if (editingTemplate.value) {
        form.put(route('modifier-catalog.update', editingTemplate.value.id), {
            onSuccess: () => { showModal.value = false },
        })
    } else {
        form.post(route('modifier-catalog.store'), {
            onSuccess: () => { showModal.value = false },
        })
    }
}

function deleteTemplate(template) {
    if (confirm(`¿Eliminar el grupo "${template.name}"? Se desvinculará de todos los productos.`)) {
        router.delete(route('modifier-catalog.destroy', template.id))
    }
}

function toggleTemplate(template) {
    router.patch(route('modifier-catalog.toggle', template.id))
}
</script>

<template>
    <Head title="Catálogo de Modificadores" />
    <AppLayout title="Catálogo de Modificadores">

        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Catálogo de Modificadores</h1>
                <p class="text-sm text-gray-500 mt-1">Grupos reutilizables que puedes vincular a múltiples productos.</p>
            </div>
            <button
                @click="openCreate"
                class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm flex items-center gap-2 transition-colors"
            >
                <span class="material-symbols-outlined text-lg">add</span>
                Nuevo grupo
            </button>
        </div>

        <!-- Empty state -->
        <div v-if="templates.length === 0" class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
            <span class="material-symbols-outlined text-gray-300 text-5xl mb-3" style="font-variation-settings:'FILL' 1">tune</span>
            <p class="text-gray-500 font-medium">No hay grupos en el catálogo.</p>
            <p class="text-sm text-gray-400 mt-1">Crea un grupo reutilizable para vincularlo a múltiples productos.</p>
        </div>

        <!-- Templates list -->
        <div v-else class="space-y-3">
            <div
                v-for="template in templates"
                :key="template.id"
                class="bg-white rounded-2xl border border-gray-100 p-5"
                :class="{ 'opacity-50': !template.is_active }"
            >
                <div class="flex items-start justify-between">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-3 mb-2">
                            <h3 class="font-semibold text-gray-900">{{ template.name }}</h3>
                            <span class="text-xs px-2.5 py-0.5 rounded-full font-medium"
                                :class="template.selection_type === 'single'
                                    ? 'bg-blue-50 text-blue-600'
                                    : 'bg-purple-50 text-purple-600'"
                            >
                                {{ template.selection_type === 'single' ? 'Única' : 'Múltiple' }}
                            </span>
                            <span v-if="template.is_required" class="text-xs bg-red-50 text-red-600 px-2.5 py-0.5 rounded-full font-medium">
                                Obligatorio
                            </span>
                            <span v-if="template.max_selections" class="text-xs bg-gray-100 text-gray-600 px-2.5 py-0.5 rounded-full font-medium">
                                Máx. {{ template.max_selections }}
                            </span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <span
                                v-for="opt in template.options"
                                :key="opt.id"
                                class="text-xs bg-gray-50 border border-gray-200 text-gray-700 px-2.5 py-1 rounded-lg"
                                :class="{ 'line-through opacity-50': !opt.is_active }"
                            >
                                {{ opt.name }}
                                <span v-if="Number(opt.price_adjustment) > 0" class="text-[#FF5722] font-medium ml-1">+${{ Number(opt.price_adjustment).toFixed(2) }}</span>
                            </span>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 ml-4 shrink-0">
                        <ToggleSwitch :modelValue="template.is_active" @update:modelValue="toggleTemplate(template)" />
                        <button
                            @click="openEdit(template)"
                            class="p-2 text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 rounded-lg transition-colors"
                            title="Editar"
                        >
                            <span class="material-symbols-outlined text-lg">edit</span>
                        </button>
                        <button
                            @click="deleteTemplate(template)"
                            class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                            title="Eliminar"
                        >
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/40" @click="showModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-2xl max-h-[90vh] overflow-y-auto mx-4 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-bold text-gray-900">
                            {{ editingTemplate ? 'Editar grupo' : 'Nuevo grupo del catálogo' }}
                        </h2>
                        <button @click="showModal = false" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Name -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre del grupo</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="Ej: Tipo de tortilla, Extras, Tamaño"
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                :class="{ 'border-red-400': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <!-- Type + Required + Max -->
                        <div class="flex items-center gap-4 flex-wrap">
                            <div>
                                <label class="block text-xs font-medium text-gray-500 mb-1">Tipo de selección</label>
                                <select
                                    v-model="form.selection_type"
                                    class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                >
                                    <option value="single">Selección única</option>
                                    <option value="multiple">Selección múltiple</option>
                                </select>
                            </div>
                            <label class="flex items-center gap-2 text-sm text-gray-700 cursor-pointer mt-4">
                                <input
                                    type="checkbox"
                                    v-model="form.is_required"
                                    class="rounded border-gray-300 text-[#FF5722] focus:ring-[#FF5722]/30"
                                />
                                Obligatorio
                            </label>
                            <div v-if="form.selection_type === 'multiple'" class="mt-1">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Máx. selecciones</label>
                                <input
                                    v-model.number="form.max_selections"
                                    type="number"
                                    min="2"
                                    placeholder="Sin límite"
                                    class="w-28 rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                />
                            </div>
                        </div>

                        <!-- Options -->
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-xs font-medium text-gray-500 uppercase tracking-wide">Opciones</p>
                                <button
                                    type="button"
                                    @click="addOption"
                                    class="text-xs font-medium text-[#FF5722] hover:text-[#D84315]"
                                >
                                    + Agregar opción
                                </button>
                            </div>
                            <p v-if="form.options.length > 1" class="text-xs text-gray-400 mb-2">Arrastra para reordenar las opciones.</p>
                            <div class="flex items-center gap-2 mb-1 text-xs text-gray-400">
                                <span class="w-6"></span>
                                <span class="flex-1">Nombre</span>
                                <span class="w-24">Precio</span>
                                <span class="w-24">Costo prod.</span>
                                <span class="w-12 text-center">Activa</span>
                                <span class="w-6"></span>
                            </div>
                            <div class="space-y-2">
                                <div
                                    v-for="(option, oi) in form.options"
                                    :key="oi"
                                    class="flex items-center gap-2 rounded-lg transition-all"
                                    :class="[
                                        dnd.isOver('options', oi) ? 'ring-2 ring-[#FF5722]/40 bg-orange-50/50' : '',
                                        dnd.isDragging('options', oi) ? 'opacity-50' : '',
                                    ]"
                                    draggable="true"
                                    @dragstart="dnd.onDragStart('options', oi, $event)"
                                    @dragover="dnd.onDragOver('options', oi, $event)"
                                    @dragleave="dnd.onDragLeave('options', oi)"
                                    @drop="dnd.onDrop('options', oi, form.options, $event)"
                                    @dragend="dnd.onDragEnd"
                                >
                                    <span
                                        class="material-symbols-outlined text-gray-300 hover:text-gray-500 cursor-grab active:cursor-grabbing select-none w-6 text-base"
                                        title="Arrastra para reordenar"
                                    >drag_indicator</span>
                                    <div class="flex-1">
                                        <input
                                            v-model="option.name"
                                            type="text"
                                            placeholder="Nombre de opción"
                                            class="w-full rounded-lg border px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                            :class="form.errors[`options.${oi}.name`] ? 'border-red-400' : 'border-gray-200'"
                                        />
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
                                    <div class="w-12 flex justify-center">
                                        <input
                                            type="checkbox"
                                            v-model="option.is_active"
                                            class="rounded border-gray-300 text-[#FF5722] focus:ring-[#FF5722]/30"
                                        />
                                    </div>
                                    <button
                                        v-if="form.options.length > 1"
                                        type="button"
                                        @click="removeOption(oi)"
                                        class="p-1 text-gray-400 hover:text-red-500 transition-colors shrink-0"
                                    >
                                        <span class="material-symbols-outlined text-base">close</span>
                                    </button>
                                    <div v-else class="w-6" />
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Actions -->
                    <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button @click="showModal = false" class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2">
                            Cancelar
                        </button>
                        <button
                            @click="submit"
                            :disabled="form.processing"
                            class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-6 py-2.5 text-sm disabled:opacity-60"
                        >
                            {{ form.processing ? 'Guardando...' : (editingTemplate ? 'Actualizar' : 'Crear') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
