<script setup>
import { ref } from 'vue'
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'

const props = defineProps({
    restaurant: Object,
})

const IMAGE_MAX_MB = 2
const IMAGE_ACCEPT = '.jpg,.jpeg,.png,.gif,.webp'
const logoPreview = ref(null)

const form = useForm({
    _method: 'put',
    name: props.restaurant.name ?? '',
    logo: null,
    notify_new_orders: props.restaurant.notify_new_orders ?? true,
})

function submit() {
    form.post(route('settings.general.update'), {
        forceFormData: true,
    })
}

function onLogoChange(e) {
    const file = e.target.files[0]
    if (!file) { return }
    form.clearErrors('logo')

    if (file.size > IMAGE_MAX_MB * 1024 * 1024) {
        form.setError('logo', `El logo no debe pesar más de ${IMAGE_MAX_MB} MB. Tu archivo pesa ${(file.size / 1024 / 1024).toFixed(1)} MB.`)
        e.target.value = ''
        return
    }

    form.logo = file
    logoPreview.value = URL.createObjectURL(file)
}
</script>

<template>
    <Head title="Configuración General" />
    <AppLayout title="Configuración">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
            <p class="mt-1 text-sm text-gray-500">Gestiona la información y preferencias de tu restaurante.</p>
        </div>

        <SettingsLayout>
            <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                <h2 class="text-lg font-bold text-gray-900 mb-6">Información general</h2>

                <form @submit.prevent="submit" class="space-y-5 max-w-lg">

                    <!-- Nombre -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Nombre del restaurante</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                        />
                        <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                    </div>

                    <!-- Logo -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Logo del restaurante</label>
                        <div class="mb-3">
                            <img
                                v-if="logoPreview || restaurant.logo_url"
                                :src="logoPreview ?? restaurant.logo_url"
                                alt="Logo actual"
                                class="h-16 w-16 rounded-xl object-cover border border-gray-100"
                            />
                        </div>
                        <input
                            type="file"
                            :accept="IMAGE_ACCEPT"
                            class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#FF5722]/10 file:text-[#FF5722] hover:file:bg-[#FF5722]/20"
                            @change="onLogoChange"
                        />
                        <div class="mt-1.5 space-y-0.5">
                            <p class="text-xs text-gray-400">Ideal 1024×1024 px · Proporción 1:1 recomendada</p>
                            <p class="text-xs text-gray-400">JPG, PNG o WebP · Máximo 2 MB</p>
                        </div>
                        <p v-if="form.errors.logo" class="text-xs text-red-500 mt-1">{{ form.errors.logo }}</p>
                    </div>

                    <!-- Notificaciones -->
                    <div class="border-t border-gray-100 pt-5">
                        <p class="text-sm font-semibold text-gray-700 mb-4">Notificaciones</p>

                        <label class="flex items-center gap-3 cursor-pointer">
                            <ToggleSwitch v-model="form.notify_new_orders" />
                            <span class="text-sm text-gray-700">Recibir correo cuando entre un nuevo pedido</span>
                        </label>
                    </div>

                    <div class="flex justify-end pt-2">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition-colors disabled:opacity-60"
                        >
                            {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                        </button>
                    </div>
                </form>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
