<script setup>
/**
 * MapPicker — Admin component for selecting branch coordinates.
 * Renders a clickable/draggable Google Maps map to set lat/lng.
 *
 * Props:
 *   lat (String|Number) — initial latitude
 *   lng (String|Number) — initial longitude
 *
 * Emits:
 *   update:lat (Number)
 *   update:lng (Number)
 *
 * Requires VITE_GOOGLE_MAPS_KEY in .env.
 */

import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
    lat: { type: [String, Number], default: null },
    lng: { type: [String, Number], default: null },
})

const emit = defineEmits(['update:lat', 'update:lng'])

const mapEl = ref(null)
const mapLoaded = ref(false)
const mapError = ref(null)

let googleMap = null
let marker = null

const API_KEY = import.meta.env.VITE_GOOGLE_MAPS_KEY

function loadGoogleMapsScript() {
    return new Promise((resolve, reject) => {
        if (window.google?.maps) { resolve(); return }
        if (!API_KEY) { reject(new Error('VITE_GOOGLE_MAPS_KEY no configurada')); return }
        const existing = document.querySelector('#google-maps-script')
        if (existing) { existing.addEventListener('load', resolve); existing.addEventListener('error', reject); return }
        const script = document.createElement('script')
        script.id = 'google-maps-script'
        script.src = `https://maps.googleapis.com/maps/api/js?key=${API_KEY}`
        script.async = true
        script.onload = resolve
        script.onerror = () => reject(new Error('Error al cargar Google Maps'))
        document.head.appendChild(script)
    })
}

async function initMap() {
    try {
        await loadGoogleMapsScript()

        const lat = parseFloat(props.lat) || 19.4326
        const lng = parseFloat(props.lng) || -99.1332
        const center = { lat, lng }

        googleMap = new window.google.maps.Map(mapEl.value, {
            center,
            zoom: props.lat ? 15 : 5,
            disableDefaultUI: true,
            zoomControl: true,
            mapTypeControl: false,
            streetViewControl: false,
        })

        marker = new window.google.maps.Marker({
            position: center,
            map: googleMap,
            draggable: true,
            icon: {
                path: window.google.maps.SymbolPath.CIRCLE,
                scale: 10,
                fillColor: '#FF5722',
                fillOpacity: 1,
                strokeColor: '#ffffff',
                strokeWeight: 2,
            },
        })

        // Click on map to reposition marker
        googleMap.addListener('click', (event) => {
            marker.setPosition(event.latLng)
            emit('update:lat', event.latLng.lat())
            emit('update:lng', event.latLng.lng())
        })

        marker.addListener('dragend', (event) => {
            emit('update:lat', event.latLng.lat())
            emit('update:lng', event.latLng.lng())
        })

        mapLoaded.value = true
    } catch (err) {
        mapError.value = err.message
    }
}

watch(() => [props.lat, props.lng], ([newLat, newLng]) => {
    if (!googleMap || !marker) { return }
    const lat = parseFloat(newLat)
    const lng = parseFloat(newLng)
    if (!lat || !lng) { return }
    const pos = { lat, lng }
    googleMap.setCenter(pos)
    marker.setPosition(pos)
})

onMounted(() => { initMap() })
onUnmounted(() => { marker = null; googleMap = null })
</script>

<template>
    <div class="relative w-full h-64 rounded-xl overflow-hidden bg-gray-100 border border-gray-200">

        <div ref="mapEl" class="w-full h-full"></div>

        <!-- Loading -->
        <div v-if="!mapLoaded && !mapError" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-100">
            <div class="w-7 h-7 rounded-full border-4 border-gray-200 border-t-[#FF5722] animate-spin mb-2"></div>
            <p class="text-xs text-gray-400">Cargando mapa...</p>
        </div>

        <!-- Error / no key -->
        <div v-if="mapError" class="absolute inset-0 flex flex-col items-center justify-center bg-gray-50 px-4 text-center">
            <span class="material-symbols-outlined text-gray-300 text-4xl mb-2">map</span>
            <p class="text-xs text-gray-500 mb-1">Mapa no disponible</p>
            <p class="text-xs text-gray-400">Configura VITE_GOOGLE_MAPS_KEY en .env</p>
            <p v-if="lat && lng" class="mt-2 font-mono text-xs text-gray-500">{{ parseFloat(lat).toFixed(6) }}, {{ parseFloat(lng).toFixed(6) }}</p>
        </div>

        <!-- Hint -->
        <div v-if="mapLoaded" class="absolute bottom-2 left-1/2 -translate-x-1/2 pointer-events-none">
            <div class="bg-black/50 text-white text-xs px-3 py-1 rounded-full backdrop-blur-sm whitespace-nowrap">
                Haz clic o arrastra el pin para ajustar la ubicación
            </div>
        </div>
    </div>
</template>
