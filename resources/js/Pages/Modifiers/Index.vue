<script setup>
import { Head, useForm, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    modifierGroups: Array,
})

const showNewGroupForm = ref(false)
const editingGroupId = ref(null)
const editingOptionId = ref(null)
const newOptionForms = ref({})

const newGroupForm = useForm({
    name: '',
    selection_type: 'single',
    is_required: false,
    sort_order: 0,
})

function submitNewGroup() {
    newGroupForm.post(route('modifiers.store'), {
        onSuccess: () => {
            newGroupForm.reset()
            showNewGroupForm.value = false
        },
    })
}

// Edit group inline
const editGroupForm = useForm({})
function startEditGroup(group) {
    editingGroupId.value = group.id
    editGroupForm.name = group.name
    editGroupForm.selection_type = group.selection_type
    editGroupForm.is_required = group.is_required
    editGroupForm.sort_order = group.sort_order
}

function submitEditGroup(group) {
    const f = useForm({
        name: editGroupForm.name,
        selection_type: editGroupForm.selection_type,
        is_required: editGroupForm.is_required,
        sort_order: editGroupForm.sort_order,
    })
    f.put(route('modifiers.update', group.id), {
        onSuccess: () => { editingGroupId.value = null },
    })
}

function deleteGroup(group) {
    if (!confirm(`Eliminar el grupo "${group.name}" y todas sus opciones?`)) { return }
    router.delete(route('modifiers.destroy', group.id))
}

// Edit option inline
const editOptionForm = ref({})
function startEditOption(option) {
    editingOptionId.value = option.id
    editOptionForm.value = { name: option.name, price_adjustment: option.price_adjustment, sort_order: option.sort_order }
}

function submitEditOption(group, option) {
    const f = useForm({
        name: editOptionForm.value.name,
        price_adjustment: editOptionForm.value.price_adjustment,
        sort_order: editOptionForm.value.sort_order,
    })
    f.put(route('modifiers.options.update', { modifierGroup: group.id, modifierOption: option.id }), {
        onSuccess: () => { editingOptionId.value = null },
    })
}

function initNewOptionForm(groupId) {
    newOptionForms.value[groupId] = useForm({
        name: '',
        price_adjustment: 0,
        sort_order: 0,
    })
}

function submitNewOption(groupId) {
    const f = newOptionForms.value[groupId]
    if (!f) { return }
    f.post(route('modifiers.options.store', groupId), {
        onSuccess: () => {
            delete newOptionForms.value[groupId]
        },
    })
}

function deleteOption(group, option) {
    if (!confirm(`Eliminar la opcion "${option.name}"?`)) { return }
    router.delete(route('modifiers.options.destroy', { modifierGroup: group.id, modifierOption: option.id }))
}
</script>

