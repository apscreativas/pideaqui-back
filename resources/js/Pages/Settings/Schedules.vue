<script setup>
import { computed, ref } from 'vue'
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import SettingsLayout from '@/Components/SettingsLayout.vue'
import TimePicker from '@/Components/TimePicker.vue'
import DatePicker from '@/Components/DatePicker.vue'
import ToggleSwitch from '@/Components/ToggleSwitch.vue'

const props = defineProps({
    schedules: Array,
    specialDates: { type: Array, default: () => [] },
})

// ─── Regular schedule ────────────────────────────────────────────────────────

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

const allClosed = computed(() => form.schedules.every((s) => s.is_closed))

function submitSchedules() {
    form.put(route('settings.schedules.update'))
}

// ─── Special dates ───────────────────────────────────────────────────────────

const showModal = ref(false)
const editingDate = ref(null)

const sdForm = useForm({
    date: '',
    type: 'closed',
    opens_at: '10:00',
    closes_at: '15:00',
    label: '',
    is_recurring: false,
})

function openCreateModal() {
    editingDate.value = null
    sdForm.reset()
    sdForm.clearErrors()
    sdForm.type = 'closed'
    sdForm.opens_at = '10:00'
    sdForm.closes_at = '15:00'
    showModal.value = true
}

function openEditModal(sd) {
    editingDate.value = sd
    sdForm.clearErrors()
    sdForm.date = sd.date
    sdForm.type = sd.type
    sdForm.opens_at = sd.opens_at || '10:00'
    sdForm.closes_at = sd.closes_at || '15:00'
    sdForm.label = sd.label || ''
    sdForm.is_recurring = sd.is_recurring
    showModal.value = true
}

function submitSpecialDate() {
    if (editingDate.value) {
        sdForm.put(route('special-dates.update', editingDate.value.id), {
            onSuccess: () => { showModal.value = false },
        })
    } else {
        sdForm.post(route('special-dates.store'), {
            onSuccess: () => { showModal.value = false },
        })
    }
}

function deleteSpecialDate(sd) {
    if (confirm(`¿Eliminar "${sd.label || sd.date}"?`)) {
        router.delete(route('special-dates.destroy', sd.id))
    }
}

function formatDate(dateStr) {
    const d = new Date(dateStr + 'T12:00:00')
    return d.toLocaleDateString('es-MX', { day: 'numeric', month: 'long', year: 'numeric' })
}

function isPast(dateStr) {
    return new Date(dateStr) < new Date(new Date().toDateString())
}

const COMMON_HOLIDAYS = [
    { month: 1, day: 1, label: 'Año Nuevo' },
    { month: 2, day: 5, label: 'Día de la Constitución' },
    { month: 3, day: 21, label: 'Natalicio de Benito Juárez' },
    { month: 5, day: 1, label: 'Día del Trabajo' },
    { month: 9, day: 16, label: 'Día de la Independencia' },
    { month: 11, day: 20, label: 'Revolución Mexicana' },
    { month: 12, day: 25, label: 'Navidad' },
]

