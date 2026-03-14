<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import CategoryModal from './Partials/CategoryModal.vue'
import ConfirmModal from '@/Components/ConfirmModal.vue'

const props = defineProps({
    categories: Array,
})

// ─── Local ordered copy for optimistic DnD ─────────────────────────────────

const localCategories = ref(props.categories.map((c) => ({
    ...c,
    products: [...c.products],
})))

// Sync when Inertia refreshes props (after server response)
router.on('success', () => {
    localCategories.value = props.categories.map((c) => ({
        ...c,
        products: [...c.products],
    }))
})

const expandedCategories = ref(new Set(props.categories.map((c) => c.id)))
const showCategoryModal = ref(false)
const editingCategory = ref(null)

const totalProducts = computed(() => localCategories.value.reduce((sum, c) => sum + c.products.length, 0))
const activeCategories = computed(() => localCategories.value.filter((c) => c.is_active).length)
const outOfStock = computed(() => localCategories.value.reduce((sum, c) => sum + c.products.filter((p) => !p.is_active).length, 0))

function toggleCategory(id) {
    if (expandedCategories.value.has(id)) {
        expandedCategories.value.delete(id)
    } else {
        expandedCategories.value.add(id)
    }
}

function openNewCategory() {
    editingCategory.value = null
    showCategoryModal.value = true
}

function openEditCategory(category) {
    editingCategory.value = category
    showCategoryModal.value = true
}

function closeModal() {
    showCategoryModal.value = false
    editingCategory.value = null
}

const confirmTarget = ref(null)
const confirmType = ref(null)

function deleteCategory(category) {
    confirmTarget.value = category
    confirmType.value = 'category'
}

function deleteProduct(product) {
    confirmTarget.value = product
    confirmType.value = 'product'
}

const confirmTitle = computed(() => confirmType.value === 'category' ? '¿Eliminar categoria?' : '¿Eliminar producto?')
const confirmMessage = computed(() => {
    if (!confirmTarget.value) { return '' }
    return confirmType.value === 'category'
        ? `La categoria "${confirmTarget.value.name}" y sus productos se eliminaran permanentemente.`
        : `El producto "${confirmTarget.value.name}" se eliminara permanentemente.`
})

function onConfirmDelete() {
    if (confirmType.value === 'category') {
        router.delete(route('categories.destroy', confirmTarget.value.id))
    } else {
        router.delete(route('products.destroy', confirmTarget.value.id))
    }
    confirmTarget.value = null
    confirmType.value = null
}

function onCancelDelete() {
    confirmTarget.value = null
    confirmType.value = null
}

function toggleProduct(product) {
    router.patch(route('products.toggle', product.id))
}

function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}

// ─── Category Drag & Drop ───────────────────────────────────────────────────

const draggingCategoryId = ref(null)
const categoryDropTarget = ref(null)

function onCatDragStart(category, event) {
    draggingCategoryId.value = category.id
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(category.id))
}

function onCatDragOver(category, event) {
    if (!draggingCategoryId.value || draggingCategoryId.value === category.id) { return }
    event.preventDefault()
    event.dataTransfer.dropEffect = 'move'
    categoryDropTarget.value = category.id
}

function onCatDragLeave(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) {
        categoryDropTarget.value = null
    }
}

function onCatDrop(targetCategory, event) {
    event.preventDefault()
    categoryDropTarget.value = null

    const fromId = draggingCategoryId.value
    if (!fromId || fromId === targetCategory.id) { return }

    const cats = [...localCategories.value]
    const fromIdx = cats.findIndex((c) => c.id === fromId)
    const toIdx = cats.findIndex((c) => c.id === targetCategory.id)
    if (fromIdx === -1 || toIdx === -1) { return }

    const [moved] = cats.splice(fromIdx, 1)
    cats.splice(toIdx, 0, moved)
    localCategories.value = cats

    router.patch(route('categories.reorder'), {
        ids: cats.map((c) => c.id),
    }, { preserveScroll: true, preserveState: true })
}

function onCatDragEnd() {
    draggingCategoryId.value = null
    categoryDropTarget.value = null
}

// ─── Product Drag & Drop ────────────────────────────────────────────────────

const draggingProductId = ref(null)
const draggingProductCategoryId = ref(null)
const productDropTarget = ref(null)

function onProdDragStart(product, categoryId, event) {
    draggingProductId.value = product.id
    draggingProductCategoryId.value = categoryId
    event.dataTransfer.effectAllowed = 'move'
    event.dataTransfer.setData('text/plain', String(product.id))
}

function onProdDragOver(product, categoryId, event) {
    if (!draggingProductId.value || draggingProductId.value === product.id) { return }
    if (draggingProductCategoryId.value !== categoryId) { return }
    event.preventDefault()
    event.dataTransfer.dropEffect = 'move'
    productDropTarget.value = product.id
}

function onProdDragLeave(event) {
    if (!event.currentTarget.contains(event.relatedTarget)) {
        productDropTarget.value = null
    }
}

