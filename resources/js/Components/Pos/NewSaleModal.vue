<script setup>
import { router } from '@inertiajs/vue3'
import { ref, computed, onMounted, onUnmounted, watch, nextTick } from 'vue'

const props = defineProps({
    show: { type: Boolean, default: false },
    branches: { type: Array, default: () => [] },
    categories: { type: Array, default: () => [] },
    cashier: { type: Object, default: () => ({}) },
    defaultProductImageUrl: { type: String, default: null },
})

// Resolved tile image: product's own image first, then the restaurant's
// default image, then null (renders the placeholder icon). Keeps POS tiles
// visually consistent with the customer SPA's ProductImage fallback.
function tileImageUrl(product) {
    return product.image_url || props.defaultProductImageUrl || null
}

const emit = defineEmits(['close', 'created'])

function fmt(v) { return new Intl.NumberFormat('es-MX', { style: 'currency', currency: 'MXN' }).format(v ?? 0) }

// ─── Form state ───────────────────────────────────────────────────────────
const activeBranchId = ref(null)
const activeCategoryId = ref(null)
const cart = ref([])
const generalNotes = ref('')
const submitting = ref(false)
const submitError = ref(null)

// ─── Lifecycle: reset on open ─────────────────────────────────────────────
watch(() => props.show, (open) => {
    if (open) {
        activeBranchId.value = props.branches[0]?.id ?? null
        activeCategoryId.value = props.categories[0]?.id ?? null
        cart.value = []
        generalNotes.value = ''
        submitError.value = null
        document.body.style.overflow = 'hidden'
    } else {
        document.body.style.overflow = ''
    }
})

onUnmounted(() => { document.body.style.overflow = '' })

// ─── Esc to close (with dirty guard) ──────────────────────────────────────
const isDirty = computed(() => cart.value.length > 0)
const showDirtyGuard = ref(false)

function onEsc(e) {
    if (!props.show || e.key !== 'Escape') { return }
    requestClose()
}

onMounted(() => window.addEventListener('keydown', onEsc))
onUnmounted(() => window.removeEventListener('keydown', onEsc))

function requestClose() {
    if (submitting.value) { return }
    if (isDirty.value) { showDirtyGuard.value = true; return }
    emit('close')
}

function discardAndClose() {
    showDirtyGuard.value = false
    emit('close')
}

// ─── Catalog navigation ───────────────────────────────────────────────────
const activeCategory = computed(() => props.categories.find((c) => c.id === activeCategoryId.value))

// ─── Item modal (modifier configuration) ──────────────────────────────────
const showItemModal = ref(false)
const modalProduct = ref(null)
const modalSelections = ref({})
const modalQuantity = ref(1)
const modalNotes = ref('')

function groupKey(g) { return (g.source || 'inline') + '_' + g.id }
function isMultiple(g) { return g.selection_type === 'multiple' }

function selectProduct(product) {
    const groups = product.modifier_groups ?? []
    if (groups.length === 0) {
        addLine(product, [], 1, '')
        return
    }
    modalProduct.value = product
    modalQuantity.value = 1
    modalNotes.value = ''
    modalSelections.value = {}
    groups.forEach((g) => {
        modalSelections.value[groupKey(g)] = isMultiple(g) ? new Set() : null
    })
    showItemModal.value = true
}

function toggleOption(group, optionId) {
    const key = groupKey(group)
    if (isMultiple(group)) {
        const set = modalSelections.value[key]
        if (set.has(optionId)) {
            set.delete(optionId)
        } else if (!group.max_selections || set.size < group.max_selections) {
            set.add(optionId)
        }
    } else if (modalSelections.value[key] === optionId && !group.is_required) {
        modalSelections.value[key] = null
    } else {
        modalSelections.value[key] = optionId
    }
}

function isOptionSelected(group, optionId) {
    const sel = modalSelections.value[groupKey(group)]
    if (isMultiple(group)) { return sel?.has(optionId) }
    return sel === optionId
}

