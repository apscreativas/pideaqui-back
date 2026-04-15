<script setup>
import { Head, Link, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    expense: Object,
    categories: Array,
    branches: { type: Array, default: () => [] },
    today: String,
})

function fmt(v) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0) }

const form = useForm({
    title: props.expense.title,
    description: props.expense.description ?? '',
    amount: props.expense.amount,
    expense_date: props.expense.expense_date.slice(0, 10),
    branch_id: props.expense.branch_id,
    expense_category_id: props.expense.expense_category_id,
    expense_subcategory_id: props.expense.expense_subcategory_id,
    attachments: [],
    _method: 'put',
})

const subcategoryOptions = computed(() => {
    if (!form.expense_category_id) { return [] }
    const cat = props.categories.find((c) => c.id === Number(form.expense_category_id))
    return cat?.subcategories ?? []
})

function onCategoryChange() {
    form.expense_subcategory_id = ''
}

const fileInput = ref(null)
function pickFiles() { fileInput.value?.click() }
function onFiles(e) {
    const files = Array.from(e.target.files)
    form.attachments = [...form.attachments, ...files].slice(0, 10)
    e.target.value = ''
}
function removeNewFile(idx) { form.attachments.splice(idx, 1) }

function removeExistingAttachment(id) {
    if (!confirm('¿Eliminar este archivo?')) { return }
    router.delete(route('expenses.attachments.destroy', id), { preserveScroll: true })
}

function destroy() {
    if (!confirm('¿Eliminar el gasto? Esta acción no se puede deshacer.')) { return }
    router.delete(route('expenses.destroy', props.expense.id))
}

function submit() {
    form.post(route('expenses.update', props.expense.id), { forceFormData: true })
}

function formatBytes(bytes) {
    if (bytes < 1024) { return bytes + ' B' }
    if (bytes < 1024 * 1024) { return (bytes / 1024).toFixed(1) + ' KB' }
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}
</script>

<template>
    <Head :title="`Editar · ${expense.title}`" />
    <AppLayout title="Editar gasto">

        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
            <Link :href="route('expenses.show', expense.id)" class="hover:text-[#FF5722] flex items-center gap-1 transition">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                {{ expense.title }}
            </Link>
        </div>
        <div class="flex items-center justify-between mb-6">
            <h1 class="text-2xl font-black text-gray-900">Editar gasto</h1>
            <button @click="destroy" type="button" class="flex items-center gap-1.5 border border-red-200 text-red-600 hover:bg-red-50 px-4 py-2 rounded-xl text-sm font-semibold transition">
                <span class="material-symbols-outlined text-lg">delete</span>
                Eliminar
            </button>
        </div>

        <form @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Título</label>
                        <input v-model="form.title" type="text" maxlength="255" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30" />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Descripción</label>
                        <textarea v-model="form.description" rows="3" maxlength="2000" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 resize-none"></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Sucursal</label>
                        <select v-model="form.branch_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30">
                            <option value="">Selecciona…</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}<span v-if="!b.is_active"> (inactiva)</span></option>
                        </select>
                        <p v-if="form.errors.branch_id" class="mt-1 text-xs text-red-600">{{ form.errors.branch_id }}</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Categoría</label>
                            <select v-model="form.expense_category_id" @change="onCategoryChange" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30">
                                <option value="">Selecciona…</option>
                                <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.expense_category_id" class="mt-1 text-xs text-red-600">{{ form.errors.expense_category_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Subcategoría</label>
                            <select v-model="form.expense_subcategory_id" :disabled="!form.expense_category_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 disabled:bg-gray-50">
                                <option value="">Selecciona…</option>
                                <option v-for="s in subcategoryOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="form.errors.expense_subcategory_id" class="mt-1 text-xs text-red-600">{{ form.errors.expense_subcategory_id }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Monto</label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input v-model="form.amount" type="number" min="0.01" step="0.01" required class="w-full pl-7 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30" />
                            </div>
                            <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Fecha del gasto</label>
                            <input v-model="form.expense_date" type="date" :max="today" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30" />
                            <p v-if="form.errors.expense_date" class="mt-1 text-xs text-red-600">{{ form.errors.expense_date }}</p>
                        </div>
                    </div>
                </div>

                <!-- Existing attachments -->
                <div v-if="expense.attachments?.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-sm font-bold text-gray-700 mb-3">Archivos actuales ({{ expense.attachments.length }})</h3>
                    <div class="space-y-2">
                        <div v-for="att in expense.attachments" :key="att.id" class="flex items-center justify-between bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 text-sm">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="material-symbols-outlined text-gray-400">{{ att.mime_type?.startsWith('image/') ? 'image' : 'picture_as_pdf' }}</span>
                                <span class="truncate">{{ att.file_name }}</span>
                            </div>
                            <button type="button" @click="removeExistingAttachment(att.id)" class="text-gray-300 hover:text-red-500">
                                <span class="material-symbols-outlined text-base">delete</span>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- New attachments -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-gray-700">Agregar archivos</h3>
                        <button type="button" @click="pickFiles" class="flex items-center gap-1 text-sm font-semibold text-[#FF5722] hover:text-[#D84315]">
                            <span class="material-symbols-outlined text-base">add</span>
                            Seleccionar
                        </button>
                        <input ref="fileInput" type="file" multiple accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf" @change="onFiles" class="hidden" />
                    </div>
                    <div v-if="form.attachments.length" class="space-y-2">
                        <div v-for="(f, idx) in form.attachments" :key="idx" class="flex items-center justify-between bg-amber-50 border border-amber-100 rounded-lg px-3 py-2 text-sm">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="material-symbols-outlined text-amber-600">add_circle</span>
                                <span class="truncate">{{ f.name }}</span>
                                <span class="text-xs text-gray-400">{{ formatBytes(f.size) }}</span>
                            </div>
                            <button type="button" @click="removeNewFile(idx)" class="text-gray-300 hover:text-red-500">
                                <span class="material-symbols-outlined text-base">close</span>
                            </button>
                        </div>
                    </div>
                    <p v-else class="text-xs text-gray-400">Sin archivos nuevos pendientes.</p>
                </div>
            </div>

            <aside class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sticky top-4">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Monto</p>
                    <p class="text-4xl font-black text-red-600">{{ fmt(form.amount || 0) }}</p>
                    <button type="submit" :disabled="form.processing" class="w-full mt-4 bg-[#FF5722] hover:bg-[#D84315] text-white py-3 rounded-xl text-sm font-bold shadow-md shadow-orange-200 transition disabled:opacity-50">
                        <span v-if="form.processing">Guardando…</span>
                        <span v-else>Guardar cambios</span>
                    </button>
                    <Link :href="route('expenses.show', expense.id)" class="block text-center mt-2 text-sm text-gray-500 hover:text-gray-700">Cancelar</Link>
                </div>
            </aside>
        </form>
    </AppLayout>
</template>