<template>
    <Head title="Modificadores" />
    <AppLayout title="Modificadores">

        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Modificadores</h1>
                <p class="mt-1 text-sm text-gray-500">Gestiona grupos de opciones reutilizables para tus productos.</p>
            </div>
            <button
                @click="showNewGroupForm = !showNewGroupForm"
                class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors"
            >
                <span class="material-symbols-outlined text-lg">add</span>
                Nuevo grupo
            </button>
        </div>

        <!-- New group form -->
        <div v-if="showNewGroupForm" class="bg-white rounded-2xl border border-gray-100 p-6 mb-4">
            <h2 class="font-semibold text-gray-900 mb-4">Nuevo Grupo de Modificadores</h2>
            <form @submit.prevent="submitNewGroup" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre del grupo</label>
                        <input
                            v-model="newGroupForm.name"
                            type="text"
                            required
                            placeholder="Ej: Elige tu tortilla"
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                        />
                        <p v-if="newGroupForm.errors.name" class="mt-1 text-xs text-red-500">{{ newGroupForm.errors.name }}</p>
                    </div>
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo de seleccion</label>
                        <select
                            v-model="newGroupForm.selection_type"
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                        >
                            <option value="single">Seleccion unica</option>
                            <option value="multiple">Seleccion multiple</option>
                        </select>
                    </div>
                </div>
                <div class="flex items-center gap-4">
                    <div
                        class="flex items-center gap-3 border border-gray-200 rounded-xl px-4 py-2.5 cursor-pointer"
                        @click="newGroupForm.is_required = !newGroupForm.is_required"
                    >
                        <span class="text-sm text-gray-700">{{ newGroupForm.is_required ? 'Obligatorio' : 'Opcional' }}</span>
                        <div class="w-10 h-6 rounded-full transition-colors relative"
                            :class="newGroupForm.is_required ? 'bg-[#FF5722]' : 'bg-gray-200'">
                            <div class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all"
                                :class="newGroupForm.is_required ? 'left-5' : 'left-1'" />
                        </div>
                    </div>
                </div>
                <div class="flex gap-3">
                    <button
                        type="button"
                        @click="showNewGroupForm = false"
                        class="border border-gray-200 text-gray-700 font-semibold rounded-xl px-4 py-2.5 text-sm hover:bg-gray-50 transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        :disabled="newGroupForm.processing"
                        class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors disabled:opacity-60"
                    >
                        Crear Grupo
                    </button>
                </div>
            </form>
        </div>

        <!-- Empty state -->
        <div v-if="modifierGroups.length === 0" class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
            <span class="material-symbols-outlined text-4xl text-gray-300 mb-3" style="font-variation-settings:'FILL' 1">tune</span>
            <p class="text-gray-500 font-medium mb-1">No hay grupos de modificadores</p>
            <p class="text-sm text-gray-400">Crea grupos para agregar opciones personalizables a tus productos.</p>
        </div>

        <!-- Groups list -->
        <div v-else class="space-y-4">
            <div
                v-for="group in modifierGroups"
                :key="group.id"
                class="bg-white rounded-2xl border border-gray-100 p-6"
            >
                <!-- Group header - display mode -->
                <div v-if="editingGroupId !== group.id" class="flex items-start justify-between mb-3">
                    <div>
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-semibold text-gray-900">{{ group.name }}</h3>
                            <span class="text-xs px-2 py-0.5 rounded-full bg-blue-50 text-blue-700">
                                {{ group.selection_type === 'single' ? 'Seleccion unica' : 'Seleccion multiple' }}
                            </span>
                            <span v-if="group.is_required" class="text-xs px-2 py-0.5 rounded-full bg-orange-50 text-orange-700">
                                Obligatorio
                            </span>
                            <span v-else class="text-xs px-2 py-0.5 rounded-full bg-gray-100 text-gray-500">
                                Opcional
                            </span>
                        </div>
                        <div class="flex items-center gap-2 mt-1 flex-wrap">
                            <span class="text-xs text-gray-400">{{ group.options.length }} opciones</span>
                            <span v-if="group.products && group.products.length > 0" class="text-xs text-gray-400">·</span>
                            <span
                                v-for="prod in (group.products || [])"
                                :key="prod.id"
                                class="text-xs px-2 py-0.5 rounded-full bg-gray-50 text-gray-600 border border-gray-100"
                            >{{ prod.name }}</span>
                            <span v-if="!group.products || group.products.length === 0" class="text-xs text-gray-300 italic">Sin productos asignados</span>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            @click="startEditGroup(group)"
                            class="p-2 text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 rounded-xl transition-colors"
                            title="Editar grupo"
                        >
                            <span class="material-symbols-outlined text-lg">edit</span>
                        </button>
                        <button
                            @click="deleteGroup(group)"
                            class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-colors"
                            title="Eliminar grupo"
                        >
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    </div>
                </div>

                <!-- Group header - edit mode -->
                <div v-else class="mb-3 border border-orange-200 rounded-xl p-4 bg-orange-50/30">
                    <div class="grid grid-cols-2 gap-3 mb-3">
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Nombre</label>
                            <input
                                v-model="editGroupForm.name"
                                type="text"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-medium text-gray-500 mb-1">Tipo</label>
                            <select
                                v-model="editGroupForm.selection_type"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                            >
                                <option value="single">Seleccion unica</option>
                                <option value="multiple">Seleccion multiple</option>
                            </select>
                        </div>
                    </div>
                    <div class="flex items-center justify-between">
                        <div
                            class="flex items-center gap-2 cursor-pointer"
                            @click="editGroupForm.is_required = !editGroupForm.is_required"
                        >
                            <div class="w-10 h-6 rounded-full transition-colors relative"
                                :class="editGroupForm.is_required ? 'bg-[#FF5722]' : 'bg-gray-200'">
                                <div class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all"
                                    :class="editGroupForm.is_required ? 'left-5' : 'left-1'" />
                            </div>
                            <span class="text-sm text-gray-700">{{ editGroupForm.is_required ? 'Obligatorio' : 'Opcional' }}</span>
                        </div>
                        <div class="flex gap-2">
                            <button @click="editingGroupId = null" class="text-sm text-gray-500 hover:text-gray-700 font-medium px-3 py-1.5">
                                Cancelar
                            </button>
                            <button @click="submitEditGroup(group)" class="bg-[#FF5722] text-white rounded-xl px-4 py-1.5 text-sm font-medium hover:bg-[#D84315] transition-colors">
                                Guardar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Options -->
                <div class="space-y-1 mb-3">
                    <div v-if="group.options.length === 0" class="text-sm text-gray-400 italic">
                        Sin opciones — agrega la primera abajo.
                    </div>
                    <div
                        v-for="option in group.options"
                        :key="option.id"
                    >
                        <!-- Option display mode -->
                        <div
                            v-if="editingOptionId !== option.id"
                            class="flex items-center justify-between py-2 px-3 rounded-xl hover:bg-gray-50"
                        >
                            <span class="text-sm text-gray-700">{{ option.name }}</span>
                            <div class="flex items-center gap-2">
                                <span :class="[
                                    'text-sm font-medium',
                                    option.price_adjustment > 0 ? 'text-green-600' : option.price_adjustment < 0 ? 'text-red-500' : 'text-gray-400'
                                ]">
                                    {{ option.price_adjustment >= 0 ? '+' : '' }}${{ Number(option.price_adjustment).toFixed(2) }}
                                </span>
                                <button
                                    @click="startEditOption(option)"
                                    class="p-1.5 text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 rounded-lg transition-colors"
                                >
                                    <span class="material-symbols-outlined text-base">edit</span>
                                </button>
                                <button
                                    @click="deleteOption(group, option)"
                                    class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                >
                                    <span class="material-symbols-outlined text-base">delete</span>
                                </button>
                            </div>
                        </div>

                        <!-- Option edit mode -->
                        <div v-else class="flex items-end gap-3 py-2 px-3 rounded-xl bg-orange-50/30 border border-orange-100">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Nombre</label>
                                <input
                                    v-model="editOptionForm.name"
                                    type="text"
                                    class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                                />
                            </div>
                            <div class="w-32">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Precio ajuste</label>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                    <input
                                        v-model.number="editOptionForm.price_adjustment"
                                        type="number"
                                        step="0.01"
                                        class="w-full rounded-xl border border-gray-200 pl-6 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                                    />
                                </div>
                            </div>
                            <button
                                @click="submitEditOption(group, option)"
                                class="bg-[#FF5722] text-white rounded-xl px-4 py-2 text-sm font-medium hover:bg-[#D84315] transition-colors"
                            >
                                Guardar
                            </button>
                            <button
                                @click="editingOptionId = null"
                                class="border border-gray-200 text-gray-600 rounded-xl px-3 py-2 text-sm hover:bg-gray-50 transition-colors"
                            >
                                Cancelar
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Add option -->
                <div v-if="newOptionForms[group.id]" class="border-t border-gray-50 pt-3">
                    <form @submit.prevent="submitNewOption(group.id)" class="flex items-end gap-3">
                        <div class="flex-1">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Nombre de la opcion</label>
                            <input
                                v-model="newOptionForms[group.id].name"
                                type="text"
                                required
                                placeholder="Ej: Tortilla de maiz"
                                class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                            />
                        </div>
                        <div class="w-32">
                            <label class="block text-xs font-medium text-gray-500 mb-1">Precio ajuste</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs">$</span>
                                <input
                                    v-model.number="newOptionForms[group.id].price_adjustment"
                                    type="number"
                                    step="0.01"
                                    class="w-full rounded-xl border border-gray-200 pl-6 pr-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                                />
                            </div>
                        </div>
                        <button
                            type="submit"
                            class="bg-[#FF5722] text-white rounded-xl px-4 py-2 text-sm font-medium hover:bg-[#D84315] transition-colors"
                        >
                            Agregar
                        </button>
                        <button
                            type="button"
                            @click="delete newOptionForms[group.id]"
                            class="border border-gray-200 text-gray-600 rounded-xl px-3 py-2 text-sm hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </button>
                    </form>
                </div>
                <button
                    v-else
                    @click="initNewOptionForm(group.id)"
                    class="text-sm text-[#FF5722] hover:text-[#D84315] font-medium transition-colors flex items-center gap-1 mt-1"
                >
                    <span class="material-symbols-outlined text-base">add</span>
                    Agregar opcion
                </button>
            </div>
        </div>

    </AppLayout>
</template>
