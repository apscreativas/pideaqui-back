<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, computed, watch } from 'vue'
import axios from 'axios'
import AppLayout from '@/Layouts/AppLayout.vue'
import MapPicker from '@/Components/MapPicker.vue'
import DateTimePicker from '@/Components/DateTimePicker.vue'

const props = defineProps({
    branches: Array,
    categories: Array,
    promotions: Array,
    paymentMethods: Array,
    mapsKey: { type: String, default: '' },
    allowsDelivery: Boolean,
    allowsPickup: Boolean,
    allowsDineIn: Boolean,
    monthly_count: Number,
    orders_limit: Number,
    limit_reason: { type: String, default: null },
    limit_period: { type: Object, default: () => ({ start: null, end: null }) },
})

function formatPeriodDate(s) {
    if (!s) { return '' }
    return new Intl.DateTimeFormat('es-MX', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(s + 'T12:00:00'))
}

const limitBlocked = computed(() => props.limit_reason !== null)

const limitBanner = computed(() => {
    if (props.limit_reason === 'limit_reached') {
        return {
            tone: 'error',
            icon: 'block',
            title: 'Límite alcanzado',
            message: `Has usado ${props.monthly_count} de ${props.orders_limit} pedidos del periodo. Actualiza tu plan para crear más.`,
        }
    }
    if (props.limit_reason === 'period_expired') {
        return {
            tone: 'neutral',
            icon: 'event_busy',
            title: 'Periodo terminado',
            message: `El periodo de pedidos terminó el ${formatPeriodDate(props.limit_period?.end)}. Comunícate con tu administrador para renovar.`,
        }
    }
    if (props.limit_reason === 'period_not_started') {
        return {
            tone: 'neutral',
            icon: 'event_upcoming',
            title: 'Periodo no iniciado',
            message: `El periodo de pedidos inicia el ${formatPeriodDate(props.limit_period?.start)}.`,
        }
    }
    // Soft warning when used >= 80% of limit (info, doesn't block).
    if (props.orders_limit && props.monthly_count / props.orders_limit >= 0.8) {
        return {
            tone: 'warning',
            icon: 'warning',
            title: 'Estás cerca del límite',
            message: `Llevas ${props.monthly_count} de ${props.orders_limit} pedidos del periodo.`,
        }
    }
    return null
})

function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}

const DELIVERY_LABELS = { delivery: 'A domicilio', pickup: 'Recoger', dine_in: 'En restaurante' }
const DELIVERY_ICONS = { delivery: 'two_wheeler', pickup: 'storefront', dine_in: 'restaurant' }
const DELIVERY_HINT = {
    delivery: 'El cliente recibe en su ubicación.',
    pickup: 'El cliente pasa por la sucursal.',
    dine_in: 'El cliente come en el restaurante.',
}
const PAYMENT_LABELS = { cash: 'Efectivo', terminal: 'Tarjeta / Terminal', transfer: 'Transferencia' }
const PAYMENT_ICONS = { cash: 'payments', terminal: 'credit_card', transfer: 'account_balance' }

const availableDeliveryTypes = computed(() => {
    const out = []
    if (props.allowsPickup) { out.push('pickup') }
    if (props.allowsDineIn) { out.push('dine_in') }
    if (props.allowsDelivery) { out.push('delivery') }
    return out
})

const activePaymentTypes = computed(() => props.paymentMethods.map((p) => p.type))

function goBack() { router.visit(route('orders.index')) }

function groupKey(group) { return (group.source || 'inline') + '_' + group.id }
function isMultiple(group) { return group.selection_type === 'multiple' }

const allProducts = computed(() => {
    const items = []
    for (const cat of props.categories) {
        for (const p of cat.products) {
            items.push({ ...p, type: 'product', categoryName: cat.name })
        }
    }
    for (const p of props.promotions) {
        items.push({ ...p, type: 'promotion', categoryName: 'Promociones' })
    }
    return items
})

function findCatalogProduct(item) {
    return allProducts.value.find((p) =>
        item.product_id ? (p.type === 'product' && p.id === item.product_id) : (p.type === 'promotion' && p.id === item.promotion_id)
    )
}
function getModifierGroups(item) { return findCatalogProduct(item)?.modifier_groups ?? [] }

// ─── Form state ────────────────────────────────────────────────────────────
const customerName = ref('')
const customerPhone = ref('')
const deliveryType = ref(availableDeliveryTypes.value[0] ?? 'pickup')
const branchId = ref(props.branches[0]?.id ?? null)

const addressStreet = ref('')
const addressNumber = ref('')
const addressColony = ref('')
const addressReferences = ref('')
const latitude = ref(null)
const longitude = ref(null)

const paymentMethod = ref(activePaymentTypes.value[0] ?? null)
const cashAmount = ref(null)
const requiresInvoice = ref(false)
const scheduledAt = ref('')

const localItems = ref([])

// ─── Delivery cost preview ────────────────────────────────────────────────
const deliveryPreview = ref(null)        // { branch, distance_km, duration_minutes, delivery_cost, in_coverage }
const deliveryPreviewError = ref(null)
const deliveryPreviewLoading = ref(false)

const canPreviewDelivery = computed(() =>
    deliveryType.value === 'delivery'
    && latitude.value !== null
    && longitude.value !== null
    && addressStreet.value.trim() !== ''
    && addressNumber.value.trim() !== ''
    && addressColony.value.trim() !== ''
)

