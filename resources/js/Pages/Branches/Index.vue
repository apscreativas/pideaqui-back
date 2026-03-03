<script setup>
import { Head, Link, router } from '@inertiajs/vue3'
import { computed } from 'vue'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
    branches: Array,
    maxBranches: Number,
})

const usagePercent = Math.round((props.branches.length / props.maxBranches) * 100)

const activeBranchCount = computed(() => props.branches.filter(b => b.is_active).length)

function isLastActive(branch) {
    return branch.is_active && activeBranchCount.value <= 1
}

function toggleBranch(branch) {
    router.patch(route('branches.toggle', branch.id))
}

function deleteBranch(branch) {
    if (!confirm(`¿Eliminar la sucursal "${branch.name}"? Esta acción no se puede deshacer.`)) { return }
    router.delete(route('branches.destroy', branch.id))
}
</script>

<template>
    <Head title="Sucursales" />
    <AppLayout title="Sucursales">

        <!-- Header -->
        <div class="flex items-start justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">Sucursales</h1>
                <p class="mt-1 text-sm text-gray-500">Gestiona las ubicaciones y el estado de tu restaurante.</p>
            </div>
            <Link
                v-if="branches.length < maxBranches"
                :href="route('branches.create')"
                class="flex items-center gap-2 bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-4 py-2.5 text-sm transition-colors"
            >
                <span class="material-symbols-outlined text-lg">store</span>
                Nueva sucursal
            </Link>
            <div v-else class="text-sm text-gray-400 bg-gray-50 rounded-xl px-4 py-2.5 border border-gray-200">
                Límite de sucursales alcanzado
            </div>
        </div>

        <!-- Plan limit banner -->
        <div class="bg-white rounded-2xl border border-gray-100 p-5 mb-6 flex items-center gap-4">
            <div class="w-10 h-10 bg-orange-50 rounded-xl flex items-center justify-center shrink-0">
                <span class="material-symbols-outlined text-[#FF5722]" style="font-variation-settings:'FILL' 1">store</span>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex items-center justify-between mb-1">
                    <p class="text-sm font-medium text-gray-900">Límite del plan</p>
                    <p class="text-sm font-semibold text-gray-900">{{ branches.length }} de {{ maxBranches }} sucursales</p>
                </div>
                <div class="w-full h-2 bg-gray-100 rounded-full overflow-hidden">
                    <div
                        class="h-2 rounded-full transition-all"
                        :class="usagePercent >= 100 ? 'bg-red-500' : usagePercent >= 80 ? 'bg-yellow-400' : 'bg-[#FF5722]'"
                        :style="`width: ${Math.min(usagePercent, 100)}%`"
                    />
                </div>
                <p v-if="usagePercent >= 100" class="text-xs text-red-500 mt-1">Actualiza tu plan para añadir más sucursales</p>
            </div>
        </div>

        <!-- Empty state -->
        <div v-if="branches.length === 0" class="bg-white rounded-2xl border border-gray-100 p-12 text-center">
            <span class="material-symbols-outlined text-4xl text-gray-300 mb-3" style="font-variation-settings:'FILL' 1">store</span>
            <p class="text-gray-500 font-medium mb-1">No hay sucursales</p>
            <p class="text-sm text-gray-400 mb-4">Crea tu primera sucursal para empezar a recibir pedidos.</p>
            <Link
                :href="route('branches.create')"
                class="bg-[#FF5722] hover:bg-[#D84315] text-white font-semibold rounded-xl px-5 py-2.5 text-sm transition-colors"
            >
                + Nueva sucursal
            </Link>
        </div>

        <!-- Branches list -->
        <div v-else class="bg-white rounded-2xl border border-gray-100 overflow-hidden">
            <table class="w-full">
                <thead>
                    <tr class="border-b border-gray-100">
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Nombre</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Dirección</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">WhatsApp</th>
                        <th class="px-4 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Estado</th>
                        <th class="px-6 py-3 text-left text-xs font-medium text-gray-400 uppercase tracking-wide">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <tr
                        v-for="branch in branches"
                        :key="branch.id"
                        class="border-b border-gray-50 last:border-0 hover:bg-gray-50/50 transition-colors"
                    >
                        <td class="px-6 py-4">
                            <p class="text-sm font-semibold text-gray-900">{{ branch.name }}</p>
                            <p v-if="branch.latitude && branch.longitude" class="text-xs text-gray-400">
                                {{ branch.latitude }}, {{ branch.longitude }}
                            </p>
                        </td>
                        <td class="px-4 py-4">
                            <p v-if="branch.address" class="text-sm text-gray-600">{{ branch.address }}</p>
                            <p v-else class="text-sm text-gray-300 italic">Sin dirección</p>
                        </td>
                        <td class="px-4 py-4">
                            <div v-if="branch.whatsapp" class="flex items-center gap-1.5">
                                <span class="material-symbols-outlined text-green-500 text-base">chat</span>
                                <span class="text-sm text-gray-700">{{ branch.whatsapp }}</span>
                            </div>
                            <p v-else class="text-sm text-gray-300 italic">No configurado</p>
                        </td>
                        <td class="px-4 py-4">
                            <span
                                class="text-xs font-medium px-2.5 py-1 rounded-full"
                                :class="branch.is_active ? 'bg-green-50 text-green-700' : 'bg-gray-100 text-gray-500'"
                            >
                                {{ branch.is_active ? '• Activa' : '• Inactiva' }}
                            </span>
                        </td>
                        <td class="px-6 py-4">
                            <div class="flex items-center gap-1">
                                <Link
                                    :href="route('branches.edit', branch.id)"
                                    class="p-2 text-gray-400 hover:text-[#FF5722] hover:bg-orange-50 rounded-xl transition-colors"
                                    title="Editar"
                                >
                                    <span class="material-symbols-outlined text-lg">edit</span>
                                </Link>
                                <button
                                    @click="toggleBranch(branch)"
                                    :disabled="isLastActive(branch)"
                                    class="p-2 rounded-xl transition-colors"
                                    :class="isLastActive(branch) ? 'text-gray-200 cursor-not-allowed' : 'text-gray-400 hover:text-gray-700 hover:bg-gray-50'"
                                    :title="isLastActive(branch) ? 'No puedes desactivar la última sucursal activa' : (branch.is_active ? 'Desactivar' : 'Activar')"
                                >
                                    <span class="material-symbols-outlined text-lg">{{ branch.is_active ? 'toggle_on' : 'toggle_off' }}</span>
                                </button>
                                <button
                                    @click="deleteBranch(branch)"
                                    :disabled="isLastActive(branch)"
                                    class="p-2 rounded-xl transition-colors"
                                    :class="isLastActive(branch) ? 'text-gray-200 cursor-not-allowed' : 'text-gray-400 hover:text-red-500 hover:bg-red-50'"
                                    :title="isLastActive(branch) ? 'No puedes eliminar la última sucursal activa' : 'Eliminar'"
                                >
                                    <span class="material-symbols-outlined text-lg">delete</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                </tbody>
            </table>
            <div class="px-6 py-3 border-t border-gray-50 text-sm text-gray-400">
                Mostrando {{ branches.length }} de {{ maxBranches }} sucursales
            </div>
        </div>

    </AppLayout>
</template>