function isOptionDisabled(group, optionId) {
    if (!isMultiple(group)) { return false }
    if (!group.max_selections) { return false }
    const sel = modalSelections.value[groupKey(group)]
    return sel?.size >= group.max_selections && !sel.has(optionId)
}

const modalValid = computed(() => {
    if (!modalProduct.value) { return false }
    return modalProduct.value.modifier_groups?.every((g) => {
        if (!g.is_required) { return true }
        const sel = modalSelections.value[groupKey(g)]
        return isMultiple(g) ? sel?.size > 0 : sel != null
    }) ?? true
})

const modalSelectedModifiers = computed(() => {
    if (!modalProduct.value) { return [] }
    const result = []
    modalProduct.value.modifier_groups?.forEach((g) => {
        const sel = modalSelections.value[groupKey(g)]
        const source = g.source || 'inline'
        const buildEntry = (opt) => ({
            modifier_option_id: source === 'inline' ? opt.id : null,
            modifier_option_template_id: source === 'catalog' ? opt.id : null,
            name: opt.name,
            price_adjustment: parseFloat(opt.price_adjustment),
        })
        if (isMultiple(g)) {
            sel?.forEach((id) => {
                const opt = g.options.find((o) => o.id === id)
                if (opt) { result.push(buildEntry(opt)) }
            })
        } else if (sel != null) {
            const opt = g.options.find((o) => o.id === sel)
            if (opt) { result.push(buildEntry(opt)) }
        }
    })
    return result
})

const modalLineTotal = computed(() => {
    if (!modalProduct.value) { return 0 }
    const base = parseFloat(modalProduct.value.price)
    const mods = modalSelectedModifiers.value.reduce((s, m) => s + m.price_adjustment, 0)
    return (base + mods) * modalQuantity.value
})

function confirmAdd() {
    if (!modalValid.value) { return }
    addLine(modalProduct.value, modalSelectedModifiers.value, modalQuantity.value, modalNotes.value)
}

function addLine(product, modifiers, quantity, notes) {
    const existing = cart.value.find((l) => l.product.id === product.id && l.modifiers.length === 0 && modifiers.length === 0)
    if (existing) {
        existing.quantity += quantity
    } else {
        cart.value.push({ product, quantity, modifiers, notes: notes || null })
    }
    showItemModal.value = false
    modalProduct.value = null
}

function removeLine(idx) { cart.value.splice(idx, 1) }
function clearCart() { cart.value = []; generalNotes.value = '' }

// ─── Totals ───────────────────────────────────────────────────────────────
const subtotal = computed(() => cart.value.reduce((sum, l) => {
    const mods = l.modifiers.reduce((s, m) => s + parseFloat(m.price_adjustment), 0)
    return sum + (parseFloat(l.product.price) + mods) * l.quantity
}, 0))

// ─── Submit ───────────────────────────────────────────────────────────────
function submit() {
    submitError.value = null
    if (cart.value.length === 0) { submitError.value = 'Agrega al menos un producto.'; return }
    if (!activeBranchId.value) { submitError.value = 'Selecciona la sucursal.'; return }

    submitting.value = true
    const payload = {
        branch_id: activeBranchId.value,
        notes: generalNotes.value || null,
        items: cart.value.map((l) => ({
            product_id: l.product.id,
            quantity: l.quantity,
            unit_price: parseFloat(l.product.price),
            notes: l.notes,
            modifiers: l.modifiers.map((m) => ({
                modifier_option_id: m.modifier_option_id,
                modifier_option_template_id: m.modifier_option_template_id,
                price_adjustment: m.price_adjustment,
            })),
        })),
    }

    router.post(route('pos.sales.store'), payload, {
        preserveScroll: true,
        onSuccess: () => {
            submitting.value = false
            // Build a snapshot for the print confirmation in the parent.
            const snapshot = {
                ticket_number: '',
                created_at: new Date().toISOString(),
                cashier: props.cashier,
                branch: props.branches.find((b) => b.id === activeBranchId.value),
                status: 'preparing',
                total: subtotal.value,
                subtotal: subtotal.value,
                notes: generalNotes.value,
                items: cart.value.map((l) => ({
                    id: Math.random(),
                    product_name: l.product.name,
                    quantity: l.quantity,
                    unit_price: parseFloat(l.product.price),
                    notes: l.notes,
                    modifiers: l.modifiers.map((m) => ({
                        id: Math.random(),
                        modifier_option_name: m.name,
                        price_adjustment: m.price_adjustment,
                    })),
                })),
                payments: [],
            }
            clearCart()
            emit('created', snapshot)
        },
        onError: (errors) => {
            submitting.value = false
            submitError.value = Object.values(errors)[0] || 'No se pudo crear la venta.'
        },
    })
}
</script>