function invalidateDeliveryPreview() {
    deliveryPreview.value = null
    deliveryPreviewError.value = null
}

// Any delivery-relevant change voids the preview (forces operator to recalc).
watch([deliveryType, latitude, longitude, addressStreet, addressNumber, addressColony], () => {
    invalidateDeliveryPreview()
})

async function calculateDelivery() {
    if (!canPreviewDelivery.value) { return }
    deliveryPreviewLoading.value = true
    deliveryPreviewError.value = null
    try {
        const { data } = await axios.post(route('orders.preview-delivery'), {
            latitude: latitude.value,
            longitude: longitude.value,
        })
        deliveryPreview.value = data
        if (!data.in_coverage) {
            deliveryPreviewError.value = 'La ubicación está fuera del rango de cobertura.'
        }
    } catch (err) {
        deliveryPreview.value = null
        deliveryPreviewError.value = err?.response?.data?.error || 'No se pudo calcular el envío. Intenta de nuevo.'
    } finally {
        deliveryPreviewLoading.value = false
    }
}

// ─── Item modifiers ────────────────────────────────────────────────────────
function toggleModifier(item, option, group) {
    const isInline = group.source === 'inline'
    const key = isInline ? 'modifier_option_id' : 'modifier_option_template_id'
    const optionId = option.id

    const idx = item.modifiers.findIndex((m) => m[key] === optionId)
    if (idx !== -1) {
        if (group.is_required) {
            const groupOptionIds = new Set(group.options.map((o) => o.id))
            const groupSelections = item.modifiers.filter((m) => groupOptionIds.has(m[key]))
            if (groupSelections.length <= 1) { return }
        }
        item.modifiers.splice(idx, 1)
        return
    }
    if (group.selection_type === 'single') {
        const groupOptionIds = new Set(group.options.map((o) => o.id))
        item.modifiers = item.modifiers.filter((m) => !groupOptionIds.has(m[key]))
    }
    if (group.max_selections) {
        const groupOptionIds = new Set(group.options.map((o) => o.id))
        const currentCount = item.modifiers.filter((m) => groupOptionIds.has(m[key])).length
        if (currentCount >= group.max_selections) { return }
    }
    item.modifiers.push({
        modifier_option_id: isInline ? optionId : null,
        modifier_option_template_id: isInline ? null : optionId,
        name: option.name,
        price_adjustment: parseFloat(option.price_adjustment),
    })
}

function isModifierSelected(item, option, group) {
    const key = group.source === 'inline' ? 'modifier_option_id' : 'modifier_option_template_id'
    return item.modifiers.some((m) => m[key] === option.id)
}

function isOptionDisabled(item, option, group) {
    if (!isMultiple(group)) { return false }
    if (!group.max_selections) { return false }
    if (isModifierSelected(item, option, group)) { return false }
    const key = group.source === 'inline' ? 'modifier_option_id' : 'modifier_option_template_id'
    const groupOptionIds = new Set(group.options.map((o) => o.id))
    const count = item.modifiers.filter((m) => groupOptionIds.has(m[key])).length
    return count >= group.max_selections
}

function groupSelectionCount(item, group) {
    const key = group.source === 'inline' ? 'modifier_option_id' : 'modifier_option_template_id'
    const groupOptionIds = new Set(group.options.map((o) => o.id))
    return item.modifiers.filter((m) => groupOptionIds.has(m[key])).length
}

function removeItem(index) { localItems.value.splice(index, 1) }

// ─── Add-product modal ────────────────────────────────────────────────────
const showAddModal = ref(false)
const searchQuery = ref('')
const addingProduct = ref(null)
const addModalSelections = ref({})
const addModalQuantity = ref(1)
const addModalNotes = ref('')

const filteredProducts = computed(() => {
    const q = searchQuery.value.toLowerCase().trim()
    if (!q) { return allProducts.value }
    return allProducts.value.filter((p) => p.name.toLowerCase().includes(q))
})

const groupedFilteredProducts = computed(() => {
    const groups = {}
    for (const p of filteredProducts.value) {
        const cat = p.categoryName
        if (!groups[cat]) { groups[cat] = [] }
        groups[cat].push(p)
    }
    return groups
})

function openAddModal() {
    searchQuery.value = ''
    addingProduct.value = null
    addModalQuantity.value = 1
    addModalNotes.value = ''
    showAddModal.value = true
}

function selectProductToAdd(product) {
    const groups = product.modifier_groups ?? []
    if (groups.length === 0) {
        addItemDirectly(product, [], 1, '')
        return
    }
    addingProduct.value = product
    addModalQuantity.value = 1
    addModalNotes.value = ''
    addModalSelections.value = {}
    groups.forEach((g) => {
        addModalSelections.value[groupKey(g)] = isMultiple(g) ? new Set() : null
    })
}

function toggleAddModalOption(group, optionId) {
    const key = groupKey(group)
    if (isMultiple(group)) {
        const set = addModalSelections.value[key]
        if (set.has(optionId)) {
            set.delete(optionId)
        } else if (!group.max_selections || set.size < group.max_selections) {
            set.add(optionId)
        }
    } else {
        if (addModalSelections.value[key] === optionId && !group.is_required) {
            addModalSelections.value[key] = null
        } else {
            addModalSelections.value[key] = optionId
        }
    }
}

