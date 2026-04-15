<script setup>
import { router } from '@inertiajs/vue3'
import { ref, computed, watch, nextTick } from 'vue'
import axios from 'axios'

const props = defineProps({
    sale: { type: Object, required: true },
    show: { type: Boolean, default: false },
    // Active payment method types for this restaurant (e.g. ['cash','terminal'])
    availableTypes: { type: Array, default: () => ['cash', 'terminal', 'transfer'] },
})

const emit = defineEmits(['close', 'paid'])

const PAYMENT_LABELS = { cash: 'Efectivo', terminal: 'Tarjeta', transfer: 'Transferencia' }
const PAYMENT_ICONS = { cash: 'payments', terminal: 'credit_card', transfer: 'account_balance' }

function fmt(v) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0) }

// ─── Local state for splits ───────────────────────────────────────────────
const payments = ref([])
const total = computed(() => parseFloat(props.sale.total ?? 0))
const paid = computed(() => payments.value.reduce((s, p) => s + parseFloat(p.amount), 0))
const pending = computed(() => Math.max(0, +(total.value - paid.value).toFixed(2)))
const isComplete = computed(() => pending.value < 0.01)

// ─── Form state ───────────────────────────────────────────────────────────
// Simplified flow:
//   - cash    → primary input is "Efectivo recibido". Amount applied = min(received, pending).
//   - other   → primary input is "Monto". Default = pending, editable down to any positive value.
//   - No partial-payment toggle. A partial pay is just "typing less than pending".
const newType = ref(props.availableTypes[0] ?? 'cash')
const cashReceived = ref(null)        // for cash: what client gives physically
const nonCashAmount = ref(null)       // for terminal/transfer: what we register
const error = ref(null)
const submitting = ref(false)
const justPaidFlash = ref(false)      // success flash before auto-close

const noMethodsAvailable = computed(() => !props.availableTypes || props.availableTypes.length === 0)

watch(() => props.show, (v) => {
    if (v) {
        payments.value = (props.sale.payments ?? []).map((p) => ({ ...p }))
        resetForm()
        error.value = null
        justPaidFlash.value = false
    }
})

watch(newType, () => { resetForm(false); error.value = null })

function resetForm(resetType = true) {
    if (resetType) {
        // Default to the first available method so no inactive type is preselected.
        newType.value = props.availableTypes[0] ?? 'cash'
    }
    cashReceived.value = null
    nonCashAmount.value = pending.value > 0 ? Number(pending.value.toFixed(2)) : null
}

watch(pending, (v) => {
    // Keep nonCashAmount in sync with pending when user hasn't explicitly changed it
    if (v > 0 && nonCashAmount.value !== null && nonCashAmount.value > v + 0.01) {
        nonCashAmount.value = Number(v.toFixed(2))
    }
})

// ─── Derived for cash flow ────────────────────────────────────────────────
// The amount registered never exceeds pending. Cash received above pending → change.
const cashAmountToApply = computed(() => {
    if (!cashReceived.value) { return 0 }
    return Math.min(parseFloat(cashReceived.value), pending.value)
})

const cashChange = computed(() => {
    if (newType.value !== 'cash' || !cashReceived.value) { return 0 }
    const received = parseFloat(cashReceived.value)
    return Math.max(0, +(received - cashAmountToApply.value).toFixed(2))
})

// ─── Derived for non-cash flow ────────────────────────────────────────────
const nonCashAmountToApply = computed(() => {
    if (!nonCashAmount.value) { return 0 }
    return Math.min(parseFloat(nonCashAmount.value), pending.value)
})

// ─── Submit gate ──────────────────────────────────────────────────────────
const canSubmit = computed(() => {
    if (submitting.value || pending.value <= 0) { return false }
    if (newType.value === 'cash') {
        return cashReceived.value > 0 && cashAmountToApply.value > 0
    }
    return nonCashAmount.value > 0
})

const willRegister = computed(() => newType.value === 'cash' ? cashAmountToApply.value : nonCashAmountToApply.value)
const willCompleteSale = computed(() => willRegister.value + 0.01 >= pending.value)

// ─── Actions ──────────────────────────────────────────────────────────────
async function addPayment() {
    if (!canSubmit.value) { return }
    error.value = null
    submitting.value = true

    const isCash = newType.value === 'cash'
    const amount = isCash ? cashAmountToApply.value : nonCashAmountToApply.value
    const received = isCash ? parseFloat(cashReceived.value) : null

    try {
        await axios.post(route('pos.sales.payments.store', props.sale.id), {
            payment_method_type: newType.value,
            amount: Number(amount.toFixed(2)),
            cash_received: received,
        })

        // Optimistic local push — backend returns redirect-back, no JSON body.
        payments.value.push({
            id: Date.now(),
            payment_method_type: newType.value,
            amount: Number(amount.toFixed(2)),
            cash_received: received,
            change_given: isCash ? Math.max(0, +(received - amount).toFixed(2)) : null,
        })

        resetForm(false)

        // If this payment covers the full total, the backend auto-closes the sale.
        // Show a brief "cobrada" flash then emit `paid` to close the modal.
        if (isComplete.value) {
            justPaidFlash.value = true
            await nextTick()
            setTimeout(() => {
                emit('paid', {
                    ...props.sale,
                    payments: payments.value,
                    status: 'paid',
                    paid_at: new Date().toISOString(),
                })
            }, 850)
        }
    } catch (err) {
        error.value = err?.response?.data?.errors?.amount?.[0]
            || err?.response?.data?.errors?.cash_received?.[0]
            || err?.response?.data?.errors?.payment_method_type?.[0]
            || err?.response?.data?.message
            || 'No se pudo registrar el pago.'
    } finally {
        submitting.value = false
    }
}

