<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

defineProps({
    title: {
        type: String,
        default: '',
    },
    flush: {
        type: Boolean,
        default: false,
    },
})

const page = usePage()
const user = computed(() => page.props.auth.user)
const flash = computed(() => page.props.flash)
const billing = computed(() => page.props.billing)

const billingGraceDaysLeft = computed(() => {
    // Prefer server-computed value; fall back to client calc for resilience.
    if (billing.value?.grace_days_remaining !== undefined && billing.value?.grace_days_remaining !== null) {
        return billing.value.grace_days_remaining
    }
    if (!billing.value?.grace_period_ends_at) return 0
    const now = new Date()
    const end = new Date(billing.value.grace_period_ends_at)
    const diff = Math.ceil((end - now) / (1000 * 60 * 60 * 24))
    return Math.max(0, diff)
})

const graceIsUrgent = computed(() => billingGraceDaysLeft.value !== null && billingGraceDaysLeft.value <= 3)

function formatBillingDate(dateStr) {
    if (!dateStr) return ''
    return new Date(dateStr).toLocaleDateString('es-MX', {
        day: 'numeric',
        month: 'long',
        year: 'numeric',
    })
}

const isAdmin = computed(() => user.value?.is_admin === true)

const allNavItems = [
    { name: 'Dashboard', route: 'dashboard', icon: 'dashboard', roles: ['admin', 'operator'] },
    { name: 'POS', route: 'pos.index', icon: 'point_of_sale', roles: ['admin', 'operator'] },
    { name: 'Pedidos', route: 'orders.index', icon: 'receipt_long', roles: ['admin', 'operator'] },
    { name: 'Menú Digital', route: 'menu.index', icon: 'restaurant_menu', roles: ['admin'] },
    { name: 'Catálogo Modif.', route: 'modifier-catalog.index', icon: 'tune', roles: ['admin'] },
    { name: 'Promociones', route: 'promotions.index', icon: 'sell', roles: ['admin'] },
    { name: 'Cupones', route: 'coupons.index', icon: 'confirmation_number', roles: ['admin'] },
    { name: 'Cancelaciones', route: 'cancellations.index', icon: 'cancel', roles: ['admin'] },
    { name: 'Gastos', route: 'expenses.index', icon: 'trending_down', roles: ['admin'] },
    { name: 'Mapa', route: 'map.index', icon: 'map', roles: ['admin', 'operator'] },
    { name: 'Sucursales', route: 'branches.index', icon: 'store', roles: ['admin'] },
    { name: 'Configuración', route: 'settings.index', icon: 'settings', roles: ['admin'] },
]

const navItems = computed(() => {
    const role = user.value?.role ?? 'admin'
    return allNavItems.filter((item) => item.roles.includes(role))
})

function isActive(routeName) {
    if (route().current(routeName + '*') || route().current(routeName)) return true
    const prefix = routeName.replace(/\.index$/, '')
    return route().current(prefix + '.*')
}

function logout() {
    router.post(route('logout'))
}
</script>

