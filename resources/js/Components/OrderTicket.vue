<script setup>
import { computed } from 'vue'

const props = defineProps({
    order: { type: Object, required: true },
    restaurantName: { type: String, default: '' },
})

const DELIVERY_LABELS = {
    delivery: 'Entrega a domicilio',
    pickup: 'Recoger en sucursal',
    dine_in: 'Comer en restaurante',
}

const PAYMENT_LABELS = {
    cash: 'Efectivo',
    terminal: 'Tarjeta / Terminal',
    transfer: 'Transferencia',
}

function fmt(value) {
    return '$' + Number(value).toFixed(2)
}

function orderNumber(id) {
    return '#' + String(id).padStart(4, '0')
}

function formatDateTime(dateStr) {
    return new Date(dateStr).toLocaleString('es-MX', {
        day: '2-digit', month: 'short', year: 'numeric',
        hour: '2-digit', minute: '2-digit',
        timeZone: 'America/Mexico_City',
    })
}

function itemTotal(item) {
    const modTotal = item.modifiers?.reduce((s, m) => s + parseFloat(m.price_adjustment ?? 0), 0) ?? 0
    return (parseFloat(item.unit_price) + modTotal) * item.quantity
}

const cashChange = computed(() => {
    if (props.order.payment_method !== 'cash' || !props.order.cash_amount) { return null }
    const change = parseFloat(props.order.cash_amount) - parseFloat(props.order.total)
    return change > 0 ? change : null
})

const hasAddress = computed(() =>
    props.order.delivery_type === 'delivery' && props.order.address_street
)

const LINE = '──────────────────────────────'
const DLINE = '══════════════════════════════'
</script>

<template>
    <div id="print-ticket">
        <!-- Header -->
        <div class="ticket-center ticket-bold">{{ DLINE }}</div>
        <div class="ticket-center ticket-bold ticket-lg">{{ restaurantName || 'Restaurante' }}</div>
        <div v-if="order.branch?.name" class="ticket-center">{{ order.branch.name }}</div>
        <div class="ticket-center ticket-bold">{{ DLINE }}</div>

        <!-- Order info -->
        <div class="ticket-bold ticket-lg">Pedido {{ orderNumber(order.id) }}<span v-if="order.edit_count > 0"> (editado)</span></div>
        <div>{{ formatDateTime(order.created_at) }}</div>
        <div>Tipo: {{ DELIVERY_LABELS[order.delivery_type] || order.delivery_type }}</div>

        <!-- Items -->
        <div>{{ LINE }}</div>
        <div class="ticket-bold">PRODUCTOS</div>
        <div>{{ LINE }}</div>

        <div v-for="item in order.items" :key="item.id" class="ticket-item">
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
        <div class="ticket-row">
            <span>Subtotal</span>
            <span>{{ fmt(order.subtotal) }}</span>
        </div>
        <div v-if="Number(order.discount_amount) > 0" class="ticket-row">
            <span>Descuento ({{ order.coupon_code }})</span>
            <span>-{{ fmt(order.discount_amount) }}</span>
        </div>
        <div v-if="order.delivery_type === 'delivery' && Number(order.delivery_cost) > 0" class="ticket-row">
            <span>Envio</span>
            <span>{{ fmt(order.delivery_cost) }}</span>
        </div>
        <div>{{ LINE }}</div>
        <div class="ticket-row ticket-bold ticket-lg">
            <span>TOTAL</span>
            <span>{{ fmt(order.total) }}</span>
        </div>
        <div>{{ LINE }}</div>

        <!-- Payment -->
        <div class="ticket-row">
            <span>Pago:</span>
            <span>{{ PAYMENT_LABELS[order.payment_method] || order.payment_method }}</span>
        </div>
        <div v-if="order.payment_method === 'cash' && order.cash_amount" class="ticket-row">
            <span>Pago con:</span>
            <span>{{ fmt(order.cash_amount) }}</span>
        </div>
        <div v-if="cashChange !== null" class="ticket-row ticket-bold">
            <span>Cambio:</span>
            <span>{{ fmt(cashChange) }}</span>
        </div>

        <!-- Invoice flag -->
        <div v-if="order.requires_invoice" class="ticket-center ticket-bold ticket-lg">
            ** REQUIERE FACTURA **
        </div>

        <!-- Customer -->
        <div class="ticket-bold">{{ DLINE }}</div>
        <div class="ticket-bold">CLIENTE</div>
        <div>{{ order.customer?.name }} · {{ order.customer?.phone }}</div>

        <!-- Address (delivery only) -->
        <template v-if="hasAddress">
            <div>{{ LINE }}</div>
            <div class="ticket-bold">DIRECCION</div>
            <div>{{ order.address_street }} {{ order.address_number }}</div>
            <div v-if="order.address_colony">Col. {{ order.address_colony }}</div>
            <div v-if="order.address_references">Ref: {{ order.address_references }}</div>
        </template>

        <!-- Branch for pickup/dine_in -->
        <template v-if="order.delivery_type !== 'delivery' && order.branch">
            <div>{{ LINE }}</div>
            <div class="ticket-bold">SUCURSAL</div>
            <div>{{ order.branch.name }}</div>
            <div v-if="order.branch.address">{{ order.branch.address }}</div>
        </template>

        <!-- Scheduled -->
        <div v-if="order.scheduled_at">
            <div>{{ LINE }}</div>
            <div>Programado: {{ formatDateTime(order.scheduled_at) }}</div>
        </div>

        <!-- Footer -->
        <div class="ticket-bold">{{ DLINE }}</div>
        <div class="ticket-center">Gracias por su pedido!</div>
        <div class="ticket-center ticket-bold">{{ DLINE }}</div>
    </div>
</template>

<style scoped>
#print-ticket {
    display: none;
}

#print-ticket * {
    font-family: 'Courier New', Courier, monospace;
    font-size: 12px;
    line-height: 1.4;
    color: #000;
    background: none;
    margin: 0;
    padding: 0;
}

.ticket-center {
    text-align: center;
}

.ticket-bold {
    font-weight: 700;
}

.ticket-lg {
    font-size: 14px;
}

.ticket-small {
    font-size: 11px;
    font-style: italic;
}

.ticket-row {
    display: flex;
    justify-content: space-between;
    gap: 8px;
}

.ticket-indent {
    padding-left: 16px;
}

.ticket-item {
    margin-bottom: 4px;
}
</style>
