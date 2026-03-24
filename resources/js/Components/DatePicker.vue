<script setup>
import { ref, computed, watch, onMounted, onUnmounted, nextTick } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    placeholder: { type: String, default: 'Seleccionar fecha' },
    disabled: { type: Boolean, default: false },
    hasError: { type: Boolean, default: false },
    size: { type: String, default: 'md', validator: (v) => ['sm', 'md'].includes(v) },
})

const emit = defineEmits(['update:modelValue', 'change'])

const open = ref(false)
const containerRef = ref(null)

const MONTHS = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre']
const DAYS_SHORT = ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sa', 'Do']

const today = new Date()
const viewYear = ref(today.getFullYear())
const viewMonth = ref(today.getMonth())

watch(() => props.modelValue, (val) => {
    if (val) {
        const d = new Date(val + 'T00:00:00')
        if (!isNaN(d.getTime())) {
            viewYear.value = d.getFullYear()
            viewMonth.value = d.getMonth()
        }
    }
}, { immediate: true })

const displayValue = computed(() => {
    if (!props.modelValue) return null
    const d = new Date(props.modelValue + 'T00:00:00')
    if (isNaN(d.getTime())) return null
    return d.toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
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
        const d = new Date(year, month, -i)
        days.push({ date: d, current: false })
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

function formatIso(date) {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

function isSelected(day) {
    return props.modelValue === formatIso(day.date)
}

function isToday(day) {
    return formatIso(day.date) === formatIso(today)
}

function selectDay(day) {
    const val = formatIso(day.date)
    emit('update:modelValue', val)
    emit('change', val)
    open.value = false
}

function prevMonth() {
    if (viewMonth.value === 0) {
        viewMonth.value = 11
        viewYear.value--
    } else {
        viewMonth.value--
    }
}

function nextMonth() {
    if (viewMonth.value === 11) {
        viewMonth.value = 0
        viewYear.value++
    } else {
        viewMonth.value++
    }
}

function goToToday() {
    viewYear.value = today.getFullYear()
    viewMonth.value = today.getMonth()
    selectDay({ date: today })
}

function toggle() {
    if (props.disabled) return
    open.value = !open.value
}

function clear() {
    emit('update:modelValue', '')
    emit('change', '')
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
            class="w-full flex items-center justify-between gap-2 border transition-colors"
            :class="[
                size === 'sm' ? 'rounded-lg px-2.5 py-1.5 text-xs' : 'rounded-xl px-3 py-2.5 text-sm',
                hasError ? 'border-red-400' : 'border-gray-200 hover:border-gray-300',
                open ? 'ring-2 ring-[#FF5722]/30 border-[#FF5722]' : '',
                disabled ? 'opacity-50 cursor-not-allowed bg-gray-50' : 'bg-white cursor-pointer',
            ]"
        >
            <div class="flex items-center gap-2 min-w-0">
                <span class="material-symbols-outlined text-gray-400" :class="size === 'sm' ? 'text-base' : 'text-lg'">calendar_today</span>
                <span :class="displayValue ? 'text-gray-900 font-medium truncate' : 'text-gray-400 truncate'">
                    {{ displayValue || placeholder }}
                </span>
            </div>
            <span
                class="material-symbols-outlined text-gray-400 transition-transform shrink-0"
                :class="[open ? 'rotate-180' : '', size === 'sm' ? 'text-base' : 'text-lg']"
            >expand_more</span>
        </button>

        <!-- Calendar dropdown -->
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
                class="absolute z-50 mt-1.5 bg-white border border-gray-200 rounded-2xl shadow-xl shadow-gray-200/50 p-4 w-[300px]"
            >
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

                <!-- Day-of-week headers -->
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

                <!-- Footer -->
                <div class="flex items-center justify-between mt-3 pt-3 border-t border-gray-100">
                    <button
                        type="button"
                        @click="goToToday"
                        class="text-xs font-semibold text-[#FF5722] hover:text-[#D84315] transition-colors focus-visible:ring-2 focus-visible:ring-[#FF5722]/30 focus-visible:outline-none rounded px-1"
                    >Hoy</button>
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
