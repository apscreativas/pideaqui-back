<script setup>
import { computed, ref, watch } from 'vue'
import axios from 'axios'

/**
 * Reusable slug input with live availability checking.
 *
 * Behaviour:
 *  - Shows a preview of the final URL (prefix + slug).
 *  - Auto-fills the slug from `nameSource` until the user edits it manually.
 *  - Debounced call to `GET /api/slug-check` for format, reserved, taken.
 *  - Caches results per slug so switching back and forth doesn't re-hit the API.
 *  - On 429 throttle, keeps the last known state instead of regressing to
 *    unavailable, so the submit button doesn't get permanently stuck.
 *  - Exposes suggestions as clickable chips on failure.
 *
 * Emits:
 *  - `update:modelValue` with the current slug string.
 *  - `update:available` with a boolean; parent can use it to gate submit.
 */
const props = defineProps({
    modelValue: { type: String, default: '' },
    nameSource: { type: String, default: '' },
    urlPrefix: { type: String, default: '/r/' },
    label: { type: String, default: 'URL pública del menú' },
    autoSuggestFromName: { type: Boolean, default: true },
    ignoreCurrentSlug: { type: String, default: '' },
})

const emit = defineEmits(['update:modelValue', 'update:available'])

const userEdited = ref(false)
const state = ref('idle') // idle | checking | available | unavailable | invalid | throttled
const message = ref('')
const suggestions = ref([])

const MIN_LENGTH = 3
const DEBOUNCE_MS = 500
const resultCache = new Map()

function sanitize(raw) {
    return String(raw || '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/[^a-z0-9-]+/g, '-')
        .replace(/-+/g, '-')
        .replace(/^-|-$/g, '')
        .slice(0, 50)
}

// Autofill from name until the user types in the slug field
watch(() => props.nameSource, (name) => {
    if (!props.autoSuggestFromName || userEdited.value) {
        return
    }
    emit('update:modelValue', sanitize(name))
})

function onInput(ev) {
    userEdited.value = true
    const cleaned = sanitize(ev.target.value)
    emit('update:modelValue', cleaned)
}

let timer = null
watch(() => props.modelValue, (value) => {
    clearTimeout(timer)

    if (!value) {
        state.value = 'idle'
        message.value = ''
        suggestions.value = []
        emit('update:available', false)
        return
    }

    // Short-circuit: avoid a round-trip on obviously-too-short values.
    if (value.length < MIN_LENGTH) {
        state.value = 'invalid'
        message.value = `Mínimo ${MIN_LENGTH} caracteres.`
        suggestions.value = []
        emit('update:available', false)
        return
    }

    // Allow the current slug without round-trip (used in rename flow).
    if (props.ignoreCurrentSlug && value === props.ignoreCurrentSlug) {
        state.value = 'available'
        message.value = 'Slug actual.'
        suggestions.value = []
        emit('update:available', true)
        return
    }

    // Client cache: if we already asked the server about this exact slug,
    // replay the prior answer instantly. Reduces load on the endpoint and
    // makes the UX feel instant when users toggle between tried values.
    if (resultCache.has(value)) {
        applyResult(value, resultCache.get(value))
        return
    }

    state.value = 'checking'
    message.value = ''
    timer = setTimeout(() => check(value), DEBOUNCE_MS)
})

function applyResult(slug, data) {
    // Ignore stale responses that arrive after the user has moved on.
    if (slug !== props.modelValue) {
        return
    }
    if (data.available) {
        state.value = 'available'
        message.value = 'Disponible.'
        suggestions.value = []
        emit('update:available', true)
    } else {
        state.value = data.reason === 'invalid_format' ? 'invalid' : 'unavailable'
        message.value = data.message || 'Slug no disponible.'
        suggestions.value = Array.isArray(data.suggestions) ? data.suggestions : []
        emit('update:available', false)
    }
}

async function check(slugAtTime) {
    try {
        const { data } = await axios.get('/api/slug-check', { params: { slug: slugAtTime } })
        resultCache.set(slugAtTime, data)
        applyResult(slugAtTime, data)
    } catch (err) {
        if (slugAtTime !== props.modelValue) {
            return
        }
        if (err?.response?.status === 429) {
            // Soft-fail: don't block the submit. Backend validation will
            // still reject an invalid slug on form submit, so the user can
            // proceed and at worst see a server-side error message.
            state.value = 'throttled'
            message.value = 'Verificación temporalmente limitada — puedes continuar, validaremos al enviar.'
            emit('update:available', true)
            return
        }
        state.value = 'invalid'
        message.value = 'No se pudo verificar el slug.'
        emit('update:available', false)
    }
}

function pickSuggestion(s) {
    userEdited.value = true
    emit('update:modelValue', s)
}

const previewUrl = computed(() => `${props.urlPrefix}${props.modelValue || '…'}`)

const badgeClass = computed(() => ({
    available: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    unavailable: 'bg-red-50 text-red-700 border-red-200',
    invalid: 'bg-amber-50 text-amber-800 border-amber-200',
    throttled: 'bg-blue-50 text-blue-700 border-blue-200',
    checking: 'bg-gray-50 text-gray-600 border-gray-200',
    idle: 'bg-gray-50 text-gray-500 border-gray-200',
}[state.value]))

const badgeIcon = computed(() => ({
    available: 'check_circle',
    unavailable: 'block',
    invalid: 'warning',
    throttled: 'hourglass_top',
    checking: 'progress_activity',
    idle: 'link',
}[state.value]))
</script>

<template>
    <div>
        <label class="block text-sm font-semibold text-gray-900">{{ label }}</label>
        <div class="mt-1.5 flex rounded-xl overflow-hidden border border-gray-200 focus-within:border-[#FF5722] focus-within:ring-2 focus-within:ring-[#FF572233]">
            <span class="px-3 py-2.5 bg-gray-50 text-gray-500 text-sm border-r border-gray-200 select-none">
                {{ urlPrefix }}
            </span>
            <input
                type="text"
                :value="modelValue"
                @input="onInput"
                maxlength="50"
                autocomplete="off"
                placeholder="mi-restaurante"
                class="flex-1 px-3 py-2.5 text-sm outline-none bg-white font-mono"
            />
        </div>

        <p class="mt-1.5 text-xs text-gray-500">
            Vista previa:
            <span class="font-mono text-gray-700">{{ previewUrl }}</span>
        </p>

        <div
            v-if="state !== 'idle'"
            :class="['mt-2 inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border', badgeClass]"
        >
            <span class="material-symbols-outlined text-sm" :class="{ 'animate-spin': state === 'checking' }">{{ badgeIcon }}</span>
            {{ message || (state === 'checking' ? 'Verificando…' : '') }}
        </div>

        <div v-if="suggestions.length > 0" class="mt-2">
            <p class="text-xs text-gray-500">Sugerencias disponibles:</p>
            <div class="mt-1 flex flex-wrap gap-1.5">
                <button
                    v-for="s in suggestions"
                    :key="s"
                    type="button"
                    @click="pickSuggestion(s)"
                    class="px-2.5 py-1 rounded-full text-xs font-medium bg-white border border-gray-300 hover:bg-gray-50 text-gray-700 font-mono"
                >
                    {{ s }}
                </button>
            </div>
        </div>
    </div>
</template>
