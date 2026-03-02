<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import { ref } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    product: Object,
    categories: Array,
    allModifierGroups: Array,
})

const imagePreview = ref(props.product.image_url ?? null)

const form = useForm({
    _method: 'put',
    name: props.product.name,
    description: props.product.description ?? '',
    price: props.product.price,
    production_cost: props.product.production_cost ?? '',
    category_id: props.product.category_id,
    sort_order: props.product.sort_order ?? 0,
    is_active: props.product.is_active,
    image: null,
    modifier_group_ids: (props.product.modifier_groups || []).map(g => g.id),
})

function handleImageChange(event) {
    const file = event.target.files[0]
    if (!file) { return }
    form.image = file
    imagePreview.value = URL.createObjectURL(file)
}

function submit() {
    form.post(route('products.update', props.product.id), {
        forceFormData: true,
    })
}

</script>

<template>
    <Head title="Editar Producto" />
    <AppLayout title="Editar Producto">

        <h1 class="text-2xl font-bold text-gray-900 mb-6">Editar Producto</h1>

        <div class="grid grid-cols-3 gap-6 pb-24">

            <!-- Left column (2/3) -->
            <div class="col-span-2 space-y-5">

                <!-- Información básica -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">info</span>
                        <h2 class="font-semibold text-gray-900">Información Básica</h2>
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Nombre del producto</label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                :class="{ 'border-red-400': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Descripción</label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors resize-none"
                            />
                            <p class="text-right text-xs text-gray-400 mt-1">{{ (form.description || '').length }}/2000 caracteres</p>
                        </div>
                    </div>
                </div>

                <!-- Precios -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">payments</span>
                        <h2 class="font-semibold text-gray-900">Precios y Costos</h2>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Precio de venta</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                <input
                                    v-model="form.price"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    required
                                    class="w-full rounded-xl border border-gray-200 pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                    :class="{ 'border-red-400': form.errors.price }"
                                />
                            </div>
                            <p v-if="form.errors.price" class="mt-1 text-xs text-red-500">{{ form.errors.price }}</p>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Costo de producción</label>
                            <div class="relative">
                                <span class="absolute left-4 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                                <input
                                    v-model="form.production_cost"
                                    type="number"
                                    min="0"
                                    step="0.01"
                                    class="w-full rounded-xl border border-gray-200 pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                />
                            </div>
                            <p class="text-xs text-gray-400 mt-1">Solo visible para administradores</p>
                        </div>
                    </div>
                </div>

                <!-- Modificadores -->
                <div v-if="allModifierGroups && allModifierGroups.length > 0" class="bg-white rounded-2xl border border-gray-100 p-6">
                    <div class="flex items-center gap-2 mb-4">
                        <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">tune</span>
                        <h2 class="font-semibold text-gray-900">Grupos de Modificadores</h2>
                    </div>
                    <p class="text-xs text-gray-400 mb-3">Selecciona los grupos de modificadores disponibles para este producto.</p>
                    <div class="space-y-2">
                        <label
                            v-for="mg in allModifierGroups"
                            :key="mg.id"
                            class="flex items-center gap-3 px-3 py-2.5 rounded-xl border cursor-pointer transition-colors"
                            :class="form.modifier_group_ids.includes(mg.id) ? 'border-[#FF5722] bg-orange-50/50' : 'border-gray-100 hover:bg-gray-50'"
                        >
                            <input
                                type="checkbox"
                                :value="mg.id"
                                v-model="form.modifier_group_ids"
                                class="rounded border-gray-300 text-[#FF5722] focus:ring-[#FF5722]/30"
                            />
                            <span class="text-sm text-gray-700">{{ mg.name }}</span>
                        </label>
                    </div>
                </div>

            </div>

            <!-- Right column -->
            <div class="space-y-5">

                <!-- Imagen -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">Imagen del Producto</h2>
                    <div
                        class="border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:border-[#FF5722]/40 transition-colors cursor-pointer bg-orange-50/30"
                        @click="$refs.imageInput.click()"
                    >
                        <img v-if="imagePreview" :src="imagePreview" class="mx-auto h-32 w-32 object-cover rounded-xl mb-2" />
                        <div v-else class="flex flex-col items-center">
                            <span class="material-symbols-outlined text-gray-300 text-4xl mb-2" style="font-variation-settings:'FILL' 1">add_photo_alternate</span>
                            <p class="text-sm font-medium text-gray-600">Subir Imagen</p>
                            <p class="text-xs text-gray-400 mt-1">RECOMENDADO: 1:1</p>
                        </div>
                        <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="handleImageChange" />
                    </div>
                    <p v-if="form.errors.image" class="mt-1 text-xs text-red-500">{{ form.errors.image }}</p>
                </div>

                <!-- Organización -->
                <div class="bg-white rounded-2xl border border-gray-100 p-6">
                    <h2 class="font-semibold text-gray-900 mb-4">Organización</h2>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Categoría</label>
                            <select
                                v-model="form.category_id"
                                required
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            >
                                <option v-for="cat in categories" :key="cat.id" :value="cat.id">{{ cat.name }}</option>
                            </select>
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Orden de visualización</label>
                            <input
                                v-model.number="form.sort_order"
                                type="number"
                                min="0"
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                            />
                        </div>

                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Estado</label>
                            <div
                                class="flex items-center gap-3 border border-gray-200 rounded-xl px-4 py-2.5 cursor-pointer"
                                @click="form.is_active = !form.is_active"
                            >
                                <span class="text-sm text-gray-700">{{ form.is_active ? 'Visible en menú digital' : 'Oculto del menú' }}</span>
                                <div class="ml-auto w-10 h-6 rounded-full transition-colors relative"
                                    :class="form.is_active ? 'bg-[#FF5722]' : 'bg-gray-200'">
                                    <div class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all"
                                        :class="form.is_active ? 'left-5' : 'left-1'" />
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Bottom action bar -->
        <div class="fixed bottom-0 left-[260px] right-0 bg-white border-t border-gray-100 px-6 py-4 flex items-center justify-between z-10">
            <Link :href="route('menu.index')" class="text-sm text-gray-500 hover:text-gray-700 font-medium">Cancelar</Link>
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
