<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import CategoryModal from './Partials/CategoryModal.vue'

const props = defineProps({
    categories: Array,
})

const expandedCategories = ref(new Set(props.categories.map(c => c.id)))
const showCategoryModal = ref(false)
const editingCategory = ref(null)

const totalProducts = computed(() => props.categories.reduce((sum, c) => sum + c.products.length, 0))
const activeCategories = computed(() => props.categories.filter(c => c.is_active).length)
const outOfStock = computed(() => props.categories.reduce((sum, c) => sum + c.products.filter(p => !p.is_active).length, 0))

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

function deleteCategory(category) {
    if (!confirm(`¿Eliminar la categoría "${category.name}"? Esta acción no se puede deshacer.`)) { return }
    router.delete(route('categories.destroy', category.id))
}

function toggleProduct(product) {
    router.patch(route('products.toggle', product.id))
}

function deleteProduct(product) {
    if (!confirm(`¿Eliminar el producto "${product.name}"?`)) { return }
    router.delete(route('products.destroy', product.id))
}

function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}
</script>

<template>
    <Head title="Menú Digital" />
    <AppLayout title="Menú Digital">

        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Menú Digital</h1>
                <p class="mt-1 text-sm text-gray-500">Gestiona tus categorías, productos y disponibilidad.</p>
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
                    Nueva Categoría
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
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Categorías Activas</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ activeCategories }}</p>
            </div>
            <div class="bg-white rounded-2xl border border-gray-100 p-4">
                <p class="text-xs font-medium text-gray-400 uppercase tracking-wide">Inactivos</p>
                <p class="text-3xl font-bold text-gray-900 mt-1">{{ outOfStock }}</p>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="categories.length === 0" class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
            <span class="material-symbols-outlined text-4xl text-gray-300 mb-3" style="font-variation-settings:'FILL' 1">restaurant_menu</span>
            <p class="text-gray-500 font-medium mb-1">No hay categorías aún</p>
            <p class="text-sm text-gray-400 mb-4">Crea tu primera categoría para comenzar a agregar productos.</p>
            <button @click="openNewCategory" class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors">
                + Nueva Categoría
            </button>
        </div>

        <!-- Categories accordion -->
        <div v-else class="space-y-3">
            <div v-for="category in categories" :key="category.id" class="bg-white rounded-2xl border border-gray-100 overflow-hidden">

                <!-- Category header -->
                <div class="flex items-center gap-3 px-5 py-4">
                    <span class="material-symbols-outlined text-gray-300 cursor-grab">drag_indicator</span>
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
                                    {{ category.is_active ? '• Activo' : '• Inactivo' }}
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
                        No hay productos en esta categoría.
                    </div>

                    <!-- Products table -->
                    <table v-else class="w-full">
                        <thead>
                            <tr class="border-b border-gray-50">
                                <th class="px-5 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide w-16">Foto</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Nombre del producto</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Precio</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-[#FF5722] uppercase tracking-wide">Costo</th>
                                <th class="px-2 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Estado</th>
                                <th class="px-5 py-2 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="product in category.products"
                                :key="product.id"
                                class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors"
                            >
                                <td class="px-5 py-3">
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

        <!-- Modifiers link -->
        <div class="mt-4">
            <Link
                :href="route('modifiers.index')"
                class="flex items-center gap-2 text-sm text-gray-500 hover:text-[#FF5722] transition-colors"
            >
                <span class="material-symbols-outlined text-lg">tune</span>
                Gestionar grupos de modificadores
            </Link>
        </div>

        <!-- Category modal -->
        <CategoryModal
            :show="showCategoryModal"
            :category="editingCategory"
            @close="closeModal"
        />

    </AppLayout>
</template>