function onProdDrop(targetProduct, categoryId, event) {
    event.preventDefault()
    productDropTarget.value = null

    const fromId = draggingProductId.value
    if (!fromId || fromId === targetProduct.id) { return }
    if (draggingProductCategoryId.value !== categoryId) { return }

    const catIdx = localCategories.value.findIndex((c) => c.id === categoryId)
    if (catIdx === -1) { return }

    const products = [...localCategories.value[catIdx].products]
    const fromIdx = products.findIndex((p) => p.id === fromId)
    const toIdx = products.findIndex((p) => p.id === targetProduct.id)
    if (fromIdx === -1 || toIdx === -1) { return }

    const [moved] = products.splice(fromIdx, 1)
    products.splice(toIdx, 0, moved)
    localCategories.value[catIdx].products = products

    router.patch(route('products.reorder'), {
        ids: products.map((p) => p.id),
    }, { preserveScroll: true, preserveState: true })
}

function onProdDragEnd() {
    draggingProductId.value = null
    draggingProductCategoryId.value = null
    productDropTarget.value = null
}
</script>

<template>
    <Head title="Menu Digital" />
    <AppLayout title="Menu Digital">

        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Menu Digital</h1>
                <p class="mt-1 text-sm text-gray-500">Gestiona tus categorias, productos y disponibilidad. Arrastra para reordenar.</p>
            </div>
            <div class="flex items-center gap-3">
                <Link
                    :href="route('products.create')"
                    class="flex items-center gap-2 border border-gray-200 text-gray-700 font-semibold rounded-xl px-4 py-2.5 text-sm hover:bg-gray-50 transition-colors"
                >
                    <span class="material-symbols-outlined text-lg">add</span>
                    Nuevo Producto
                </Link>
                <button
                    @click="openNewCategory"
                    class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors"
                >
                    <span class="material-symbols-outlined text-lg">add</span>
                    Nueva Categoria
                </button>
            </div>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-2xl border border-gray-100 p-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Total Productos</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ totalProducts }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 p-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Categorias Activas</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ activeCategories }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 p-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Inactivos</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ outOfStock }}</p>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="localCategories.length === 0" class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
            <span class="material-symbols-outlined text-4xl text-gray-300 mb-3" style="font-variation-settings:'FILL' 1">restaurant_menu</span>
            <p class="text-gray-500 font-medium mb-1">No hay categorias aun</p>
            <p class="text-sm text-gray-400 mb-4">Crea tu primera categoria para comenzar a agregar productos.</p>
            <button @click="openNewCategory" class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors">
                + Nueva Categoria
            </button>
        </div>

        <!-- Categories accordion with DnD -->
        <div v-else class="space-y-3">
            <div
                v-for="(category, catIndex) in localCategories"
                :key="category.id"
                class="bg-white rounded-2xl border overflow-hidden transition-all"
                :class="[
                    categoryDropTarget === category.id ? 'border-[#FF5722] ring-2 ring-[#FF5722]/20' : 'border-gray-100',
                    draggingCategoryId === category.id ? 'opacity-50' : '',
                ]"
                draggable="true"
                @dragstart="onCatDragStart(category, $event)"
                @dragover="onCatDragOver(category, $event)"
                @dragleave="onCatDragLeave($event)"
                @drop="onCatDrop(category, $event)"
                @dragend="onCatDragEnd"
            >

                <!-- Category header -->
                <div class="flex items-center gap-3 px-5 py-4">
                    <!-- Position number + drag handle -->
                    <div class="flex items-center gap-1.5 shrink-0 cursor-grab active:cursor-grabbing select-none">
                        <span class="text-xs font-bold text-gray-300 w-5 text-center">{{ catIndex + 1 }}</span>
                        <span class="material-symbols-outlined text-gray-300 hover:text-gray-400 transition-colors">drag_indicator</span>
                    </div>

                    <div class="flex-1 flex items-center gap-3 min-w-0">
                        <img
                            v-if="category.image_path"
                            :src="category.image_url"
                            class="w-10 h-10 rounded-xl object-cover shrink-0"
                        />
                        <div class="w-10 h-10 rounded-xl bg-orange-50 flex items-center justify-center shrink-0" v-else>
                            <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">category</span>
                        </div>
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <h3 class="font-semibold text-gray-900">{{ category.name }}</h3>
                                <span
                                    class="text-xs font-medium px-2 py-0.5 rounded-full"
                                    :class="category.is_active
                                        ? 'bg-green-50 text-green-700'
                                        : 'bg-gray-100 text-gray-500'"
                                >
                                    {{ category.is_active ? 'Activo' : 'Inactivo' }}
                                </span>
                            </div>
                            <p class="text-xs text-gray-400">{{ category.products.length }} productos</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <button
                            @click="openEditCategory(category)"
                            class="p-2 text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 rounded-xl transition-colors"
                            title="Editar"
                        >
                            <span class="material-symbols-outlined text-lg">edit</span>
                        </button>
                        <button
                            @click="deleteCategory(category)"
                            class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-xl transition-colors"
                            title="Eliminar"
                        >
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                        <button
                            @click="toggleCategory(category.id)"
                            class="p-2 text-gray-400 hover:text-gray-700 hover:bg-gray-50 rounded-xl transition-colors"
                        >
                            <span class="material-symbols-outlined text-lg">{{ expandedCategories.has(category.id) ? 'expand_less' : 'expand_more' }}</span>
                        </button>
                    </div>
                </div>

                <!-- Products list -->
                <div v-if="expandedCategories.has(category.id)" class="border-t border-gray-50">

                    <!-- Add product button -->
                    <div class="px-5 py-2 flex justify-end">
                        <Link
                            :href="route('products.create')"
                            class="text-sm text-[#FF5722] hover:text-[#D84315] font-medium transition-colors"
                        >
                            + Agregar producto
                        </Link>
                    </div>

                    <!-- Empty products -->
                    <div v-if="category.products.length === 0" class="px-5 py-4 text-center text-sm text-gray-400">
                        No hay productos en esta categoria.
                    </div>

                    <!-- Products table with DnD rows -->
                    <table v-else class="w-full">
                        <thead>
                            <tr class="border-b border-gray-50">
                                <th class="px-3 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide w-20">#</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide w-16">Foto</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Nombre del producto</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Precio</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-[#FF5722] uppercase tracking-wide">Costo</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Estado</th>
                                <th class="px-5 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="(product, prodIndex) in category.products"
                                :key="product.id"
                                class="border-b border-gray-50 last:border-0 transition-all"
                                :class="[
                                    productDropTarget === product.id ? 'bg-orange-50/70 ring-1 ring-[#FF5722]/30' : 'hover:bg-gray-50/50',
                                    draggingProductId === product.id ? 'opacity-50' : '',
                                ]"
                                draggable="true"
                                @dragstart.stop="onProdDragStart(product, category.id, $event)"
                                @dragover.stop="onProdDragOver(product, category.id, $event)"
                                @dragleave="onProdDragLeave($event)"
                                @drop.stop="onProdDrop(product, category.id, $event)"
                                @dragend="onProdDragEnd"
                            >
                                <td class="px-3 py-3">
                                    <div class="flex items-center gap-1 cursor-grab active:cursor-grabbing select-none">
                                        <span class="text-xs font-bold text-gray-300 w-4 text-center">{{ prodIndex + 1 }}</span>
                                        <span class="material-symbols-outlined text-gray-300 hover:text-gray-400 transition-colors text-lg">drag_indicator</span>
                                    </div>
                                </td>
                                <td class="px-2 py-3">
                                    <img
                                        v-if="product.image_path"
                                        :src="product.image_url"
                                        class="w-10 h-10 rounded-xl object-cover"
                                    />
                                    <div v-else class="w-10 h-10 rounded-xl bg-gray-100 flex items-center justify-center">
                                        <span class="material-symbols-outlined text-gray-300 text-lg">image</span>
                                    </div>
                                </td>
                                <td class="px-2 py-3">
                                    <p class="text-sm font-medium text-gray-900">{{ product.name }}</p>
                                    <p v-if="product.description" class="text-xs text-gray-400 truncate max-w-xs">{{ product.description }}</p>
                                </td>
                                <td class="px-2 py-3 text-sm font-medium text-gray-900">
                                    {{ formatPrice(product.price) }}
                                </td>
                                <td class="px-2 py-3 text-sm font-medium text-[#FF5722]">
                                    {{ product.production_cost ? formatPrice(product.production_cost) : '—' }}
                                </td>
                                <td class="px-2 py-3">
                                    <button
                                        @click="toggleProduct(product)"
                                        class="w-10 h-6 rounded-full transition-colors relative"
                                        :class="product.is_active ? 'bg-[#FF5722]' : 'bg-gray-200'"
                                    >
                                        <div class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all"
                                            :class="product.is_active ? 'left-5' : 'left-1'" />
                                    </button>
                                </td>
                                <td class="px-5 py-3">
                                    <div class="flex items-center gap-1">
                                        <Link
                                            :href="route('products.edit', product.id)"
                                            class="p-1.5 text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 rounded-lg transition-colors"
                                        >
                                            <span class="material-symbols-outlined text-base">edit</span>
                                        </Link>
                                        <button
                                            @click="deleteProduct(product)"
                                            class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                                        >
                                            <span class="material-symbols-outlined text-base">delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Category modal -->
        <CategoryModal
            :show="showCategoryModal"
            :category="editingCategory"
            @close="closeModal"
        />

        <!-- Confirm delete modal -->
        <ConfirmModal
            :show="!!confirmTarget"
            :title="confirmTitle"
            :message="confirmMessage"
            confirm-label="Eliminar"
            @confirm="onConfirmDelete"
            @cancel="onCancelDelete"
        />

    </AppLayout>
</template>
