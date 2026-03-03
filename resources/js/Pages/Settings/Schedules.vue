<script setup>
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'

const props = defineProps({
    schedules: Array,
})

const DAY_ORDER = [1, 2, 3, 4, 5, 6, 0]
const DAY_NAMES = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']

const sorted = DAY_ORDER.map((d) => props.schedules.find((s) => s.day_of_week === d) ?? {
    day_of_week: d, opens_at: '09:00', closes_at: '21:00', is_closed: true,
})

const form = useForm({
    schedules: sorted.map((s) => ({
        day_of_week: s.day_of_week,
        opens_at: s.opens_at ?? '09:00',
        closes_at: s.closes_at ?? '21:00',
        is_closed: s.is_closed ?? true,
    })),
})

function submit() {
    form.put(route('settings.schedules.update'))
}
</script>

<template>
    <Head title="Horarios" />
    <AppLayout title="Configuración">
        <div class="mb-6">
            <h1 class="text-2xl font-bold text-gray-900">Configuración</h1>
            <p class="mt-1 text-sm text-gray-500">Gestiona la información y preferencias de tu restaurante.</p>
        </div>

        <SettingsLayout>
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900">Horarios de atención</h2>
                    <p class="text-sm text-gray-500 mt-1">Define los días y horas que tu restaurante está abierto. Fuera de estos horarios, tus clientes verán que estás cerrado.</p>
                </div>

                <!-- Schedule rows -->
                <div class="divide-y divide-gray-100">
                    <div
                        v-for="(schedule, index) in form.schedules"
                        :key="schedule.day_of_week"
                        class="px-6 py-4 flex items-center gap-4"
                    >
                        <!-- Day name -->
                        <span class="w-28 text-sm font-semibold text-gray-700 shrink-0">
                            {{ DAY_NAMES[schedule.day_of_week] }}
                        </span>

                        <!-- Toggle -->
                        <button
                            type="button"
                            @click="schedule.is_closed = !schedule.is_closed"
                            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors focus:outline-none"
                            :class="!schedule.is_closed ? 'bg-[#FF5722]' : 'bg-gray-200'"
                        >
                            <span
                                class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow ring-0 transition-transform"
                                :class="!schedule.is_closed ? 'translate-x-5' : 'translate-x-0'"
                            />
                        </button>

                        <span
                            class="text-xs font-medium w-16 shrink-0"
                            :class="!schedule.is_closed ? 'text-green-600' : 'text-gray-400'"
                        >
                            {{ !schedule.is_closed ? 'Abierto' : 'Cerrado' }}
                        </span>

                        <!-- Time inputs -->
                        <template v-if="!schedule.is_closed">
                            <input
                                v-model="schedule.opens_at"
                                type="time"
                                class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50 w-32"
                            />
                            <span class="text-gray-400 text-sm">a</span>
                            <input
                                v-model="schedule.closes_at"
                                type="time"
                                class="bg-gray-50 border border-gray-200 rounded-lg px-3 py-2 text-sm text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50 w-32"
                            />
                        </template>
                        <template v-else>
                            <span class="text-sm text-gray-400 italic">Sin horario</span>
                        </template>
                    </div>
                </div>

                <!-- Errors -->
                <div v-if="form.hasErrors" class="px-6 py-3 bg-red-50 border-t border-red-100">
                    <p class="text-sm text-red-600">Revisa los horarios ingresados. El formato debe ser HH:MM.</p>
                </div>
            </div>

            <!-- Action bar -->
            <div class="fixed bottom-0 left-[260px] right-0 bg-white border-t border-gray-200 px-8 py-4 flex justify-end z-20">
                <button
                    @click="submit"
                    :disabled="form.processing"
                    class="bg-[#FF5722] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-[#D84315] disabled:opacity-50 transition-colors"
                >
                    {{ form.processing ? 'Guardando...' : 'Guardar horarios' }}
                </button>
            </div>
        </SettingsLayout>
    </AppLayout>
</template>