function isAddModalSelected(group, optionId) {
    const sel = addModalSelections.value[groupKey(group)]
    if (isMultiple(group)) { return sel?.has(optionId) }
    return sel === optionId
}

function isAddModalDisabled(group, optionId) {
    if (!isMultiple(group)) { return false }
    if (!group.max_selections) { return false }
    const sel = addModalSelections.value[groupKey(group)]
    return sel?.size >= group.max_selections && !sel.has(optionId)
}

function addModalMaxReached(group) {
    if (!isMultiple(group) || !group.max_selections) { return false }
    return (addModalSelections.value[groupKey(group)]?.size ?? 0) >= group.max_selections
}

const addModalValid = computed(() => {
    if (!addingProduct.value) { return false }
    return addingProduct.value.modifier_groups?.every((g) => {
        if (!g.is_required) { return true }
        const sel = addModalSelections.value[groupKey(g)]
        if (isMultiple(g)) { return sel?.size > 0 }
        return sel !== null && sel !== undefined
    }) ?? true
})

const addModalModifiers = computed(() => {
    if (!addingProduct.value) { return [] }
    const result = []
    addingProduct.value.modifier_groups?.forEach((g) => {
        const sel = addModalSelections.value[groupKey(g)]
        const source = g.source || 'inline'
        if (isMultiple(g)) {
            sel?.forEach((optionId) => {
                const opt = g.options.find((o) => o.id === optionId)
                if (opt) {
                    result.push({
                        modifier_option_id: source === 'inline' ? opt.id : null,
                        modifier_option_template_id: source === 'catalog' ? opt.id : null,
                        name: opt.name,
                        price_adjustment: parseFloat(opt.price_adjustment),
                    })
                }
            })
        } else if (sel != null) {
            const opt = g.options.find((o) => o.id === sel)
            if (opt) {
                result.push({
                    modifier_option_id: source === 'inline' ? opt.id : null,
                    modifier_option_template_id: source === 'catalog' ? opt.id : null,
                    name: opt.name,
                    price_adjustment: parseFloat(opt.price_adjustment),
                })
            }
        }
    })
    return result
})

const addModalTotal = computed(() => {
    if (!addingProduct.value) { return 0 }
    const base = parseFloat(addingProduct.value.price)
    const mods = addModalModifiers.value.reduce((s, m) => s + m.price_adjustment, 0)
    return (base + mods) * addModalQuantity.value
})

function confirmAddProduct() {
    if (!addModalValid.value) { return }
    addItemDirectly(addingProduct.value, addModalModifiers.value, addModalQuantity.value, addModalNotes.value)
}

function addItemDirectly(product, modifiers, quantity, notes) {
    const existing = localItems.value.find((i) =>
        product.type === 'product' ? i.product_id === product.id : i.promotion_id === product.id
    )
    if (existing && modifiers.length === 0 && existing.modifiers.length === 0) {
        existing.quantity += quantity
    } else {
        localItems.value.push({
            product_id: product.type === 'product' ? product.id : null,
            promotion_id: product.type === 'promotion' ? product.id : null,
            product_name: product.name,
            quantity,
            unit_price: parseFloat(product.price),
            notes: notes || null,
            modifiers,
        })
    }
    showAddModal.value = false
    addingProduct.value = null
}

// ─── Totals ────────────────────────────────────────────────────────────────
function itemTotal(item) {
    const modTotal = item.modifiers.reduce((s, m) => s + m.price_adjustment, 0)
    return (item.unit_price + modTotal) * item.quantity
}

const subtotal = computed(() => localItems.value.reduce((sum, item) => sum + itemTotal(item), 0))
const deliveryCost = computed(() => {
    if (deliveryType.value !== 'delivery') { return 0 }
    if (!deliveryPreview.value || !deliveryPreview.value.in_coverage) { return 0 }
    return parseFloat(deliveryPreview.value.delivery_cost) || 0
})
const total = computed(() => subtotal.value + deliveryCost.value)

const missingRequiredModifiers = computed(() => {
    const issues = []
    for (const item of localItems.value) {
        const groups = getModifierGroups(item)
        for (const g of groups) {
            if (!g.is_required) { continue }
            const key = g.source === 'inline' ? 'modifier_option_id' : 'modifier_option_template_id'
            const groupOptionIds = new Set(g.options.map((o) => o.id))
            const hasSelection = item.modifiers.some((m) => groupOptionIds.has(m[key]))
            if (!hasSelection) { issues.push({ itemName: item.product_name, groupName: g.name }) }
        }
    }
    return issues
})

// ─── Submit ────────────────────────────────────────────────────────────────
const submitting = ref(false)
const validationError = ref(null)
const formErrors = ref({})

