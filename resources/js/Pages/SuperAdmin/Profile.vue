<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    user: Object,
})

const form = useForm({
    name: props.user.name ?? '',
    email: props.user.email ?? '',
    current_password: '',
    password: '',
    password_confirmation: '',
})

function submit() {
    form.put(route('super.profile.update'), {
        onSuccess: () => {
            form.current_password = ''
            form.password = ''
            form.password_confirmation = ''
        },
    })
}
</script>

<template>
    <Head title="SuperAdmin — Mi Cuenta" />
    <SuperAdminLayout>
        <h1 class="text-2xl font-bold text-gray-900 mb-6">Mi cuenta</h1>

        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 max-w-lg">
            <form @submit.prevent="submit" class="space-y-5">

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Nombre completo</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                        :class="{ 'border-red-400': form.errors.name }"
                    />
                    <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                </div>

                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Correo electrónico</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                        :class="{ 'border-red-400': form.errors.email }"
                    />
                    <p v-if="form.errors.email" class="text-xs text-red-500 mt-1">{{ form.errors.email }}</p>
                </div>

                <!-- Password section -->
                <div class="border-t border-gray-100 pt-5">
                    <p class="text-sm font-semibold text-gray-700 mb-4">Cambiar contraseña</p>
                    <p class="text-xs text-gray-500 mb-4">Deja los campos en blanco si no deseas cambiar tu contraseña.</p>

                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Contraseña actual</label>
                            <input
                                v-model="form.current_password"
                                type="password"
                                autocomplete="current-password"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                :class="{ 'border-red-400': form.errors.current_password }"
                            />
                            <p v-if="form.errors.current_password" class="text-xs text-red-500 mt-1">{{ form.errors.current_password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Nueva contraseña</label>
                            <input
                                v-model="form.password"
                                type="password"
                                autocomplete="new-password"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                :class="{ 'border-red-400': form.errors.password }"
                            />
                            <p v-if="form.errors.password" class="text-xs text-red-500 mt-1">{{ form.errors.password }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Confirmar nueva contraseña</label>
                            <input
                                v-model="form.password_confirmation"
                                type="password"
                                autocomplete="new-password"
                                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
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
    </SuperAdminLayout>
</template>
