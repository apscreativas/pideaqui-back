<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'

const props = defineProps({
    restaurant: Object,
})

const form = useForm({
    _method: 'put',
    name: props.restaurant.name ?? '',
    logo: null,
    instagram: props.restaurant.instagram ?? '',
    facebook: props.restaurant.facebook ?? '',
    tiktok: props.restaurant.tiktok ?? '',
})

function submit() {
    form.post(route('settings.general.update'), {
        forceFormData: true,
    })
}

function onLogoChange(e) {
    form.logo = e.target.files[0] ?? null
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
                        <div v-if="restaurant.logo_url" class="mb-3">
                            <img
                                :src="restaurant.logo_url"
                                alt="Logo actual"
                                class="h-16 w-16 rounded-xl object-cover border border-gray-100"
                            />
                        </div>
                        <input
                            type="file"
                            accept="image/*"
                            class="block w-full text-sm text-gray-500 file:mr-3 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-sm file:font-semibold file:bg-[#FF5722]/10 file:text-[#FF5722] hover:file:bg-[#FF5722]/20"
                            @change="onLogoChange"
                        />
                        <p v-if="form.errors.logo" class="text-xs text-red-500 mt-1">{{ form.errors.logo }}</p>
                    </div>

                    <!-- Redes sociales -->
                    <div class="border-t border-gray-100 pt-5">
                        <p class="text-sm font-semibold text-gray-700 mb-4">Redes sociales (opcional)</p>

                        <div class="space-y-3">
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-gray-400 w-6">photo_camera</span>
                                <input
                                    v-model="form.instagram"
                                    type="text"
                                    placeholder="instagram.com/turestaurante"
                                    class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-gray-400 w-6">groups</span>
                                <input
                                    v-model="form.facebook"
                                    type="text"
                                    placeholder="facebook.com/turestaurante"
                                    class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                            </div>
                            <div class="flex items-center gap-3">
                                <span class="material-symbols-outlined text-gray-400 w-6">music_note</span>
                                <input
                                    v-model="form.tiktok"
                                    type="text"
                                    placeholder="tiktok.com/@turestaurante"
                                    class="flex-1 border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                            </div>
                        </div>
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