<template>
    <Teleport to="body">
        <div v-if="show" class="fixed inset-0 z-50 bg-white flex flex-col">

            <!-- Top bar -->
            <header class="border-b border-gray-200 px-6 py-3 shrink-0 flex items-center justify-between gap-4">
                <div class="flex items-center gap-3 min-w-0">
                    <div class="size-10 rounded-xl bg-orange-50 text-[#FF5722] flex items-center justify-center shrink-0">
                        <span class="material-symbols-outlined">point_of_sale</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Nueva venta</p>
                        <h2 class="text-base font-bold text-gray-900 truncate">Caja POS</h2>
                    </div>
                </div>

                <!-- Branch + Cashier compact strip (right of header) -->
                <div class="flex items-center gap-3 flex-1 justify-center max-w-2xl">
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-base">store</span>
                        <select v-model="activeBranchId" class="pl-9 pr-8 py-2 bg-white border border-gray-200 rounded-lg text-sm font-semibold text-gray-700 focus:outline-none focus:ring-2 focus:ring-[#FF5722]/30 appearance-none min-w-[180px]">
                            <option v-for="b in branches" :key="b.id" :value="b.id">{{ b.name }}</option>
                        </select>
                        <span class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 material-symbols-outlined text-base pointer-events-none">arrow_drop_down</span>
                    </div>
                    <div class="hidden md:flex items-center gap-1.5 text-sm text-gray-500">
                        <span class="material-symbols-outlined text-base text-gray-400">badge</span>
                        Cajero <span class="font-semibold text-gray-700">{{ cashier.name }}</span>
                    </div>
                </div>

                <button
                    @click="requestClose"
                    type="button"
                    class="flex items-center gap-1.5 border border-gray-200 hover:bg-gray-50 px-4 py-2 rounded-xl text-sm font-semibold transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-[#FF5722]/40"
                    :disabled="submitting"
                >
                    <span class="material-symbols-outlined text-lg">close</span>
                    Cerrar
                </button>
            </header>

            <!-- 3-column body -->
            <div class="flex-1 grid grid-cols-12 gap-4 p-4 min-h-0">

                <!-- Categories sidebar -->
                <aside class="col-span-2 bg-white rounded-xl border border-gray-100 shadow-sm p-3 overflow-y-auto">
                    <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-2 px-2">Categorías</p>
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        type="button"
                        @click="activeCategoryId = cat.id"
                        class="w-full flex items-center justify-between px-3 py-2.5 rounded-lg text-left text-sm font-semibold transition-colors mb-1"
                        :class="activeCategoryId === cat.id ? 'bg-orange-50 text-[#FF5722] border-l-4 border-[#FF5722]' : 'text-gray-700 hover:bg-gray-50'"
                    >
                        <span class="truncate">{{ cat.name }}</span>
                        <span class="text-xs text-gray-400">{{ cat.products.length }}</span>
                    </button>
                    <div v-if="categories.length === 0" class="text-xs text-gray-400 text-center py-6">
                        Sin categorías activas
                    </div>
                </aside>

                <!-- Products grid -->
                <main class="col-span-7 bg-white rounded-xl border border-gray-100 shadow-sm p-4 overflow-y-auto">
                    <div v-if="!activeCategory" class="text-center text-gray-400 py-12">
                        <span class="material-symbols-outlined text-5xl text-gray-200">restaurant_menu</span>
                        <p class="text-sm mt-2">Selecciona una categoría</p>
                    </div>
                    <div v-else>
                        <div class="grid grid-cols-3 lg:grid-cols-4 gap-3">
                            <button
                                v-for="product in activeCategory.products"
                                :key="product.id"
                                type="button"
                                @click="selectProduct(product)"
                                class="bg-white border border-gray-200 hover:border-[#FF5722] hover:shadow-lg hover:shadow-orange-100 rounded-xl p-3 flex flex-col gap-2 transition-all text-left"
                            >
                                <div
                                    class="aspect-square rounded-lg bg-gradient-to-br from-orange-100 to-orange-200 flex items-center justify-center"
                                    :style="tileImageUrl(product) ? `background-image: url(${tileImageUrl(product)}); background-size: cover; background-position: center;` : ''"
                                >
                                    <span v-if="!tileImageUrl(product)" class="material-symbols-outlined text-3xl text-orange-300">restaurant</span>
                                </div>
                                <div class="min-h-[2.5rem] flex flex-col justify-between">
                                    <p class="font-bold text-gray-900 text-sm leading-tight line-clamp-2">{{ product.name }}</p>
                                    <p class="text-[#FF5722] font-black text-sm mt-1">{{ fmt(product.price) }}</p>
                                </div>
                                <span v-if="product.modifier_groups?.length" class="material-symbols-outlined text-gray-300 text-sm" title="Tiene modificadores">tune</span>
                            </button>
                        </div>
                        <div v-if="activeCategory.products.length === 0" class="text-center text-sm text-gray-400 py-12">
                            Sin productos activos en esta categoría
                        </div>
                    </div>
                </main>

                <!-- Cart panel -->
                <aside class="col-span-3 bg-white rounded-xl border border-gray-100 shadow-sm flex flex-col overflow-hidden">
                    <div class="p-4 border-b border-gray-100 shrink-0 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wide">Ticket actual</p>
                            <p class="text-sm font-semibold text-gray-700">{{ cart.length }} producto{{ cart.length !== 1 ? 's' : '' }}</p>
                        </div>
                        <button v-if="cart.length" @click="clearCart" class="text-xs font-medium text-gray-400 hover:text-red-500 transition">Vaciar</button>
                    </div>

                    <div class="flex-1 overflow-y-auto p-3">
                        <div v-if="cart.length === 0" class="text-center text-sm text-gray-400 py-8">
                            <span class="material-symbols-outlined text-4xl text-gray-200">shopping_cart</span>
                            <p class="mt-2">Aún no agregas productos</p>
                        </div>
                        <div v-for="(line, idx) in cart" :key="idx" class="border-b border-dashed border-gray-100 py-2 last:border-0">
                            <div class="flex items-start justify-between gap-2">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-900 truncate">{{ line.product.name }}</p>
                                    <p class="text-xs text-gray-500">{{ fmt(line.product.price) }} c/u</p>
                                </div>
                                <button @click="removeLine(idx)" class="text-gray-300 hover:text-red-500 transition" aria-label="Quitar">
                                    <span class="material-symbols-outlined text-base">close</span>
                                </button>
                            </div>
                            <div v-if="line.modifiers.length" class="mt-1 ml-2 space-y-0.5">
                                <p v-for="(m, mi) in line.modifiers" :key="mi" class="text-[11px] text-gray-500">
                                    + {{ m.name }}<span v-if="m.price_adjustment > 0" class="text-gray-400"> ({{ fmt(m.price_adjustment) }})</span>
                                </p>
                            </div>
                            <p v-if="line.notes" class="text-[11px] text-gray-400 mt-1 italic">📝 {{ line.notes }}</p>
                            <div class="flex items-center justify-between mt-1">
                                <div class="flex items-center border border-gray-200 rounded">
                                    <button @click="line.quantity > 1 ? line.quantity-- : null" class="px-1.5 py-0.5 text-gray-500" :disabled="line.quantity <= 1" aria-label="Restar">
                                        <span class="material-symbols-outlined text-sm">remove</span>
                                    </button>
                                    <input v-model.number="line.quantity" type="number" min="1" max="100" class="w-8 text-center text-xs font-bold border-x border-gray-200 py-0.5 focus:outline-none" />
                                    <button @click="line.quantity < 100 ? line.quantity++ : null" class="px-1.5 py-0.5 text-gray-500" aria-label="Sumar">
                                        <span class="material-symbols-outlined text-sm">add</span>
                                    </button>
                                </div>
                                <span class="text-sm font-bold text-gray-900">
                                    {{ fmt((parseFloat(line.product.price) + line.modifiers.reduce((s,m) => s + m.price_adjustment, 0)) * line.quantity) }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="border-t border-gray-100 p-3 shrink-0">
                        <input
                            v-model="generalNotes"
                            type="text"
                            placeholder="Nota general (opcional)"
                            class="w-full text-xs border border-gray-100 rounded-lg px-2 py-1.5 mb-3 bg-gray-50 focus:outline-none focus:ring-1 focus:ring-[#FF5722]/30"
                        />
                        <div class="flex justify-between items-center mb-1">
                            <span class="text-xs font-bold text-gray-500 uppercase">Total</span>
                            <span class="text-2xl font-black text-gray-900">{{ fmt(subtotal) }}</span>
                        </div>
                    </div>
                </aside>
            </div>

            <!-- Footer -->
            <footer class="border-t border-gray-200 bg-gray-50/60 px-6 py-3 shrink-0 flex items-center justify-between gap-4">
                <p v-if="submitError" class="text-sm text-red-600 bg-red-50 border border-red-200 px-3 py-1.5 rounded-lg">{{ submitError }}</p>
                <p v-else class="text-xs text-gray-500">
                    <span v-if="cart.length">{{ cart.length }} producto{{ cart.length !== 1 ? 's' : '' }} · Total <span class="font-bold text-gray-700">{{ fmt(subtotal) }}</span></span>
                    <span v-else>Selecciona productos del catálogo</span>
                </p>
                <div class="flex items-center gap-3 ml-auto">
                    <button
                        @click="requestClose"
                        type="button"
                        class="border border-gray-200 text-gray-600 hover:bg-white px-5 py-2.5 rounded-xl text-sm font-semibold transition"
                        :disabled="submitting"
                    >
                        Cancelar
                    </button>
                    <button
                        @click="submit"
                        type="button"
                        :disabled="submitting || cart.length === 0"
                        class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white px-6 py-2.5 rounded-xl text-sm font-bold shadow-md shadow-orange-200 transition disabled:opacity-50 disabled:cursor-not-allowed"
                    >
                        <span class="material-symbols-outlined text-lg">add_shopping_cart</span>
                        {{ submitting ? 'Creando…' : 'Crear venta' }}
                    </button>
                </div>
            </footer>

            <!-- Item / Modifier modal -->
            <div v-if="showItemModal && modalProduct" class="fixed inset-0 z-[60] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="showItemModal = false"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md max-h-[90vh] overflow-hidden flex flex-col">
                    <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100 shrink-0">
                        <h3 class="text-lg font-bold text-gray-900">{{ modalProduct.name }}</h3>
                        <button @click="showItemModal = false" class="text-gray-400 hover:text-gray-600">
                            <span class="material-symbols-outlined">close</span>
                        </button>
                    </div>
                    <div class="flex-1 overflow-y-auto p-5">
                        <p class="text-sm text-gray-500 mb-4">{{ fmt(modalProduct.price) }} c/u</p>
                        <div v-for="group in modalProduct.modifier_groups" :key="groupKey(group)" class="mb-4">
                            <div class="flex items-center justify-between mb-2">
                                <p class="text-sm font-bold text-gray-700">
                                    {{ group.name }}
                                    <span v-if="group.is_required" class="text-red-400 text-xs">*</span>
                                    <span v-if="group.source === 'catalog'" class="text-indigo-400 font-medium text-[10px] bg-indigo-50 px-1.5 py-0.5 rounded ml-1">Catálogo</span>
                                </p>
                                <span class="text-[10px] text-gray-400 font-medium">
                                    <template v-if="group.selection_type === 'single'">Elige una</template>
                                    <template v-else-if="group.max_selections">Máx. {{ group.max_selections }}</template>
                                    <template v-else>Múltiple</template>
                                </span>
                            </div>
                            <div class="space-y-1.5">
                                <button
                                    v-for="opt in group.options"
                                    :key="(group.source || 'inline') + '_' + opt.id"
                                    type="button"
                                    @click="toggleOption(group, opt.id)"
                                    :disabled="isOptionDisabled(group, opt.id)"
                                    class="w-full flex items-center gap-2 px-3 py-2 rounded-lg border text-left transition disabled:opacity-40 disabled:cursor-not-allowed"
                                    :class="isOptionSelected(group, opt.id) ? 'border-[#FF5722] bg-orange-50' : 'border-gray-200 bg-white hover:border-gray-300'"
                                >
                                    <div class="w-4 h-4 border-2 flex items-center justify-center shrink-0"
                                        :class="[isMultiple(group) ? 'rounded' : 'rounded-full', isOptionSelected(group, opt.id) ? 'border-[#FF5722] bg-[#FF5722]' : 'border-gray-300']">
                                        <span v-if="isOptionSelected(group, opt.id)" class="material-symbols-outlined text-white text-[10px]">check</span>
                                    </div>
                                    <span class="text-sm flex-1">{{ opt.name }}</span>
                                    <span v-if="parseFloat(opt.price_adjustment) > 0" class="text-xs text-gray-500">+{{ fmt(opt.price_adjustment) }}</span>
                                </button>
                            </div>
                        </div>

                        <div class="border-t border-gray-100 pt-4 mt-4 space-y-3">
                            <div class="flex items-center gap-3">
                                <label class="text-xs font-semibold text-gray-500">Cantidad</label>
                                <div class="flex items-center border border-gray-200 rounded-lg">
                                    <button @click="modalQuantity > 1 ? modalQuantity-- : null" class="px-2 py-1 text-gray-500" :disabled="modalQuantity <= 1">
                                        <span class="material-symbols-outlined text-lg">remove</span>
                                    </button>
                                    <input v-model.number="modalQuantity" type="number" min="1" max="100" class="w-12 text-center text-sm font-bold border-x border-gray-200 py-1 focus:outline-none" />
                                    <button @click="modalQuantity < 100 ? modalQuantity++ : null" class="px-2 py-1 text-gray-500">
                                        <span class="material-symbols-outlined text-lg">add</span>
                                    </button>
                                </div>
                            </div>
                            <input v-model="modalNotes" type="text" placeholder="Nota del item (opcional)" maxlength="255" class="w-full text-xs border border-gray-100 rounded-lg px-2 py-1.5 bg-gray-50 focus:outline-none focus:ring-1 focus:ring-[#FF5722]/30" />
                        </div>
                    </div>
                    <div class="px-5 py-4 border-t border-gray-100 shrink-0 bg-gray-50">
                        <button
                            type="button"
                            @click="confirmAdd"
                            :disabled="!modalValid"
                            class="w-full flex items-center justify-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white py-3 rounded-xl text-sm font-bold transition disabled:opacity-50"
                        >
                            Agregar · {{ fmt(modalLineTotal) }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Dirty guard -->
            <div v-if="showDirtyGuard" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-black/50 backdrop-blur-sm"></div>
                <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
                    <div class="flex justify-center mb-3">
                        <div class="size-12 rounded-full bg-amber-100 text-amber-600 flex items-center justify-center">
                            <span class="material-symbols-outlined text-2xl">warning</span>
                        </div>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-1">¿Descartar la venta?</h3>
                    <p class="text-sm text-gray-500 mb-5">Tienes {{ cart.length }} producto(s) en el carrito. Se perderán si cierras.</p>
                    <div class="grid grid-cols-2 gap-3">
                        <button @click="discardAndClose" class="border border-gray-200 hover:bg-gray-50 py-2.5 rounded-xl text-sm font-semibold">Descartar</button>
                        <button @click="showDirtyGuard = false" class="bg-[#FF5722] hover:bg-[#D84315] text-white py-2.5 rounded-xl text-sm font-bold">Seguir</button>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>
