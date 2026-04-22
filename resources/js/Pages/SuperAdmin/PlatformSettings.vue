<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    settings: Object,
    defaults: Object,
})

const form = useForm({
    public_menu_base_url: props.settings.public_menu_base_url || '',
})

function submit() {
    form.put(route('super.platform-settings.update'))
}
</script>

<template>
    <Head title="SuperAdmin — Plataforma" />
    <SuperAdminLayout>
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración de plataforma</h1>
            <p class="mt-1 text-sm text-gray-500">Parámetros globales que afectan a toda la plataforma, no a un restaurante en particular.</p>
        </div>

        <div class="max-w-2xl">
            <form @submit.prevent="submit" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-5">
                <div>
                    <label class="block text-sm font-semibold text-gray-900 mb-1">URL base del SPA del menú</label>
                    <p class="text-xs text-gray-500 mb-2">
                        Dominio donde corre la SPA pública del menú. El slug de cada restaurante se adjunta como
                        <code class="px-1 py-0.5 rounded bg-gray-100">{URL}/r/{slug}</code>.
                        Si la dejas vacía, se usa <code class="px-1 py-0.5 rounded bg-gray-100">{{ defaults.public_menu_base_url }}</code>.
                    </p>
                    <input
                        v-model="form.public_menu_base_url"
                        type="url"
                        placeholder="https://menu.pideaqui.mx"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                    />
                    <p v-if="form.errors.public_menu_base_url" class="text-xs text-red-500 mt-1">{{ form.errors.public_menu_base_url }}</p>
                </div>

                <div class="flex items-start gap-2 bg-amber-50 border border-amber-200 text-amber-800 rounded-xl px-3 py-2 text-xs">
                    <span class="material-symbols-outlined text-sm mt-0.5">warning</span>
                    <span>Cambiar esta URL reconstruye los enlaces y QR de todos los restaurantes. Los QR impresos con la URL anterior dejarán de funcionar.</span>
                </div>

                <div class="flex justify-end">
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-6 py-2.5 text-sm transition-colors disabled:opacity-60"
                    >
                        {{ form.processing ? 'Guardando…' : 'Guardar' }}
                    </button>
                </div>
            </form>
        </div>
    </SuperAdminLayout>
</template>
