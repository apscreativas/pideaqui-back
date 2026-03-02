<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, watch, ref } from 'vue'

const props = defineProps({
    show: Boolean,
    category: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['close'])

const imagePreview = ref(null)

const form = useForm({
    name: '',
    description: '',
    sort_order: 0,
    is_active: true,
    image: null,
})

watch(() => props.category, (cat) => {
    if (cat) {
        form.name = cat.name
        form.description = cat.description ?? ''
        form.sort_order = cat.sort_order ?? 0
        form.is_active = cat.is_active ?? true
        imagePreview.value = cat.image_url ?? null
    } else {
        form.reset()
        imagePreview.value = null
    }
}, { immediate: true })

const isEditing = computed(() => !!props.category)
const title = computed(() => isEditing.value ? 'Editar Categoría' : 'Nueva Categoría')
const subtitle = computed(() => isEditing.value ? 'Modifica los detalles de esta categoría del menú.' : 'Agrega una nueva categoría al menú.')

function handleImageChange(event) {
    const file = event.target.files[0]
    if (!file) { return }
    form.image = file
    imagePreview.value = URL.createObjectURL(file)
}

function submit() {
    if (isEditing.value) {
        form.transform((data) => ({ ...data, _method: 'put' })).post(route('categories.update', props.category.id), {
            forceFormData: true,
            onSuccess: () => emit('close'),
        })
    } else {
        form.post(route('categories.store'), {
            forceFormData: true,
            onSuccess: () => emit('close'),
        })
    }
}
</script>

<template>
    <Transition name="modal">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <!-- Backdrop -->
            <div class="absolute inset-0 bg-black/40" @click="emit('close')" />

            <!-- Modal -->
            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-1">
                        <h2 class="text-xl font-bold text-gray-900">{{ title }}</h2>
                        <button @click="emit('close')" class="text-gray-400 hover:text-gray-600 transition-colors ml-4">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    <p class="text-sm text-[#FF5722] mb-5">{{ subtitle }}</p>

                    <form @submit.prevent="submit" class="space-y-4">

                        <!-- Nombre -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Nombre de la categoría
                            </label>
                            <input
                                v-model="form.name"
                                type="text"
                                required
                                placeholder="Ej: Tacos, Bebidas, Postres..."
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                :class="{ 'border-red-400': form.errors.name }"
                            />
                            <p v-if="form.errors.name" class="mt-1 text-xs text-red-500">{{ form.errors.name }}</p>
                        </div>

                        <!-- Descripción -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Descripción
                            </label>
                            <textarea
                                v-model="form.description"
                                rows="3"
                                placeholder="Descripción opcional de la categoría..."
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors resize-none"
                            />
                        </div>

                        <!-- Imagen -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Imagen de categoría
                            </label>
                            <div
                                class="relative border-2 border-dashed border-gray-200 rounded-xl p-6 text-center hover:border-[#FF5722]/40 transition-colors cursor-pointer bg-orange-50/30"
                                @click="$refs.imageInput.click()"
                            >
                                <img v-if="imagePreview" :src="imagePreview" class="mx-auto h-20 w-20 object-cover rounded-xl mb-2" />
                                <div v-else class="flex flex-col items-center">
                                    <span class="material-symbols-outlined text-[#FF5722] text-3xl mb-1" style="font-variation-settings:'FILL' 1">add_photo_alternate</span>
                                    <p class="text-sm font-medium text-gray-700">Sube una imagen</p>
                                    <p class="text-xs text-[#FF5722]">PNG, JPG hasta 5MB</p>
                                </div>
                                <input ref="imageInput" type="file" accept="image/*" class="hidden" @change="handleImageChange" />
                            </div>
                            <p v-if="form.errors.image" class="mt-1 text-xs text-red-500">{{ form.errors.image }}</p>
                        </div>

                        <!-- Sort order + Estado -->
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Orden de visualización
                                </label>
                                <div class="flex items-center gap-2 border border-gray-200 rounded-xl px-4 py-2.5">
                                    <span class="material-symbols-outlined text-gray-400 text-lg">sort</span>
                                    <input
                                        v-model.number="form.sort_order"
                                        type="number"
                                        min="0"
                                        class="w-full text-sm focus:outline-none"
                                    />
                                </div>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                    Estado
                                </label>
                                <div
                                    class="flex items-center gap-3 border border-gray-200 rounded-xl px-4 py-2.5 cursor-pointer"
                                    @click="form.is_active = !form.is_active"
                                >
                                    <span class="text-sm text-gray-700">{{ form.is_active ? 'Activa' : 'Inactiva' }}</span>
                                    <div class="ml-auto w-10 h-6 rounded-full transition-colors relative"
                                        :class="form.is_active ? 'bg-[#FF5722]' : 'bg-gray-200'">
                                        <div class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all"
                                            :class="form.is_active ? 'left-5' : 'left-1'" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Buttons -->
                        <div class="flex gap-3 pt-2">
                            <button
                                type="button"
                                @click="emit('close')"
                                class="flex-1 border border-gray-200 text-gray-700 font-semibold rounded-full py-2.5 text-sm hover:bg-gray-50 transition-colors"
                            >
                                Cancelar
                            </button>
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="flex-1 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-full py-2.5 text-sm transition-colors disabled:opacity-60"
                            >
                                {{ form.processing ? 'Guardando…' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </Transition>
</template>

<style scoped>
.modal-enter-active, .modal-leave-active {
    transition: opacity 0.2s ease;
}
.modal-enter-from, .modal-leave-to {
    opacity: 0;
}
</style>
