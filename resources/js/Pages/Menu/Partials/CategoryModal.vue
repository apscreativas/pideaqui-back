<script setup>
import { useForm } from '@inertiajs/vue3'
import { computed, watch, ref } from 'vue'
import TimePicker from '@/Components/TimePicker.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'

const props = defineProps({
    show: Boolean,
    category: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['close'])

const imagePreview = ref(null)
const sizeWarning = ref(null)
const scheduleEnabled = ref(false)

const DAY_LABELS = ['D', 'L', 'M', 'Mi', 'J', 'V', 'S']

const form = useForm({
    name: '',
    description: '',
    is_active: true,
    image: null,
    available_days: null,
    available_from: null,
    available_until: null,
})

watch(() => props.category, (cat) => {
    if (cat) {
        form.name = cat.name
        form.description = cat.description ?? ''
        form.is_active = cat.is_active ?? true
        imagePreview.value = cat.image_url ?? null
        if (cat.available_days !== null && cat.available_days !== undefined) {
            scheduleEnabled.value = true
            form.available_days = cat.available_days.map(Number)
            form.available_from = cat.available_from ?? null
            form.available_until = cat.available_until ?? null
        } else {
            scheduleEnabled.value = false
            form.available_days = null
            form.available_from = null
            form.available_until = null
        }
    } else {
        form.reset()
        imagePreview.value = null
        scheduleEnabled.value = false
    }
}, { immediate: true })

function toggleSchedule() {
    scheduleEnabled.value = !scheduleEnabled.value
    if (scheduleEnabled.value) {
        form.available_days = []
        form.available_from = null
        form.available_until = null
    } else {
        form.available_days = null
        form.available_from = null
        form.available_until = null
    }
}

function toggleDay(day) {
    if (!form.available_days) { form.available_days = [] }
    const idx = form.available_days.indexOf(day)
    if (idx !== -1) {
        form.available_days.splice(idx, 1)
    } else {
        form.available_days.push(day)
        form.available_days.sort()
    }
}

function isDaySelected(day) {
    return form.available_days?.includes(day) ?? false
}

const isEditing = computed(() => !!props.category)
const title = computed(() => isEditing.value ? 'Editar Categoría' : 'Nueva Categoría')
const subtitle = computed(() => isEditing.value ? 'Modifica los detalles de esta categoría del menú.' : 'Agrega una nueva categoría al menú.')

const IMAGE_MAX_MB = 5
const IMAGE_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp'

function handleImageChange(event) {
    const file = event.target.files[0]
    if (!file) { return }
    form.clearErrors('image')
    sizeWarning.value = null

    if (file.size > IMAGE_MAX_MB * 1024 * 1024) {
        form.setError('image', `La imagen no debe pesar más de ${IMAGE_MAX_MB} MB. Tu archivo pesa ${(file.size / 1024 / 1024).toFixed(1)} MB.`)
        event.target.value = ''
        return
    }

    form.image = file
    imagePreview.value = URL.createObjectURL(file)

    // Non-blocking advisory when the image isn't square. The backend
    // (CategoryImageProcessor) center-crops to 512×512 WebP, so uploading a
    // rectangular image still works, we just tell the user what will happen.
    const probe = new Image()
    probe.onload = () => {
        if (probe.width !== probe.height) {
            sizeWarning.value = `La imagen es ${probe.width}×${probe.height} px. Se recortará al centro para quedar cuadrada.`
        }
    }
    probe.src = imagePreview.value
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
                                <div v-if="!imagePreview" class="flex flex-col items-center">
                                    <span class="material-symbols-outlined text-[#FF5722] text-3xl mb-1" style="font-variation-settings:'FILL' 1">add_photo_alternate</span>
                                    <p class="text-sm font-medium text-gray-700">Sube una imagen</p>
                                </div>
                                <div class="mt-1 space-y-0.5">
                                    <p class="text-xs text-gray-400">Imagen cuadrada (1:1) · Ideal 1024×1024 px</p>
                                    <p class="text-xs text-gray-400">JPG, PNG o WebP · Máximo 5 MB</p>
                                </div>
                                <input ref="imageInput" type="file" :accept="IMAGE_ACCEPT" class="hidden" @change="handleImageChange" />
                            </div>
                            <p v-if="form.errors.image" class="mt-1 text-xs text-red-500">{{ form.errors.image }}</p>
                            <p v-if="sizeWarning && !form.errors.image" class="mt-1 text-xs text-amber-600">{{ sizeWarning }}</p>
                        </div>

                        <!-- Estado -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Estado
                            </label>
                            <div class="flex items-center gap-3 border border-gray-200 rounded-xl px-4 py-2.5">
                                <span class="text-sm text-gray-700">{{ form.is_active ? 'Activa' : 'Inactiva' }}</span>
                                <div class="ml-auto">
                                    <ToggleSwitch v-model="form.is_active" />
                                </div>
                            </div>
                        </div>

                        <!-- Horario de disponibilidad -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">
                                Horario de disponibilidad
                            </label>
                            <div class="flex items-center gap-3 border border-gray-200 rounded-xl px-4 py-2.5">
                                <span class="material-symbols-outlined text-gray-400 text-xl">schedule</span>
                                <span class="text-sm text-gray-700">Restringir por horario</span>
                                <div class="ml-auto">
                                    <ToggleSwitch :model-value="scheduleEnabled" @update:model-value="toggleSchedule" />
                                </div>
                            </div>

                            <Transition name="schedule">
                                <div v-if="scheduleEnabled" class="mt-3 space-y-3">
                                    <!-- Day selector -->
                                    <div>
                                        <p class="text-xs font-medium text-gray-500 mb-2">Días disponibles</p>
                                        <div class="flex gap-1.5">
                                            <button
                                                v-for="(label, index) in DAY_LABELS"
                                                :key="index"
                                                type="button"
                                                @click="toggleDay(index)"
                                                class="w-9 h-9 rounded-xl text-xs font-semibold transition-colors flex items-center justify-center"
                                                :class="isDaySelected(index)
                                                    ? 'bg-[#FF5722] text-white'
                                                    : 'bg-gray-100 text-gray-500 hover:bg-gray-200'"
                                            >
                                                {{ label }}
                                            </button>
                                        </div>
                                        <p v-if="form.errors['available_days'] || form.errors['available_days.0']" class="mt-1 text-xs text-red-500">
                                            {{ form.errors['available_days'] || form.errors['available_days.0'] }}
                                        </p>
                                    </div>

                                    <!-- Time range -->
                                    <div class="flex gap-3">
                                        <div class="flex-1">
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Desde</label>
                                            <TimePicker v-model="form.available_from" placeholder="Inicio" :has-error="!!form.errors.available_from" />
                                            <p v-if="form.errors.available_from" class="mt-1 text-xs text-red-500">{{ form.errors.available_from }}</p>
                                        </div>
                                        <div class="flex-1">
                                            <label class="block text-xs font-medium text-gray-500 mb-1">Hasta</label>
                                            <TimePicker v-model="form.available_until" placeholder="Fin" :has-error="!!form.errors.available_until" />
                                            <p v-if="form.errors.available_until" class="mt-1 text-xs text-red-500">{{ form.errors.available_until }}</p>
                                        </div>
                                    </div>

                                    <p class="text-xs text-gray-400 leading-snug">
                                        Esta categoría solo será visible en el menú del cliente durante los días y horarios seleccionados.
                                    </p>
                                </div>
                            </Transition>
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
.schedule-enter-active, .schedule-leave-active {
    transition: all 0.2s ease;
    overflow: hidden;
}
.schedule-enter-from, .schedule-leave-to {
    opacity: 0;
    max-height: 0;
}
.schedule-enter-to, .schedule-leave-from {
    opacity: 1;
    max-height: 300px;
}
</style>
