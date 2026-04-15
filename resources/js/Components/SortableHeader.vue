<script setup>
import { computed } from 'vue'

/**
 * Interactive <th> that emits a `sort` event when clicked. Presentational
 * only — the parent decides what to do (typically navigate with updated
 * sort_by/sort_direction query params). The parent owns the current sort
 * state and passes it in via `activeKey` and `direction`.
 *
 * Click cycle (parent implementation):
 *   1st click on a new column → asc
 *   2nd click (same column)   → desc
 *   3rd click (same column)   → clear sort (back to backend default)
 */
const props = defineProps({
    /** Unique key for this column, e.g. 'total', 'created_at'. */
    columnKey: { type: String, required: true },
    /** Visible label, e.g. 'Total'. */
    label: { type: String, required: true },
    /** Key of the currently active sort column, or null. */
    activeKey: { type: String, default: null },
    /** 'asc' | 'desc' | null. */
    direction: { type: String, default: null },
    /** 'left' | 'right' | 'center'. Mirrors the non-sortable <th> alignment. */
    align: { type: String, default: 'left' },
})

const emit = defineEmits(['sort'])

const isActive = computed(() => props.activeKey === props.columnKey)
const arrow = computed(() => {
    if (!isActive.value) { return 'unfold_more' }
    return props.direction === 'asc' ? 'arrow_upward' : 'arrow_downward'
})

const alignClass = computed(() => {
    if (props.align === 'right') { return 'text-right justify-end' }
    if (props.align === 'center') { return 'text-center justify-center' }
    return 'text-left justify-start'
})

function onClick() {
    emit('sort', props.columnKey)
}
</script>

<template>
    <th
        scope="col"
        :class="[
            'px-4 py-3 font-semibold uppercase text-xs tracking-wider select-none',
            align === 'right' ? 'text-right' : align === 'center' ? 'text-center' : 'text-left',
        ]"
    >
        <button
            type="button"
            @click="onClick"
            class="inline-flex items-center gap-1 cursor-pointer hover:text-gray-800 transition-colors"
            :class="[
                alignClass,
                isActive ? 'text-[#FF5722]' : 'text-gray-500',
            ]"
            :aria-sort="isActive ? (direction === 'asc' ? 'ascending' : 'descending') : 'none'"
        >
            <span>{{ label }}</span>
            <span
                class="material-symbols-outlined text-base transition-opacity"
                :class="isActive ? 'opacity-100' : 'opacity-40'"
            >{{ arrow }}</span>
        </button>
    </th>
</template>
