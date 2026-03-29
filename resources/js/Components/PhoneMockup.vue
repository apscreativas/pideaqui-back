<script setup>
import { computed, shallowRef } from 'vue'

const props = defineProps({
    primaryColor: { type: String, default: '#f6f8f7' },
    secondaryColor: { type: String, default: '#FF5722' },
    textColor: { type: String, default: 'dark' },
    defaultProductImageUrl: { type: String, default: null },
})

const activeCategory = shallowRef(0)

const categories = [
    { id: 0, name: 'Promociones', isPromo: true },
    { id: 1, name: 'Comidas', isPromo: false },
    { id: 2, name: 'Bebidas', isPromo: false },
]

const products = [
    { id: 1, name: 'Taco al Pastor', description: 'Tortilla de maiz con carne al pastor', price: 45.00, isPromo: true },
    { id: 2, name: 'Enchiladas Verdes', description: 'Tortillas rellenas de pollo bañadas en salsa verde', price: 85.00, isPromo: false },
    { id: 3, name: 'Agua de Horchata', description: 'Bebida tradicional de arroz', price: 30.00, isPromo: false },
]

function luminance(hex) {
    const c = hex.replace('#', '')
    const full = c.length === 3 ? c[0]+c[0]+c[1]+c[1]+c[2]+c[2] : c
    const r = parseInt(full.substring(0, 2), 16) / 255
    const g = parseInt(full.substring(2, 4), 16) / 255
    const b = parseInt(full.substring(4, 6), 16) / 255
    const toLinear = (v) => v <= 0.03928 ? v / 12.92 : Math.pow((v + 0.055) / 1.055, 2.4)
    return 0.2126 * toLinear(r) + 0.7152 * toLinear(g) + 0.0722 * toLinear(b)
}

const textOnSecondary = computed(() =>
    luminance(props.secondaryColor) > 0.4 ? '#1a1a1a' : '#ffffff'
)

const isDark = computed(() => props.textColor === 'dark')

const t = computed(() => ({
    text: isDark.value ? '#1a1a1a' : '#ffffff',
    textSecondary: isDark.value ? '#6b7280' : 'rgba(255,255,255,0.6)',
    textMuted: isDark.value ? '#d1d5db' : 'rgba(255,255,255,0.2)',
    border: isDark.value ? '#e5e7eb' : 'rgba(255,255,255,0.15)',
    cardBg: isDark.value ? '#ffffff' : 'rgba(255,255,255,0.08)',
    cardBorder: isDark.value ? '#f3f4f6' : 'rgba(255,255,255,0.1)',
    pillBg: isDark.value ? '#ffffff' : 'rgba(255,255,255,0.12)',
    pillText: isDark.value ? '#6b7280' : 'rgba(255,255,255,0.7)',
    logoBorder: isDark.value ? '#e5e7eb' : 'rgba(255,255,255,0.2)',
    imageBg: isDark.value ? '#f3f4f6' : 'rgba(255,255,255,0.06)',
}))
</script>

<template>
    <div class="mockup-device" aria-hidden="true">
        <div class="mockup-frame">
            <div class="mockup-notch"></div>

            <div class="mockup-screen" :style="{ backgroundColor: primaryColor }">
                <!-- Header -->
                <div class="mockup-header" :style="{ backgroundColor: primaryColor, borderColor: t.border }">
                    <div class="mockup-header-logo" :style="{ borderColor: t.logoBorder }">
                        <span
                            class="material-symbols-outlined"
                            :style="{ color: secondaryColor, fontSize: '14px', fontVariationSettings: `'FILL' 1` }"
                        >restaurant</span>
                    </div>
                    <span class="mockup-header-name" :style="{ color: t.text }">Mi Restaurante</span>
                </div>

                <!-- Category pills -->
                <div class="mockup-categories" :style="{ backgroundColor: primaryColor, borderColor: t.border }">
                    <button
                        v-for="cat in categories"
                        :key="cat.id"
                        class="mockup-pill"
                        :style="activeCategory === cat.id
                            ? { backgroundColor: secondaryColor, color: textOnSecondary, boxShadow: '0 1px 3px rgba(0,0,0,0.12)' }
                            : { backgroundColor: t.pillBg, color: t.pillText, border: '1px solid ' + t.border }"
                        @click="activeCategory = cat.id"
                    >
                        <span
                            v-if="cat.isPromo"
                            class="material-symbols-outlined"
                            style="font-size: 10px; font-variation-settings: 'FILL' 1"
                        >local_fire_department</span>
                        {{ cat.name }}
                    </button>
                </div>

                <!-- Product cards -->
                <div class="mockup-products">
                    <div
                        v-for="product in products"
                        :key="product.id"
                        class="mockup-card"
                        :style="{ backgroundColor: t.cardBg, borderColor: t.cardBorder }"
                    >
                        <div class="mockup-card-content">
                            <p class="mockup-card-name" :style="{ color: t.text }">{{ product.name }}</p>
                            <p class="mockup-card-desc" :style="{ color: t.textSecondary }">{{ product.description }}</p>
                            <div class="mockup-card-footer">
                                <span class="mockup-card-price" :style="{ color: secondaryColor }">${{ product.price.toFixed(2) }}</span>
                                <span
                                    v-if="product.isPromo"
                                    class="mockup-badge"
                                    :style="{ backgroundColor: secondaryColor, color: textOnSecondary }"
                                >Promo</span>
                            </div>
                        </div>
                        <div class="mockup-card-image-wrap">
                            <div class="mockup-card-image" :style="{ backgroundColor: t.imageBg }">
                                <img
                                    v-if="defaultProductImageUrl"
                                    :src="defaultProductImageUrl"
                                    alt=""
                                    class="mockup-card-img-el"
                                />
                                <span
                                    v-else
                                    class="material-symbols-outlined"
                                    :style="{ color: t.textMuted, fontSize: '18px', fontVariationSettings: `'FILL' 1` }"
                                >fastfood</span>
                            </div>
                            <div class="mockup-add-btn" :style="{ backgroundColor: secondaryColor }">
                                <span
                                    class="material-symbols-outlined"
                                    :style="{ color: textOnSecondary, fontSize: '10px' }"
                                >add</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Cart bar -->
                <div class="mockup-cart-bar" :style="{ backgroundColor: secondaryColor }">
                    <span class="mockup-cart-text" :style="{ color: textOnSecondary }">Ver carrito (3)</span>
                    <span class="mockup-cart-total" :style="{ color: textOnSecondary }">$160.00</span>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.mockup-device { display: flex; justify-content: center; }

