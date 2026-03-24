<script setup>
import { ref, computed, watch, onMounted, onUnmounted } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Seleccionar fecha y hora' },
    disabled: { type: Boolean, default: false },
    hasError: { type: Boolean, default: false },
})

const emit = defineEmits(['update:modelValue'])

const open = ref(false)
const containerRef = ref(null)
const activeTab = ref('date')

const MONTHS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const DAYS_SHORT = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do']

const today = new Date()
const viewYear = ref(today.getFullYear())
const viewMonth = ref(today.getMonth())
const selectedDate = ref('')
const selectedTime = ref('12:00')

watch(() => props.modelValue, (val) => {
    if (val) {
        const [datePart, timePart] = val.split('T')
        if (datePart) {
            selectedDate.value = datePart
            const d = new Date(datePart + 'T00:00:00')
            if (!isNaN(d.getTime())) {
                viewYear.value = d.getFullYear()
                viewMonth.value = d.getMonth()
            }
        }
        if (timePart) {
            selectedTime.value = timePart.slice(0, 5)
        }
    } else {
        selectedDate.value = ''
        selectedTime.value = '12:00'
    }
}, { immediate: true })

const displayValue = computed(() => {
    if (!props.modelValue) return null
    const [datePart, timePart] = props.modelValue.split('T')
    if (!datePart) return null
    const d = new Date(datePart + 'T00:00:00')
    if (isNaN(d.getTime())) return null
    const dateStr = d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
    if (!timePart) return dateStr
    const [h, m] = timePart.split(':')
    const hour = parseInt(h, 10)
    const suffix = hour >= 12 ? 'p.m.' : 'a.m.'
    const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour
    return `${dateStr}, ${displayHour}:${m} ${suffix}`
})

const calendarDays = computed(() => {
    const year = viewYear.value
    const month = viewMonth.value
    const firstDay = new Date(year, month, 1)
    const lastDay = new Date(year, month + 1, 0)
    let startDow = firstDay.getDay()
    startDow = startDow === 0 ? 6 : startDow - 1
    const days = []
    for (let i = startDow - 1; i >= 0; i--) {
        days.push({ date: new Date(year, month, -i), current: false })
    }
    for (let i = 1; i <= lastDay.getDate(); i++) {
        days.push({ date: new Date(year, month, i), current: true })
    }
    const remaining = 42 - days.length
    for (let i = 1; i <= remaining; i++) {
        days.push({ date: new Date(year, month + 1, i), current: false })
    }
    return days
})

const timeSlots = computed(() => {
    const slots = []
    for (let h = 0; h < 24; h++) {
        for (let m = 0; m < 60; m += 30) {
            slots.push(`${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}`)
        }
    }
    return slots
})

