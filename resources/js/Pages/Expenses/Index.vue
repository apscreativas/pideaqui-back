<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import DatePicker from '@/Components/DatePicker.vue'

const props = defineProps({
    expenses: Array,
    categories: Array,
    branches: { type: Array, default: () => [] },
    filters: Object,
    totals: Object,
})

function fmt(v) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0) }
function dt(d) { return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric' }).format(new Date(d)) }

const dateFrom = ref(props.filters?.date_from ?? '')
const dateTo = ref(props.filters?.date_to ?? '')
const branchId = ref(props.filters?.branch_id ?? '')
const categoryId = ref(props.filters?.category_id ?? '')
const subcategoryId = ref(props.filters?.subcategory_id ?? '')
const minAmount = ref(props.filters?.min_amount ?? '')
const maxAmount = ref(props.filters?.max_amount ?? '')

const subcategoryOptions = computed(() => {
    if (!categoryId.value) { return [] }
    const cat = props.categories.find((c) => c.id === Number(categoryId.value))
    return cat?.subcategories ?? []
})

function applyFilters() {
    router.get(route('expenses.index'), {
        date_from: dateFrom.value || undefined,
        date_to: dateTo.value || undefined,
        branch_id: branchId.value || undefined,
        category_id: categoryId.value || undefined,
        subcategory_id: subcategoryId.value || undefined,
        min_amount: minAmount.value || undefined,
        max_amount: maxAmount.value || undefined,
    }, { preserveState: true, replace: true })
}

function resetSubcategory() {
    subcategoryId.value = ''
    applyFilters()
}

// ─── Attachment preview (lightbox) ────────────────────────────────────────
const previewAttachment = ref(null)
const previewError = ref(false)
function openPreview(att) { previewAttachment.value = att; previewError.value = false }
function closePreview() { previewAttachment.value = null; previewError.value = false }
function onImageError() { previewError.value = true }

function isImage(att) { return !!att?.is_image || att?.mime_type?.startsWith('image/') }
function isPdf(att) { return !!att?.is_pdf || att?.mime_type === 'application/pdf' }

const brokenThumbs = ref(new Set())
function onThumbError(id) { brokenThumbs.value.add(id); brokenThumbs.value = new Set(brokenThumbs.value) }

function clearCategoryFilter() {
    categoryId.value = ''
    subcategoryId.value = ''
    applyFilters()
}
</script>

<template>
    <Head title="Gastos" />
    <AppLayout title="Gastos">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-5">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Gastos</h1>
                <p class="mt-1 text-sm text-gray-500">Registro y reporte de gastos del negocio.</p>
            </div>
            <div class="flex items-center gap-2">
                <Link
                    :href="route('expense-categories.index')"
                    class="flex items-center gap-1.5 bg-white border-2 border-[#FF5722]/30 text-[#FF5722] hover:bg-orange-50 hover:border-[#FF5722] px-4 py-2.5 rounded-xl text-sm font-bold transition"
                >
                    <span class="material-symbols-outlined text-lg">category</span>
                    Categorías y subcategorías
                </Link>
                <Link
                    v-if="categories.length > 0"
                    :href="route('expenses.create')"
                    class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition"
                >
                    <span class="material-symbols-outlined text-lg">add</span>
                    Nuevo gasto
                </Link>
            </div>
        </div>

        <!-- Onboarding: no categories — block flow and push to admin -->
        <div v-if="categories.length === 0" class="mb-5 bg-gradient-to-br from-orange-50 to-amber-50 border border-orange-200 rounded-2xl p-6 flex flex-col md:flex-row items-start md:items-center gap-4">
            <div class="size-14 rounded-2xl bg-white text-[#FF5722] flex items-center justify-center shrink-0 shadow-sm">
                <span class="material-symbols-outlined text-3xl">category</span>
            </div>
            <div class="flex-1">
                <p class="text-base font-black text-gray-900">Primero crea tus categorías</p>
                <p class="text-sm text-gray-600 mt-1">
                    Para registrar gastos necesitas al menos <strong>una categoría</strong> con <strong>una subcategoría</strong>.
                    Ej. <em>Insumos → Carne, Verdura</em>; <em>Servicios → Luz, Agua</em>.
                </p>
            </div>
            <Link
                :href="route('expense-categories.index')"
                class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-5 py-2.5 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition shrink-0"
            >
                <span class="material-symbols-outlined text-lg">arrow_forward</span>
                Crear categorías
            </Link>
        </div>

        <!-- KPIs -->
        <div class="grid grid-cols-2 md:grid-cols-3 gap-3 mb-5">
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="material-symbols-outlined text-gray-400 text-base">receipt_long</span>
                    <p class="text-xs font-bold text-gray-500 uppercase">Gastos</p>
                </div>
                <p class="text-2xl font-black text-gray-900">{{ totals.count }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="material-symbols-outlined text-red-500 text-base">trending_down</span>
                    <p class="text-xs font-bold text-gray-500 uppercase">Total</p>
                </div>
                <p class="text-2xl font-black text-red-600">{{ fmt(totals.total) }}</p>
            </div>
            <div class="bg-white p-4 rounded-xl border border-gray-100 shadow-sm">
                <div class="flex items-center gap-2 mb-1.5">
                    <span class="material-symbols-outlined text-gray-400 text-base">trending_flat</span>
                    <p class="text-xs font-bold text-gray-500 uppercase">Promedio</p>
                </div>
                <p class="text-2xl font-black text-gray-900">{{ fmt(totals.avg) }}</p>
            </div>
        </div>

        <!-- Filters card -->
        <div class="bg-white border border-gray-100 shadow-sm rounded-2xl p-4 mb-4 space-y-4">

            <!-- Date range -->
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">Rango de fechas</label>
                <div class="flex items-center gap-2">
                    <div class="flex-1 min-w-[180px]">
                        <DatePicker v-model="dateFrom" @change="applyFilters" placeholder="Desde" />
                    </div>
                    <span class="text-gray-300 text-sm font-semibold">—</span>
                    <div class="flex-1 min-w-[180px]">
                        <DatePicker v-model="dateTo" @change="applyFilters" placeholder="Hasta" />
                    </div>
                </div>
            </div>

            <!-- Branch pills -->
            <div v-if="branches.length > 1">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">Sucursal</label>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="branchId = ''; applyFilters()"
                        class="px-3 py-1.5 rounded-full text-xs font-bold transition border"
                        :class="!branchId ? 'bg-[#FF5722] text-white border-[#FF5722]' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    >Todas</button>
                    <button
                        v-for="b in branches"
                        :key="b.id"
                        type="button"
                        @click="branchId = b.id; applyFilters()"
                        class="px-3 py-1.5 rounded-full text-xs font-bold transition border flex items-center gap-1.5"
                        :class="Number(branchId) === b.id ? 'bg-[#FF5722] text-white border-[#FF5722]' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    >
                        <span class="material-symbols-outlined text-sm">store</span>
                        {{ b.name }}
                    </button>
                </div>
            </div>

            <!-- Category pills -->
            <div>
                <div class="flex items-center justify-between mb-1.5">
                    <label class="text-[10px] font-bold text-gray-500 uppercase tracking-wide">Categoría</label>
                    <button v-if="categoryId" @click="clearCategoryFilter" type="button" class="text-[10px] font-semibold text-gray-400 hover:text-gray-700">
                        Limpiar
                    </button>
                </div>
                <div v-if="categories.length === 0" class="text-xs text-gray-400 italic">No hay categorías configuradas</div>
                <div v-else class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="categoryId = ''; subcategoryId = ''; applyFilters()"
                        class="px-3 py-1.5 rounded-full text-xs font-bold transition border"
                        :class="!categoryId ? 'bg-[#FF5722] text-white border-[#FF5722]' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    >Todas</button>
                    <button
                        v-for="c in categories"
                        :key="c.id"
                        type="button"
                        @click="categoryId = c.id; resetSubcategory()"
                        class="px-3 py-1.5 rounded-full text-xs font-bold transition border"
                        :class="Number(categoryId) === c.id ? 'bg-[#FF5722] text-white border-[#FF5722]' : 'bg-white text-gray-600 border-gray-200 hover:border-gray-300'"
                    >{{ c.name }}</button>
                </div>
            </div>

            <!-- Subcategory pills (only if category selected) -->
            <div v-if="categoryId && subcategoryOptions.length > 0">
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">Subcategoría</label>
                <div class="flex flex-wrap gap-2">
                    <button
                        type="button"
                        @click="subcategoryId = ''; applyFilters()"
                        class="px-2.5 py-1 rounded-full text-[11px] font-semibold transition border"
                        :class="!subcategoryId ? 'bg-orange-50 text-[#FF5722] border-[#FF5722]' : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300'"
                    >Todas</button>
                    <button
                        v-for="s in subcategoryOptions"
                        :key="s.id"
                        type="button"
                        @click="subcategoryId = s.id; applyFilters()"
                        class="px-2.5 py-1 rounded-full text-[11px] font-semibold transition border"
                        :class="Number(subcategoryId) === s.id ? 'bg-orange-50 text-[#FF5722] border-[#FF5722]' : 'bg-white text-gray-500 border-gray-200 hover:border-gray-300'"
                    >{{ s.name }}</button>
                </div>
            </div>

            <!-- Amount range -->
            <div>
                <label class="block text-[10px] font-bold text-gray-500 uppercase tracking-wide mb-1.5">Rango de monto</label>
                <div class="flex items-center gap-2 max-w-sm">
                    <div class="relative flex-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                        <input v-model="minAmount" @change="applyFilters" type="number" min="0" step="0.01" placeholder="Mínimo" class="w-full pl-7 pr-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30" />
                    </div>
                    <span class="text-gray-300 text-sm">—</span>
                    <div class="relative flex-1">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                        <input v-model="maxAmount" @change="applyFilters" type="number" min="0" step="0.01" placeholder="Máximo" class="w-full pl-7 pr-3 py-2 border border-gray-200 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30" />
                    </div>
                </div>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 text-xs text-gray-500 uppercase">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Gasto</th>
                        <th class="px-4 py-3 text-left font-semibold">Sucursal</th>
                        <th class="px-4 py-3 text-left font-semibold">Categoría</th>
                        <th class="px-4 py-3 text-left font-semibold">Fecha</th>
                        <th class="px-4 py-3 text-left font-semibold">Archivos</th>
                        <th class="px-4 py-3 text-right font-semibold">Monto</th>
                        <th class="px-4 py-3 text-right font-semibold"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr v-for="e in expenses" :key="e.id" class="border-t border-gray-100 hover:bg-gray-50/50 transition">
                        <td class="px-4 py-3 cursor-pointer" @click="router.visit(route('expenses.show', e.id))">
                            <p class="font-semibold text-gray-900 truncate max-w-xs" :title="e.title">{{ e.title }}</p>
                            <p v-if="e.description" class="text-xs text-gray-400 truncate max-w-xs">{{ e.description }}</p>
                        </td>
                        <td class="px-4 py-3 cursor-pointer" @click="router.visit(route('expenses.show', e.id))">
                            <span class="inline-flex items-center gap-1 text-xs font-semibold text-gray-700 bg-gray-100 px-2 py-0.5 rounded-full">
                                <span class="material-symbols-outlined text-sm text-gray-400">store</span>
                                {{ e.branch?.name ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 cursor-pointer" @click="router.visit(route('expenses.show', e.id))">
                            <p class="text-sm text-gray-700">{{ e.category?.name }}</p>
                            <p class="text-xs text-gray-400">{{ e.subcategory?.name }}</p>
                        </td>
                        <td class="px-4 py-3 text-gray-500 whitespace-nowrap cursor-pointer" @click="router.visit(route('expenses.show', e.id))">
                            <p class="text-sm">{{ dt(e.expense_date) }}</p>
                            <p class="text-[10px] text-gray-400">Capturado {{ dt(e.created_at) }}</p>
                        </td>
                        <td class="px-4 py-3">
                            <div v-if="e.attachments?.length" class="flex items-center gap-1.5 flex-wrap">
                                <button
                                    v-for="att in e.attachments.slice(0, 3)" :key="att.id"
                                    type="button"
                                    @click.stop="openPreview(att)"
                                    class="group relative size-10 rounded-lg border border-gray-200 overflow-hidden bg-gray-50 hover:border-[#FF5722] transition shrink-0"
                                    :title="att.file_name"
                                >
                                    <img
                                        v-if="isImage(att) && att.url && !brokenThumbs.has(att.id)"
                                        :src="att.url"
                                        class="w-full h-full object-cover"
                                        :alt="att.file_name"
                                        @error="onThumbError(att.id)"
                                        loading="lazy"
                                    />
                                    <div v-else-if="isPdf(att)" class="w-full h-full flex items-center justify-center bg-red-50">
                                        <span class="material-symbols-outlined text-red-400 text-lg">picture_as_pdf</span>
                                    </div>
                                    <div v-else class="w-full h-full flex items-center justify-center">
                                        <span class="material-symbols-outlined text-gray-300 text-lg">broken_image</span>
                                    </div>
                                </button>
                                <span v-if="e.attachments.length > 3" class="text-xs text-gray-500 font-semibold">+{{ e.attachments.length - 3 }}</span>
                            </div>
                            <span v-else class="text-xs text-gray-300">—</span>
                        </td>
                        <td class="px-4 py-3 text-right font-bold text-red-600 cursor-pointer whitespace-nowrap" @click="router.visit(route('expenses.show', e.id))">{{ fmt(e.amount) }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-1">
                                <Link :href="route('expenses.edit', e.id)" class="text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 p-1.5 rounded-lg transition" title="Editar">
                                    <span class="material-symbols-outlined text-base">edit</span>
                                </Link>
                            </div>
                        </td>
                    </tr>
                    <tr v-if="expenses.length === 0">
                        <td colspan="7" class="text-center py-16">
                            <span class="material-symbols-outlined text-5xl text-gray-200">receipt_long</span>
                            <p class="text-sm text-gray-400 mt-2">No hay gastos en este periodo</p>
                            <Link
                                v-if="categories.length > 0"
                                :href="route('expenses.create')"
                                class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[#FF5722] hover:text-[#D84315]"
                            >
                                <span class="material-symbols-outlined text-base">add_circle</span>
                                Registrar primer gasto
                            </Link>
                            <Link
                                v-else
                                :href="route('expense-categories.index')"
                                class="mt-4 inline-flex items-center gap-1.5 text-sm font-semibold text-[#FF5722] hover:text-[#D84315]"
                            >
                                <span class="material-symbols-outlined text-base">category</span>
                                Crear categorías primero
                            </Link>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- Attachment preview lightbox -->
        <Teleport to="body">
            <div v-if="previewAttachment" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closePreview">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="closePreview"></div>

                <!-- Close button -->
                <button
                    type="button"
                    @click="closePreview"
                    class="absolute top-4 right-4 size-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition z-10"
                    aria-label="Cerrar"
                >
                    <span class="material-symbols-outlined">close</span>
                </button>

                <!-- Download link -->
                <a
                    v-if="previewAttachment.url"
                    :href="previewAttachment.url"
                    :download="previewAttachment.file_name"
                    target="_blank"
                    rel="noopener"
                    class="absolute top-4 left-4 flex items-center gap-1.5 bg-white/10 hover:bg-white/20 text-white px-3 py-2 rounded-xl text-sm font-semibold transition z-10"
                >
                    <span class="material-symbols-outlined text-base">download</span>
                    Descargar
                </a>

                <div class="relative max-w-5xl max-h-[90vh] w-full flex flex-col items-center">
                    <!-- Image preview -->
                    <img
                        v-if="isImage(previewAttachment) && previewAttachment.url && !previewError"
                        :src="previewAttachment.url"
                        :alt="previewAttachment.file_name"
                        class="max-w-full max-h-[85vh] object-contain rounded-xl shadow-2xl"
                        @error="onImageError"
                    />
                    <!-- PDF preview (iframe) -->
                    <iframe
                        v-else-if="isPdf(previewAttachment) && previewAttachment.url"
                        :src="previewAttachment.url"
                        class="w-full h-[85vh] rounded-xl bg-white shadow-2xl"
                        :title="previewAttachment.file_name"
                    ></iframe>
                    <!-- Unsupported / broken -->
                    <div v-else class="bg-white rounded-xl shadow-2xl p-12 text-center max-w-md">
                        <span class="material-symbols-outlined text-6xl text-gray-300">broken_image</span>
                        <p class="text-gray-700 font-semibold mt-3">No se puede previsualizar</p>
                        <p class="text-xs text-gray-500 mt-1">{{ previewAttachment.mime_type || 'Formato desconocido' }}</p>
                        <a
                            v-if="previewAttachment.url"
                            :href="previewAttachment.url"
                            target="_blank"
                            rel="noopener"
                            class="mt-4 inline-flex items-center gap-1.5 bg-[#FF5722] hover:bg-[#D84315] text-white px-4 py-2 rounded-xl text-sm font-bold"
                        >
                            <span class="material-symbols-outlined text-base">download</span>
                            Descargar archivo
                        </a>
                    </div>

                    <p class="mt-3 text-sm text-white/80 text-center">{{ previewAttachment.file_name }}</p>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
