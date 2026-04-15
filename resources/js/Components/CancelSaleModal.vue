<script setup>
import { useForm } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    title: { type: String, default: 'Cancelar' },
    subtitle: { type: String, default: '' },           // e.g. "Pedido #0042" or "Ticket POS-0007"
    method: { type: String, default: 'put' },          // 'put' | 'delete'
    url: { type: String, required: true },             // e.g. route('orders.cancel', id)
    submitLabel: { type: String, default: 'Confirmar cancelación' },
    submittingLabel: { type: String, default: 'Cancelando…' },
})

const emit = defineEmits(['close', 'cancelled'])

// Same reasons used in Orders/Show. Single source of truth.
const REASONS = [
    'El cliente ya no lo necesita',
    'Tiempo de espera demasiado largo',
    'Error en la selección de productos / Pedido duplicado',
    'Falta de disponibilidad (Sin stock)',
    'Otro',
]

const selectedReason = ref('')
const customReason = ref('')

const form = useForm({ cancellation_reason: '' })

watch(() => props.show, (v) => {
    if (v) {
        selectedReason.value = ''
        customReason.value = ''
        form.clearErrors()
    }
})

const canSubmit = computed(() => {
    if (!selectedReason.value) { return false }
    if (selectedReason.value === 'Otro') { return customReason.value.trim().length > 0 }
    return true
})

function submit() {
    form.cancellation_reason = selectedReason.value === 'Otro' ? customReason.value : selectedReason.value
    form[props.method](props.url, {
        preserveScroll: true,
        onSuccess: () => emit('cancelled'),
    })
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="emit('close')"></div>

            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto overscroll-contain">
                <div class="p-6">
                    <!-- Header -->
                    <div class="flex items-start justify-between mb-1">
                        <div class="flex items-center gap-3">
                            <div class="flex size-10 items-center justify-center rounded-full bg-red-100 text-red-600">
                                <span class="material-symbols-outlined" aria-hidden="true">warning</span>
                            </div>
                            <h2 class="text-xl font-bold text-gray-900">{{ title }}</h2>
                        </div>
                        <button
                            @click="emit('close')"
                            class="text-gray-400 hover:text-gray-600 transition-colors ml-4 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 rounded"
                            aria-label="Cerrar"
                        >
                            <span class="material-symbols-outlined" aria-hidden="true">close</span>
                        </button>
                    </div>
                    <p class="text-sm text-gray-500 mb-1 ml-[52px]">
                        Esta acción no se puede deshacer. Selecciona el motivo de la cancelación.
                    </p>
                    <p v-if="subtitle" class="text-xs text-gray-400 mb-5 ml-[52px]">{{ subtitle }}</p>
                    <div v-else class="mb-4"></div>

                    <!-- Reasons -->
                    <div class="space-y-2 mb-5">
                        <label
                            v-for="reason in REASONS"
                            :key="reason"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all"
                            :class="selectedReason === reason
                                ? 'border-red-300 bg-red-50'
                                : 'border-gray-100 bg-gray-50 hover:border-gray-200'"
                        >
                            <input
                                type="radio"
                                name="cancel_reason"
                                :value="reason"
                                v-model="selectedReason"
                                class="accent-red-600"
                            />
                            <span class="text-sm font-medium text-gray-800">{{ reason }}</span>
                        </label>
                    </div>

                    <!-- Custom reason -->
                    <div v-if="selectedReason === 'Otro'" class="mb-5">
                        <textarea
                            v-model="customReason"
                            placeholder="Describe brevemente el motivo…"
                            rows="3"
                            maxlength="500"
                            class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-red-300 focus:border-red-300"
                        ></textarea>
                    </div>

                    <!-- Validation error -->
                    <p v-if="form.errors.cancellation_reason" class="text-xs text-red-500 mb-4">
                        {{ form.errors.cancellation_reason }}
                    </p>

                    <!-- Buttons -->
                    <div class="flex gap-3">
                        <button
                            type="button"
                            @click="emit('close')"
                            class="flex-1 border border-gray-200 text-gray-700 font-semibold rounded-xl py-2.5 text-sm hover:bg-gray-50 transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-gray-300"
                        >
                            Volver
                        </button>
                        <button
                            type="button"
                            @click="submit"
                            :disabled="!canSubmit || form.processing"
                            class="flex-1 bg-red-600 hover:bg-red-700 text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-red-500 focus-visible:ring-offset-2"
                        >
                            {{ form.processing ? submittingLabel : submitLabel }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
