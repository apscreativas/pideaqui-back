<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import Pagination from '@/Components/Pagination.vue'
import { Link, router } from '@inertiajs/vue3'
import { ref, computed } from 'vue'

const props = defineProps({
    coupons: { type: Object, required: true },
    stats: { type: Object, default: () => ({ total: 0, active: 0, expired: 0 }) },
    filters: { type: Object, default: () => ({ per_page: 20 }) },
})

const showDeleteModal = ref(false)
const couponToDelete = ref(null)

const rows = computed(() => props.coupons?.data ?? [])

function navigate(params = {}) {
    router.get(route('coupons.index'), {
        per_page: props.filters.per_page,
        ...params,
    }, { preserveState: true, preserveScroll: true, replace: true })
}

function isExpired(coupon) {
    return coupon.ends_at && new Date(coupon.ends_at) < new Date()
}

function isFuture(coupon) {
    return coupon.starts_at && new Date(coupon.starts_at) > new Date()
}

function statusBadge(coupon) {
    if (!coupon.is_active) return { label: 'Inactivo', class: 'bg-gray-100 text-gray-600' }
    if (isExpired(coupon)) return { label: 'Expirado', class: 'bg-red-50 text-red-600' }
    if (isFuture(coupon)) return { label: 'Programado', class: 'bg-blue-50 text-blue-600' }
    return { label: 'Activo', class: 'bg-green-50 text-green-600' }
}

function formatDiscount(coupon) {
    if (coupon.discount_type === 'fixed') {
        return `$${parseFloat(coupon.discount_value).toFixed(2)}`
    }
    let text = `${parseFloat(coupon.discount_value)}%`
    if (coupon.max_discount) {
        text += ` (máx. $${parseFloat(coupon.max_discount).toFixed(2)})`
    }
    return text
}

function formatType(coupon) {
    return coupon.discount_type === 'fixed' ? 'Monto fijo' : 'Porcentaje'
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('es-MX', { day: '2-digit', month: 'short', year: 'numeric' })
}

function formatUses(coupon) {
    const used = coupon.uses_count ?? 0
    const max = coupon.max_total_uses
    return max ? `${used}/${max}` : `${used}/∞`
}

function toggleActive(coupon) {
    router.patch(route('coupons.toggle-active', coupon.id), {}, { preserveScroll: true })
}

function confirmDelete(coupon) {
    couponToDelete.value = coupon
    showDeleteModal.value = true
}

function deleteCoupon() {
    router.delete(route('coupons.destroy', couponToDelete.value.id), { preserveScroll: true })
    showDeleteModal.value = false
    couponToDelete.value = null
}
</script>

