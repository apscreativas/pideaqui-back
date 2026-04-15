<script setup>
import { computed } from 'vue'

/**
 * Paginator footer for admin tables backed by Laravel's LengthAwarePaginator.
 *
 * Expected `paginator` shape (same as ->paginate() JSON):
 *   { data: [], current_page, last_page, per_page, total, from, to }
 *
 * The component is presentation-only: the parent decides what to do with
 * the `page` and `per-page` events (typically Inertia `router.get` with
 * filters preserved).
 */
const props = defineProps({
    paginator: {
        type: Object,
        required: true,
    },
    perPageOptions: {
        type: Array,
        default: () => [20, 50, 100],
    },
    /** Word used in the "N {label}" count, e.g. "ventas", "cancelaciones". */
    label: {
        type: String,
        default: 'registros',
    },
})

const emit = defineEmits(['page', 'per-page'])

const currentPage = computed(() => Number(props.paginator?.current_page ?? 1))
const lastPage = computed(() => Number(props.paginator?.last_page ?? 1))
const perPage = computed(() => Number(props.paginator?.per_page ?? props.perPageOptions[0]))
const total = computed(() => Number(props.paginator?.total ?? 0))
const from = computed(() => Number(props.paginator?.from ?? 0))
const to = computed(() => Number(props.paginator?.to ?? 0))

/**
 * Build a compact page-number strip. Always show first & last, current page
 * ±1 neighbours, and insert `…` ellipses when there are gaps. Caps the
 * visible buttons at ~7 so the footer stays tidy regardless of total pages.
 */
const pageNumbers = computed(() => {
    const last = lastPage.value
    const cur = currentPage.value
    if (last <= 7) {
        return Array.from({ length: last }, (_, i) => i + 1)
    }

    const pages = new Set([1, last, cur, cur - 1, cur + 1])
    // Keep ellipsis boundary slots on both ends.
    pages.add(2)
    pages.add(last - 1)
    const sorted = [...pages].filter((n) => n >= 1 && n <= last).sort((a, b) => a - b)

    const result = []
    for (let i = 0; i < sorted.length; i++) {
        result.push(sorted[i])
        const next = sorted[i + 1]
        if (next && next - sorted[i] > 1) {
            result.push('…')
        }
    }
    return result
})

function goTo(page) {
    if (typeof page !== 'number') { return }
    if (page < 1 || page > lastPage.value || page === currentPage.value) { return }
    emit('page', page)
}

function changePerPage(e) {
    const v = Number(e.target.value)
    if (!Number.isFinite(v) || v === perPage.value) { return }
    emit('per-page', v)
}

const hasRows = computed(() => total.value > 0)
</script>

<template>
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 px-6 py-4 border-t border-gray-100 bg-white">
        <!-- Left: range + total -->
        <p class="text-xs text-gray-500">
            <template v-if="hasRows">
                Mostrando
                <span class="font-semibold text-gray-700">{{ from }}</span>
                –
                <span class="font-semibold text-gray-700">{{ to }}</span>
                de
                <span class="font-semibold text-gray-700">{{ total }}</span>
                {{ label }}
            </template>
            <template v-else>
                Sin {{ label }} en este periodo
            </template>
        </p>

        <div class="flex flex-wrap items-center gap-3">
            <!-- Per-page selector -->
            <label class="flex items-center gap-2 text-xs text-gray-500">
                Por página
                <select
                    :value="perPage"
                    @change="changePerPage"
                    class="border border-gray-200 rounded-lg px-2 py-1 text-xs font-semibold text-gray-700 focus:outline-none focus:ring-1 focus:ring-[#FF5722] focus:border-[#FF5722]"
                >
                    <option v-for="opt in perPageOptions" :key="opt" :value="opt">{{ opt }}</option>
                </select>
            </label>

            <!-- Page navigation -->
            <div v-if="lastPage > 1" class="flex items-center gap-1">
                <button
                    type="button"
                    :disabled="currentPage <= 1"
                    @click="goTo(currentPage - 1)"
                    class="px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 rounded-lg disabled:opacity-40 disabled:cursor-not-allowed inline-flex items-center gap-1"
                    aria-label="Página anterior"
                >
                    <span class="material-symbols-outlined text-base">chevron_left</span>
                    Anterior
                </button>

                <button
                    v-for="(p, idx) in pageNumbers"
                    :key="`${p}-${idx}`"
                    type="button"
                    :disabled="p === '…'"
                    @click="goTo(p)"
                    class="min-w-[32px] px-2 py-1 text-xs font-semibold rounded-lg transition-colors"
                    :class="[
                        p === currentPage
                            ? 'bg-[#FF5722] text-white'
                            : p === '…'
                                ? 'text-gray-400 cursor-default'
                                : 'text-gray-600 hover:bg-gray-100',
                    ]"
                >{{ p }}</button>

                <button
                    type="button"
                    :disabled="currentPage >= lastPage"
                    @click="goTo(currentPage + 1)"
                    class="px-2.5 py-1.5 text-xs font-medium text-gray-600 hover:bg-gray-50 rounded-lg disabled:opacity-40 disabled:cursor-not-allowed inline-flex items-center gap-1"
                    aria-label="Página siguiente"
                >
                    Siguiente
                    <span class="material-symbols-outlined text-base">chevron_right</span>
                </button>
            </div>
        </div>
    </div>
</template>
