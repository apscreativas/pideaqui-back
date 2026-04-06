<script setup>
import { Link, usePage } from '@inertiajs/vue3'
import { router } from '@inertiajs/vue3'
import { computed } from 'vue'

const page = usePage()
const flash = computed(() => page.props.flash)

const navItems = [
    { name: 'Dashboard', route: 'super.dashboard', icon: 'dashboard' },
    { name: 'Restaurantes', route: 'super.restaurants.index', icon: 'storefront' },
    { name: 'Estadísticas', route: 'super.statistics', icon: 'bar_chart' },
    { name: 'Planes', route: 'super.plans.index', icon: 'payments' },
    { name: 'Billing', route: 'super.billing-settings', icon: 'tune' },
    { name: 'Mi cuenta', route: 'super.profile', icon: 'person' },
]

function isActive(routeName) {
    return route().current(routeName + '*') || route().current(routeName)
}

function logout() {
    router.post(route('super.logout'))
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
                        <p class="text-xs text-gray-400">SuperAdmin</p>
                    </div>
                </div>
            </div>

            <!-- Navigation -->
            <nav class="flex-1 px-4 py-4 space-y-1 overflow-y-auto">
                <template v-for="item in navItems" :key="item.route">
                    <Link
                        :href="route(item.route)"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-xl text-sm font-medium transition-colors"
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

            <!-- Page content -->
            <main class="flex-1 px-8 py-8">
                <slot />
            </main>
        </div>
    </div>
</template>
