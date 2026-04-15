<script setup>
import { computed } from 'vue'

const props = defineProps({
    sale: { type: Object, required: true },
    restaurantName: { type: String, default: '' },
})

const PAYMENT_LABELS = {
    cash: 'Efectivo',
    terminal: 'Tarjeta / Terminal',
    transfer: 'Transferencia',
}

function fmt(value) {
    return '$' + Number(value ?? 0).toFixed(2)
}

function formatDateTime(dateStr) {
    if (!dateStr) { return '—' }
    const parsed = new Date(dateStr)
    if (Number.isNaN(parsed.getTime())) { return '—' }
    return parsed.toLocaleString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
        timeZone: 'America/Mexico_City',
    })
}

function itemTotal(item) {
    const modTotal = item.modifiers?.reduce((s, m) => s + parseFloat(m.price_adjustment ?? 0), 0) ?? 0
    return (parseFloat(item.unit_price) + modTotal) * item.quantity
}

const totalChange = computed(() => {
    return (props.sale.payments ?? [])
        .filter((p) => p.payment_method_type === 'cash' && p.change_given)
        .reduce((s, p) => s + parseFloat(p.change_given), 0)
})

const LINE = '──────────────────────────────'
const DLINE = '══════════════════════════════'
</script>

<template>
    <div id="print-ticket">
        <!-- Header -->
        <div class="ticket-center ticket-bold">{{ DLINE }}</div>
        <div class="ticket-center ticket-bold ticket-lg">{{ restaurantName || 'Restaurante' }}</div>
        <div v-if="sale.branch?.name" class="ticket-center">{{ sale.branch.name }}</div>
        <div class="ticket-center ticket-bold">{{ DLINE }}</div>

        <!-- Sale info -->
        <div class="ticket-bold ticket-lg">Ticket {{ sale.ticket_number }}</div>
        <div>{{ formatDateTime(sale.created_at) }}</div>
        <div v-if="sale.cashier?.name">Cajero: {{ sale.cashier.name }}</div>

        <!-- Items -->
        <div>{{ LINE }}</div>
        <div class="ticket-bold">PRODUCTOS</div>
        <div>{{ LINE }}</div>

        <div v-for="item in sale.items" :key="item.id" class="ticket-item">
            <div class="ticket-row">
                <span>{{ item.quantity }}x {{ item.product_name }}</span>
            </div>
            <div class="ticket-row ticket-indent">
                <span>c/u {{ fmt(item.unit_price) }}</span>
                <span>{{ fmt(itemTotal(item)) }}</span>
            </div>
            <template v-if="item.modifiers?.length">
                <div
                    v-for="mod in item.modifiers"
                    :key="mod.id"
                    class="ticket-row ticket-indent"
                >
                    <span>+ {{ mod.modifier_option_name }}</span>
                    <span v-if="Number(mod.price_adjustment) > 0">{{ fmt(mod.price_adjustment) }}</span>
                </div>
            </template>
            <div v-if="item.notes" class="ticket-indent ticket-small">
                Nota: {{ item.notes }}
            </div>
        </div>

        <!-- Totals -->
        <div>{{ LINE }}</div>
        <div class="ticket-row ticket-bold ticket-lg">
            <span>TOTAL</span>
            <span>{{ fmt(sale.total) }}</span>
        </div>
        <div>{{ LINE }}</div>

        <!-- Payments (only when paid) -->
        <template v-if="sale.status === 'paid' && sale.payments?.length">
            <div class="ticket-bold">PAGOS</div>
            <div v-for="p in sale.payments" :key="p.id" class="ticket-row">
                <span>{{ PAYMENT_LABELS[p.payment_method_type] || p.payment_method_type }}<span v-if="p.cash_received"> (rec {{ fmt(p.cash_received) }})</span></span>
                <span>{{ fmt(p.amount) }}</span>
            </div>
            <div v-if="totalChange > 0" class="ticket-row ticket-bold">
                <span>Cambio:</span>
                <span>{{ fmt(totalChange) }}</span>
            </div>
            <div>{{ LINE }}</div>
        </template>

        <!-- General notes -->
        <div v-if="sale.notes">
            <div class="ticket-bold">NOTAS</div>
            <div>{{ sale.notes }}</div>
            <div>{{ LINE }}</div>
        </div>

        <!-- Footer -->
        <div class="ticket-bold">{{ DLINE }}</div>
        <div class="ticket-center">Gracias por su compra!</div>
        <div class="ticket-center ticket-bold">{{ DLINE }}</div>
    </div>
</template>

<style>
/* Unscoped on purpose: print-ticket.css needs to override `display:none` for @media print
   without competing with Vue's scoped attribute selectors. */
#print-ticket {
    display: none;
}
</style>
