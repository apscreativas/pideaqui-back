<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    expense: Object,
})

function fmt(v) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0) }
function dt(d) { return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }).format(new Date(d)) }
function dtDate(d) { return new Intl.DateTimeFormat('es-MX', { day: '2-digit', month: 'long', year: 'numeric' }).format(new Date(d)) }

function isImage(att) { return !!att?.is_image || att?.mime_type?.startsWith('image/') }
function isPdf(att) { return !!att?.is_pdf || att?.mime_type === 'application/pdf' }

const previewAttachment = ref(null)
const previewError = ref(false)
function openPreview(att) { previewAttachment.value = att; previewError.value = false }
function closePreview() { previewAttachment.value = null; previewError.value = false }
function onImageError() { previewError.value = true }
const brokenThumbs = ref(new Set())
function onThumbError(id) { brokenThumbs.value.add(id); brokenThumbs.value = new Set(brokenThumbs.value) }

function destroy() {
    if (!confirm('¿Eliminar el gasto? Esta acción no se puede deshacer.')) { return }
    router.delete(route('expenses.destroy', props.expense.id))
}
</script>

<template>
    <Head :title="expense.title" />
    <AppLayout :title="expense.title">

        <!-- Header -->
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6">
            <div class="min-w-0">
                <Link :href="route('expenses.index')" class="text-sm text-gray-500 hover:text-[#FF5722] flex items-center gap-1 mb-2 transition">
                    <span class="material-symbols-outlined text-lg">arrow_back</span>
                    Gastos
                </Link>
                <h1 class="text-2xl font-black text-gray-900">{{ expense.title }}</h1>
                <p class="text-sm text-gray-500 mt-1">{{ expense.category?.name }} · {{ expense.subcategory?.name }}</p>
            </div>
            <div class="flex items-center gap-2">
                <Link :href="route('expenses.edit', expense.id)" class="flex items-center gap-1.5 border border-gray-200 hover:bg-gray-50 px-4 py-2.5 rounded-xl text-sm font-semibold transition">
                    <span class="material-symbols-outlined text-lg">edit</span>
                    Editar
                </Link>
                <button @click="destroy" type="button" class="flex items-center gap-1.5 border border-red-200 text-red-600 hover:bg-red-50 px-4 py-2.5 rounded-xl text-sm font-semibold transition">
                    <span class="material-symbols-outlined text-lg">delete</span>
                    Eliminar
                </button>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: info -->
            <div class="lg:col-span-2 space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Fecha del gasto</p>
                            <p class="font-semibold text-gray-900">{{ dtDate(expense.expense_date) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Capturado</p>
                            <p class="font-semibold text-gray-900">{{ dt(expense.created_at) }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Categoría</p>
                            <p class="font-semibold text-gray-900">{{ expense.category?.name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Subcategoría</p>
                            <p class="font-semibold text-gray-900">{{ expense.subcategory?.name }}</p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Sucursal</p>
                            <p class="font-semibold text-gray-900 flex items-center gap-1">
                                <span class="material-symbols-outlined text-base text-gray-400">store</span>
                                {{ expense.branch?.name ?? '—' }}
                            </p>
                        </div>
                        <div>
                            <p class="text-[10px] font-bold text-gray-400 uppercase">Creado por</p>
                            <p class="font-semibold text-gray-900">{{ expense.creator?.name ?? '—' }}</p>
                        </div>
                    </div>

                    <div v-if="expense.description">
                        <p class="text-[10px] font-bold text-gray-400 uppercase mb-1">Descripción</p>
                        <p class="text-sm text-gray-700 bg-gray-50 border border-gray-100 rounded-lg p-3 whitespace-pre-wrap">{{ expense.description }}</p>
                    </div>
                </div>

                <!-- Attachments -->
                <div v-if="expense.attachments?.length" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-1.5">
                        <span class="material-symbols-outlined text-[#FF5722] text-base">attach_file</span>
                        Archivos ({{ expense.attachments.length }})
                        <span class="text-xs text-gray-400 font-normal ml-2">Click para previsualizar</span>
                    </h3>
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
                        <button
                            v-for="att in expense.attachments" :key="att.id"
                            type="button"
                            @click="openPreview(att)"
                            class="flex flex-col border border-gray-200 rounded-lg overflow-hidden hover:border-[#FF5722] hover:shadow-md transition bg-white group text-left"
                        >
                            <!-- Image thumbnail -->
                            <div v-if="isImage(att) && att.url && !brokenThumbs.has(att.id)" class="h-32 w-full bg-gray-100 overflow-hidden">
                                <img
                                    :src="att.url"
                                    class="w-full h-full object-cover group-hover:scale-105 transition"
                                    :alt="att.file_name"
                                    @error="onThumbError(att.id)"
                                    loading="lazy"
                                />
                            </div>
                            <!-- PDF thumbnail -->
                            <div v-else-if="isPdf(att)" class="h-32 w-full bg-gradient-to-br from-red-50 to-red-100/50 flex flex-col items-center justify-center">
                                <span class="material-symbols-outlined text-5xl text-red-400">picture_as_pdf</span>
                                <span class="text-[10px] font-bold text-red-500 uppercase mt-1">PDF</span>
                            </div>
                            <!-- Broken/unknown fallback -->
                            <div v-else class="h-32 w-full bg-gray-100 flex flex-col items-center justify-center">
                                <span class="material-symbols-outlined text-5xl text-gray-300">broken_image</span>
                                <span class="text-[10px] font-bold text-gray-400 uppercase mt-1">Archivo</span>
                            </div>
                            <div class="px-3 py-2 border-t border-gray-100">
                                <p class="text-xs font-semibold text-gray-800 truncate" :title="att.file_name">{{ att.file_name }}</p>
                                <p class="text-[10px] text-gray-400">{{ att.mime_type }}</p>
                            </div>
                        </button>
                    </div>
                </div>
            </div>

            <!-- Right: amount -->
            <aside class="space-y-4">
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 text-center">
                    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wide mb-2">Monto del gasto</p>
                    <p class="text-4xl font-black text-red-600">{{ fmt(expense.amount) }}</p>
                </div>
            </aside>
        </div>

        <!-- Attachment preview lightbox -->
        <Teleport to="body">
            <div v-if="previewAttachment" class="fixed inset-0 z-50 flex items-center justify-center p-4" @click.self="closePreview">
                <div class="absolute inset-0 bg-black/80 backdrop-blur-sm" @click="closePreview"></div>

                <button
                    type="button"
                    @click="closePreview"
                    class="absolute top-4 right-4 size-10 rounded-full bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition z-10"
                >
                    <span class="material-symbols-outlined">close</span>
                </button>

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
                    <!-- Unsupported or missing URL -->
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
