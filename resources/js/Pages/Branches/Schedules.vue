<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    branch: Object,
})

const dayNames = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado']

// Sort schedules by day_of_week, starting Monday (1)
const sortedSchedules = computed(() => {
    const order = [1, 2, 3, 4, 5, 6, 0] // Mon-Sun
    return [...props.branch.schedules].sort((a, b) => order.indexOf(a.day_of_week) - order.indexOf(b.day_of_week))
})

const form = useForm({
    schedules: sortedSchedules.value.map(s => ({
        day_of_week: s.day_of_week,
        opens_at: s.opens_at ?? '09:00',
        closes_at: s.closes_at ?? '21:00',
        is_closed: s.is_closed ?? false,
    })),
})

function submit() {
    form.put(route('branches.schedules.update', props.branch.id))
}
</script>

<template>
    <Head :title="`Horarios — ${branch.name}`" />
    <AppLayout :title="`Horarios — ${branch.name}`">

        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Horarios — {{ branch.name }}</h1>
                <p class="mt-1 text-sm text-gray-500">Configura los dias y horas de operacion para recibir pedidos.</p>
            </div>
        </div>

        <div class="max-w-2xl pb-24">
            <div class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
                <table class="w-full">
                    <thead>
                        <tr class="border-b border-gray-100">
                            <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide w-32">Día</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide w-28">Estado</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Apertura</th>
                            <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Cierre</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="(schedule, index) in form.schedules"
                            :key="schedule.day_of_week"
                            class="border-b border-gray-50 last:border-0"
                            :class="schedule.is_closed ? 'opacity-60' : ''"
                        >
                            <td class="px-6 py-4">
                                <span class="text-sm font-medium text-gray-900">{{ dayNames[schedule.day_of_week] }}</span>
                            </td>
                            <td class="px-4 py-4">
                                <button
                                    type="button"
                                    @click="schedule.is_closed = !schedule.is_closed"
                                    class="w-10 h-6 rounded-full transition-colors relative"
                                    :class="!schedule.is_closed ? 'bg-[#FF5722]' : 'bg-gray-200'"
                                >
                                    <div class="absolute top-1 w-4 h-4 rounded-full bg-white shadow transition-all"
                                        :class="!schedule.is_closed ? 'left-5' : 'left-1'" />
                                </button>
                            </td>
                            <td class="px-4 py-4">
                                <input
                                    v-model="schedule.opens_at"
                                    type="time"
                                    :disabled="schedule.is_closed"
                                    class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors disabled:bg-gray-50 disabled:text-gray-300"
                                />
                            </td>
                            <td class="px-4 py-4">
                                <input
                                    v-model="schedule.closes_at"
                                    type="time"
                                    :disabled="schedule.is_closed"
                                    class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors disabled:bg-gray-50 disabled:text-gray-300"
                                />
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Info note -->
            <div class="flex items-start gap-2 mt-4 bg-blue-50 rounded-xl px-4 py-3">
                <span class="material-symbols-outlined text-blue-500 text-base mt-0.5" style="font-variation-settings:'FILL' 1">info</span>
                <p class="text-xs text-blue-700">Los cambios en los horarios pueden tardar hasta 10 minutos en reflejarse en la aplicación de pedidos.</p>
            </div>

        </div>

        <!-- Bottom action bar -->
        <div class="fixed bottom-0 left-[260px] right-0 bg-white border-t border-gray-100 px-6 py-4 flex items-center justify-between z-10">
            <Link :href="route('branches.index')" class="text-sm text-gray-500 hover:text-gray-700 font-medium">Cancelar</Link>
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
