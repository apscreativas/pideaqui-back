<script setup>
import { Link } from '@inertiajs/vue3'

const NAV = [
    { label: 'General',            icon: 'storefront',        route: 'settings.general' },
    { label: 'Horarios',           icon: 'schedule',          route: 'settings.schedules' },
    { label: 'Métodos de entrega', icon: 'two_wheeler',       route: 'settings.delivery-methods' },
    { label: 'Tarifas de envío',   icon: 'local_shipping',    route: 'settings.shipping-rates' },
    { label: 'Métodos de pago',    icon: 'payments',          route: 'settings.payment-methods' },
    { label: 'Personalización',    icon: 'palette',           route: 'settings.branding' },
    { label: 'Usuarios',           icon: 'group',             route: 'settings.users' },
    { label: 'Mi cuenta',          icon: 'manage_accounts',   route: 'settings.profile' },
    { label: 'Mis límites',        icon: 'monitor_heart',     route: 'settings.limits' },
]

function isActive(routeName) {
    return route().current(routeName)
}
</script>

<template>
    <div class="flex gap-8">
        <!-- Settings sub-navigation -->
        <nav class="w-56 shrink-0">
            <ul class="space-y-1">
                <li v-for="item in NAV" :key="item.route">
                    <Link
                        :href="route(item.route)"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors"
                        :class="isActive(item.route)
                            ? 'bg-[#FF5722]/10 text-[#FF5722]'
                            : 'text-gray-600 hover:bg-gray-50 hover:text-gray-900'"
                    >
                        <span
                            class="material-symbols-outlined text-lg"
                            :style="isActive(item.route) ? 'font-variation-settings: FILL 1' : ''"
                        >{{ item.icon }}</span>
                        {{ item.label }}
                    </Link>
                </li>
            </ul>
        </nav>

        <!-- Page content -->
        <div class="flex-1 min-w-0">
            <slot />
        </div>
    </div>
</template>