async function removePayment(id) {
    error.value = null
    try {
        await axios.delete(route('pos.sales.payments.destroy', [props.sale.id, id]))
        payments.value = payments.value.filter((p) => p.id !== id)
    } catch (err) {
        error.value = err?.response?.data?.message || 'No se pudo eliminar el pago.'
    }
}

function close() {
    if (!submitting.value && !justPaidFlash.value) { emit('close') }
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-black/40" @click="close"></div>

            <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">

                <!-- Header -->
                <div class="px-6 py-4 border-b border-gray-100 shrink-0 flex items-center justify-between">
                    <div>
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Cobro de venta</p>
                        <h2 class="text-xl font-bold text-gray-900">{{ sale.ticket_number }}</h2>
                    </div>
                    <button
                        @click="close"
                        :disabled="submitting || justPaidFlash"
                        class="text-gray-400 hover:text-gray-600 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/50 rounded disabled:opacity-50"
                        aria-label="Cerrar"
                    >
                        <span class="material-symbols-outlined">close</span>
                    </button>
                </div>

                <!-- Total summary -->
                <div class="px-6 py-4 bg-gradient-to-br from-orange-50 to-orange-100/50 shrink-0 grid grid-cols-3 gap-3 text-center">
                    <div>
                        <p class="text-[10px] font-bold text-orange-700 uppercase tracking-wide">Total</p>
                        <p class="text-lg font-black text-gray-900">{{ fmt(total) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold text-green-700 uppercase tracking-wide">Pagado</p>
                        <p class="text-lg font-black text-green-600">{{ fmt(paid) }}</p>
                    </div>
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-wide" :class="isComplete ? 'text-green-700' : 'text-amber-700'">Pendiente</p>
                        <p class="text-lg font-black" :class="isComplete ? 'text-green-600' : 'text-amber-600'">{{ fmt(pending) }}</p>
                    </div>
                </div>

                <!-- Paid flash overlay -->
                <div v-if="justPaidFlash" class="flex-1 flex flex-col items-center justify-center py-12 px-6 text-center bg-green-50 animate-in fade-in">
                    <div class="size-16 rounded-full bg-green-500 text-white flex items-center justify-center mb-3 shadow-lg shadow-green-200">
                        <span class="material-symbols-outlined text-3xl">check_circle</span>
                    </div>
                    <p class="text-lg font-black text-green-800">Venta cobrada</p>
                    <p class="text-sm text-green-700 mt-1">Total cubierto. Cerrando…</p>
                </div>

                <!-- Body -->
                <div v-else class="flex-1 overflow-y-auto px-6 py-4 space-y-4">

                    <!-- Existing splits -->
                    <div v-if="payments.length > 0">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Pagos registrados</p>
                        <ul class="space-y-2">
                            <li
                                v-for="p in payments"
                                :key="p.id"
                                class="flex items-center justify-between bg-gray-50 border border-gray-100 rounded-xl px-3 py-2.5"
                            >
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <span class="material-symbols-outlined text-gray-400">{{ PAYMENT_ICONS[p.payment_method_type] }}</span>
                                    <div class="min-w-0">
                                        <p class="text-sm font-semibold text-gray-900">{{ PAYMENT_LABELS[p.payment_method_type] }}</p>
                                        <p v-if="p.cash_received" class="text-[10px] text-gray-500">
                                            Recibió {{ fmt(p.cash_received) }} · cambio {{ fmt(p.change_given) }}
                                        </p>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <span class="text-sm font-bold text-gray-900">{{ fmt(p.amount) }}</span>
                                    <button @click="removePayment(p.id)" class="text-gray-300 hover:text-red-500 transition" title="Eliminar pago">
                                        <span class="material-symbols-outlined text-base">close</span>
                                    </button>
                                </div>
                            </li>
                        </ul>
                    </div>

                    <!-- No active payment methods — block registration -->
                    <div v-if="noMethodsAvailable" class="flex flex-col items-center text-center py-10 text-gray-500">
                        <span class="material-symbols-outlined text-5xl text-gray-300 mb-2">block</span>
                        <p class="text-sm font-semibold text-gray-700">No hay métodos de pago activos</p>
                        <p class="text-xs text-gray-500 mt-1">Actívalos desde Configuración → Métodos de pago para poder cobrar.</p>
                    </div>

                    <!-- Add payment form (only if pending > 0 AND methods exist) -->
                    <div v-else-if="!isComplete">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2">Registrar pago</p>

                        <!-- Method selector — only active methods of this restaurant -->
                        <div
                            class="grid gap-2 mb-3"
                            :class="{
                                'grid-cols-3': availableTypes.length >= 3,
                                'grid-cols-2': availableTypes.length === 2,
                                'grid-cols-1': availableTypes.length === 1,
                            }"
                        >
                            <button
                                v-for="t in availableTypes"
                                :key="t"
                                type="button"
                                @click="newType = t"
                                class="flex flex-col items-center gap-1 py-3 rounded-xl border-2 transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/40"
                                :class="newType === t ? 'border-[#FF5722] bg-orange-50 text-[#FF5722]' : 'border-gray-200 text-gray-500 hover:border-gray-300'"
                            >
                                <span class="material-symbols-outlined">{{ PAYMENT_ICONS[t] }}</span>
                                <span class="text-xs font-bold">{{ PAYMENT_LABELS[t] }}</span>
                            </button>
                        </div>

                        <!-- CASH: primary input is what client physically gives -->
                        <div v-if="newType === 'cash'" class="space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Efectivo recibido del cliente</label>
                                <p class="text-[11px] text-gray-500 mb-2">Lo que entrega físicamente. Puede ser mayor al pendiente (se calcula el cambio).</p>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                                    <input
                                        v-model.number="cashReceived"
                                        type="number" min="0.01" step="0.01"
                                        :placeholder="`Ej. ${fmt(pending).replace('$','').trim()}`"
                                        class="w-full pl-7 pr-3 py-3 border-2 border-gray-200 rounded-xl text-lg font-bold focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                                    />
                                </div>
                            </div>

                            <!-- Live calculation — always visible when there's a received value -->
                            <div v-if="cashReceived > 0" class="bg-gray-50 border border-gray-100 rounded-xl px-4 py-3 grid grid-cols-2 gap-3 text-sm">
                                <div>
                                    <p class="text-[10px] font-bold text-gray-500 uppercase">Se aplica a la venta</p>
                                    <p class="font-bold text-gray-900">{{ fmt(cashAmountToApply) }}</p>
                                </div>
                                <div>
                                    <p class="text-[10px] font-bold uppercase" :class="cashChange > 0 ? 'text-amber-700' : 'text-gray-500'">Cambio a entregar</p>
                                    <p class="font-bold" :class="cashChange > 0 ? 'text-amber-700' : 'text-gray-400'">{{ fmt(cashChange) }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- NON-CASH: single amount input, defaults to pending, editable for partial -->
                        <div v-else class="space-y-3">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 mb-1">Monto a cobrar</label>
                                <p class="text-[11px] text-gray-500 mb-2">Por defecto es el pendiente. Puedes reducirlo si es un abono parcial.</p>
                                <div class="relative">
                                    <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 font-bold">$</span>
                                    <input
                                        v-model.number="nonCashAmount"
                                        type="number" :max="pending" min="0.01" step="0.01"
                                        class="w-full pl-7 pr-3 py-3 border-2 border-gray-200 rounded-xl text-lg font-bold focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 focus:border-[#FF5722]"
                                    />
                                </div>
                            </div>
                        </div>

                        <p v-if="error" class="mt-3 text-xs text-red-600 bg-red-50 border border-red-200 px-3 py-2 rounded-lg">{{ error }}</p>

                        <!-- Single primary action -->
                        <button
                            type="button"
                            :disabled="!canSubmit"
                            @click="addPayment"
                            class="w-full mt-3 flex items-center justify-center gap-2 text-white py-3 rounded-xl text-sm font-bold shadow-md transition disabled:opacity-50 disabled:cursor-not-allowed"
                            :class="willCompleteSale
                                ? 'bg-green-600 hover:bg-green-700 shadow-green-200'
                                : 'bg-[#FF5722] hover:bg-[#D84315] shadow-orange-200'"
                        >
                            <span class="material-symbols-outlined text-lg">{{ willCompleteSale ? 'check_circle' : 'add' }}</span>
                            <template v-if="submitting">Procesando…</template>
                            <template v-else-if="willCompleteSale">Registrar {{ fmt(willRegister) }} y cobrar venta</template>
                            <template v-else>Registrar {{ fmt(willRegister || pending) }}</template>
                        </button>
                    </div>

                    <div v-else class="flex items-center gap-2 text-sm text-green-700 bg-green-50 border border-green-200 px-4 py-3 rounded-xl">
                        <span class="material-symbols-outlined">check_circle</span>
                        Venta cobrada.
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<style scoped>
@keyframes fade-in {
    from { opacity: 0; transform: scale(0.95); }
    to   { opacity: 1; transform: scale(1); }
}
.animate-in { animation: fade-in 0.2s ease-out; }
</style>