function validate() {
    if (!customerName.value.trim()) { return 'Captura el nombre del cliente.' }
    if (!/^\d{10}$/.test(customerPhone.value.trim())) { return 'El teléfono debe ser de exactamente 10 dígitos.' }
    if (!deliveryType.value) { return 'Selecciona el tipo de entrega.' }
    if (deliveryType.value !== 'delivery' && !branchId.value) { return 'Selecciona la sucursal.' }
    if (deliveryType.value === 'delivery') {
        if (!addressStreet.value.trim() || !addressNumber.value.trim() || !addressColony.value.trim()) {
            return 'Captura la dirección completa.'
        }
        if (latitude.value === null || longitude.value === null) {
            return 'Selecciona la ubicación en el mapa.'
        }
        if (!deliveryPreview.value) {
            return 'Calcula el costo de envío antes de crear el pedido.'
        }
        if (!deliveryPreview.value.in_coverage) {
            return 'La ubicación está fuera del rango de cobertura.'
        }
    }
    if (!paymentMethod.value) { return 'Selecciona el método de pago.' }
    if (paymentMethod.value === 'cash' && cashAmount.value && parseFloat(cashAmount.value) < total.value) {
        return 'El monto en efectivo no cubre el total.'
    }
    if (localItems.value.length === 0) { return 'Agrega al menos un producto al pedido.' }
    if (missingRequiredModifiers.value.length > 0) {
        const f = missingRequiredModifiers.value[0]
        return `El grupo "${f.groupName}" es obligatorio para "${f.itemName}".`
    }
    return null
}

function submit() {
    const err = validate()
    if (err) {
        validationError.value = err
        return
    }
    validationError.value = null
    formErrors.value = {}
    submitting.value = true

    const payload = {
        customer: { name: customerName.value.trim(), phone: customerPhone.value.trim() },
        delivery_type: deliveryType.value,
        branch_id: deliveryType.value === 'delivery' ? (branchId.value ?? props.branches[0]?.id) : branchId.value,
        payment_method: paymentMethod.value,
        cash_amount: paymentMethod.value === 'cash' && cashAmount.value ? parseFloat(cashAmount.value) : null,
        requires_invoice: requiresInvoice.value,
        scheduled_at: scheduledAt.value || null,
        items: localItems.value.map((item) => ({
            product_id: item.product_id,
            promotion_id: item.promotion_id,
            quantity: item.quantity,
            unit_price: item.unit_price,
            notes: item.notes,
            modifiers: item.modifiers.map((m) => ({
                modifier_option_id: m.modifier_option_id,
                modifier_option_template_id: m.modifier_option_template_id,
                price_adjustment: m.price_adjustment,
            })),
        })),
    }

    if (deliveryType.value === 'delivery') {
        payload.address_street = addressStreet.value.trim()
        payload.address_number = addressNumber.value.trim()
        payload.address_colony = addressColony.value.trim()
        payload.address_references = addressReferences.value.trim() || null
        payload.latitude = latitude.value
        payload.longitude = longitude.value
    }

    router.post(route('orders.store'), payload, {
        preserveScroll: true,
        onSuccess: () => { submitting.value = false },
        onError: (errors) => {
            submitting.value = false
            formErrors.value = errors
            const first = Object.values(errors)[0]
            validationError.value = Array.isArray(first) ? first[0] : first
        },
    })
}
</script>

