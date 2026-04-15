<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    categories: Array,
})

// ─── New category form ────────────────────────────────────────────────────
const catForm = useForm({ name: '' })
function createCategory() {
    catForm.post(route('expense-categories.store'), { preserveScroll: true, onSuccess: () => catForm.reset() })
}

// ─── Edit category ────────────────────────────────────────────────────────
const editingCategoryId = ref(null)
const editCategoryName = ref('')
function startEditCategory(cat) {
    editingCategoryId.value = cat.id
    editCategoryName.value = cat.name
}
function saveCategory(cat) {
    router.put(route('expense-categories.update', cat.id), { name: editCategoryName.value, is_active: cat.is_active }, {
        preserveScroll: true,
        onSuccess: () => { editingCategoryId.value = null },
    })
}
function toggleCategory(cat) {
    router.patch(route('expense-categories.toggle', cat.id), {}, { preserveScroll: true })
}
function deleteCategory(cat) {
    if (!confirm(`¿Eliminar "${cat.name}"? Solo funciona si no tiene gastos registrados.`)) { return }
    router.delete(route('expense-categories.destroy', cat.id), { preserveScroll: true })
}

// ─── Subcategory ──────────────────────────────────────────────────────────
const subForms = ref({})    // categoryId → { name }
function subForm(catId) {
    if (!subForms.value[catId]) { subForms.value[catId] = { name: '' } }
    return subForms.value[catId]
}
function createSubcategory(cat) {
    const f = subForm(cat.id)
    if (!f.name.trim()) { return }
    router.post(route('expense-subcategories.store', cat.id), { name: f.name }, {
        preserveScroll: true,
        onSuccess: () => { f.name = '' },
    })
}

const editingSubId = ref(null)
const editSubName = ref('')
function startEditSub(sub) {
    editingSubId.value = sub.id
    editSubName.value = sub.name
}
function saveSub(sub) {
    router.put(route('expense-subcategories.update', sub.id), { name: editSubName.value, is_active: sub.is_active }, {
        preserveScroll: true,
        onSuccess: () => { editingSubId.value = null },
    })
}
function toggleSub(sub) {
    router.patch(route('expense-subcategories.toggle', sub.id), {}, { preserveScroll: true })
}
function deleteSub(sub) {
    if (!confirm(`¿Eliminar subcategoría "${sub.name}"? Solo funciona si no tiene gastos asociados.`)) { return }
    router.delete(route('expense-subcategories.destroy', sub.id), { preserveScroll: true })
}

const expanded = ref(new Set())
function toggleExpand(id) {
    if (expanded.value.has(id)) { expanded.value.delete(id) } else { expanded.value.add(id) }
    expanded.value = new Set(expanded.value)
}
</script>