<template>
    <div class="min-h-screen bg-[#FAFAFA] flex">

        <!-- Sidebar -->
        <aside class="fixed top-0 left-0 h-full w-[260px] bg-white border-r border-gray-100 flex flex-col z-30">

            <!-- Logo -->
            <div class="px-6 py-5 border-b border-gray-100">
                <div class="flex items-center gap-3">
                    <div class="bg-orange-50 p-2 rounded-xl">
                        <span class="material-symbols-outlined text-[#FF5722] text-2xl" style="font-variation-settings:'FILL' 1">
                            local_fire_department
                        </span>
                    </div>
                    <div>
                        <p class="font-bold text-gray-900 text-sm leading-tight">PideAqui</p>
                        <p class="text-xs text-gray-400">Panel Admin</p>
                    </div>
                </div>
            </div>

            <!-- User info -->
            <div v-if="user" class="px-4 py-4 border-b border-gray-100">
                <div class="flex items-center gap-3 px-2 py-2 rounded-xl bg-gray-50">
                    <div class="w-8 h-8 rounded-full bg-[#FF5722]/10 flex items-center justify-center shrink-0">
                        <span class="text-[#FF5722] text-sm font-semibold">{{ user.name.charAt(0).toUpperCase() }}</span>
                    </div>
                    <div class="min-w-0">
                        <p class="text-sm font-medium text-gray-900 truncate">{{ user.name }}</p>
                        <p class="text-xs text-gray-400 truncate">{{ user.email }}</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <template v-for="item in navItems" :key="item.route">
                    <Link
                        :href="route(item.route)"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors group"
                        :class="isActive(item.route)
                            ? 'bg-[#FF5722]/10 text-[#FF5722]'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                    >
                        <span
                            class="material-symbols-outlined text-xl"
                            :style="isActive(item.route) ? 'font-variation-settings:\'FILL\' 1' : ''"
                        >{{ item.icon }}</span>
                        <span>{{ item.name }}</span>
                    </Link>
                </template>
            </nav>

            <!-- Logout -->
            <div class="px-4 py-4 border-t border-gray-100">
                <button
                    @click="logout"
                    class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium text-gray-500 hover:bg-red-50 hover:text-red-600 transition-colors w-full"
                >
                    <span class="material-symbols-outlined text-xl">logout</span>
                    <span>Cerrar sesión</span>
                </button>
            </div>
        </aside>

        <!-- Main content -->
        <div class="ml-[260px] flex-1 flex flex-col min-h-screen">

            <!-- Flash messages -->
            <div v-if="flash?.success || flash?.error" class="px-8 pt-6">
                <div
                    v-if="flash.success"
                    class="flex items-center gap-3 bg-green-50 border border-green-200 text-green-800 rounded-xl px-4 py-3 text-sm"
                >
                    <span class="material-symbols-outlined text-green-600 text-xl" style="font-variation-settings:'FILL' 1">check_circle</span>
                    {{ flash.success }}
                </div>
                <div
                    v-if="flash.error"
                    class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm"
                >
                    <span class="material-symbols-outlined text-red-600 text-xl" style="font-variation-settings:'FILL' 1">error</span>
                    {{ flash.error }}
                </div>
            </div>

            <!-- Billing banners -->
            <div v-if="billing?.status === 'grace_period'" class="px-8 pt-4">
                <div
                    class="flex items-center gap-3 rounded-xl px-4 py-3 text-sm border"
                    :class="graceIsUrgent
                        ? 'bg-red-50 border-red-200 text-red-800'
                        : 'bg-orange-50 border-orange-200 text-orange-800'"
                >
                    <span
                        class="material-symbols-outlined text-xl"
                        :class="graceIsUrgent ? 'text-red-500' : 'text-orange-500'"
                        style="font-variation-settings:'FILL' 1"
                    >warning</span>
                    <span>
                        <template v-if="billingGraceDaysLeft === 0">Tu periodo de gracia vence <strong>hoy</strong>.</template>
                        <template v-else-if="billingGraceDaysLeft === 1">Tu periodo de gracia vence <strong>mañana</strong>.</template>
                        <template v-else>Tu periodo de gracia vence en <strong>{{ billingGraceDaysLeft }} dias</strong>.</template>
                        <Link
                            :href="route('settings.subscription')"
                            class="font-semibold underline"
                            :class="graceIsUrgent ? 'hover:text-red-900' : 'hover:text-orange-900'"
                        >Elige un plan</Link>
                        para continuar operando.
                    </span>
                </div>
            </div>

            <div v-else-if="billing?.status === 'past_due'" class="px-8 pt-4">
                <div class="flex items-center gap-3 bg-yellow-50 border border-yellow-200 text-yellow-800 rounded-xl px-4 py-3 text-sm">
                    <span class="material-symbols-outlined text-yellow-500 text-xl" style="font-variation-settings:'FILL' 1">payment</span>
                    <span>
                        Hay un problema con tu metodo de pago.
                        <Link :href="route('settings.subscription')" class="font-semibold underline hover:text-yellow-900">Actualiza tu pago</Link>
                        para evitar la suspension de tu cuenta.
                    </span>
                </div>
            </div>

            <div v-else-if="billing?.status === 'suspended'" class="px-8 pt-4">
                <div class="flex items-center gap-3 bg-red-50 border border-red-200 text-red-800 rounded-xl px-4 py-3 text-sm">
                    <span class="material-symbols-outlined text-red-500 text-xl" style="font-variation-settings:'FILL' 1">block</span>
                    <span>
                        Tu cuenta esta suspendida. Tus clientes no pueden realizar pedidos.
                        <Link :href="route('settings.subscription')" class="font-semibold underline hover:text-red-900">Elige un plan</Link>
                        para reactivar tu cuenta.
                    </span>
                </div>
            </div>

            <div v-else-if="billing?.status === 'incomplete'" class="px-8 pt-4">
                <div class="flex items-center gap-3 bg-blue-50 border border-blue-200 text-blue-800 rounded-xl px-4 py-3 text-sm">
                    <span class="material-symbols-outlined text-blue-500 text-xl" style="font-variation-settings:'FILL' 1">info</span>
                    <span>
                        Tu suscripcion esta incompleta.
                        <Link :href="route('settings.subscription')" class="font-semibold underline hover:text-blue-900">Completa el proceso</Link>
                        para activar tu plan.
                    </span>
                </div>
            </div>

            <div v-else-if="billing?.status === 'canceled'" class="px-8 pt-4">
                <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 text-gray-600 rounded-xl px-4 py-3 text-sm">
                    <span class="material-symbols-outlined text-gray-400 text-xl" style="font-variation-settings:'FILL' 1">event_busy</span>
                    <span>
                        Tu suscripcion ha sido cancelada.
                        <template v-if="billing.subscription_ends_at">
                            Tu plan actual expira el {{ formatBillingDate(billing.subscription_ends_at) }}.
                        </template>
                        <Link :href="route('settings.subscription')" class="font-semibold underline hover:text-gray-800">Renovar suscripcion</Link>
                    </span>
                </div>
            </div>

            <!-- Page content -->
            <main class="flex-1" :class="flush ? 'min-h-0 overflow-hidden' : 'px-8 py-8'">
                <slot />
            </main>
        </div>
    </div>
</template>