<template>
    <Head title="Nuevo Pedido" />
    <AppLayout title="Nuevo Pedido">

        <!-- Breadcrumb / header -->
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <button @click="goBack" class="hover:text-[#FF5722] flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_back</span>
                        Tablero
                    </button>
                    <span class="text-gray-300">/</span>
                    <span class="text-gray-800 font-medium">Nuevo pedido</span>
                </div>
                <h2 class="text-2xl font-black text-gray-900 mt-2">Crear pedido manual</h2>
                <p class="text-sm text-gray-500 mt-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm align-middle" aria-hidden="true">info</span>
                    El pedido entrará a la columna "Recibido" como cualquier otro.
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button
                    type="button"
                    @click="goBack"
                    class="border border-gray-200 text-gray-600 hover:bg-gray-50 px-5 py-3 rounded-xl text-sm font-bold transition"
                >
                    Cancelar
                </button>
                <button
                    type="button"
                    :disabled="submitting || limitBlocked"
                    @click="submit"
                    class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span class="material-symbols-outlined text-lg">add_shopping_cart</span>
                    {{ submitting ? 'Creando...' : 'Crear pedido' }}
                </button>
            </div>
        </div>

        <!-- Limit / period banner — differentiated by reason -->
        <div
            v-if="limitBanner"
            class="flex items-start gap-3 text-sm border px-4 py-3 rounded-xl mb-6"
            :class="{
                'text-red-800 bg-red-50 border-red-200': limitBanner.tone === 'error',
                'text-amber-800 bg-amber-50 border-amber-200': limitBanner.tone === 'warning',
                'text-gray-700 bg-gray-50 border-gray-200': limitBanner.tone === 'neutral',
            }"
        >
            <span class="material-symbols-outlined text-lg shrink-0 mt-0.5">{{ limitBanner.icon }}</span>
            <div class="flex-1">
                <p class="font-bold">{{ limitBanner.title }}</p>
                <p class="text-xs mt-0.5 opacity-90">{{ limitBanner.message }}</p>
            </div>
        </div>

        <!-- Validation banner -->
        <div v-if="validationError" class="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-200 px-4 py-3 rounded-xl mb-6">
            <span class="material-symbols-outlined text-base shrink-0">error</span>
            {{ validationError }}
            <button @click="validationError = null" class="ml-auto text-red-400 hover:text-red-600">
                <span class="material-symbols-outlined text-base">close</span>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left column: customer + items + delivery -->
            <div class="lg:col-span-2 flex flex-col gap-6">

                <!-- Customer -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">person</span>
                        Cliente
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Nombre</label>
                            <input
                                v-model="customerName"
                                type="text"
                                placeholder="Ej. María González"
                                maxlength="255"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">
                                Teléfono <span class="text-gray-400 font-normal">(10 dígitos)</span>
                            </label>
                            <input
                                v-model="customerPhone"
                                type="tel"
                                inputmode="numeric"
                                pattern="\d{10}"
                                placeholder="55 1234 5678"
                                maxlength="10"
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            />
                        </div>
                    </div>
                    <p class="text-xs text-gray-400 mt-3 flex items-start gap-1">
                        <span class="material-symbols-outlined text-sm shrink-0 mt-0.5">info</span>
                        Si ya existe un cliente con este teléfono, se actualizará su nombre.
                    </p>
                </div>

                <!-- Delivery type -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">local_shipping</span>
                        Tipo de entrega
                    </h3>
                    <div v-if="availableDeliveryTypes.length === 0" class="text-sm text-amber-700 bg-amber-50 px-3 py-2 rounded-lg">
                        No tienes ningún tipo de entrega activo. Configúralos en Ajustes → Métodos de entrega.
                    </div>
                    <div v-else class="grid grid-cols-1 md:grid-cols-3 gap-3">
                        <button
                            v-for="t in availableDeliveryTypes"
                            :key="t"
                            type="button"
                            @click="deliveryType = t"
                            class="flex flex-col items-start gap-1 px-4 py-3 rounded-xl border text-left transition-all"
                            :class="deliveryType === t
                                ? 'border-[#FF5722] bg-orange-50 ring-1 ring-[#FF5722]/20'
                                : 'border-gray-200 bg-white hover:border-gray-300'"
                        >
                            <span
                                class="material-symbols-outlined text-2xl"
                                :class="deliveryType === t ? 'text-[#FF5722]' : 'text-gray-400'"
                            >{{ DELIVERY_ICONS[t] }}</span>
                            <span class="text-sm font-bold" :class="deliveryType === t ? 'text-[#FF5722]' : 'text-gray-800'">
                                {{ DELIVERY_LABELS[t] }}
                            </span>
                            <span class="text-xs text-gray-500">{{ DELIVERY_HINT[t] }}</span>
                        </button>
                    </div>

                    <!-- Branch selector (pickup/dine_in only) -->
                    <div v-if="deliveryType && deliveryType !== 'delivery'" class="mt-4">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Sucursal</label>
                        <select
                            v-model="branchId"
                            class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50 bg-white"
                        >
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                    </div>
                    <div v-else-if="deliveryType === 'delivery'" class="mt-3 flex items-start gap-2 text-xs text-gray-500 bg-gray-50 px-3 py-2 rounded-lg">
                        <span class="material-symbols-outlined text-sm mt-0.5">auto_awesome</span>
                        La sucursal y el costo de envío se calculan automáticamente desde la ubicación.
                    </div>
                </div>

                <!-- Address (delivery only) -->
                <div v-if="deliveryType === 'delivery'" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">location_on</span>
                        Dirección de entrega
                    </h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Calle</label>
                            <input v-model="addressStreet" type="text" maxlength="255" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Número</label>
                            <input v-model="addressNumber" type="text" maxlength="50" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Colonia</label>
                            <input v-model="addressColony" type="text" maxlength="255" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Referencias</label>
                            <input v-model="addressReferences" type="text" maxlength="500" placeholder="Ej. portón gris" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
                    </div>
                    <MapPicker v-model:lat="latitude" v-model:lng="longitude" :maps-key="mapsKey" />

                    <!-- Calculate delivery preview -->
                    <div class="mt-4 border-t border-dashed border-gray-200 pt-4">
                        <div class="flex items-center justify-between gap-3 mb-3">
                            <div>
                                <p class="text-sm font-bold text-gray-800">Costo de envío</p>
                                <p class="text-xs text-gray-500">Calcula la sucursal y tarifa antes de crear el pedido.</p>
                            </div>
                            <button
                                type="button"
                                :disabled="!canPreviewDelivery || deliveryPreviewLoading"
                                @click="calculateDelivery"
                                class="flex items-center gap-2 border-2 border-[#FF5722] text-[#FF5722] hover:bg-orange-50 px-4 py-2 rounded-xl text-sm font-bold transition disabled:opacity-40 disabled:cursor-not-allowed"
                            >
                                <span
                                    class="material-symbols-outlined text-lg"
                                    :class="deliveryPreviewLoading ? 'animate-spin' : ''"
                                >{{ deliveryPreviewLoading ? 'progress_activity' : 'route' }}</span>
                                {{ deliveryPreviewLoading ? 'Calculando...' : (deliveryPreview ? 'Recalcular envío' : 'Calcular envío') }}
                            </button>
                        </div>

                        <!-- Preview result card -->
                        <div
                            v-if="deliveryPreview && deliveryPreview.in_coverage"
                            class="bg-orange-50 border border-orange-200 rounded-xl px-4 py-3 grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm"
                        >
                            <div>
                                <p class="text-[10px] font-bold text-orange-700 uppercase tracking-wide">Sucursal</p>
                                <p class="text-gray-900 font-semibold truncate">{{ deliveryPreview.branch.name }}</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-orange-700 uppercase tracking-wide">Distancia</p>
                                <p class="text-gray-900 font-semibold">{{ deliveryPreview.distance_km }} km</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-orange-700 uppercase tracking-wide">ETA</p>
                                <p class="text-gray-900 font-semibold">~{{ deliveryPreview.duration_minutes }} min</p>
                            </div>
                            <div>
                                <p class="text-[10px] font-bold text-orange-700 uppercase tracking-wide">Envío</p>
                                <p class="text-[#FF5722] font-bold">{{ formatPrice(deliveryPreview.delivery_cost) }}</p>
                            </div>
                        </div>

                        <!-- Out-of-coverage / error -->
                        <div
                            v-else-if="deliveryPreviewError"
                            class="flex items-start gap-2 text-sm text-red-700 bg-red-50 border border-red-200 px-3 py-2 rounded-xl"
                        >
                            <span class="material-symbols-outlined text-base shrink-0 mt-0.5">error</span>
                            <span>{{ deliveryPreviewError }}</span>
                        </div>

                        <!-- Hint -->
                        <p v-else-if="!canPreviewDelivery" class="text-xs text-gray-400 flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">info</span>
                            Captura calle, número, colonia y selecciona el pin en el mapa para habilitar el cálculo.
                        </p>
                    </div>
                </div>

                <!-- Items -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#FF5722]">list_alt</span>
                            Productos
                        </h3>
                        <button
                            type="button"
                            @click="openAddModal"
                            class="flex items-center gap-1 text-sm font-semibold text-[#FF5722] hover:text-[#D84315] transition-colors"
                        >
                            <span class="material-symbols-outlined text-lg">add_circle</span>
                            Agregar producto
                        </button>
                    </div>

                    <div class="divide-y divide-dashed divide-gray-200">
                        <div v-for="(item, index) in localItems" :key="index" class="py-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span v-if="item.promotion_id" class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Promo</span>
                                        <h4 class="font-bold text-gray-900 truncate">{{ item.product_name }}</h4>
                                    </div>
                                    <div class="flex items-center gap-3 text-sm text-gray-500">
                                        <span>{{ formatPrice(item.unit_price) }} c/u</span>
                                        <span class="font-bold text-gray-900">{{ formatPrice(itemTotal(item)) }}</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 shrink-0">
                                    <div class="flex items-center border border-gray-200 rounded-lg">
                                        <button type="button" @click="item.quantity > 1 ? item.quantity-- : null" class="px-2 py-1 text-gray-500 hover:text-gray-700" :disabled="item.quantity <= 1">
                                            <span class="material-symbols-outlined text-lg">remove</span>
                                        </button>
                                        <input v-model.number="item.quantity" type="number" min="1" max="100" class="w-12 text-center text-sm font-bold border-x border-gray-200 py-1 focus:outline-none" />
                                        <button type="button" @click="item.quantity < 100 ? item.quantity++ : null" class="px-2 py-1 text-gray-500 hover:text-gray-700">
                                            <span class="material-symbols-outlined text-lg">add</span>
                                        </button>
                                    </div>
                                    <button type="button" @click="removeItem(index)" class="p-2 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50" title="Eliminar">
                                        <span class="material-symbols-outlined text-lg">delete</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Modifiers (interactive) -->
                            <div v-if="getModifierGroups(item).length" class="mt-3 space-y-3">
                                <div v-for="group in getModifierGroups(item)" :key="groupKey(group)" class="bg-gray-50 rounded-lg px-3.5 py-3 border border-gray-100">
                                    <div class="flex items-center justify-between mb-2">
                                        <p class="text-xs font-bold text-gray-500 uppercase tracking-wide flex items-center gap-1">
                                            {{ group.name }}
                                            <span v-if="group.is_required" class="text-red-400">*</span>
                                            <span v-if="group.source === 'catalog'" class="text-indigo-400 font-medium normal-case text-[10px] bg-indigo-50 px-1.5 py-0.5 rounded ml-1">Catálogo</span>
                                        </p>
                                        <span class="text-[10px] text-gray-400 font-medium">
                                            <template v-if="group.selection_type === 'single'">Elige una</template>
                                            <template v-else-if="group.max_selections">{{ groupSelectionCount(item, group) }}/{{ group.max_selections }}</template>
                                            <template v-else>Múltiple</template>
                                        </span>
                                    </div>
                                    <div class="space-y-1">
                                        <button
                                            v-for="option in group.options"
                                            :key="(group.source || 'inline') + '_' + option.id"
                                            type="button"
                                            @click="toggleModifier(item, option, group)"
                                            :disabled="isOptionDisabled(item, option, group)"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg border text-left transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                            :class="isModifierSelected(item, option, group) ? 'border-[#FF5722] bg-orange-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                                        >
                                            <div
                                                class="w-4 h-4 border-2 flex items-center justify-center shrink-0 transition-colors"
                                                :class="[
                                                    isMultiple(group) ? 'rounded' : 'rounded-full',
                                                    isModifierSelected(item, option, group) ? 'border-[#FF5722] bg-[#FF5722]' : 'border-gray-300',
                                                ]"
                                            >
                                                <span v-if="isModifierSelected(item, option, group)" class="material-symbols-outlined text-white text-[10px]">check</span>
                                            </div>
                                            <span class="text-sm text-gray-800 flex-1">{{ option.name }}</span>
                                            <span v-if="parseFloat(option.price_adjustment) > 0" class="text-xs text-gray-500 shrink-0">+{{ formatPrice(option.price_adjustment) }}</span>
                                            <span v-else class="text-xs text-gray-400 shrink-0">Sin costo</span>
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <input
                                v-model="item.notes"
                                type="text"
                                placeholder="Nota del item..."
                                maxlength="255"
                                class="mt-3 w-full border border-gray-100 rounded-lg px-3 py-1.5 text-xs text-gray-600 focus:outline-none focus:ring-1 focus:ring-[#FF5722]/30 bg-gray-50"
                            />
                        </div>

                        <div v-if="localItems.length === 0" class="py-12 text-center">
                            <span class="material-symbols-outlined text-5xl text-gray-200" aria-hidden="true">restaurant_menu</span>
                            <p class="text-sm text-gray-400 mt-2">Aún no agregas productos.</p>
                            <button type="button" @click="openAddModal" class="mt-3 inline-flex items-center gap-1 text-sm font-semibold text-[#FF5722] hover:text-[#D84315]">
                                <span class="material-symbols-outlined text-base">add_circle</span>
                                Agregar primer producto
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right sidebar -->
            <div class="flex flex-col gap-6">

                <!-- Payment -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">payments</span>
                        Método de pago
                    </h3>
                    <div v-if="activePaymentTypes.length === 0" class="text-sm text-amber-700 bg-amber-50 px-3 py-2 rounded-lg">
                        No tienes ningún método de pago activo. Configúralos en Ajustes.
                    </div>
                    <div v-else class="space-y-2">
                        <label
                            v-for="pm in activePaymentTypes"
                            :key="pm"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all"
                            :class="paymentMethod === pm ? 'border-[#FF5722] bg-orange-50' : 'border-gray-100 bg-gray-50 hover:border-gray-200'"
                        >
                            <input type="radio" name="payment_method" :value="pm" v-model="paymentMethod" class="accent-[#FF5722]" />
                            <span class="material-symbols-outlined text-lg" :class="paymentMethod === pm ? 'text-[#FF5722]' : 'text-gray-400'">{{ PAYMENT_ICONS[pm] }}</span>
                            <span class="text-sm font-medium" :class="paymentMethod === pm ? 'text-[#FF5722]' : 'text-gray-700'">{{ PAYMENT_LABELS[pm] }}</span>
                        </label>
                    </div>
                    <div v-if="paymentMethod === 'cash'" class="mt-4">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Paga con (opcional)</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                            <input v-model.number="cashAmount" type="number" min="0.01" step="0.01" placeholder="0.00" class="w-full pl-7 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
                    </div>
                </div>

                <!-- Extras -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6 space-y-4">
                    <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">tune</span>
                        Opciones
                    </h3>
                    <label class="flex items-center gap-3 cursor-pointer">
                        <input type="checkbox" v-model="requiresInvoice" class="accent-[#FF5722] size-4" />
                        <span class="text-sm text-gray-700">Requiere factura</span>
                    </label>
                    <div>
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Programar para (opcional)</label>
                        <DateTimePicker v-model="scheduledAt" placeholder="Pedido inmediato" />
                    </div>
                </div>

                <!-- Summary -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">receipt</span>
                        Resumen
                    </h3>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-500">Productos</span>
                            <span class="font-medium text-gray-900">{{ localItems.length }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Subtotal</span>
                            <span class="font-medium text-gray-900">{{ formatPrice(subtotal) }}</span>
                        </div>
                        <template v-if="deliveryType === 'delivery'">
                            <div v-if="deliveryPreview && deliveryPreview.in_coverage" class="flex justify-between">
                                <span class="text-gray-500">Envío</span>
                                <span class="font-medium text-gray-900">{{ formatPrice(deliveryCost) }}</span>
                            </div>
                            <div v-else class="flex justify-between text-xs italic">
                                <span class="text-gray-400">Envío</span>
                                <span class="text-gray-400">por calcular</span>
                            </div>
                        </template>
                        <div class="flex justify-between text-base font-black text-gray-900 border-t border-gray-100 pt-2 mt-2">
                            <span>Total</span>
                            <span>{{ formatPrice(total) }}</span>
                        </div>
                    </div>
                    <button
                        type="button"
                        :disabled="submitting || limitBlocked"
                        @click="submit"
                        class="w-full mt-5 flex items-center justify-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white py-3 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="material-symbols-outlined text-lg">add_shopping_cart</span>
                        {{ submitting ? 'Creando...' : 'Crear pedido' }}
                    </button>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- Add product modal                                                  -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="showAddModal = false"></div>

                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                        <div class="flex items-center gap-3">
                            <button v-if="addingProduct" @click="addingProduct = null" class="text-gray-400 hover:text-gray-600">
                                <span class="material-symbols-outlined text-xl">arrow_back</span>
                            </button>
                            <h2 class="text-lg font-bold text-gray-900">
                                {{ addingProduct ? addingProduct.name : 'Agregar producto' }}
                            </h2>
                        </div>
                        <button @click="showAddModal = false" class="text-gray-400 hover:text-gray-600">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <!-- Product list -->
                    <div v-if="!addingProduct" class="flex-1 overflow-y-auto">
                        <div class="px-6 py-3 sticky top-0 bg-white z-10 border-b border-gray-50">
                            <input
                                v-model="searchQuery"
                                type="text"
                                placeholder="Buscar producto o promoción..."
                                class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                            />
                        </div>
                        <div class="px-6 py-3">
                            <template v-for="(products, catName) in groupedFilteredProducts" :key="catName">
                                <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2 mt-3 first:mt-0">{{ catName }}</p>
                                <button
                                    v-for="product in products"
                                    :key="product.type + '-' + product.id"
                                    type="button"
                                    @click="selectProductToAdd(product)"
                                    class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg hover:bg-gray-50 transition-colors text-left mb-1"
                                >
                                    <div class="flex items-center gap-2 min-w-0">
                                        <span class="text-sm font-medium text-gray-900 truncate">{{ product.name }}</span>
                                        <span v-if="product.type === 'promotion'" class="text-[10px] font-semibold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded-full shrink-0">Promo</span>
                                        <span v-if="(product.modifier_groups ?? []).length" class="material-symbols-outlined text-gray-300 text-sm shrink-0" title="Tiene modificadores">tune</span>
                                    </div>
                                    <span class="text-sm font-bold text-gray-700 shrink-0 ml-3">{{ formatPrice(product.price) }}</span>
                                </button>
                            </template>
                            <div v-if="Object.keys(groupedFilteredProducts).length === 0" class="text-sm text-gray-400 text-center py-8">
                                No se encontraron productos
                            </div>
                        </div>
                    </div>

                    <!-- Modifier configuration -->
                    <div v-else class="flex-1 overflow-y-auto px-6 py-4">
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-sm text-gray-500">{{ formatPrice(addingProduct.price) }} c/u</p>
                            <span v-if="addingProduct.type === 'promotion'" class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Promo</span>
                        </div>

                        <div v-for="group in addingProduct.modifier_groups" :key="groupKey(group)" class="mb-5">
                            <div class="flex items-center justify-between mb-2.5">
                                <h3 class="text-sm font-bold text-gray-700 flex items-center gap-1">
                                    {{ group.name }}
                                    <span v-if="group.is_required" class="text-red-400 text-xs">*</span>
                                    <span v-if="group.source === 'catalog'" class="text-indigo-400 font-medium text-[10px] bg-indigo-50 px-1.5 py-0.5 rounded ml-1">Catálogo</span>
                                </h3>
                                <span class="text-[10px] text-gray-400 font-medium">
                                    <template v-if="group.selection_type === 'single'">Elige una</template>
                                    <template v-else-if="group.max_selections">
                                        <span :class="addModalMaxReached(group) ? 'text-orange-500 font-bold' : ''">
                                            {{ addModalSelections[groupKey(group)]?.size ?? 0 }}/{{ group.max_selections }}
                                        </span>
                                    </template>
                                    <template v-else>Múltiple</template>
                                </span>
                            </div>
                            <div class="space-y-1.5">
                                <button
                                    v-for="option in group.options"
                                    :key="(group.source || 'inline') + '_' + option.id"
                                    type="button"
                                    @click="toggleAddModalOption(group, option.id)"
                                    :disabled="isAddModalDisabled(group, option.id)"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl border text-left transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                    :class="isAddModalSelected(group, option.id) ? 'border-[#FF5722] bg-orange-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                                >
                                    <div
                                        class="w-4 h-4 border-2 flex items-center justify-center shrink-0 transition-colors"
                                        :class="[
                                            isMultiple(group) ? 'rounded' : 'rounded-full',
                                            isAddModalSelected(group, option.id) ? 'border-[#FF5722] bg-[#FF5722]' : 'border-gray-300',
                                        ]"
                                    >
                                        <span v-if="isAddModalSelected(group, option.id)" class="material-symbols-outlined text-white text-[10px]">check</span>
                                    </div>
                                    <span class="text-sm text-gray-800 flex-1">{{ option.name }}</span>
                                    <span v-if="parseFloat(option.price_adjustment) > 0" class="text-xs text-gray-500 shrink-0">+{{ formatPrice(option.price_adjustment) }}</span>
                                    <span v-else class="text-xs text-gray-400 shrink-0">Sin costo</span>
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-4 mt-4 space-y-3">
                            <div class="flex items-center gap-3">
                                <label class="text-xs font-semibold text-gray-500">Cantidad</label>
                                <div class="flex items-center border border-gray-200 rounded-lg">
                                    <button type="button" @click="addModalQuantity > 1 ? addModalQuantity-- : null" class="px-2 py-1 text-gray-500 hover:text-gray-700" :disabled="addModalQuantity <= 1">
                                        <span class="material-symbols-outlined text-lg">remove</span>
                                    </button>
                                    <input v-model.number="addModalQuantity" type="number" min="1" max="100" class="w-12 text-center text-sm font-bold border-x border-gray-200 py-1 focus:outline-none" />
                                    <button type="button" @click="addModalQuantity < 100 ? addModalQuantity++ : null" class="px-2 py-1 text-gray-500 hover:text-gray-700">
                                        <span class="material-symbols-outlined text-lg">add</span>
                                    </button>
                                </div>
                            </div>
                            <input v-model="addModalNotes" type="text" placeholder="Nota del item (opcional)" maxlength="255" class="w-full border border-gray-100 rounded-lg px-3 py-1.5 text-xs text-gray-600 focus:outline-none focus:ring-1 focus:ring-[#FF5722]/30 bg-gray-50" />
                        </div>
                    </div>

                    <div v-if="addingProduct" class="px-6 py-4 border-t border-gray-100 shrink-0 bg-gray-50">
                        <button
                            type="button"
                            @click="confirmAddProduct"
                            :disabled="!addModalValid"
                            class="w-full flex items-center justify-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white py-3 rounded-xl text-sm font-bold transition disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            Agregar · {{ formatPrice(addModalTotal) }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
