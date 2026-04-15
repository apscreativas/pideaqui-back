<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    categories: Array,
    branches: { type: Array, default: () => [] },
    today: String,
})

function fmt(v) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0) }

const form = useForm({
    title: '',
    description: '',
    amount: '',
    expense_date: props.today,
    branch_id: props.branches.length === 1 ? props.branches[0].id : '',
    expense_category_id: '',
    expense_subcategory_id: '',
    attachments: [],
})

const activeCategories = computed(() =>
    props.categories.filter((c) => c.is_active && (c.subcategories ?? []).some((s) => s.is_active))
)
const hasUsableCategories = computed(() => activeCategories.value.length > 0)

const subcategoryOptions = computed(() => {
    if (!form.expense_category_id) { return [] }
    const cat = props.categories.find((c) => c.id === Number(form.expense_category_id))
    return (cat?.subcategories ?? []).filter((s) => s.is_active)
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
function removeFile(idx) {
    form.attachments.splice(idx, 1)
}

function submit() {
    form.post(route('expenses.store'), { forceFormData: true })
}

function formatBytes(bytes) {
    if (bytes < 1024) { return bytes + ' B' }
    if (bytes < 1024 * 1024) { return (bytes / 1024).toFixed(1) + ' KB' }
    return (bytes / (1024 * 1024)).toFixed(1) + ' MB'
}
</script>

<template>
    <Head title="Nuevo gasto" />
    <AppLayout title="Nuevo gasto">

        <div class="flex items-center gap-2 text-sm text-gray-500 mb-2">
            <Link :href="route('expenses.index')" class="hover:text-[#FF5722] flex items-center gap-1 transition">
                <span class="material-symbols-outlined text-lg">arrow_back</span>
                Gastos
            </Link>
        </div>
        <h1 class="text-2xl font-black text-gray-900 mb-6">Registrar nuevo gasto</h1>

        <!-- Blocker: no usable categories -->
        <div v-if="!hasUsableCategories" class="bg-gradient-to-br from-orange-50 to-amber-50 border border-orange-200 rounded-2xl p-6 flex flex-col md:flex-row items-start md:items-center gap-4">
            <div class="size-14 rounded-2xl bg-white text-[#FF5722] flex items-center justify-center shrink-0 shadow-sm">
                <span class="material-symbols-outlined text-3xl">category</span>
            </div>
            <div class="flex-1">
                <p class="text-base font-black text-gray-900">Necesitas categorías activas</p>
                <p class="text-sm text-gray-600 mt-1">No hay categorías con subcategorías activas. Crea al menos una categoría con una subcategoría para poder registrar gastos.</p>
            </div>
            <Link :href="route('expense-categories.index')" class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition shrink-0">
                <span class="material-symbols-outlined text-lg">arrow_forward</span>
                Administrar categorías
            </Link>
        </div>

        <form v-else @submit.prevent="submit" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: form -->
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <h3 class="text-sm font-bold text-gray-700 flex items-center gap-1.5 mb-1">
                        <span class="material-symbols-outlined text-[#FF5722] text-base">description</span>
                        Datos del gasto
                    </h3>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Título <span class="text-red-400">*</span></label>
                        <input
                            v-model="form.title"
                            type="text" maxlength="255" required
                            placeholder="Ej. Factura CFE octubre"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30"
                        />
                        <p v-if="form.errors.title" class="mt-1 text-xs text-red-600">{{ form.errors.title }}</p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Descripción</label>
                        <textarea
                            v-model="form.description" rows="3" maxlength="2000"
                            placeholder="Notas opcionales..."
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 resize-none"
                        ></textarea>
                    </div>

                    <div>
                        <label class="block text-xs font-bold text-gray-600 mb-1">Sucursal <span class="text-red-400">*</span></label>
                        <select v-model="form.branch_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30">
                            <option value="">Selecciona…</option>
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <p v-if="form.errors.branch_id" class="mt-1 text-xs text-red-600">{{ form.errors.branch_id }}</p>
                        <p v-if="branches.length === 0" class="mt-1 text-xs text-amber-600">No hay sucursales activas. <a href="/branches" class="underline">Crear sucursal</a>.</p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Categoría <span class="text-red-400">*</span></label>
                            <select v-model="form.expense_category_id" @change="onCategoryChange" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30">
                                <option value="">Selecciona…</option>
                                <option v-for="c in activeCategories" :key="c.id" :value="c.id">{{ c.name }}</option>
                            </select>
                            <p v-if="form.errors.expense_category_id" class="mt-1 text-xs text-red-600">{{ form.errors.expense_category_id }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Subcategoría <span class="text-red-400">*</span></label>
                            <select v-model="form.expense_subcategory_id" :disabled="!form.expense_category_id" required class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm bg-white focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 disabled:bg-gray-50 disabled:text-gray-400">
                                <option value="">{{ form.expense_category_id ? 'Selecciona…' : 'Elige categoría primero' }}</option>
                                <option v-for="s in subcategoryOptions" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <p v-if="subcategoryOptions.length === 0 && form.expense_category_id" class="mt-1 text-xs text-amber-600">Esta categoría no tiene subcategorías. <Link :href="route('expense-categories.index')" class="underline">Agregar una</Link>.</p>
                            <p v-if="form.errors.expense_subcategory_id" class="mt-1 text-xs text-red-600">{{ form.errors.expense_subcategory_id }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Monto <span class="text-red-400">*</span></label>
                            <div class="relative">
                                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">$</span>
                                <input
                                    v-model="form.amount"
                                    type="number" min="0.01" step="0.01" required
                                    class="w-full pl-7 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30"
                                />
                            </div>
                            <p v-if="form.errors.amount" class="mt-1 text-xs text-red-600">{{ form.errors.amount }}</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-600 mb-1">Fecha del gasto <span class="text-red-400">*</span></label>
                            <input
                                v-model="form.expense_date"
                                type="date" :max="today" required
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30"
                            />
                            <p v-if="form.errors.expense_date" class="mt-1 text-xs text-red-600">{{ form.errors.expense_date }}</p>
                        </div>
                    </div>
                </div>

                <!-- Attachments -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-3">
                        <h3 class="text-sm font-bold text-gray-700 flex items-center gap-1.5">
                            <span class="material-symbols-outlined text-[#FF5722] text-base">attach_file</span>
                            Archivos adjuntos
                            <span class="text-xs text-gray-400 font-normal">(opcional · máx. 10 · imágenes o PDF · 5 MB c/u)</span>
                        </h3>
                        <button type="button" @click="pickFiles" :disabled="form.attachments.length >= 10" class="flex items-center gap-1 text-sm font-semibold text-[#FF5722] hover:text-[#D84315] disabled:opacity-40">
                            <span class="material-symbols-outlined text-base">add</span>
                            Agregar
                        </button>
                        <input ref="fileInput" type="file" multiple accept="image/jpeg,image/jpg,image/png,image/webp,application/pdf" @change="onFiles" class="hidden" />
                    </div>
                    <div v-if="form.attachments.length" class="space-y-2">
                        <div v-for="(f, idx) in form.attachments" :key="idx" class="flex items-center justify-between bg-gray-50 border border-gray-100 rounded-lg px-3 py-2 text-sm">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="material-symbols-outlined text-gray-400">{{ f.type?.startsWith('image/') ? 'image' : 'picture_as_pdf' }}</span>
                                <span class="truncate">{{ f.name }}</span>
                                <span class="text-xs text-gray-400 shrink-0">{{ formatBytes(f.size) }}</span>
                            </div>
                            <button type="button" @click="removeFile(idx)" class="text-gray-300 hover:text-red-500">
                                <span class="material-symbols-outlined text-base">close</span>
                            </button>
                        </div>
                    </div>
                    <div v-else class="text-center text-sm text-gray-400 py-6">
                        <span class="material-symbols-outlined text-4xl text-gray-200">upload_file</span>
                        <p class="mt-1">Sin archivos. Agrega recibos o facturas si aplica.</p>
                    </div>
                    <p v-if="form.errors.attachments" class="mt-2 text-xs text-red-600">{{ form.errors.attachments }}</p>
                </div>
            </div>

            <!-- Right: summary + submit -->
            <aside class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 sticky top-4">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Resumen</p>
                    <div class="text-center py-3">
                        <p class="text-[10px] font-bold text-gray-500 uppercase">Monto a registrar</p>
                        <p class="text-4xl font-black text-red-600 mt-1">{{ fmt(form.amount || 0) }}</p>
                    </div>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="w-full mt-2 bg-[#FF5722] hover:bg-[#D84315] text-white py-3 rounded-xl text-sm font-bold shadow-md shadow-orange-200 transition disabled:opacity-50"
                    >
                        <span v-if="form.processing">Guardando…</span>
                        <span v-else>Registrar gasto</span>
                    </button>
                    <Link :href="route('expenses.index')" class="block text-center mt-2 text-sm text-gray-500 hover:text-gray-700">
                        Cancelar
                    </Link>
                </div>
            </aside>
        </form>
    </AppLayout>
</template>
