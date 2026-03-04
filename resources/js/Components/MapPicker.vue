<script setup>
/**
 * MapPicker — Admin component for selecting branch coordinates.
 * Fixed center pin — move the map to set lat/lng.
 * Includes a "get my location" GPS button.
 *
 * Props:
 *   lat (String|Number) — initial latitude
 *   lng (String|Number) — initial longitude
 *   mapsKey (String) — Google Maps API key (passed from server)
 *
 * Emits:
 *   update:lat (Number)
 *   update:lng (Number)
 */

import { ref, onMounted, onUnmounted, watch } from 'vue'

const props = defineProps({
    lat: { type: [String, Number], default: null },
    lng: { type: [String, Number], default: null },
    mapsKey: { type: String, default: '' },
})

const emit = defineEmits(['update:lat', 'update:lng'])

const mapEl = ref(null)
const mapLoaded = ref(false)
const mapError = ref(null)
const locating = ref(false)
const gpsError = ref(null)

let googleMap = null

function loadGoogleMapsScript() {
    return new Promise((resolve, reject) => {
        if (window.google?.maps) { resolve(); return }
        const key = props.mapsKey || import.meta.env.VITE_GOOGLE_MAPS_KEY
        if (!key) { reject(new Error('Google Maps API key no configurada')); return }
        const existing = document.querySelector('#google-maps-script')
        if (existing) { existing.addEventListener('load', resolve); existing.addEventListener('error', reject); return }
        const script = document.createElement('script')
        script.id = 'google-maps-script'
        script.src = `https://maps.googleapis.com/maps/api/js?key=${key}`
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
            fullscreenControl: false,
            styles: [{ featureType: 'poi', elementType: 'labels', stylers: [{ visibility: 'off' }] }],
        })

        // Emit center coordinates when map stops moving
        googleMap.addListener('idle', () => {
            const center = googleMap.getCenter()
            emit('update:lat', center.lat())
            emit('update:lng', center.lng())
        })

        mapLoaded.value = true
    } catch (err) {
        mapError.value = err.message
    }
}

// Re-center map when props change externally (e.g. manual input or GPS)
let skipNextWatch = false
watch(() => [props.lat, props.lng], ([newLat, newLng]) => {
    if (!googleMap || skipNextWatch) { skipNextWatch = false; return }
    const lat = parseFloat(newLat)
    const lng = parseFloat(newLng)
    if (!lat || !lng) { return }
    googleMap.setCenter({ lat, lng })
})

function requestGps() {
    if (!navigator.geolocation) {
        gpsError.value = 'Tu navegador no soporta geolocalización.'
        return
    }
    locating.value = true
    gpsError.value = null
    navigator.geolocation.getCurrentPosition(
        (pos) => {
            locating.value = false
            gpsError.value = null
            const lat = pos.coords.latitude
            const lng = pos.coords.longitude
            emit('update:lat', lat)
            emit('update:lng', lng)
            if (googleMap) {
                googleMap.setCenter({ lat, lng })
                googleMap.setZoom(15)
            }
        },
        (err) => {
            locating.value = false
            if (err.code === 1) {
                gpsError.value = 'Permiso de ubicación denegado.'
            } else if (err.code === 2) {
                gpsError.value = 'No se pudo obtener tu ubicación.'
            } else {
                gpsError.value = 'Tiempo de espera agotado.'
            }
        },
        { enableHighAccuracy: true, timeout: 10000, maximumAge: 60000 },
    )
}

onMounted(() => { initMap() })
onUnmounted(() => { googleMap = null })
</script>

<template>
    <div class="space-y-2">
        <!-- GPS button -->
        <button
            type="button"
            @click="requestGps"
            :disabled="locating"
            class="flex items-center gap-2 text-sm font-medium text-[#FF5722] hover:text-[#D84315] transition-colors disabled:opacity-50"
        >
            <span class="material-symbols-outlined text-lg" style="font-variation-settings:'FILL' 1">my_location</span>
            {{ locating ? 'Obteniendo ubicación...' : 'Usar mi ubicación actual' }}
        </button>

        <!-- GPS error -->
        <div v-if="gpsError" class="flex items-center gap-2 text-xs text-amber-700 bg-amber-50 rounded-lg px-3 py-2">
            <span class="material-symbols-outlined text-amber-500 text-sm">warning</span>
            {{ gpsError }}
        </div>

        <!-- Map -->
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
                <p class="text-xs text-gray-400">{{ mapError }}</p>
                <p v-if="lat && lng" class="mt-2 font-mono text-xs text-gray-500">{{ parseFloat(lat).toFixed(6) }}, {{ parseFloat(lng).toFixed(6) }}</p>
            </div>

            <!-- Fixed center pin -->
            <div v-if="mapLoaded" class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-full pointer-events-none z-10">
                <span class="material-symbols-outlined text-[#FF5722] text-4xl drop-shadow-lg" style="font-variation-settings:'FILL' 1">location_on</span>
            </div>

            <!-- Hint -->
            <div v-if="mapLoaded" class="absolute bottom-2 left-1/2 -translate-x-1/2 pointer-events-none">
                <div class="bg-black/50 text-white text-xs px-3 py-1 rounded-full backdrop-blur-sm whitespace-nowrap">
                    Mueve el mapa para ajustar la ubicación
                </div>
            </div>
        </div>
    </div>
</template>
