<script setup>
import AppLayout from '@/Layouts/AppLayout.vue'
import DateTimePicker from '@/Components/DateTimePicker.vue'
import { Link, useForm } from '@inertiajs/vue3'

const form = useForm({
    code: '',
    discount_type: 'fixed',
    discount_value: '',
    max_discount: '',
    min_purchase: '',
    starts_at: '',
    ends_at: '',
    max_uses_per_customer: '',
    max_total_uses: '',
    is_active: true,
})

function onCodeInput(e) {
    form.code = e.target.value.toUpperCase()
}

function submit() {
    form.post(route('coupons.store'))
}
</script>

<template>
    <AppLayout title="Nuevo cupón">
        <div class="max-w-2xl">
            <!-- Header -->
            <div class="flex items-center gap-3 mb-6">
                <Link :href="route('coupons.index')" class="p-2 rounded-xl hover:bg-gray-100 transition-colors">
                    <span class="material-symbols-outlined text-gray-500">arrow_back</span>
                </Link>
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Nuevo cupón</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Crea un cupón de descuento para tus clientes</p>
                </div>
            </div>

            <form @submit.prevent="submit" class="space-y-6">
                <!-- Code -->
                <div class="bg-white rounded-xl border border-gray-100 p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Información del cupón</h2>

                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Código del cupón</label>
                        <input
                            type="text"
                            :value="form.code"
                            @input="onCodeInput"
                            maxlength="20"
                            placeholder="Ej: PROMO20, BIENVENIDO"
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm font-mono uppercase placeholder:normal-case focus:outline-none focus:ring-2 focus:ring-[#FF5722]/20 focus:border-[#FF5722]"
                        />
                        <p v-if="form.errors.code" class="text-red-500 text-xs mt-1">{{ form.errors.code }}</p>
                    </div>

                    <!-- Discount type -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-2">Tipo de descuento</label>
                        <div class="flex gap-3">
                            <label
                                class="flex-1 flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                                :class="form.discount_type === 'fixed' ? 'border-[#FF5722] bg-orange-50' : 'border-gray-200 hover:bg-gray-50'"
                            >
                                <input type="radio" v-model="form.discount_type" value="fixed" class="sr-only" />
                                <span class="material-symbols-outlined text-lg" :class="form.discount_type === 'fixed' ? 'text-[#FF5722]' : 'text-gray-400'">payments</span>
                                <span class="text-sm font-medium" :class="form.discount_type === 'fixed' ? 'text-[#FF5722]' : 'text-gray-600'">Monto fijo ($)</span>
                            </label>
                            <label
                                class="flex-1 flex items-center gap-3 px-4 py-3 rounded-xl border cursor-pointer transition-colors"
                                :class="form.discount_type === 'percentage' ? 'border-[#FF5722] bg-orange-50' : 'border-gray-200 hover:bg-gray-50'"
                            >
                                <input type="radio" v-model="form.discount_type" value="percentage" class="sr-only" />
                                <span class="material-symbols-outlined text-lg" :class="form.discount_type === 'percentage' ? 'text-[#FF5722]' : 'text-gray-400'">percent</span>
                                <span class="text-sm font-medium" :class="form.discount_type === 'percentage' ? 'text-[#FF5722]' : 'text-gray-600'">Porcentaje (%)</span>
                            </label>
                        </div>
                        <p v-if="form.errors.discount_type" class="text-red-500 text-xs mt-1">{{ form.errors.discount_type }}</p>
                    </div>

                    <!-- Discount value -->
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">
                                {{ form.discount_type === 'fixed' ? 'Monto de descuento ($)' : 'Porcentaje de descuento (%)' }}
                            </label>
                            <input
                                type="number"
                                v-model="form.discount_value"
                                step="0.01"
                                min="0.01"
                                :max="form.discount_type === 'percentage' ? 100 : 99999.99"
                                placeholder="0.00"
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/20 focus:border-[#FF5722]"
                            />
                            <p v-if="form.errors.discount_value" class="text-red-500 text-xs mt-1">{{ form.errors.discount_value }}</p>
                        </div>

                        <div v-if="form.discount_type === 'percentage'">
                            <label class="block text-sm font-medium text-gray-700 mb-1">Descuento máximo ($)</label>
                            <input
                                type="number"
                                v-model="form.max_discount"
                                step="0.01"
                                min="0.01"
                                placeholder="Sin límite"
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/20 focus:border-[#FF5722]"
                            />
                            <p v-if="form.errors.max_discount" class="text-red-500 text-xs mt-1">{{ form.errors.max_discount }}</p>
                        </div>
                    </div>

                    <!-- Min purchase -->
                    <div>
                        <label class="block text-sm font-medium text-gray-700 mb-1">Compra mínima ($)</label>
                        <input
                            type="number"
                            v-model="form.min_purchase"
                            step="0.01"
                            min="0"
                            placeholder="Sin mínimo"
                            class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/20 focus:border-[#FF5722]"
                        />
                        <p v-if="form.errors.min_purchase" class="text-red-500 text-xs mt-1">{{ form.errors.min_purchase }}</p>
                    </div>
                </div>

                <!-- Validity & Limits -->
                <div class="bg-white rounded-xl border border-gray-100 p-6 space-y-5">
                    <h2 class="text-sm font-semibold text-gray-900 uppercase tracking-wider">Vigencia y límites</h2>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de inicio</label>
                            <DateTimePicker v-model="form.starts_at" placeholder="Sin fecha de inicio" :has-error="!!form.errors.starts_at" />
                            <p v-if="form.errors.starts_at" class="text-red-500 text-xs mt-1">{{ form.errors.starts_at }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Fecha de fin</label>
                            <DateTimePicker v-model="form.ends_at" placeholder="Sin fecha de fin" :has-error="!!form.errors.ends_at" />
                            <p v-if="form.errors.ends_at" class="text-red-500 text-xs mt-1">{{ form.errors.ends_at }}</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Máx. usos por cliente</label>
                            <input
                                type="number"
                                v-model="form.max_uses_per_customer"
                                min="1"
                                step="1"
                                placeholder="Sin límite"
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/20 focus:border-[#FF5722]"
                            />
                            <p v-if="form.errors.max_uses_per_customer" class="text-red-500 text-xs mt-1">{{ form.errors.max_uses_per_customer }}</p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-700 mb-1">Máx. usos totales</label>
                            <input
                                type="number"
                                v-model="form.max_total_uses"
                                min="1"
                                step="1"
                                placeholder="Sin límite"
                                class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/20 focus:border-[#FF5722]"
                            />
                            <p v-if="form.errors.max_total_uses" class="text-red-500 text-xs mt-1">{{ form.errors.max_total_uses }}</p>
                        </div>
                    </div>

                    <!-- Active toggle -->
                    <div class="flex items-center justify-between pt-2">
                        <div>
                            <p class="text-sm font-medium text-gray-700">Cupón activo</p>
                            <p class="text-xs text-gray-400">Solo los cupones activos pueden ser usados por clientes</p>
                        </div>
                        <button
                            type="button"
                            @click="form.is_active = !form.is_active"
                            class="relative inline-flex h-6 w-11 items-center rounded-full transition-colors"
                            :class="form.is_active ? 'bg-[#FF5722]' : 'bg-gray-300'"
                        >
                            <span class="inline-block h-4 w-4 rounded-full bg-white transition-transform" :class="form.is_active ? 'translate-x-6' : 'translate-x-1'" />
                        </button>
                    </div>
                </div>

                <!-- Actions -->
                <div class="flex items-center gap-3">
                    <Link :href="route('coupons.index')" class="px-6 py-2.5 rounded-xl text-sm font-semibold text-gray-700 bg-gray-100 hover:bg-gray-200 transition-colors">
                        Cancelar
                    </Link>
                    <button
                        type="submit"
                        :disabled="form.processing"
                        class="px-6 py-2.5 rounded-xl text-sm font-semibold text-white bg-[#FF5722] hover:bg-[#E64A19] transition-colors disabled:opacity-50"
                    >
                        Crear cupón
                    </button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>
