<script setup>
import { Head, router, useForm } from '@inertiajs/vue3'
import { ref, computed } from 'vue'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'

const props = defineProps({
    restaurant: Object,
    admin: Object,
    monthly_orders_count: Number,
    branch_count: Number,
})

const editingLimits = ref(false)
const showToken = ref(false)

const limitsForm = useForm({
    max_monthly_orders: props.restaurant.max_monthly_orders,
    max_branches: props.restaurant.max_branches,
})

const ordersPercent = computed(() =>
    Math.min(100, Math.round((props.monthly_orders_count / props.restaurant.max_monthly_orders) * 100)),
)

const branchesPercent = computed(() =>
    Math.min(100, Math.round((props.branch_count / props.restaurant.max_branches) * 100)),
)

function barClass(percent) {
    if (percent > 90) { return 'bg-red-500' }
    if (percent > 70) { return 'bg-amber-400' }
    return 'bg-green-500'
}

function toggleActive() {
    router.patch(route('super.restaurants.toggle', props.restaurant.id))
}

function saveLimits() {
    limitsForm.put(route('super.restaurants.update-limits', props.restaurant.id), {
        onSuccess: () => { editingLimits.value = false },
    })
}

function copyToken() {
    navigator.clipboard.writeText(props.restaurant.access_token)
}
</script>

<template>
    <Head :title="`SuperAdmin — ${restaurant.name}`" />
    <SuperAdminLayout>
        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                    <a :href="route('super.restaurants.index')" class="hover:text-gray-600">Restaurantes</a>
                    <span>/</span>
                    <span class="text-gray-900 font-medium">{{ restaurant.name }}</span>
                </div>
                <h1 class="text-2xl font-bold text-gray-900">{{ restaurant.name }}</h1>
                <p class="mt-1 text-sm text-gray-500">{{ restaurant.slug }}</p>
            </div>
            <div class="flex items-center gap-3">
                <span
                    class="inline-flex items-center px-3 py-1 rounded-full text-sm font-medium"
                    :class="restaurant.is_active ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500'"
                >
                    {{ restaurant.is_active ? 'Activo' : 'Inactivo' }}
                </span>
                <button
                    @click="toggleActive"
                    class="px-4 py-2 rounded-xl border text-sm font-semibold transition-colors"
                    :class="restaurant.is_active
                        ? 'border-red-200 text-red-600 hover:bg-red-50'
                        : 'border-green-200 text-green-600 hover:bg-green-50'"
                >
                    {{ restaurant.is_active ? 'Desactivar' : 'Activar' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-6">

            <!-- Left column -->
            <div class="col-span-2 space-y-6">

                <!-- Uso del mes -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <h2 class="text-base font-semibold text-gray-900 mb-5">Uso del mes</h2>

                    <div class="space-y-5">
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Pedidos mensuales</span>
                                <span class="text-sm font-bold text-gray-900">{{ monthly_orders_count }} / {{ restaurant.max_monthly_orders }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="barClass(ordersPercent)"
                                    :style="{ width: ordersPercent + '%' }"
                                ></div>
                            </div>
                        </div>

                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <span class="text-sm font-medium text-gray-700">Sucursales</span>
                                <span class="text-sm font-bold text-gray-900">{{ branch_count }} / {{ restaurant.max_branches }}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2.5 overflow-hidden">
                                <div
                                    class="h-full rounded-full transition-all"
                                    :class="barClass(branchesPercent)"
                                    :style="{ width: branchesPercent + '%' }"
                                ></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Límites -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                    <div class="flex items-center justify-between mb-5">
                        <h2 class="text-base font-semibold text-gray-900">Límites del plan</h2>
                        <button
                            v-if="!editingLimits"
                            @click="editingLimits = true"
                            class="text-sm text-[#FF5722] hover:underline font-medium"
                        >Editar</button>
                    </div>

                    <div v-if="!editingLimits" class="grid grid-cols-2 gap-4">
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-xs text-gray-500 mb-1">Pedidos mensuales máx.</p>
                            <p class="text-2xl font-bold text-gray-900">{{ restaurant.max_monthly_orders }}</p>
                        </div>
                        <div class="bg-gray-50 rounded-xl p-4">
                            <p class="text-xs text-gray-500 mb-1">Sucursales máx.</p>
                            <p class="text-2xl font-bold text-gray-900">{{ restaurant.max_branches }}</p>
                        </div>
                    </div>

                    <form v-else @submit.prevent="saveLimits" class="space-y-4">
                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Pedidos mensuales máx.</label>
                                <input
                                    v-model.number="limitsForm.max_monthly_orders"
                                    type="number"
                                    min="1"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="limitsForm.errors.max_monthly_orders" class="text-xs text-red-500 mt-1">{{ limitsForm.errors.max_monthly_orders }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Sucursales máx.</label>
                                <input
                                    v-model.number="limitsForm.max_branches"
                                    type="number"
                                    min="1"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="limitsForm.errors.max_branches" class="text-xs text-red-500 mt-1">{{ limitsForm.errors.max_branches }}</p>
                            </div>
                        </div>
                        <div class="flex gap-3">
                            <button
                                type="submit"
                                :disabled="limitsForm.processing"
                                class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2 text-sm transition-colors disabled:opacity-60"
                            >Guardar</button>
                            <button
                                type="button"
                                @click="editingLimits = false"
                                class="px-5 py-2 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                            >Cancelar</button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right column -->
            <div class="space-y-6">

                <!-- Admin info -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-4">Administrador</h2>
                    <div v-if="admin" class="space-y-2">
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-gray-400 text-lg">person</span>
                            <span class="text-sm text-gray-700">{{ admin.name }}</span>
                        </div>
                        <div class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-gray-400 text-lg">mail</span>
                            <span class="text-sm text-gray-700">{{ admin.email }}</span>
                        </div>
                    </div>
                    <p v-else class="text-sm text-gray-400">Sin administrador asignado.</p>
                </div>

                <!-- Access token -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3">Access Token (API)</h2>
                    <div class="bg-gray-50 rounded-xl p-3 font-mono text-xs text-gray-700 break-all mb-3">
                        {{ showToken ? restaurant.access_token : '••••••••••••••••' }}
                    </div>
                    <div class="flex gap-2">
                        <button
                            @click="showToken = !showToken"
                            class="flex-1 text-xs font-medium px-3 py-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors"
                        >
                            {{ showToken ? 'Ocultar' : 'Mostrar' }}
                        </button>
                        <button
                            @click="copyToken"
                            class="flex-1 text-xs font-medium px-3 py-2 rounded-lg border border-gray-200 text-gray-600 hover:bg-gray-50 transition-colors flex items-center justify-center gap-1"
                        >
                            <span class="material-symbols-outlined text-base">content_copy</span>
                            Copiar
                        </button>
                    </div>
                </div>

                <!-- Metadata -->
                <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5">
                    <h2 class="text-sm font-semibold text-gray-900 mb-3">Información</h2>
                    <div class="space-y-2 text-sm text-gray-600">
                        <div class="flex justify-between">
                            <span class="text-gray-500">ID</span>
                            <span class="font-mono">{{ restaurant.id }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-500">Creado</span>
                            <span>{{ new Date(restaurant.created_at).toLocaleDateString('es-MX') }}</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </SuperAdminLayout>
</template>
