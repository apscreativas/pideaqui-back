<script setup>
defineProps({
    status: { type: String, required: true },
})

const LABELS = {
    preparing: 'Creada',
    ready: 'Creada',           // legacy state, treated same as preparing
    paid: 'Cobrada',
    cancelled: 'Cancelada',
}

const STYLES = {
    preparing: 'bg-amber-50 text-amber-700 border-amber-200',
    ready: 'bg-amber-50 text-amber-700 border-amber-200',
    paid: 'bg-green-50 text-green-700 border-green-200',
    cancelled: 'bg-gray-100 text-gray-500 border-gray-200',
}

const ICONS = {
    preparing: 'receipt_long',
    ready: 'receipt_long',
    paid: 'payments',
    cancelled: 'cancel',
}
</script>

<template>
    <span
        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-bold border"
        :class="STYLES[status]"
    >
        <span class="material-symbols-outlined text-sm">{{ ICONS[status] }}</span>
        {{ LABELS[status] || status }}
    </span>
</template>
