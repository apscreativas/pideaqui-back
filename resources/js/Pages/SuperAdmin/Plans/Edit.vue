<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3'
import SuperAdminLayout from '@/Layouts/SuperAdminLayout.vue'
import { ref } from 'vue'

const props = defineProps({
    plan: Object,
})

const showAdvanced = ref(false)

const form = useForm({
    name: props.plan.name,
    slug: props.plan.slug,
    description: props.plan.description || '',
    orders_limit: props.plan.orders_limit,
    max_branches: props.plan.max_branches,
    monthly_price: Number(props.plan.monthly_price),
    yearly_price: Number(props.plan.yearly_price),
    sort_order: props.plan.sort_order,
    stripe_product_id: props.plan.stripe_product_id || '',
    stripe_monthly_price_id: props.plan.stripe_monthly_price_id || '',
    stripe_yearly_price_id: props.plan.stripe_yearly_price_id || '',
})

function generateSlug() {
    form.slug = form.name
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/[^a-z0-9\s-]/g, '')
        .trim()
        .replace(/\s+/g, '-')
}

function submit() {
    form.put(route('super.plans.update', props.plan.id))
}

const stripeConnected = props.plan.stripe_product_id && props.plan.stripe_monthly_price_id
</script>

<template>
    <Head :title="`SuperAdmin — ${plan.name}`" />
    <SuperAdminLayout>
        <!-- Header -->
        <div class="mb-8">
            <div class="flex items-center gap-2 text-sm text-gray-400 mb-2">
                <Link :href="route('super.plans.index')" class="hover:text-gray-600 transition-colors">Planes</Link>
                <span class="material-symbols-outlined text-xs">chevron_right</span>
                <span class="text-gray-600">{{ plan.name }}</span>
            </div>
            <div class="flex items-center gap-3">
                <h1 class="text-2xl font-bold text-gray-900">{{ plan.name }}</h1>
                <span
                    v-if="plan.is_default_grace"
                    class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-700"
                >Plan de gracia</span>
            </div>
        </div>

        <form @submit.prevent="submit">
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 max-w-5xl">

                <!-- Left Column -->
                <div class="lg:col-span-2 space-y-6">

                    <!-- Identity -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-900">Información del plan</h2>
                        </div>
                        <div class="p-6 space-y-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Nombre</label>
                                <input
                                    v-model="form.name"
                                    @input="generateSlug"
                                    type="text"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.name" class="text-xs text-red-500 mt-1">{{ form.errors.name }}</p>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Descripción</label>
                                <textarea
                                    v-model="form.description"
                                    rows="2"
                                    placeholder="Breve descripción del plan..."
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50 resize-none"
                                ></textarea>
                                <p v-if="form.errors.description" class="text-xs text-red-500 mt-1">{{ form.errors.description }}</p>
                            </div>
                        </div>
                    </div>

                    <!-- Pricing -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-900">Precios</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Mensual (MXN)</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">$</span>
                                        <input
                                            v-model.number="form.monthly_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full border border-gray-200 rounded-xl pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                        />
                                    </div>
                                    <p v-if="form.errors.monthly_price" class="text-xs text-red-500 mt-1">{{ form.errors.monthly_price }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Anual (MXN)</label>
                                    <div class="relative">
                                        <span class="absolute left-4 top-1/2 -translate-y-1/2 text-sm text-gray-400">$</span>
                                        <input
                                            v-model.number="form.yearly_price"
                                            type="number"
                                            min="0"
                                            step="0.01"
                                            class="w-full border border-gray-200 rounded-xl pl-8 pr-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                        />
                                    </div>
                                    <p v-if="form.errors.yearly_price" class="text-xs text-red-500 mt-1">{{ form.errors.yearly_price }}</p>
                                </div>
                            </div>
                            <p
                                v-if="form.monthly_price > 0 && form.yearly_price > 0 && form.yearly_price < form.monthly_price * 12"
                                class="text-xs text-emerald-600 mt-2"
                            >
                                El plan anual ahorra {{ Math.round((1 - form.yearly_price / (form.monthly_price * 12)) * 100) }}% respecto al mensual.
                            </p>
                        </div>
                    </div>

                    <!-- Limits -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <h2 class="text-sm font-semibold text-gray-900">Límites del plan</h2>
                        </div>
                        <div class="p-6">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Pedidos por mes</label>
                                    <input
                                        v-model.number="form.orders_limit"
                                        type="number"
                                        min="1"
                                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                    />
                                    <p v-if="form.errors.orders_limit" class="text-xs text-red-500 mt-1">{{ form.errors.orders_limit }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Sucursales</label>
                                    <input
                                        v-model.number="form.max_branches"
                                        type="number"
                                        min="1"
                                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                    />
                                    <p v-if="form.errors.max_branches" class="text-xs text-red-500 mt-1">{{ form.errors.max_branches }}</p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Stripe Integration -->
                    <div v-if="!plan.is_default_grace" class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <div class="px-6 py-4 border-b border-gray-100">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <h2 class="text-sm font-semibold text-gray-900">Integración con Stripe</h2>
                                    <span
                                        class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                        :class="stripeConnected
                                            ? 'bg-green-50 text-green-700'
                                            : 'bg-amber-50 text-amber-700'"
                                    >
                                        <span class="w-1.5 h-1.5 rounded-full" :class="stripeConnected ? 'bg-green-500' : 'bg-amber-500'"></span>
                                        {{ stripeConnected ? 'Conectado' : 'Pendiente' }}
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="p-6 space-y-4">
                            <p class="text-xs text-gray-500 leading-relaxed">
                                Vincula este plan con un producto de Stripe para habilitar el cobro automático.
                                Crea el producto y precios en el
                                <a href="https://dashboard.stripe.com/products" target="_blank" class="text-[#FF5722] hover:underline">Dashboard de Stripe</a>
                                y copia los IDs aquí.
                            </p>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Product ID</label>
                                <input
                                    v-model="form.stripe_product_id"
                                    type="text"
                                    placeholder="prod_..."
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.stripe_product_id" class="text-xs text-red-500 mt-1">{{ form.errors.stripe_product_id }}</p>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Price ID mensual</label>
                                    <input
                                        v-model="form.stripe_monthly_price_id"
                                        type="text"
                                        placeholder="price_..."
                                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                    />
                                    <p v-if="form.errors.stripe_monthly_price_id" class="text-xs text-red-500 mt-1">{{ form.errors.stripe_monthly_price_id }}</p>
                                </div>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700 mb-1">Price ID anual</label>
                                    <input
                                        v-model="form.stripe_yearly_price_id"
                                        type="text"
                                        placeholder="price_..."
                                        class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                    />
                                    <p v-if="form.errors.stripe_yearly_price_id" class="text-xs text-red-500 mt-1">{{ form.errors.stripe_yearly_price_id }}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Sidebar -->
                <div class="space-y-6">

                    <!-- Actions -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors disabled:opacity-60 shadow-sm"
                        >
                            {{ form.processing ? 'Guardando...' : 'Guardar cambios' }}
                        </button>
                        <Link
                            :href="route('super.plans.index')"
                            class="block w-full text-center mt-3 px-5 py-2.5 rounded-xl border border-gray-200 text-sm font-semibold text-gray-600 hover:bg-gray-50 transition-colors"
                        >
                            Cancelar
                        </Link>
                    </div>

                    <!-- Plan Stats -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-6">
                        <h3 class="text-sm font-semibold text-gray-900 mb-4">Estadísticas</h3>
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Restaurantes</span>
                                <span class="text-sm font-semibold text-gray-900">{{ plan.restaurants_count ?? 0 }}</span>
                            </div>
                            <div class="flex items-center justify-between">
                                <span class="text-sm text-gray-500">Visibilidad</span>
                                <span
                                    class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-medium"
                                    :class="plan.is_active ? 'bg-green-50 text-green-700' : 'bg-amber-50 text-amber-700'"
                                >
                                    <span class="material-symbols-outlined text-xs" style="font-variation-settings:'FILL' 1">{{ plan.is_active ? 'visibility' : 'visibility_off' }}</span>
                                    {{ plan.is_active ? 'En catálogo' : 'Oculto' }}
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Advanced -->
                    <div class="bg-white rounded-xl border border-gray-100 shadow-sm">
                        <button
                            type="button"
                            @click="showAdvanced = !showAdvanced"
                            class="w-full flex items-center justify-between px-6 py-4 text-sm text-gray-600 hover:text-gray-900 transition-colors"
                        >
                            <span class="font-medium">Avanzado</span>
                            <span
                                class="material-symbols-outlined text-lg transition-transform"
                                :class="{ 'rotate-180': showAdvanced }"
                            >expand_more</span>
                        </button>
                        <div v-if="showAdvanced" class="px-6 pb-5 space-y-4 border-t border-gray-100 pt-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Slug</label>
                                <input
                                    v-model="form.slug"
                                    type="text"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p class="text-xs text-gray-400 mt-1">Identificador URL del plan.</p>
                                <p v-if="form.errors.slug" class="text-xs text-red-500 mt-1">{{ form.errors.slug }}</p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-1">Orden</label>
                                <input
                                    v-model.number="form.sort_order"
                                    type="number"
                                    min="0"
                                    class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-[#FF5722]/50"
                                />
                                <p v-if="form.errors.sort_order" class="text-xs text-red-500 mt-1">{{ form.errors.sort_order }}</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </SuperAdminLayout>
</template>
