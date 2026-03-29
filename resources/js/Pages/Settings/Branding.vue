<script setup>
import { ref, computed, shallowRef } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'
import PhoneMockup from '@/Components/PhoneMockup.vue'

const DEFAULT_PRIMARY = '#f6f8f7'
const DEFAULT_SECONDARY = '#FF5722'
const IMAGE_MAX_MB = 2
const IMAGE_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp'

const TEXT_OPTIONS = [
    { value: null, label: 'Automatico' },
    { value: 'light', label: 'Claro' },
    { value: 'dark', label: 'Oscuro' },
]

const props = defineProps({
    restaurant: Object,
})

const imagePreview = ref(null)

const form = useForm({
    _method: 'put',
    primary_color: props.restaurant.primary_color ?? '',
    secondary_color: props.restaurant.secondary_color ?? '',
    default_product_image: null,
    remove_default_image: false,
    text_color: props.restaurant.text_color ?? null,
})

const livePrimary = computed(() =>
    form.primary_color && /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(form.primary_color)
        ? form.primary_color
        : DEFAULT_PRIMARY
)

const liveSecondary = computed(() =>
    form.secondary_color && /^#([0-9A-Fa-f]{3}|[0-9A-Fa-f]{6})$/.test(form.secondary_color)
        ? form.secondary_color
        : DEFAULT_SECONDARY
)

const liveImage = computed(() =>
    imagePreview.value ?? (form.remove_default_image ? null : props.restaurant.default_product_image_url)
)

function perceivedBrightness(hex) {
    const c = hex.replace('#', '')
    const full = c.length === 3 ? c[0]+c[0]+c[1]+c[1]+c[2]+c[2] : c
    const r = parseInt(full.substring(0, 2), 16)
    const g = parseInt(full.substring(2, 4), 16)
    const b = parseInt(full.substring(4, 6), 16)
    return (r * 0.299) + (g * 0.587) + (b * 0.114)
}

const resolvedTextColor = computed(() => {
    if (form.text_color) { return form.text_color }
    return perceivedBrightness(livePrimary.value) > 128 ? 'dark' : 'light'
})

function onImageChange(e) {
    const file = e.target.files[0]
    if (!file) { return }
    form.clearErrors('default_product_image')

    if (file.size > IMAGE_MAX_MB * 1024 * 1024) {
        form.setError('default_product_image', `La imagen no debe pesar mas de ${IMAGE_MAX_MB} MB. Tu archivo pesa ${(file.size / 1024 / 1024).toFixed(1)} MB.`)
        e.target.value = ''
        return
    }

    form.default_product_image = file
    form.remove_default_image = false
    imagePreview.value = URL.createObjectURL(file)
}

function removeImage() {
    form.default_product_image = null
    form.remove_default_image = true
    imagePreview.value = null
}

function resetColors() {
    form.primary_color = ''
    form.secondary_color = ''
    form.text_color = null
}

function submit() {
    form.post(route('settings.branding.update'), {
        forceFormData: true,
    })
}
</script>