<template>
    <AppLayout title="Cupones">
        <!-- Header -->
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Cupones</h1>
                <p class="text-sm text-gray-500 mt-1">Gestiona los cupones de descuento de tu restaurante</p>
            </div>
            <Link
                :href="route('coupons.create')"
                class="inline-flex items-center gap-2 bg-[#FF5722] text-white px-5 py-2.5 rounded-xl text-sm font-semibold hover:bg-[#E64A19] transition-colors"
            >
                <span class="material-symbols-outlined text-xl">add</span>
                Nuevo cupón
            </Link>
        </div>

        <!-- Stats -->
        <div class="grid grid-cols-3 gap-4 mb-6">
            <div class="bg-white rounded-xl border border-gray-100 p-4">
                <p class="text-sm text-gray-500">Total</p>
                <p class="text-2xl font-bold text-gray-900">{{ stats.total }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4">
                <p class="text-sm text-gray-500">Activos</p>
                <p class="text-2xl font-bold text-green-600">{{ stats.active }}</p>
            </div>
            <div class="bg-white rounded-xl border border-gray-100 p-4">
                <p class="text-sm text-gray-500">Expirados</p>
                <p class="text-2xl font-bold text-red-600">{{ stats.expired }}</p>
            </div>
        </div>

        <!-- Table -->
        <div class="bg-white rounded-xl border border-gray-100 overflow-hidden">
            <div v-if="rows.length === 0" class="px-6 py-12 text-center text-gray-400">
                <span class="material-symbols-outlined text-5xl mb-3 block">confirmation_number</span>
                <p class="text-lg font-medium">No hay cupones aún</p>
                <p class="text-sm mt-1">Crea tu primer cupón de descuento.</p>
            </div>

            <table v-else class="w-full">
                <thead class="border-b border-gray-100">
                    <tr class="text-left text-xs font-medium text-gray-500 uppercase tracking-wider">
                        <th class="px-6 py-3">Código</th>
                        <th class="px-6 py-3">Tipo</th>
                        <th class="px-6 py-3">Valor</th>
                        <th class="px-6 py-3">Compra mín.</th>
                        <th class="px-6 py-3">Vigencia</th>
                        <th class="px-6 py-3">Usos</th>
                        <th class="px-6 py-3">Estado</th>
                        <th class="px-6 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    <tr v-for="coupon in rows" :key="coupon.id" class="hover:bg-gray-50/50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="font-mono font-semibold text-sm text-gray-900 bg-gray-100 px-2 py-1 rounded-lg">
                                {{ coupon.code }}
                            </span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">{{ formatType(coupon) }}</td>
                        <td class="px-6 py-4 text-sm font-medium text-gray-900">{{ formatDiscount(coupon) }}</td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            {{ parseFloat(coupon.min_purchase) > 0 ? `$${parseFloat(coupon.min_purchase).toFixed(2)}` : '—' }}
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600">
                            <div v-if="coupon.starts_at || coupon.ends_at">
                                <span v-if="coupon.starts_at">{{ formatDate(coupon.starts_at) }}</span>
                                <span v-if="coupon.starts_at && coupon.ends_at"> — </span>
                                <span v-if="coupon.ends_at">{{ formatDate(coupon.ends_at) }}</span>
                            </div>
                            <span v-else class="text-gray-400">Sin límite</span>
                        </td>
                        <td class="px-6 py-4 text-sm text-gray-600 font-medium">{{ formatUses(coupon) }}</td>
                        <td class="px-6 py-4">
                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium" :class="statusBadge(coupon).class">
                                {{ statusBadge(coupon).label }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center justify-end gap-1">
                                <button
                                    @click="toggleActive(coupon)"
                                    class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                                    :title="coupon.is_active ? 'Desactivar' : 'Activar'"
                                >
                                    <span class="material-symbols-outlined text-lg" :class="coupon.is_active ? 'text-green-600' : 'text-gray-400'">
                                        {{ coupon.is_active ? 'toggle_on' : 'toggle_off' }}
                                    </span>
                                </button>
                                <Link
                                    :href="route('coupons.edit', coupon.id)"
                                    class="p-1.5 rounded-lg hover:bg-gray-100 transition-colors"
                                    title="Editar"
                                >
                                    <span class="material-symbols-outlined text-lg text-gray-500">edit</span>
                                </Link>
                                <button
                                    @click="confirmDelete(coupon)"
                                    class="p-1.5 rounded-lg hover:bg-red-50 transition-colors"
                                    title="Eliminar"
                                >
                                    <span class="material-symbols-outlined text-lg text-red-500">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>

            <Pagination
                v-if="rows.length > 0"
                :paginator="coupons"
                label="cupones"
                @page="(p) => navigate({ page: p })"
                @per-page="(n) => navigate({ per_page: n, page: 1 })"
            />
        </div>

        <!-- Delete modal -->
        <Teleport to="body">
            <div v-if="showDeleteModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
                <div class="bg-white rounded-2xl shadow-xl p-6 w-full max-w-sm mx-4">
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Eliminar cupón</h3>
                    <p class="text-sm text-gray-600 mb-6">
                        ¿Estás seguro de eliminar el cupón <strong class="font-mono">{{ couponToDelete?.code }}</strong>? Esta acción no se puede deshacer.
                    </p>
                    <div class="flex gap-3">
                        <button
                            @click="showDeleteModal = false"
                            class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors"
                        >
                            Cancelar
                        </button>
                        <button
                            @click="deleteCoupon"
                            class="flex-1 px-4 py-2.5 rounded-xl text-sm font-semibold text-white bg-red-600 hover:bg-red-700 transition-colors"
                        >
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </AppLayout>
</template>