function addCommonHolidays() {
    const year = new Date().getFullYear()
    const existing = props.specialDates.map((sd) => sd.date)

    const toCreate = COMMON_HOLIDAYS.filter((h) => {
        const dateStr = `${year}-${String(h.month).padStart(2, '0')}-${String(h.day).padStart(2, '0')}`
        return !existing.includes(dateStr)
    })

    if (toCreate.length === 0) {
        alert('Todos los festivos comunes ya están agregados.')
        return
    }

    // Create them one by one via sequential posts using onFinish callback
    function createNext(items, index) {
        if (index >= items.length) { return }
        const h = items[index]
        const dateStr = `${year}-${String(h.month).padStart(2, '0')}-${String(h.day).padStart(2, '0')}`
        router.post(route('special-dates.store'), {
            date: dateStr,
            type: 'closed',
            label: h.label,
            is_recurring: true,
        }, {
            preserveState: true,
            preserveScroll: true,
            onFinish: () => createNext(items, index + 1),
        })
    }
    createNext(toCreate, 0)
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
            <div class="pb-20 space-y-6">

            <!-- ─── HORARIO REGULAR ─────────────────────────────────────────── -->
            <div v-if="allClosed" class="flex items-start gap-3 bg-amber-50 border border-amber-200 rounded-xl px-5 py-4">
                <span class="material-symbols-outlined text-amber-500 text-xl shrink-0 mt-0.5">warning</span>
                <div>
                    <p class="text-sm font-semibold text-amber-800">Tu restaurante aparece como cerrado</p>
                    <p class="text-sm text-amber-700 mt-0.5">No tienes ningún día con horario activo. Tus clientes verán que estás cerrado y no podrán realizar pedidos.</p>
                </div>
            </div>

            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100">
                    <h2 class="text-lg font-bold text-gray-900">Horario regular</h2>
                    <p class="text-sm text-gray-500 mt-1">Define los días y horas que tu restaurante está abierto.</p>
                </div>

                <div class="divide-y divide-gray-100">
                    <div
                        v-for="(schedule, index) in form.schedules"
                        :key="schedule.day_of_week"
                        class="px-6 py-4"
                    >
                        <div class="flex items-center gap-4">
                            <span class="w-28 text-sm font-semibold text-gray-700 shrink-0">
                                {{ DAY_NAMES[schedule.day_of_week] }}
                            </span>
                            <ToggleSwitch
                                :model-value="!schedule.is_closed"
                                @update:model-value="schedule.is_closed = !$event"
                            />
                            <span
                                class="text-xs font-medium w-16 shrink-0"
                                :class="!schedule.is_closed ? 'text-green-600' : 'text-gray-400'"
                            >
                                {{ !schedule.is_closed ? 'Abierto' : 'Cerrado' }}
                            </span>
                            <template v-if="!schedule.is_closed">
                                <div class="w-40">
                                    <TimePicker v-model="schedule.opens_at" placeholder="Apertura" :has-error="!!form.errors[`schedules.${index}.opens_at`]" />
                                </div>
                                <span class="text-gray-400 text-sm">a</span>
                                <div class="w-40">
                                    <TimePicker v-model="schedule.closes_at" placeholder="Cierre" :has-error="!!form.errors[`schedules.${index}.closes_at`]" />
                                </div>
                            </template>
                            <template v-else>
                                <span class="text-sm text-gray-400 italic">Sin horario</span>
                            </template>
                        </div>
                        <div v-if="form.errors[`schedules.${index}.opens_at`] || form.errors[`schedules.${index}.closes_at`]" class="mt-2 ml-28 text-xs text-red-500 space-y-0.5">
                            <p v-if="form.errors[`schedules.${index}.opens_at`]">{{ form.errors[`schedules.${index}.opens_at`] }}</p>
                            <p v-if="form.errors[`schedules.${index}.closes_at`]">{{ form.errors[`schedules.${index}.closes_at`] }}</p>
                        </div>
                    </div>
                </div>

                <div class="px-6 py-4 border-t border-gray-100 flex justify-end">
                    <button
                        @click="submitSchedules"
                        :disabled="form.processing"
                        class="bg-[#FF5722] text-white px-6 py-2.5 rounded-lg text-sm font-semibold hover:bg-[#D84315] disabled:opacity-50 transition-colors"
                    >
                        {{ form.processing ? 'Guardando...' : 'Guardar horarios' }}
                    </button>
                </div>
            </div>

            <!-- ─── FECHAS ESPECIALES ───────────────────────────────────────── -->
            <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden">
                <div class="px-6 py-5 border-b border-gray-100 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-gray-900">Fechas especiales y días festivos</h2>
                        <p class="text-sm text-gray-500 mt-1">Configura días donde el restaurante cierra o tiene un horario diferente al regular.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <button
                            @click="addCommonHolidays"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800 transition-colors px-3 py-1.5 border border-indigo-200 rounded-lg hover:bg-indigo-50"
                        >
                            Festivos comunes
                        </button>
                        <button
                            @click="openCreateModal"
                            class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-lg px-4 py-2 text-sm flex items-center gap-1.5 transition-colors"
                        >
                            <span class="material-symbols-outlined text-base">add</span>
                            Agregar fecha
                        </button>
                    </div>
                </div>

                <!-- Empty state -->
                <div v-if="specialDates.length === 0" class="px-6 py-10 text-center">
                    <span class="material-symbols-outlined text-gray-300 text-4xl mb-2" style="font-variation-settings:'FILL' 1">event_busy</span>
                    <p class="text-sm text-gray-500">No hay fechas especiales configuradas.</p>
                    <p class="text-xs text-gray-400 mt-1">Agrega días festivos o fechas con horario especial.</p>
                </div>

                <!-- List -->
                <div v-else class="divide-y divide-gray-100">
                    <div
                        v-for="sd in specialDates"
                        :key="sd.id"
                        class="px-6 py-4 flex items-center gap-4"
                        :class="{ 'opacity-40': !sd.is_recurring && isPast(sd.date) }"
                    >
                        <!-- Type icon -->
                        <span
                            class="material-symbols-outlined text-xl shrink-0"
                            :class="sd.type === 'closed' ? 'text-red-500' : 'text-amber-500'"
                            style="font-variation-settings:'FILL' 1"
                        >
                            {{ sd.type === 'closed' ? 'event_busy' : 'schedule' }}
                        </span>

                        <!-- Info -->
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="text-sm font-semibold text-gray-900">{{ formatDate(sd.date) }}</span>
                                <span v-if="sd.is_recurring" class="text-xs bg-indigo-50 text-indigo-600 px-2 py-0.5 rounded-full font-medium">Cada año</span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5">
                                <span v-if="sd.type === 'closed'" class="text-xs text-red-600 font-medium">Cerrado todo el día</span>
                                <span v-else class="text-xs text-amber-700 font-medium">Horario especial: {{ sd.opens_at }} – {{ sd.closes_at }}</span>
                                <span v-if="sd.label" class="text-xs text-gray-500">· {{ sd.label }}</span>
                            </div>
                        </div>

                        <!-- Actions -->
                        <button
                            @click="openEditModal(sd)"
                            class="p-2 text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 rounded-lg transition-colors"
                        >
                            <span class="material-symbols-outlined text-lg">edit</span>
                        </button>
                        <button
                            @click="deleteSpecialDate(sd)"
                            class="p-2 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition-colors"
                        >
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    </div>
                </div>
            </div>

            </div>
        </SettingsLayout>

        <!-- ─── MODAL ─────────────────────────────────────────────────────── -->
        <Teleport to="body">
            <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center">
                <div class="absolute inset-0 bg-black/40" @click="showModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg mx-4 p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-lg font-bold text-gray-900">
                            {{ editingDate ? 'Editar fecha especial' : 'Nueva fecha especial' }}
                        </h2>
                        <button @click="showModal = false" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <div class="space-y-4">
                        <!-- Date -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Fecha</label>
                            <DatePicker v-model="sdForm.date" placeholder="Seleccionar fecha" :has-error="!!sdForm.errors.date" />
                            <p v-if="sdForm.errors.date" class="mt-1 text-xs text-red-500">{{ sdForm.errors.date }}</p>
                        </div>

                        <!-- Type -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Tipo</label>
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    @click="sdForm.type = 'closed'"
                                    class="flex-1 flex items-center gap-2 px-4 py-3 rounded-xl border text-sm font-medium transition-all"
                                    :class="sdForm.type === 'closed'
                                        ? 'border-red-400 bg-red-50 text-red-700'
                                        : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                >
                                    <span class="material-symbols-outlined text-lg" style="font-variation-settings:'FILL' 1">event_busy</span>
                                    Cerrado todo el día
                                </button>
                                <button
                                    type="button"
                                    @click="sdForm.type = 'special'"
                                    class="flex-1 flex items-center gap-2 px-4 py-3 rounded-xl border text-sm font-medium transition-all"
                                    :class="sdForm.type === 'special'
                                        ? 'border-amber-400 bg-amber-50 text-amber-700'
                                        : 'border-gray-200 text-gray-600 hover:bg-gray-50'"
                                >
                                    <span class="material-symbols-outlined text-lg" style="font-variation-settings:'FILL' 1">schedule</span>
                                    Horario especial
                                </button>
                            </div>
                            <p v-if="sdForm.errors.type" class="mt-1 text-xs text-red-500">{{ sdForm.errors.type }}</p>
                        </div>

                        <!-- Hours (only for special) -->
                        <div v-if="sdForm.type === 'special'" class="flex items-center gap-3">
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Apertura</label>
                                <TimePicker v-model="sdForm.opens_at" :has-error="!!sdForm.errors.opens_at" />
                                <p v-if="sdForm.errors.opens_at" class="mt-1 text-xs text-red-500">{{ sdForm.errors.opens_at }}</p>
                            </div>
                            <span class="text-gray-400 text-sm mt-4">a</span>
                            <div class="flex-1">
                                <label class="block text-xs font-medium text-gray-500 mb-1">Cierre</label>
                                <TimePicker v-model="sdForm.closes_at" :has-error="!!sdForm.errors.closes_at" />
                                <p v-if="sdForm.errors.closes_at" class="mt-1 text-xs text-red-500">{{ sdForm.errors.closes_at }}</p>
                            </div>
                        </div>

                        <!-- Label -->
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1.5">Etiqueta (opcional)</label>
                            <input
                                v-model="sdForm.label"
                                type="text"
                                placeholder="Ej: Navidad, Inventario, Evento privado"
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722] transition-colors"
                                :class="{ 'border-red-400': sdForm.errors.label }"
                            />
                            <p v-if="sdForm.errors.label" class="mt-1 text-xs text-red-500">{{ sdForm.errors.label }}</p>
                        </div>

                        <!-- Recurring -->
                        <div>
                            <label class="flex items-center gap-2.5 cursor-pointer">
                                <input
                                    type="checkbox"
                                    v-model="sdForm.is_recurring"
                                    class="rounded border-gray-300 text-[#FF5722] focus:ring-[#FF5722]/30"
                                />
                                <span class="text-sm text-gray-700">Se repite cada año</span>
                            </label>
                            <p v-if="sdForm.errors.is_recurring" class="mt-1 text-xs text-red-500">{{ sdForm.errors.is_recurring }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-3 mt-6 pt-4 border-t border-gray-100">
                        <button @click="showModal = false" class="text-sm text-gray-500 hover:text-gray-700 font-medium px-4 py-2">
                            Cancelar
                        </button>
                        <button
                            @click="submitSpecialDate"
                            :disabled="sdForm.processing"
                            class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-6 py-2.5 text-sm disabled:opacity-60"
                        >
                            {{ sdForm.processing ? 'Guardando...' : (editingDate ? 'Actualizar' : 'Agregar') }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