<template>
    <Head title="Personalización" />
    <AppLayout title="Configuración">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
            <p class="mt-1 text-sm text-gray-500">Personaliza la apariencia de tu menu digital.</p>
        </div>

        <SettingsLayout>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-6">Personalización del menu</h2>

                <div class="flex flex-col lg:flex-row gap-8">
                    <!-- Left: Controls -->
                    <form @submit.prevent="submit" class="flex-1 space-y-6 max-w-lg">

                        <!-- Primary color -->
                        <fieldset>
                            <legend class="block text-sm font-medium text-gray-700 mb-2">Color de fondo</legend>
                            <p class="text-xs text-gray-400 mb-3">Color de fondo de la pagina, tarjetas y encabezado del menu.</p>
                            <div class="flex items-center gap-3">
                                <label class="sr-only" for="primary-picker">Selector de color de fondo</label>
                                <input
                                    id="primary-picker"
                                    type="color"
                                    :value="livePrimary"
                                    class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5"
                                    @input="form.primary_color = $event.target.value"
                                />
                                <label class="sr-only" for="primary-hex">Valor hexadecimal del color de fondo</label>
                                <input
                                    id="primary-hex"
                                    v-model="form.primary_color"
                                    type="text"
                                    placeholder="#f6f8f7"
                                    maxlength="7"
                                    class="w-28 border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <span
                                    class="w-6 h-6 rounded-md border border-gray-200"
                                    :style="{ backgroundColor: livePrimary }"
                                ></span>
                            </div>
                            <p v-if="form.errors.primary_color" class="text-xs text-red-500 mt-1">{{ form.errors.primary_color }}</p>
                        </fieldset>

                        <!-- Secondary color -->
                        <fieldset>
                            <legend class="block text-sm font-medium text-gray-700 mb-2">Color de acento</legend>
                            <p class="text-xs text-gray-400 mb-3">Color de botones, precios, categorias activas y elementos interactivos.</p>
                            <div class="flex items-center gap-3">
                                <label class="sr-only" for="secondary-picker">Selector de color de acento</label>
                                <input
                                    id="secondary-picker"
                                    type="color"
                                    :value="liveSecondary"
                                    class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5"
                                    @input="form.secondary_color = $event.target.value"
                                />
                                <label class="sr-only" for="secondary-hex">Valor hexadecimal del color de acento</label>
                                <input
                                    id="secondary-hex"
                                    v-model="form.secondary_color"
                                    type="text"
                                    placeholder="#FF5722"
                                    maxlength="7"
                                    class="w-28 border border-gray-200 rounded-xl px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <span
                                    class="w-6 h-6 rounded-md border border-gray-200"
                                    :style="{ backgroundColor: liveSecondary }"
                                ></span>
                            </div>
                            <p v-if="form.errors.secondary_color" class="text-xs text-red-500 mt-1">{{ form.errors.secondary_color }}</p>
                        </fieldset>

                        <!-- Text color -->
                        <fieldset>
                            <legend class="block text-sm font-medium text-gray-700 mb-2">Color de texto</legend>
                            <p class="text-xs text-gray-400 mb-3">Color del texto sobre el fondo del menu.</p>
                            <div class="inline-flex rounded-xl border border-gray-200 overflow-hidden" role="radiogroup" aria-label="Color de texto">
                                <button
                                    v-for="opt in TEXT_OPTIONS"
                                    :key="opt.label"
                                    type="button"
                                    role="radio"
                                    :aria-checked="form.text_color === opt.value"
                                    class="px-4 py-2 text-sm font-medium transition-colors border-r border-gray-200 last:border-r-0"
                                    :class="form.text_color === opt.value
                                        ? 'bg-[#FF5722] text-white'
                                        : 'bg-white text-gray-600 hover:bg-gray-50'"
                                    @click="form.text_color = opt.value"
                                >
                                    {{ opt.label }}
                                </button>
                            </div>
                            <p v-if="form.text_color === null" class="text-xs text-gray-400 mt-2">
                                Se ajustara automaticamente segun el color de fondo.
                            </p>
                            <p v-if="form.errors.text_color" class="text-xs text-red-500 mt-1">{{ form.errors.text_color }}</p>
                        </fieldset>

                        <!-- Reset colors -->
                        <button
                            type="button"
                            class="text-sm text-gray-500 hover:text-gray-700 underline"
                            @click="resetColors"
                        >
                            Restaurar colores por defecto
                        </button>

                        <!-- Default product image -->
                        <fieldset class="border-t border-gray-100 pt-5">
                            <legend class="block text-sm font-medium text-gray-700 mb-2">Imagen por defecto de productos</legend>
                            <p class="text-xs text-gray-400 mb-3">Se mostrara cuando un producto no tenga imagen propia.</p>

                            <div v-if="liveImage" class="mb-3 flex items-center gap-3">
                                <img
                                    :src="liveImage"
                                    alt="Imagen por defecto actual"
                                    class="h-16 w-16 rounded-xl object-cover border border-gray-100"
                                />
                                <button
                                    type="button"
                                    class="text-sm text-red-500 hover:text-red-700 font-medium"
                                    @click="removeImage"
                                >
                                    Eliminar
                                </button>
                            </div>

                            <input
                                type="file"
                                :accept="IMAGE_ACCEPT"
                                class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#FF5722]/10 file:text-[#FF5722] hover:file:bg-[#FF5722]/20"
                                @change="onImageChange"
                            />
                            <p class="text-xs text-gray-400 mt-1.5">JPG, PNG, GIF o WebP · Maximo 2 MB</p>
                            <p v-if="form.errors.default_product_image" class="text-xs text-red-500 mt-1">{{ form.errors.default_product_image }}</p>
                        </fieldset>

                        <!-- Submit -->
                        <div class="flex justify-end pt-2">
                            <button
                                type="submit"
                                :disabled="form.processing"
                                class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition-colors disabled:opacity-60"
                            >
                                {{ form.processing ? 'Guardando\u2026' : 'Guardar cambios' }}
                            </button>
                        </div>
                    </form>

                    <!-- Right: Live preview -->
                    <div class="lg:flex-shrink-0">
                        <p class="text-sm font-medium text-gray-700 mb-3">Vista previa en tiempo real</p>
                        <PhoneMockup
                            :primary-color="livePrimary"
                            :secondary-color="liveSecondary"
                            :text-color="resolvedTextColor"
                            :default-product-image-url="liveImage"
                        />
                    </div>
                </div>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