.mockup-frame {
    position: relative;
    width: 260px; height: 520px;
    border-radius: 32px;
    border: 4px solid #1f2937;
    background: #1f2937;
    box-shadow: 0 20px 40px rgba(0,0,0,0.15), inset 0 0 0 1px rgba(255,255,255,0.05);
    overflow: hidden;
}

.mockup-notch {
    position: absolute; top: 0; left: 50%; transform: translateX(-50%);
    width: 100px; height: 20px;
    background: #1f2937; border-radius: 0 0 16px 16px; z-index: 10;
}

.mockup-screen { width: 100%; height: 100%; overflow-y: auto; display: flex; flex-direction: column; }

.mockup-header {
    position: sticky; top: 0; z-index: 5;
    display: flex; align-items: center; gap: 6px;
    padding: 24px 10px 8px; border-bottom: 1px solid;
}

.mockup-header-logo {
    width: 28px; height: 28px; border-radius: 8px; border: 1px solid;
    display: flex; align-items: center; justify-content: center;
    flex-shrink: 0; background: rgba(255,255,255,0.5);
}

.mockup-header-name { font-size: 11px; font-weight: 700; line-height: 1.2; }

.mockup-categories {
    display: flex; gap: 4px; padding: 6px 10px;
    border-bottom: 1px solid; overflow: hidden;
}

.mockup-pill {
    flex-shrink: 0; padding: 3px 8px; border-radius: 9999px;
    font-size: 9px; font-weight: 600;
    display: flex; align-items: center; gap: 2px;
    cursor: pointer; border: 1px solid transparent; transition: all 0.15s;
}

.mockup-products { flex: 1; padding: 8px 10px 48px; display: flex; flex-direction: column; gap: 6px; }

.mockup-card { display: flex; align-items: center; gap: 8px; padding: 8px; border-radius: 12px; border: 1px solid; }
.mockup-card-content { flex: 1; min-width: 0; }
.mockup-card-name { font-size: 10px; font-weight: 600; line-height: 1.3; margin-bottom: 2px; }
.mockup-card-desc {
    font-size: 8px; line-height: 1.3;
    display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden; margin-bottom: 4px;
}
.mockup-card-footer { display: flex; align-items: center; gap: 4px; }
.mockup-card-price { font-size: 10px; font-weight: 700; }
.mockup-badge { font-size: 7px; font-weight: 600; padding: 1px 5px; border-radius: 9999px; }

.mockup-card-image-wrap { position: relative; flex-shrink: 0; }
.mockup-card-image { width: 48px; height: 48px; border-radius: 8px; overflow: hidden; display: flex; align-items: center; justify-content: center; }
.mockup-card-img-el { width: 100%; height: 100%; object-fit: contain; }

.mockup-add-btn {
    position: absolute; bottom: -3px; right: -3px;
    width: 16px; height: 16px; border-radius: 9999px;
    display: flex; align-items: center; justify-content: center;
    box-shadow: 0 1px 3px rgba(0,0,0,0.15);
}

.mockup-cart-bar {
    position: absolute; bottom: 8px; left: 10px; right: 10px;
    padding: 8px 12px; border-radius: 14px;
    display: flex; align-items: center; justify-content: space-between;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.mockup-cart-text { font-size: 9px; font-weight: 600; }
.mockup-cart-total { font-size: 9px; font-weight: 700; }
</style>