<template>
    <Head title="Categorías de gasto" />
    <AppLayout title="Categorías de gasto">

        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
            <Link :href="route('expenses.index')" class="hover:text-[#FF5722] flex items-center gap-1 transition">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                Gastos
            </Link>
        </div>
        <h1 class="text-2xl font-black text-gray-900 mb-1">Categorías de gasto</h1>
        <p class="text-sm text-gray-500 mb-6">Administra las categorías y subcategorías que puedes elegir al registrar un gasto.</p>

        <!-- New category input -->
        <form @submit.prevent="createCategory" class="mb-5 flex items-center gap-2 bg-white border border-gray-100 shadow-sm rounded-xl p-3">
            <span class="material-symbols-outlined text-gray-400 ml-1">add_circle</span>
            <input
                v-model="catForm.name" type="text" maxlength="120" required
                placeholder="Nueva categoría (ej. Insumos, Servicios, Sueldos…)"
                class="flex-1 border-0 focus:outline-none text-sm"
            />
            <button type="submit" :disabled="!catForm.name || catForm.processing" class="bg-[#FF5722] hover:bg-[#D84315] text-white px-4 py-2 rounded-lg text-sm font-bold transition disabled:opacity-50">
                Agregar
            </button>
        </form>

        <!-- Categories list -->
        <div class="space-y-3">
            <div v-for="cat in categories" :key="cat.id" class="bg-white border border-gray-100 shadow-sm rounded-xl overflow-hidden">

                <!-- Category row -->
                <div class="flex items-center gap-3 px-4 py-3">
                    <button type="button" @click="toggleExpand(cat.id)" class="text-gray-400 hover:text-gray-700 transition">
                        <span class="material-symbols-outlined text-lg">{{ expanded.has(cat.id) ? 'expand_less' : 'expand_more' }}</span>
                    </button>

                    <div class="flex-1 min-w-0">
                        <div v-if="editingCategoryId === cat.id" class="flex items-center gap-2">
                            <input v-model="editCategoryName" type="text" class="flex-1 border border-gray-200 rounded-lg px-3 py-1.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30" />
                            <button @click="saveCategory(cat)" type="button" class="text-green-600 hover:bg-green-50 p-1.5 rounded-lg">
                                <span class="material-symbols-outlined text-base">check</span>
                            </button>
                            <button @click="editingCategoryId = null" type="button" class="text-gray-400 hover:bg-gray-50 p-1.5 rounded-lg">
                                <span class="material-symbols-outlined text-base">close</span>
                            </button>
                        </div>
                        <div v-else class="flex items-center gap-3">
                            <h3 class="font-bold text-gray-900 truncate" :class="cat.is_active ? '' : 'text-gray-400 line-through'">{{ cat.name }}</h3>
                            <span class="text-xs text-gray-400">{{ cat.subcategories?.length ?? 0 }} sub · {{ cat.expenses_count ?? 0 }} gasto(s)</span>
                            <span v-if="!cat.is_active" class="text-[10px] font-bold text-gray-500 bg-gray-100 px-1.5 py-0.5 rounded">Inactiva</span>
                        </div>
                    </div>

                    <div v-if="editingCategoryId !== cat.id" class="flex items-center gap-1">
                        <button @click="toggleCategory(cat)" type="button" :title="cat.is_active ? 'Desactivar' : 'Activar'" class="text-gray-400 hover:text-gray-700 hover:bg-gray-100 p-1.5 rounded-lg transition">
                            <span class="material-symbols-outlined text-base">{{ cat.is_active ? 'visibility' : 'visibility_off' }}</span>
                        </button>
                        <button @click="startEditCategory(cat)" type="button" title="Editar" class="text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 p-1.5 rounded-lg transition">
                            <span class="material-symbols-outlined text-base">edit</span>
                        </button>
                        <button @click="deleteCategory(cat)" type="button" title="Eliminar" class="text-gray-400 hover:text-red-600 hover:bg-red-50 p-1.5 rounded-lg transition">
                            <span class="material-symbols-outlined text-base">delete</span>
                        </button>
                    </div>
                </div>

                <!-- Subcategories (expanded) -->
                <div v-if="expanded.has(cat.id)" class="border-t border-gray-100 bg-gray-50/50">

                    <!-- Add subcategory -->
                    <form @submit.prevent="createSubcategory(cat)" class="flex items-center gap-2 px-4 py-2 border-b border-gray-100">
                        <span class="material-symbols-outlined text-gray-400 ml-6 text-base">add</span>
                        <input v-model="subForm(cat.id).name" type="text" maxlength="120" placeholder="Agregar subcategoría…" class="flex-1 bg-transparent border-0 focus:outline-none text-sm" />
                        <button type="submit" :disabled="!subForm(cat.id).name" class="text-sm font-bold text-[#FF5722] hover:text-[#D84315] disabled:opacity-40">
                            Agregar
                        </button>
                    </form>

                    <!-- Subcategory list -->
                    <div v-if="cat.subcategories?.length">
                        <div v-for="sub in cat.subcategories" :key="sub.id" class="flex items-center gap-3 px-4 py-2.5 ml-6">
                            <span class="material-symbols-outlined text-gray-300 text-base">subdirectory_arrow_right</span>
                            <div class="flex-1 min-w-0">
                                <div v-if="editingSubId === sub.id" class="flex items-center gap-2">
                                    <input v-model="editSubName" type="text" class="flex-1 border border-gray-200 rounded-lg px-2 py-1 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30" />
                                    <button @click="saveSub(sub)" type="button" class="text-green-600 hover:bg-green-50 p-1 rounded">
                                        <span class="material-symbols-outlined text-sm">check</span>
                                    </button>
                                    <button @click="editingSubId = null" type="button" class="text-gray-400 hover:bg-gray-50 p-1 rounded">
                                        <span class="material-symbols-outlined text-sm">close</span>
                                    </button>
                                </div>
                                <div v-else class="flex items-center gap-2">
                                    <span class="text-sm text-gray-800" :class="sub.is_active ? '' : 'text-gray-400 line-through'">{{ sub.name }}</span>
                                    <span v-if="!sub.is_active" class="text-[9px] font-bold text-gray-500 bg-gray-200 px-1 rounded">Inactiva</span>
                                </div>
                            </div>
                            <div v-if="editingSubId !== sub.id" class="flex items-center gap-1">
                                <button @click="toggleSub(sub)" type="button" class="text-gray-400 hover:text-gray-700 hover:bg-gray-100 p-1 rounded transition">
                                    <span class="material-symbols-outlined text-sm">{{ sub.is_active ? 'visibility' : 'visibility_off' }}</span>
                                </button>
                                <button @click="startEditSub(sub)" type="button" class="text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 p-1 rounded transition">
                                    <span class="material-symbols-outlined text-sm">edit</span>
                                </button>
                                <button @click="deleteSub(sub)" type="button" class="text-gray-400 hover:text-red-600 hover:bg-red-50 p-1 rounded transition">
                                    <span class="material-symbols-outlined text-sm">delete</span>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div v-else class="px-4 py-3 ml-6 text-xs text-gray-400 italic">
                        Sin subcategorías. Agrega una arriba.
                    </div>
                </div>
            </div>

            <div v-if="categories.length === 0" class="text-center py-16 text-gray-400">
                <span class="material-symbols-outlined text-5xl text-gray-200">category</span>
                <p class="text-sm mt-2">No hay categorías aún. Agrega la primera con el formulario de arriba.</p>
            </div>
        </div>
    </AppLayout>
</template>