function formatIso(date) {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

function isSelected(day) {
    return selectedDate.value === formatIso(day.date)
}

function isToday(day) {
    return formatIso(day.date) === formatIso(today)
}

function selectDay(day) {
    selectedDate.value = formatIso(day.date)
    viewYear.value = day.date.getFullYear()
    viewMonth.value = day.date.getMonth()
    emitValue()
    activeTab.value = 'time'
}

function selectTime(time) {
    selectedTime.value = time
    emitValue()
    if (selectedDate.value) {
        open.value = false
    }
}

function emitValue() {
    if (selectedDate.value && selectedTime.value) {
        emit('update:modelValue', `${selectedDate.value}T${selectedTime.value}`)
    } else if (selectedDate.value) {
        emit('update:modelValue', `${selectedDate.value}T${selectedTime.value}`)
    }
}

function formatTimeLabel(time) {
    const [h, m] = time.split(':')
    const hour = parseInt(h, 10)
    const suffix = hour >= 12 ? 'p.m.' : 'a.m.'
    const displayHour = hour === 0 ? 12 : hour > 12 ? hour - 12 : hour
    return `${displayHour}:${m} ${suffix}`
}

function prevMonth() {
    if (viewMonth.value === 0) { viewMonth.value = 11; viewYear.value-- }
    else { viewMonth.value-- }
}

function nextMonth() {
    if (viewMonth.value === 11) { viewMonth.value = 0; viewYear.value++ }
    else { viewMonth.value++ }
}

function toggle() {
    if (props.disabled) return
    open.value = !open.value
    if (open.value) activeTab.value = 'date'
}

function clear() {
    selectedDate.value = ''
    selectedTime.value = '12:00'
    emit('update:modelValue', '')
    open.value = false
}

function onClickOutside(e) {
    if (containerRef.value && !containerRef.value.contains(e.target)) {
        open.value = false
    }
}

onMounted(() => document.addEventListener('mousedown', onClickOutside))
onUnmounted(() => document.removeEventListener('mousedown', onClickOutside))
</script>

<template>
    <div ref="containerRef" class="relative">
        <!-- Trigger -->
        <button
            type="button"
            @click="toggle"
            :disabled="disabled"
            :aria-label="placeholder"
            class="w-full flex items-center justify-between gap-2 rounded-xl border px-3 py-2.5 text-sm transition-colors"
            :class="[
                hasError ? 'border-red-400' : 'border-gray-200 hover:border-gray-300',
                open ? 'ring-2 ring-[#FF5722]/30 border-[#FF5722]' : '',
                disabled ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'bg-white cursor-pointer',
            ]"
        >
            <div class="flex items-center gap-2 min-w-0">
                <span class="material-symbols-outlined text-gray-400 text-lg">calendar_today</span>
                <span :class="displayValue ? 'text-gray-900 font-medium truncate' : 'text-gray-400 truncate'">
                    {{ displayValue || placeholder }}
                </span>
            </div>
            <span
                class="material-symbols-outlined text-gray-400 text-lg transition-transform shrink-0"
                :class="{ 'rotate-180': open }"
            >expand_more</span>
        </button>

        <!-- Dropdown -->
        <Transition
            enter-active-class="transition duration-150 ease-out"
            enter-from-class="opacity-0 scale-95 -translate-y-1"
            enter-to-class="opacity-100 scale-100 translate-y-0"
            leave-active-class="transition duration-100 ease-in"
            leave-from-class="opacity-100 scale-100 translate-y-0"
            leave-to-class="opacity-0 scale-95 -translate-y-1"
        >
            <div
                v-if="open"
                class="absolute z-50 mt-1.5 bg-white border border-gray-200 rounded-2xl shadow-xl shadow-gray-200/50 w-[320px] overflow-hidden"
            >
                <!-- Tabs -->
                <div class="flex border-b border-gray-100">
                    <button
                        type="button"
                        @click="activeTab = 'date'"
                        class="flex-1 flex items-center justify-center gap-1.5 py-2.5 text-xs font-semibold transition-colors"
                        :class="activeTab === 'date' ? 'text-[#FF5722] border-b-2 border-[#FF5722]' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span class="material-symbols-outlined text-base">calendar_today</span>
                        Fecha
                    </button>
                    <button
                        type="button"
                        @click="activeTab = 'time'"
                        class="flex-1 flex items-center justify-center gap-1.5 py-2.5 text-xs font-semibold transition-colors"
                        :class="activeTab === 'time' ? 'text-[#FF5722] border-b-2 border-[#FF5722]' : 'text-gray-400 hover:text-gray-600'"
                    >
                        <span class="material-symbols-outlined text-base">schedule</span>
                        Hora
                    </button>
                </div>

                <!-- Date panel -->
                <div v-show="activeTab === 'date'" class="p-4">
                    <!-- Month/Year header -->
                    <div class="flex items-center justify-between mb-3">
                        <button
                            type="button"
                            @click="prevMonth"
                            aria-label="Mes anterior"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors focus-visible:ring-2 focus-visible:ring-[#FF5722]/30 focus-visible:outline-none"
                        >
                            <span class="material-symbols-outlined text-gray-500 text-lg">chevron_left</span>
                        </button>
                        <span class="text-sm font-semibold text-gray-900 select-none">
                            {{ MONTHS[viewMonth] }} {{ viewYear }}
                        </span>
                        <button
                            type="button"
                            @click="nextMonth"
                            aria-label="Mes siguiente"
                            class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors focus-visible:ring-2 focus-visible:ring-[#FF5722]/30 focus-visible:outline-none"
                        >
                            <span class="material-symbols-outlined text-gray-500 text-lg">chevron_right</span>
                        </button>
                    </div>

                    <!-- Day headers -->
                    <div class="grid grid-cols-7 mb-1">
                        <div
                            v-for="day in DAYS_SHORT"
                            :key="day"
                            class="h-8 flex items-center justify-center text-xs font-semibold text-gray-400 select-none"
                        >{{ day }}</div>
                    </div>

                    <!-- Calendar grid -->
                    <div class="grid grid-cols-7">
                        <button
                            v-for="(day, i) in calendarDays"
                            :key="i"
                            type="button"
                            @click="selectDay(day)"
                            class="h-9 w-full flex items-center justify-center text-sm rounded-lg transition-colors focus-visible:ring-2 focus-visible:ring-[#FF5722]/30 focus-visible:outline-none"
                            :class="[
                                isSelected(day)
                                    ? 'bg-[#FF5722] text-white font-bold shadow-sm shadow-[#FF5722]/30'
                                    : isToday(day)
                                        ? 'font-bold text-[#FF5722] bg-orange-50'
                                        : day.current
                                            ? 'text-gray-800 hover:bg-gray-100'
                                            : 'text-gray-300 hover:bg-gray-50',
                            ]"
                        >
                            {{ day.date.getDate() }}
                        </button>
                    </div>
                </div>

                <!-- Time panel -->
                <div v-show="activeTab === 'time'" class="max-h-64 overflow-y-auto py-1">
                    <button
                        v-for="time in timeSlots"
                        :key="time"
                        type="button"
                        @click="selectTime(time)"
                        class="w-full px-5 py-2 text-sm text-left transition-colors focus-visible:ring-2 focus-visible:ring-[#FF5722]/30 focus-visible:outline-none"
                        :class="time === selectedTime
                            ? 'bg-[#FF5722] text-white font-semibold'
                            : 'text-gray-700 hover:bg-orange-50 hover:text-[#FF5722]'"
                    >
                        {{ formatTimeLabel(time) }}
                    </button>
                </div>

                <!-- Footer -->
                <div class="flex items-center justify-between px-4 py-3 border-t border-gray-100 bg-gray-50/50">
                    <span v-if="selectedDate" class="text-xs text-gray-500">
                        {{ new Date(selectedDate + 'T00:00:00').toLocaleDateString('es-MX', { day: '2-digit', month: 'short' }) }}, {{ formatTimeLabel(selectedTime) }}
                    </span>
                    <span v-else class="text-xs text-gray-400">Sin seleccionar</span>
                    <button
                        v-if="modelValue"
                        type="button"
                        @click="clear"
                        class="text-xs font-medium text-gray-400 hover:text-gray-600 transition-colors focus-visible:ring-2 focus-visible:ring-[#FF5722]/30 focus-visible:outline-none rounded px-1"
                    >Limpiar</button>
                </div>
            </div>
        </Transition>
    </div>
</template>
