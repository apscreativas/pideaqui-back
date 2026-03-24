<script setup>
import { Head, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'
import MapPicker from '@/Components/MapPicker.vue'

const props = defineProps({
    order: Object,
    categories: Array,
    promotions: Array,
    paymentMethods: Array,
    mapsKey: { type: String, default: '' },
})

// --- Helpers ---
function formatPrice(value) {
    return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(value)
}
function orderNumber(id) { return '#' + String(id).padStart(4, '0') }

const DELIVERY_LABELS = { delivery: 'Entrega a domicilio', pickup: 'Recoger en sucursal', dine_in: 'En restaurante' }
const PAYMENT_LABELS = { cash: 'Efectivo', terminal: 'Tarjeta / Terminal', transfer: 'Transferencia' }
const PAYMENT_ICONS = { cash: 'payments', terminal: 'credit_card', transfer: 'account_balance' }
const activePaymentTypes = computed(() => props.paymentMethods.map((p) => p.type))
function goBack() { router.visit(route('orders.show', props.order.id)) }

// ─────────────────────────────────────────────────────────────────────────────
// Modifier helpers (shared between existing items and add-product modal)
// ─────────────────────────────────────────────────────────────────────────────
function groupKey(group) { return (group.source || 'inline') + '_' + group.id }
function isMultiple(group) { return group.selection_type === 'multiple' }

// Build flat product lookup
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

function getModifierGroups(item) {
    return findCatalogProduct(item)?.modifier_groups ?? []
}

// ─────────────────────────────────────────────────────────────────────────────
// Recover modifier IDs from order snapshot
// ─────────────────────────────────────────────────────────────────────────────
// order_item_modifiers stores modifier_option_id for inline modifiers and NULL
// for catalog modifiers (template IDs aren't persisted). We match catalog
// modifiers back to the product's modifier groups by name to recover the
// template option ID needed by the backend validation.
function resolveModifier(mod, catalogProduct) {
    // Inline modifier — modifier_option_id is set
    if (mod.modifier_option_id) {
        return {
            modifier_option_id: mod.modifier_option_id,
            modifier_option_template_id: null,
            name: mod.modifier_option_name,
            price_adjustment: parseFloat(mod.price_adjustment),
        }
    }

    // Catalog modifier — find template option by matching name in catalog groups
    const groups = catalogProduct?.modifier_groups ?? []
    for (const g of groups) {
        if (g.source !== 'catalog') { continue }
        const match = g.options.find((o) => o.name === mod.modifier_option_name)
        if (match) {
            return {
                modifier_option_id: null,
                modifier_option_template_id: match.id,
                name: mod.modifier_option_name,
                price_adjustment: parseFloat(mod.price_adjustment),
            }
        }
    }

    // Fallback — should not happen, but keep the modifier visible
    return {
        modifier_option_id: null,
        modifier_option_template_id: null,
        name: mod.modifier_option_name,
        price_adjustment: parseFloat(mod.price_adjustment),
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Local state for existing items
// ─────────────────────────────────────────────────────────────────────────────
const localItems = ref(props.order.items.map((item) => {
    const catalogProduct = allProducts.value.find((p) =>
        item.product_id ? (p.type === 'product' && p.id === item.product_id) : (p.type === 'promotion' && p.id === item.promotion_id)
    )
    return {
        id: item.id,
        product_id: item.product_id,
        promotion_id: item.promotion_id,
        product_name: item.product_name,
        quantity: item.quantity,
        unit_price: parseFloat(item.unit_price),
        notes: item.notes,
        modifiers: (item.modifiers ?? []).map((m) => resolveModifier(m, catalogProduct)),
    }
}))

// --- Address fields ---
const addressStreet = ref(props.order.address_street ?? '')
const addressNumber = ref(props.order.address_number ?? '')
const addressColony = ref(props.order.address_colony ?? '')
const addressReferences = ref(props.order.address_references ?? '')
const latitude = ref(props.order.latitude ? parseFloat(props.order.latitude) : null)
const longitude = ref(props.order.longitude ? parseFloat(props.order.longitude) : null)

// --- Payment ---
const paymentMethod = ref(props.order.payment_method)
const cashAmount = ref(props.order.cash_amount ? parseFloat(props.order.cash_amount) : null)

// ─────────────────────────────────────────────────────────────────────────────
// Toggle modifier on an existing item (pill-button style)
// ─────────────────────────────────────────────────────────────────────────────
function toggleModifier(item, option, group) {
    const isInline = group.source === 'inline'
    const key = isInline ? 'modifier_option_id' : 'modifier_option_template_id'
    const optionId = option.id

    const idx = item.modifiers.findIndex((m) => m[key] === optionId)

    if (idx !== -1) {
        // Removing — but don't allow if group is required and it's the last selection
        if (group.is_required) {
            const groupOptionIds = new Set(group.options.map((o) => o.id))
            const groupSelections = item.modifiers.filter((m) => groupOptionIds.has(m[key]))
            if (groupSelections.length <= 1) { return } // can't remove the last one
        }
        item.modifiers.splice(idx, 1)
        return
    }

    // Single selection — replace
    if (group.selection_type === 'single') {
        const groupOptionIds = new Set(group.options.map((o) => o.id))
        item.modifiers = item.modifiers.filter((m) => !groupOptionIds.has(m[key]))
    }

    // Max selections check
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

function removeItem(index) {
    localItems.value.splice(index, 1)
}

// ─────────────────────────────────────────────────────────────────────────────
// Add product modal — with modifier configuration
// ─────────────────────────────────────────────────────────────────────────────
const showAddModal = ref(false)
const searchQuery = ref('')
const addingProduct = ref(null)          // product being configured (has modifiers)
const addModalSelections = ref({})       // { groupKey: Set() | optionId | null }
const addModalQuantity = ref(1)
const addModalNotes = ref('')

const filteredProducts = computed(() => {
    const q = searchQuery.value.toLowerCase().trim()
    if (!q) { return allProducts.value }
    return allProducts.value.filter((p) => p.name.toLowerCase().includes(q))
})

// Grouped for display
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
        // No modifiers → add directly
        addItemDirectly(product, [], 1, '')
        return
    }

    // Has modifiers → show configuration step
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
        // Single: toggle off only if not required
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
    // Check if same product already exists (only if no modifiers to merge simply)
    const existing = localItems.value.find((i) =>
        product.type === 'product' ? i.product_id === product.id : i.promotion_id === product.id
    )
    if (existing && modifiers.length === 0 && existing.modifiers.length === 0) {
        existing.quantity += quantity
    } else {
        localItems.value.push({
            id: null,
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

// ─────────────────────────────────────────────────────────────────────────────
// Calculations
// ─────────────────────────────────────────────────────────────────────────────
function itemTotal(item) {
    const modTotal = item.modifiers.reduce((s, m) => s + m.price_adjustment, 0)
    return (item.unit_price + modTotal) * item.quantity
}

const newSubtotal = computed(() => localItems.value.reduce((sum, item) => sum + itemTotal(item), 0))
const deliveryCost = parseFloat(props.order.delivery_cost)
const newTotal = computed(() => newSubtotal.value + deliveryCost)
const oldTotal = parseFloat(props.order.total)
const totalChanged = computed(() => Math.abs(newTotal.value - oldTotal) > 0.01)

// ─────────────────────────────────────────────────────────────────────────────
// Track changes
// ─────────────────────────────────────────────────────────────────────────────
const hasItemChanges = computed(() => {
    const orig = props.order.items
    const curr = localItems.value
    if (orig.length !== curr.length) { return true }
    for (let i = 0; i < orig.length; i++) {
        const o = orig[i]; const c = curr[i]
        if (o.product_id !== c.product_id || o.promotion_id !== c.promotion_id) { return true }
        if (o.quantity !== c.quantity) { return true }
        if ((o.notes ?? '') !== (c.notes ?? '')) { return true }
        const modKey = (m) => m.modifier_option_id ? `i:${m.modifier_option_id}` : `c:${m.name}`
        const origMods = (o.modifiers ?? []).map((m) => modKey(m)).sort().join(',')
        const currMods = c.modifiers.map((m) => modKey(m)).sort().join(',')
        if (origMods !== currMods) { return true }
    }
    return false
})

const hasAddressChanges = computed(() =>
    addressStreet.value !== (props.order.address_street ?? '')
    || addressNumber.value !== (props.order.address_number ?? '')
    || addressColony.value !== (props.order.address_colony ?? '')
    || addressReferences.value !== (props.order.address_references ?? '')
    || (latitude.value !== null && Math.abs(latitude.value - parseFloat(props.order.latitude ?? 0)) > 0.00001)
    || (longitude.value !== null && Math.abs(longitude.value - parseFloat(props.order.longitude ?? 0)) > 0.00001)
)

const hasPaymentChanges = computed(() =>
    paymentMethod.value !== props.order.payment_method
    || (paymentMethod.value === 'cash' && parseFloat(cashAmount.value ?? 0) !== parseFloat(props.order.cash_amount ?? 0))
)

const hasAnyChanges = computed(() => hasItemChanges.value || hasAddressChanges.value || hasPaymentChanges.value)

// ─────────────────────────────────────────────────────────────────────────────
// Required modifier validation
// ─────────────────────────────────────────────────────────────────────────────
const missingRequiredModifiers = computed(() => {
    const issues = []
    for (const item of localItems.value) {
        const groups = getModifierGroups(item)
        for (const g of groups) {
            if (!g.is_required) { continue }
            const key = g.source === 'inline' ? 'modifier_option_id' : 'modifier_option_template_id'
            const groupOptionIds = new Set(g.options.map((o) => o.id))
            const hasSelection = item.modifiers.some((m) => groupOptionIds.has(m[key]))
            if (!hasSelection) {
                issues.push({ itemName: item.product_name, groupName: g.name })
            }
        }
    }
    return issues
})

// ─────────────────────────────────────────────────────────────────────────────
// Form submission
// ─────────────────────────────────────────────────────────────────────────────
const showConfirmModal = ref(false)
const editReason = ref('')
const submitting = ref(false)
const conflictError = ref(null)
const validationError = ref(null)

function openConfirmModal() {
    if (localItems.value.length === 0) {
        validationError.value = 'El pedido debe tener al menos un producto.'
        return
    }
    if (missingRequiredModifiers.value.length > 0) {
        const first = missingRequiredModifiers.value[0]
        validationError.value = `El grupo "${first.groupName}" es obligatorio para "${first.itemName}".`
        return
    }
    validationError.value = null
    editReason.value = ''
    conflictError.value = null
    showConfirmModal.value = true
}

function submitEdit() {
    submitting.value = true
    conflictError.value = null

    const data = {
        expected_updated_at: props.order.updated_at,
        reason: editReason.value || null,
    }

    if (hasItemChanges.value) {
        data.items = localItems.value.map((item) => ({
            id: item.id,
            product_id: item.product_id,
            promotion_id: item.promotion_id,
            quantity: item.quantity,
            notes: item.notes,
            modifiers: item.modifiers.map((m) => ({
                modifier_option_id: m.modifier_option_id,
                modifier_option_template_id: m.modifier_option_template_id,
            })),
        }))
    }

    if (hasAddressChanges.value) {
        data.address_street = addressStreet.value
        data.address_number = addressNumber.value
        data.address_colony = addressColony.value
        data.address_references = addressReferences.value
        data.latitude = latitude.value
        data.longitude = longitude.value
    }

    if (hasPaymentChanges.value) {
        data.payment_method = paymentMethod.value
        if (paymentMethod.value === 'cash') {
            data.cash_amount = cashAmount.value
        }
    }

    router.put(route('orders.update', props.order.id), data, {
        onSuccess: () => {
            showConfirmModal.value = false
            submitting.value = false
        },
        onError: (errors) => {
            submitting.value = false
            if (errors.order) { conflictError.value = errors.order }
        },
    })
}
</script>

<template>
    <Head :title="`Editar Pedido ${orderNumber(order.id)}`" />
    <AppLayout :title="`Editar Pedido ${orderNumber(order.id)}`">

        <!-- Breadcrumb -->
        <div class="flex flex-col md:flex-row md:items-start justify-between gap-4 mb-6">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-500">
                    <button @click="goBack" class="hover:text-[#FF5722] flex items-center gap-1 transition-colors">
                        <span class="material-symbols-outlined text-lg" aria-hidden="true">arrow_back</span>
                        Regresar
                    </button>
                    <span class="text-gray-300">/</span>
                    <span class="text-gray-800 font-medium">Editar Pedido {{ orderNumber(order.id) }}</span>
                </div>
                <h2 class="text-2xl font-black text-gray-900 mt-2">Editar Pedido {{ orderNumber(order.id) }}</h2>
                <p class="text-sm text-gray-500 mt-1">
                    <span class="material-symbols-outlined text-sm align-middle mr-1" aria-hidden="true">person</span>
                    {{ order.customer?.name }} · {{ order.customer?.phone }}
                </p>
            </div>
            <div class="flex items-center gap-3">
                <button @click="goBack" class="flex items-center gap-2 border border-gray-200 text-gray-600 hover:bg-gray-50 px-5 py-3 rounded-xl text-sm font-bold transition">
                    Cancelar
                </button>
                <button
                    :disabled="!hasAnyChanges"
                    @click="openConfirmModal"
                    class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-6 py-3 rounded-xl text-sm font-bold shadow-lg shadow-orange-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                >
                    <span class="material-symbols-outlined text-lg">save</span>
                    Guardar cambios
                </button>
            </div>
        </div>

        <!-- Validation error banner -->
        <div v-if="validationError" class="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-200 px-4 py-3 rounded-xl mb-6">
            <span class="material-symbols-outlined text-base shrink-0">error</span>
            {{ validationError }}
            <button @click="validationError = null" class="ml-auto text-red-400 hover:text-red-600"><span class="material-symbols-outlined text-base">close</span></button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

            <!-- Left: Products -->
            <div class="lg:col-span-2 flex flex-col gap-6">

                <!-- Items section -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-4">
                        <h3 class="text-lg font-bold text-gray-900 flex items-center gap-2">
                            <span class="material-symbols-outlined text-[#FF5722]">list_alt</span>
                            Productos
                        </h3>
                        <button @click="openAddModal" class="flex items-center gap-1 text-sm font-semibold text-[#FF5722] hover:text-[#D84315] transition-colors">
                            <span class="material-symbols-outlined text-lg">add_circle</span>
                            Agregar producto
                        </button>
                    </div>

                    <!-- Current items -->
                    <div class="divide-y divide-dashed divide-gray-200">
                        <div v-for="(item, index) in localItems" :key="index" class="py-4">
                            <!-- Item header row -->
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
                                    <!-- Quantity -->
                                    <div class="flex items-center border border-gray-200 rounded-lg">
                                        <button @click="item.quantity > 1 ? item.quantity-- : null" class="px-2 py-1 text-gray-500 hover:text-gray-700 transition-colors" :disabled="item.quantity <= 1">
                                            <span class="material-symbols-outlined text-lg">remove</span>
                                        </button>
                                        <input v-model.number="item.quantity" type="number" min="1" max="100" class="w-12 text-center text-sm font-bold border-x border-gray-200 py-1 focus:outline-none" />
                                        <button @click="item.quantity < 100 ? item.quantity++ : null" class="px-2 py-1 text-gray-500 hover:text-gray-700 transition-colors">
                                            <span class="material-symbols-outlined text-lg">add</span>
                                        </button>
                                    </div>
                                    <!-- Remove -->
                                    <button @click="removeItem(index)" class="p-2 text-gray-400 hover:text-red-500 transition-colors rounded-lg hover:bg-red-50" title="Eliminar">
                                        <span class="material-symbols-outlined text-lg">delete</span>
                                    </button>
                                </div>
                            </div>

                            <!-- Modifier groups — always visible, interactive -->
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
                                            @click="toggleModifier(item, option, group)"
                                            :disabled="isOptionDisabled(item, option, group)"
                                            class="w-full flex items-center gap-2.5 px-3 py-2 rounded-lg border text-left transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                            :class="isModifierSelected(item, option, group)
                                                ? 'border-[#FF5722] bg-orange-50'
                                                : 'border-gray-200 bg-white hover:border-gray-300'"
                                        >
                                            <!-- Radio/checkbox indicator -->
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

                            <!-- Notes -->
                            <input
                                v-model="item.notes"
                                type="text"
                                placeholder="Nota del item..."
                                class="mt-3 w-full border border-gray-100 rounded-lg px-3 py-1.5 text-xs text-gray-600 focus:outline-none focus:ring-1 focus:ring-[#FF5722]/30 bg-gray-50"
                            />
                        </div>

                        <div v-if="localItems.length === 0" class="py-8 text-center text-sm text-gray-400">
                            No hay productos en el pedido. Agrega al menos uno.
                        </div>
                    </div>
                </div>

                <!-- Address section (delivery only) -->
                <div v-if="order.delivery_type === 'delivery'" class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-1 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">location_on</span>
                        Dirección de entrega
                    </h3>
                    <p class="text-xs text-gray-400 mb-4 flex items-center gap-1">
                        <span class="material-symbols-outlined text-sm">info</span>
                        Solo corrección de dirección. El costo de envío no cambiará.
                    </p>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Calle</label>
                            <input v-model="addressStreet" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Número</label>
                            <input v-model="addressNumber" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Colonia</label>
                            <input v-model="addressColony" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Referencias</label>
                            <input v-model="addressReferences" type="text" class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
                    </div>
                    <MapPicker v-model:lat="latitude" v-model:lng="longitude" :maps-key="mapsKey" />
                </div>
            </div>

            <!-- Right sidebar -->
            <div class="flex flex-col gap-6">
                <!-- Immutable info -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">info</span>
                        Datos del pedido
                    </h3>
                    <div class="space-y-3 text-sm">
                        <div class="flex justify-between"><span class="text-gray-500">Cliente</span><span class="font-medium text-gray-900">{{ order.customer?.name }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Teléfono</span><span class="font-medium text-gray-900">{{ order.customer?.phone }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Tipo de entrega</span><span class="font-medium text-gray-900">{{ DELIVERY_LABELS[order.delivery_type] }}</span></div>
                        <div class="flex justify-between"><span class="text-gray-500">Sucursal</span><span class="font-medium text-gray-900">{{ order.branch?.name }}</span></div>
                        <div v-if="order.distance_km" class="flex justify-between"><span class="text-gray-500">Distancia</span><span class="font-medium text-gray-900">{{ parseFloat(order.distance_km).toFixed(2) }} km</span></div>
                    </div>
                </div>

                <!-- Payment section -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h3 class="text-lg font-bold text-gray-900 mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-[#FF5722]">payments</span>
                        Método de pago
                    </h3>
                    <div class="space-y-2">
                        <label
                            v-for="pm in activePaymentTypes" :key="pm"
                            class="flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-all"
                            :class="paymentMethod === pm ? 'border-[#FF5722] bg-orange-50' : 'border-gray-100 bg-gray-50 hover:border-gray-200'"
                        >
                            <input type="radio" name="payment_method" :value="pm" v-model="paymentMethod" class="accent-[#FF5722]" />
                            <span class="material-symbols-outlined text-lg" :class="paymentMethod === pm ? 'text-[#FF5722]' : 'text-gray-400'">{{ PAYMENT_ICONS[pm] }}</span>
                            <span class="text-sm font-medium" :class="paymentMethod === pm ? 'text-[#FF5722]' : 'text-gray-700'">{{ PAYMENT_LABELS[pm] }}</span>
                        </label>
                    </div>
                    <div v-if="paymentMethod === 'cash'" class="mt-4">
                        <label class="block text-xs font-semibold text-gray-500 mb-1">Paga con</label>
                        <div class="relative">
                            <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm">$</span>
                            <input v-model.number="cashAmount" type="number" min="0.01" step="0.01" class="w-full pl-7 pr-3 py-2 border border-gray-200 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50" />
                        </div>
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
                            <span class="text-gray-500">Subtotal</span>
                            <span class="font-medium" :class="hasItemChanges ? 'text-[#FF5722] font-bold' : 'text-gray-900'">{{ formatPrice(newSubtotal) }}</span>
                        </div>
                        <div v-if="order.delivery_type === 'delivery'" class="flex justify-between">
                            <span class="text-gray-500">Costo de envío <span class="text-xs text-gray-400">(fijo)</span></span>
                            <span class="text-gray-900">{{ formatPrice(deliveryCost) }}</span>
                        </div>
                        <div class="flex justify-between text-base font-black text-gray-900 border-t border-gray-100 pt-2 mt-2">
                            <span>Total</span>
                            <span :class="totalChanged ? 'text-[#FF5722]' : ''">{{ formatPrice(newTotal) }}</span>
                        </div>
                        <div v-if="totalChanged" class="flex items-center gap-2 text-xs text-amber-700 bg-amber-50 px-3 py-2 rounded-lg mt-2">
                            <span class="material-symbols-outlined text-sm">compare_arrows</span>
                            Total anterior: {{ formatPrice(oldTotal) }} → Nuevo: {{ formatPrice(newTotal) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- Add Product Modal                                                  -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="showAddModal = false"></div>

                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-lg max-h-[90vh] overflow-hidden flex flex-col">
                    <!-- Modal header -->
                    <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 shrink-0">
                        <div class="flex items-center gap-3">
                            <button v-if="addingProduct" @click="addingProduct = null" class="text-gray-400 hover:text-gray-600 transition-colors">
                                <span class="material-symbols-outlined text-xl">arrow_back</span>
                            </button>
                            <h2 class="text-lg font-bold text-gray-900">
                                {{ addingProduct ? addingProduct.name : 'Agregar producto' }}
                            </h2>
                        </div>
                        <button @click="showAddModal = false" class="text-gray-400 hover:text-gray-600 transition-colors">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>

                    <!-- Modal body — product list -->
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
                                    v-for="product in products" :key="product.type + '-' + product.id"
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

                    <!-- Modal body — modifier configuration -->
                    <div v-else class="flex-1 overflow-y-auto px-6 py-4">
                        <!-- Product info -->
                        <div class="flex items-center justify-between mb-4">
                            <p class="text-sm text-gray-500">{{ formatPrice(addingProduct.price) }} c/u</p>
                            <span v-if="addingProduct.type === 'promotion'" class="text-xs font-semibold text-indigo-600 bg-indigo-50 px-2 py-0.5 rounded-full">Promo</span>
                        </div>

                        <!-- Modifier groups -->
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
                                    @click="toggleAddModalOption(group, option.id)"
                                    :disabled="isAddModalDisabled(group, option.id)"
                                    class="w-full flex items-center gap-2.5 px-3 py-2.5 rounded-xl border text-left transition-all disabled:opacity-40 disabled:cursor-not-allowed"
                                    :class="isAddModalSelected(group, option.id)
                                        ? 'border-[#FF5722] bg-orange-50'
                                        : 'border-gray-200 bg-white hover:border-gray-300'"
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

                        <!-- Quantity & notes for new product -->
                        <div class="border-t border-gray-100 pt-4 mt-4 space-y-3">
                            <div class="flex items-center gap-3">
                                <label class="text-xs font-semibold text-gray-500">Cantidad</label>
                                <div class="flex items-center border border-gray-200 rounded-lg">
                                    <button @click="addModalQuantity > 1 ? addModalQuantity-- : null" class="px-2 py-1 text-gray-500 hover:text-gray-700" :disabled="addModalQuantity <= 1">
                                        <span class="material-symbols-outlined text-lg">remove</span>
                                    </button>
                                    <input v-model.number="addModalQuantity" type="number" min="1" max="100" class="w-12 text-center text-sm font-bold border-x border-gray-200 py-1 focus:outline-none" />
                                    <button @click="addModalQuantity < 100 ? addModalQuantity++ : null" class="px-2 py-1 text-gray-500 hover:text-gray-700">
                                        <span class="material-symbols-outlined text-lg">add</span>
                                    </button>
                                </div>
                            </div>
                            <input v-model="addModalNotes" type="text" placeholder="Nota del item (opcional)" class="w-full border border-gray-100 rounded-lg px-3 py-1.5 text-xs text-gray-600 focus:outline-none focus:ring-1 focus:ring-[#FF5722]/30 bg-gray-50" />
                        </div>
                    </div>

                    <!-- Modal footer — confirm button (modifier config step) -->
                    <div v-if="addingProduct" class="px-6 py-4 border-t border-gray-100 shrink-0 bg-gray-50">
                        <button
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

        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <!-- Confirm changes modal                                              -->
        <!-- ═══════════════════════════════════════════════════════════════════ -->
        <Teleport to="body">
            <div v-if="showConfirmModal" class="fixed inset-0 z-50 flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/40" @click="showConfirmModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md max-h-[90vh] overflow-y-auto">
                    <div class="p-6">
                        <div class="flex items-start justify-between mb-1">
                            <div class="flex items-center gap-3">
                                <div class="flex size-10 items-center justify-center rounded-full bg-orange-100 text-[#FF5722]">
                                    <span class="material-symbols-outlined">edit_note</span>
                                </div>
                                <h2 class="text-xl font-bold text-gray-900">Confirmar cambios</h2>
                            </div>
                            <button @click="showConfirmModal = false" class="text-gray-400 hover:text-gray-600 transition-colors ml-4">
                                <span class="material-symbols-outlined">close</span>
                            </button>
                        </div>
                        <p class="text-sm text-gray-500 mb-4 ml-[52px]">Pedido {{ orderNumber(order.id) }}</p>

                        <div class="space-y-2 mb-4">
                            <div v-if="hasItemChanges" class="flex items-center gap-2 text-sm text-gray-700 bg-gray-50 px-3 py-2 rounded-lg">
                                <span class="material-symbols-outlined text-base text-[#FF5722]">list_alt</span>
                                Productos modificados
                            </div>
                            <div v-if="hasAddressChanges" class="flex items-center gap-2 text-sm text-gray-700 bg-gray-50 px-3 py-2 rounded-lg">
                                <span class="material-symbols-outlined text-base text-[#FF5722]">location_on</span>
                                Dirección actualizada
                            </div>
                            <div v-if="hasPaymentChanges" class="flex items-center gap-2 text-sm text-gray-700 bg-gray-50 px-3 py-2 rounded-lg">
                                <span class="material-symbols-outlined text-base text-[#FF5722]">payments</span>
                                Método de pago cambiado
                            </div>
                            <div v-if="totalChanged" class="flex items-center gap-2 text-sm text-amber-700 bg-amber-50 px-3 py-2 rounded-lg">
                                <span class="material-symbols-outlined text-base">compare_arrows</span>
                                {{ formatPrice(oldTotal) }} → {{ formatPrice(newTotal) }}
                            </div>
                        </div>

                        <div class="flex items-start gap-2 text-sm text-amber-700 bg-amber-50 border border-amber-200 px-3 py-2 rounded-lg mb-4">
                            <span class="material-symbols-outlined text-base mt-0.5 shrink-0">warning</span>
                            <span>El mensaje de WhatsApp ya fue enviado. Comunica los cambios al cliente manualmente.</span>
                        </div>

                        <div class="mb-4">
                            <label class="block text-xs font-semibold text-gray-500 mb-1">Motivo del cambio (opcional)</label>
                            <textarea v-model="editReason" rows="2" placeholder="Ej: Cliente pidió agregar un producto extra..." class="w-full border border-gray-200 rounded-xl px-4 py-3 text-sm resize-none focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30"></textarea>
                        </div>

                        <div v-if="conflictError" class="flex items-center gap-2 text-sm text-red-600 bg-red-50 px-3 py-2 rounded-lg mb-4">
                            <span class="material-symbols-outlined text-base">error</span>
                            {{ conflictError }}
                        </div>

                        <div class="flex gap-3">
                            <button @click="showConfirmModal = false" class="flex-1 border border-gray-200 text-gray-700 font-semibold rounded-xl py-2.5 text-sm hover:bg-gray-50 transition-colors">
                                Volver
                            </button>
                            <button @click="submitEdit" :disabled="submitting" class="flex-1 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl py-2.5 text-sm transition-colors disabled:opacity-50 disabled:cursor-not-allowed">
                                {{ submitting ? 'Guardando...' : 'Confirmar cambios' }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </Teleport>

    </AppLayout>
</template>
