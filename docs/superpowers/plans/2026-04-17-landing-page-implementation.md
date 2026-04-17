# GuisoGo Landing Page — Implementation Plan

**Goal:** Build a modern, animated, single-page marketing site for GuisoGo at `landing/` using Nuxt 3, ready to deploy as a static site to Vercel.

**Architecture:** Standalone Nuxt 3 SSG project (sibling of `admin/` and `client/`). Vue 3 `<script setup>` + TypeScript + Tailwind v4. Animations driven by GSAP + ScrollTrigger (scroll-pinned sections), Lenis (smooth scroll), and @vueuse/motion (declarative enter transitions). 10 long-scroll sections, all mockups built as Vue components (no real backend).

**Tech Stack:** Nuxt 3, Vue 3 TS, Tailwind v4, GSAP 3, Lenis, @vueuse/motion, @nuxt/image, @nuxt/fonts.

**Spec:** `docs/superpowers/specs/2026-04-17-landing-page-design.md`

**Note on git:** The parent folder is not a git repo. We'll `git init` inside `landing/` so the project has its own history. "Commit" steps use that local repo.

**Note on testing:** This is a visual/marketing site. Unit tests for animations/visuals are low-value. Verification happens via `nuxi dev` in browser + Lighthouse + build success. No forced TDD.

---

## Phase 1 — Scaffolding & configuration

### Task 1: Initialize Nuxt 3 project

**Files:**
- Create: `landing/` (whole project)

- [ ] **Step 1: Create Nuxt project non-interactively**

```bash
cd /Users/sebas/Documents/GuisoGo
npx nuxi@latest init landing --packageManager npm --gitInit false --no-install
```

Expected: folder `landing/` created with Nuxt 3 starter.

- [ ] **Step 2: Install base deps**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm install
```

- [ ] **Step 3: Verify dev server boots**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run dev -- --port 3100
```

Expected: `Local: http://localhost:3100`. Open in browser, see Nuxt welcome. Kill with Ctrl-C.

- [ ] **Step 4: Initialize git inside landing/**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
git init
git add .
git commit -m "chore: bootstrap nuxt 3 landing"
```

---

### Task 2: Install runtime + dev dependencies

**Files:**
- Modify: `landing/package.json`

- [ ] **Step 1: Install modules and libraries**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm install -D @nuxtjs/tailwindcss @nuxt/image @nuxt/fonts
npm install gsap lenis @vueuse/motion @vueuse/core
```

- [ ] **Step 2: Verify versions installed**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm ls gsap lenis @vueuse/motion
```

Expected: all three listed with versions.

- [ ] **Step 3: Commit**

```bash
git add package.json package-lock.json
git commit -m "chore: install animation and ui libraries"
```

---

### Task 3: Configure `nuxt.config.ts`

**Files:**
- Modify: `landing/nuxt.config.ts`

- [ ] **Step 1: Replace nuxt.config.ts content**

```ts
// landing/nuxt.config.ts
export default defineNuxtConfig({
  compatibilityDate: '2026-04-17',
  devtools: { enabled: true },
  modules: [
    '@nuxtjs/tailwindcss',
    '@nuxt/image',
    '@nuxt/fonts',
    '@vueuse/motion/nuxt',
  ],
  css: ['~/assets/css/tailwind.css'],
  app: {
    head: {
      htmlAttrs: { lang: 'es-MX' },
      title: 'GuisoGo — Menú digital y pedidos en tiempo real',
      meta: [
        { charset: 'utf-8' },
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'description', content: 'Plataforma para restaurantes mexicanos: menú digital por QR, pedidos por WhatsApp, tablero Kanban en tiempo real, cobertura por distancia y ganancia neta en vivo.' },
        { property: 'og:title', content: 'GuisoGo — Tu restaurante, en tiempo real' },
        { property: 'og:description', content: 'Menú digital, pedidos por WhatsApp y operación en tiempo real para restaurantes.' },
        { property: 'og:type', content: 'website' },
        { property: 'og:locale', content: 'es_MX' },
      ],
      link: [
        { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
      ],
    },
  },
  fonts: {
    families: [
      { name: 'Inter', provider: 'google', weights: [300, 400, 500, 600, 700, 800, 900] },
    ],
  },
  image: {
    format: ['avif', 'webp'],
  },
  nitro: {
    prerender: {
      crawlLinks: true,
      routes: ['/'],
    },
  },
})
```

- [ ] **Step 2: Verify dev server still boots**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run dev -- --port 3100
```

Expected: boots without module resolution errors. Kill.

- [ ] **Step 3: Commit**

```bash
git add nuxt.config.ts
git commit -m "feat: configure nuxt with modules, fonts, meta"
```

---

### Task 4: Configure Tailwind v4 with design tokens

**Files:**
- Create: `landing/assets/css/tailwind.css`
- Create: `landing/tailwind.config.ts`

- [ ] **Step 1: Create tailwind.css**

```css
/* landing/assets/css/tailwind.css */
@import "tailwindcss";

@theme {
  --color-ink: #0a0a0a;
  --color-paper: #ffffff;
  --color-brand: #FF5722;
  --color-brand-dark: #D84315;
  --color-ember: #FFB800;

  --font-sans: 'Inter', ui-sans-serif, system-ui, sans-serif;

  --radius-xl: 1.25rem;
  --radius-2xl: 1.75rem;
}

@layer base {
  :root {
    color-scheme: dark;
  }
  html {
    -webkit-font-smoothing: antialiased;
    text-rendering: optimizeLegibility;
  }
  body {
    background: var(--color-ink);
    color: var(--color-paper);
    font-family: var(--font-sans);
    font-feature-settings: "cv11", "ss01";
  }
  ::selection {
    background: var(--color-brand);
    color: var(--color-paper);
  }
}

@layer utilities {
  .grain {
    position: relative;
  }
  .grain::after {
    content: '';
    position: absolute;
    inset: 0;
    pointer-events: none;
    background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.5'/%3E%3C/svg%3E");
    opacity: 0.025;
    mix-blend-mode: overlay;
  }
  .dots-dark {
    background-image: radial-gradient(rgba(255,255,255,0.08) 1px, transparent 1px);
    background-size: 24px 24px;
  }
  .dots-light {
    background-image: radial-gradient(rgba(0,0,0,0.06) 1px, transparent 1px);
    background-size: 24px 24px;
  }
  .fire-gradient {
    background: linear-gradient(135deg, #FF5722 0%, #FFB800 100%);
  }
  .text-fire {
    background: linear-gradient(135deg, #FF5722 0%, #FFB800 100%);
    -webkit-background-clip: text;
    background-clip: text;
    color: transparent;
  }
  .tabular {
    font-variant-numeric: tabular-nums;
  }
}
```

- [ ] **Step 2: Verify Tailwind compiles**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run dev -- --port 3100
```

Expected: no CSS errors. Open http://localhost:3100, check body background is near-black. Kill.

- [ ] **Step 3: Commit**

```bash
git add assets/css/tailwind.css
git commit -m "feat: tailwind v4 config with brand tokens"
```

---

### Task 5: GSAP client plugin

**Files:**
- Create: `landing/plugins/gsap.client.ts`

- [ ] **Step 1: Create plugin**

```ts
// landing/plugins/gsap.client.ts
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

export default defineNuxtPlugin(() => {
  gsap.registerPlugin(ScrollTrigger)
  return {
    provide: {
      gsap,
      ScrollTrigger,
    },
  }
})
```

- [ ] **Step 2: Verify no SSR errors**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run dev -- --port 3100
```

Expected: boots clean. Kill.

- [ ] **Step 3: Commit**

```bash
git add plugins/gsap.client.ts
git commit -m "feat: register gsap with scrolltrigger (client only)"
```

---

### Task 6: Lenis smooth scroll in `app.vue`

**Files:**
- Create: `landing/composables/useLenis.ts`
- Modify: `landing/app.vue`

- [ ] **Step 1: Create `useLenis` composable**

```ts
// landing/composables/useLenis.ts
import Lenis from 'lenis'
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

export const useLenis = () => {
  const lenis = ref<Lenis | null>(null)

  onMounted(() => {
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return

    lenis.value = new Lenis({
      duration: 1.15,
      easing: (t) => Math.min(1, 1.001 - Math.pow(2, -10 * t)),
      smoothWheel: true,
    })

    lenis.value.on('scroll', ScrollTrigger.update)
    gsap.ticker.add((time) => lenis.value?.raf(time * 1000))
    gsap.ticker.lagSmoothing(0)
  })

  onBeforeUnmount(() => {
    lenis.value?.destroy()
  })

  return lenis
}
```

- [ ] **Step 2: Replace `app.vue`**

```vue
<!-- landing/app.vue -->
<script setup lang="ts">
useLenis()
</script>

<template>
  <div class="min-h-screen bg-ink text-paper antialiased">
    <NuxtPage />
  </div>
</template>
```

- [ ] **Step 3: Create initial `pages/index.vue` placeholder**

```vue
<!-- landing/pages/index.vue -->
<template>
  <main>
    <section class="min-h-screen flex items-center justify-center">
      <h1 class="text-6xl font-black">GuisoGo</h1>
    </section>
    <section class="min-h-screen bg-paper text-ink flex items-center justify-center">
      <p>Scroll test — debería sentirse suave</p>
    </section>
  </main>
</template>
```

- [ ] **Step 4: Verify smooth scroll works**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run dev -- --port 3100
```

Open http://localhost:3100, scroll with trackpad/mouse wheel, feel the Lenis smoothing. Kill.

- [ ] **Step 5: Commit**

```bash
git add composables/useLenis.ts app.vue pages/index.vue
git commit -m "feat: wire lenis smooth scroll at app root"
```

---

## Phase 2 — UI primitives & composables

### Task 7: `useReducedMotion` composable

**Files:**
- Create: `landing/composables/useReducedMotion.ts`

- [ ] **Step 1: Create composable**

```ts
// landing/composables/useReducedMotion.ts
export const useReducedMotion = () => {
  const prefers = ref(false)

  onMounted(() => {
    const mq = window.matchMedia('(prefers-reduced-motion: reduce)')
    prefers.value = mq.matches
    const handler = (e: MediaQueryListEvent) => { prefers.value = e.matches }
    mq.addEventListener('change', handler)
    onBeforeUnmount(() => mq.removeEventListener('change', handler))
  })

  return prefers
}
```

- [ ] **Step 2: Commit**

```bash
git add composables/useReducedMotion.ts
git commit -m "feat: add useReducedMotion composable"
```

---

### Task 8: `MagneticButton` component

**Files:**
- Create: `landing/components/ui/MagneticButton.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/ui/MagneticButton.vue -->
<script setup lang="ts">
import { gsap } from 'gsap'

const props = withDefaults(defineProps<{
  href?: string
  strength?: number
  variant?: 'fire' | 'ghost'
}>(), { strength: 0.35, variant: 'fire' })

const root = ref<HTMLElement | null>(null)
const inner = ref<HTMLElement | null>(null)
const reduced = useReducedMotion()

let xTo: ReturnType<typeof gsap.quickTo> | null = null
let yTo: ReturnType<typeof gsap.quickTo> | null = null

onMounted(() => {
  if (!root.value || !inner.value) return
  xTo = gsap.quickTo(inner.value, 'x', { duration: 0.5, ease: 'elastic.out(1, 0.4)' })
  yTo = gsap.quickTo(inner.value, 'y', { duration: 0.5, ease: 'elastic.out(1, 0.4)' })
})

const onMove = (e: MouseEvent) => {
  if (reduced.value || !root.value || !xTo || !yTo) return
  const rect = root.value.getBoundingClientRect()
  const x = (e.clientX - rect.left - rect.width / 2) * props.strength
  const y = (e.clientY - rect.top - rect.height / 2) * props.strength
  xTo(x)
  yTo(y)
}

const onLeave = () => {
  xTo?.(0)
  yTo?.(0)
}

const classes = computed(() => [
  'relative inline-flex items-center justify-center rounded-full px-8 py-4 text-base font-semibold transition-shadow will-change-transform',
  props.variant === 'fire'
    ? 'text-white fire-gradient shadow-[0_0_60px_rgba(255,87,34,0.45)] hover:shadow-[0_0_80px_rgba(255,87,34,0.65)]'
    : 'text-paper border border-white/20 hover:border-white/40 backdrop-blur',
])
</script>

<template>
  <component
    :is="href ? 'a' : 'button'"
    ref="root"
    :href="href"
    :target="href ? '_blank' : undefined"
    :rel="href ? 'noopener' : undefined"
    :class="classes"
    @mousemove="onMove"
    @mouseleave="onLeave"
  >
    <span ref="inner" class="inline-flex items-center gap-2">
      <slot />
    </span>
  </component>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/ui/MagneticButton.vue
git commit -m "feat: add MagneticButton with GSAP quickTo"
```

---

### Task 9: `RevealText` component (word-by-word reveal)

**Files:**
- Create: `landing/components/ui/RevealText.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/ui/RevealText.vue -->
<script setup lang="ts">
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

const props = withDefaults(defineProps<{
  text: string
  as?: string
  delay?: number
  stagger?: number
  trigger?: 'mount' | 'scroll'
}>(), { as: 'span', delay: 0, stagger: 0.04, trigger: 'scroll' })

const root = ref<HTMLElement | null>(null)
const reduced = useReducedMotion()

const words = computed(() => props.text.split(' '))

onMounted(() => {
  if (!root.value) return
  const els = root.value.querySelectorAll<HTMLElement>('[data-word]')

  if (reduced.value) {
    gsap.set(els, { opacity: 1, y: 0, filter: 'blur(0px)' })
    return
  }

  gsap.set(els, { opacity: 0, y: 24, filter: 'blur(8px)' })

  const anim = {
    opacity: 1,
    y: 0,
    filter: 'blur(0px)',
    duration: 0.9,
    ease: 'power3.out',
    stagger: props.stagger,
    delay: props.delay,
  }

  if (props.trigger === 'mount') {
    gsap.to(els, anim)
  } else {
    gsap.to(els, {
      ...anim,
      scrollTrigger: {
        trigger: root.value,
        start: 'top 85%',
        once: true,
      },
    })
  }
})
</script>

<template>
  <component :is="as" ref="root" class="inline-block">
    <span
      v-for="(word, i) in words"
      :key="i"
      data-word
      class="inline-block will-change-transform"
    >{{ word }}<span v-if="i < words.length - 1">&nbsp;</span></span>
  </component>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/ui/RevealText.vue
git commit -m "feat: add RevealText word-by-word reveal component"
```

---

### Task 10: `CountUp` component (odometer)

**Files:**
- Create: `landing/components/ui/CountUp.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/ui/CountUp.vue -->
<script setup lang="ts">
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

const props = withDefaults(defineProps<{
  to: number
  from?: number
  duration?: number
  prefix?: string
  suffix?: string
  decimals?: number
}>(), { from: 0, duration: 2, prefix: '', suffix: '', decimals: 0 })

const el = ref<HTMLSpanElement | null>(null)
const reduced = useReducedMotion()

const format = (n: number) => {
  const rounded = props.decimals > 0 ? n.toFixed(props.decimals) : Math.round(n).toString()
  return `${props.prefix}${rounded}${props.suffix}`
}

onMounted(() => {
  if (!el.value) return
  if (reduced.value) {
    el.value.textContent = format(props.to)
    return
  }
  const obj = { val: props.from }
  el.value.textContent = format(props.from)
  gsap.to(obj, {
    val: props.to,
    duration: props.duration,
    ease: 'power2.out',
    onUpdate: () => {
      if (el.value) el.value.textContent = format(obj.val)
    },
    scrollTrigger: {
      trigger: el.value,
      start: 'top 85%',
      once: true,
    },
  })
})
</script>

<template>
  <span ref="el" class="tabular">{{ format(from) }}</span>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/ui/CountUp.vue
git commit -m "feat: add CountUp odometer component"
```

---

### Task 11: `BentoCard` component (3D tilt)

**Files:**
- Create: `landing/components/ui/BentoCard.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/ui/BentoCard.vue -->
<script setup lang="ts">
import { gsap } from 'gsap'

const props = withDefaults(defineProps<{
  icon?: string
  title: string
  description: string
  span?: 'sm' | 'md' | 'lg' | 'wide' | 'tall'
  accent?: boolean
}>(), { span: 'sm', accent: false })

const card = ref<HTMLElement | null>(null)
const reduced = useReducedMotion()

const onMove = (e: MouseEvent) => {
  if (reduced.value || !card.value) return
  const rect = card.value.getBoundingClientRect()
  const x = (e.clientX - rect.left) / rect.width
  const y = (e.clientY - rect.top) / rect.height
  gsap.to(card.value, {
    rotateX: (0.5 - y) * 8,
    rotateY: (x - 0.5) * 8,
    duration: 0.4,
    ease: 'power2.out',
  })
}

const onLeave = () => {
  if (!card.value) return
  gsap.to(card.value, { rotateX: 0, rotateY: 0, duration: 0.6, ease: 'power2.out' })
}

const spanClass = computed(() => ({
  sm: 'md:col-span-1 md:row-span-1',
  md: 'md:col-span-2 md:row-span-1',
  lg: 'md:col-span-2 md:row-span-2',
  wide: 'md:col-span-3 md:row-span-1',
  tall: 'md:col-span-1 md:row-span-2',
}[props.span]))
</script>

<template>
  <div
    ref="card"
    :class="[
      'group relative rounded-3xl border p-8 transition-colors',
      spanClass,
      accent
        ? 'border-transparent fire-gradient text-white'
        : 'border-black/10 bg-white text-ink hover:border-brand/40',
    ]"
    style="transform-style: preserve-3d; perspective: 1000px;"
    @mousemove="onMove"
    @mouseleave="onLeave"
  >
    <div v-if="icon" class="mb-6 inline-flex h-12 w-12 items-center justify-center rounded-2xl" :class="accent ? 'bg-white/20' : 'bg-brand/10 text-brand'">
      <span class="material-symbols-outlined text-2xl">{{ icon }}</span>
    </div>
    <h3 class="text-2xl font-bold tracking-tight mb-3">{{ title }}</h3>
    <p class="text-base opacity-80 leading-relaxed">{{ description }}</p>
    <slot />
  </div>
</template>
```

- [ ] **Step 2: Load Material Symbols font in nuxt.config.ts**

Modify `landing/nuxt.config.ts` — add to `app.head.link`:

```ts
{ rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,400,0,0&display=swap' },
```

- [ ] **Step 3: Commit**

```bash
git add components/ui/BentoCard.vue nuxt.config.ts
git commit -m "feat: add BentoCard with 3D tilt and Material Symbols"
```

---

## Phase 3 — Mockup components

### Task 12: `PhoneMockup` frame

**Files:**
- Create: `landing/components/mockups/PhoneMockup.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/mockups/PhoneMockup.vue -->
<script setup lang="ts">
defineProps<{ dark?: boolean }>()
</script>

<template>
  <div class="relative mx-auto w-[280px] sm:w-[320px] aspect-[9/19.5]">
    <div class="absolute inset-0 rounded-[3rem] bg-black shadow-2xl ring-1 ring-white/10" />
    <div class="absolute inset-[6px] rounded-[2.7rem] bg-black" />
    <div class="absolute top-3 left-1/2 -translate-x-1/2 z-20 h-6 w-24 rounded-full bg-black border border-white/10" />
    <div
      class="absolute inset-[10px] rounded-[2.55rem] overflow-hidden"
      :class="dark ? 'bg-[#131f18]' : 'bg-[#f6f8f7]'"
    >
      <slot />
    </div>
  </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/mockups/PhoneMockup.vue
git commit -m "feat: add PhoneMockup frame"
```

---

### Task 13: `MenuScreen` mockup

**Files:**
- Create: `landing/components/mockups/MenuScreen.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/mockups/MenuScreen.vue -->
<script setup lang="ts">
const categories = ['Todos', 'Tacos', 'Bebidas', 'Postres']
const activeCat = ref('Tacos')

const products = [
  { name: 'Taco de pastor', price: 28, emoji: '🌮', desc: 'Con piña, cebolla y cilantro' },
  { name: 'Taco de bistec', price: 32, emoji: '🥩', desc: 'Con frijoles y queso' },
  { name: 'Quesadilla', price: 45, emoji: '🫓', desc: 'Tortilla de maíz a mano' },
  { name: 'Agua de horchata', price: 25, emoji: '🥤', desc: '500 ml, fría' },
]
</script>

<template>
  <div class="h-full w-full flex flex-col text-ink">
    <div class="px-4 pt-5 pb-3">
      <div class="flex items-center justify-between mb-4">
        <div>
          <p class="text-[10px] uppercase tracking-widest opacity-60">Ordena en línea</p>
          <h4 class="text-lg font-black">Tacos El Jefe</h4>
        </div>
        <div class="h-9 w-9 rounded-full bg-brand flex items-center justify-center text-white text-xs font-bold">3</div>
      </div>
      <div class="flex gap-2 overflow-hidden">
        <button
          v-for="c in categories"
          :key="c"
          :class="[
            'px-3 py-1.5 rounded-full text-xs font-semibold whitespace-nowrap transition-colors',
            c === activeCat ? 'bg-ink text-paper' : 'bg-black/5 text-ink',
          ]"
        >{{ c }}</button>
      </div>
    </div>
    <div class="flex-1 overflow-hidden px-4 pb-4 space-y-2.5">
      <div
        v-for="p in products"
        :key="p.name"
        class="flex items-center gap-3 bg-white rounded-2xl p-3 border border-black/5"
      >
        <div class="h-12 w-12 rounded-xl bg-brand/10 flex items-center justify-center text-2xl">{{ p.emoji }}</div>
        <div class="flex-1 min-w-0">
          <p class="text-sm font-semibold truncate">{{ p.name }}</p>
          <p class="text-[11px] opacity-60 truncate">{{ p.desc }}</p>
        </div>
        <div class="text-sm font-bold">${{ p.price }}</div>
      </div>
    </div>
    <div class="px-4 pb-5">
      <button class="w-full rounded-full fire-gradient text-white text-sm font-bold py-3.5 shadow-lg shadow-brand/40">
        Ver carrito · $133
      </button>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/mockups/MenuScreen.vue
git commit -m "feat: add MenuScreen mockup"
```

---

### Task 14: `KanbanBoard` animated mockup

**Files:**
- Create: `landing/components/mockups/KanbanBoard.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/mockups/KanbanBoard.vue -->
<script setup lang="ts">
type OrderCard = {
  id: string
  customer: string
  items: string
  total: number
  status: 0 | 1 | 2 | 3
  isNew?: boolean
}

const columns = [
  { key: 0, label: 'Recibidos', color: 'bg-amber-500' },
  { key: 1, label: 'En preparación', color: 'bg-blue-500' },
  { key: 2, label: 'En camino', color: 'bg-purple-500' },
  { key: 3, label: 'Entregados', color: 'bg-emerald-500' },
]

const orders = ref<OrderCard[]>([
  { id: '#1042', customer: 'María R.', items: '3 tacos pastor, 1 agua', total: 109, status: 1 },
  { id: '#1043', customer: 'Luis P.', items: '2 quesadillas, 1 coca', total: 115, status: 2 },
  { id: '#1041', customer: 'Ana S.', items: '1 taco bistec', total: 32, status: 3 },
  { id: '#1044', customer: 'Juan M.', items: '4 tacos pastor', total: 112, status: 0, isNew: true },
])

const ordersByCol = computed(() => (col: 0 | 1 | 2 | 3) => orders.value.filter(o => o.status === col))

defineExpose({ orders })
</script>

<template>
  <div class="grid grid-cols-4 gap-3 h-full text-ink">
    <div v-for="col in columns" :key="col.key" class="flex flex-col min-w-0">
      <div class="flex items-center gap-2 mb-3 px-1">
        <span :class="[col.color, 'h-2 w-2 rounded-full']" />
        <p class="text-[11px] font-bold uppercase tracking-wider">{{ col.label }}</p>
        <span class="ml-auto text-[11px] opacity-60 tabular">{{ ordersByCol(col.key as 0 | 1 | 2 | 3).length }}</span>
      </div>
      <div class="flex-1 space-y-2 overflow-hidden">
        <div
          v-for="o in ordersByCol(col.key as 0 | 1 | 2 | 3)"
          :key="o.id"
          :data-order-id="o.id"
          :class="[
            'rounded-xl border p-3 bg-white transition-all',
            o.isNew ? 'border-brand shadow-[0_0_0_3px_rgba(255,87,34,0.12)]' : 'border-black/10',
          ]"
        >
          <div class="flex items-center justify-between mb-1">
            <p class="text-xs font-bold tabular">{{ o.id }}</p>
            <p class="text-[11px] font-semibold opacity-70">${{ o.total }}</p>
          </div>
          <p class="text-xs font-semibold mb-0.5">{{ o.customer }}</p>
          <p class="text-[11px] opacity-60 line-clamp-2">{{ o.items }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/mockups/KanbanBoard.vue
git commit -m "feat: add KanbanBoard mockup"
```

---

### Task 15: `MapCoverage` SVG mockup

**Files:**
- Create: `landing/components/mockups/MapCoverage.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/mockups/MapCoverage.vue -->
<template>
  <svg viewBox="0 0 500 400" class="w-full h-auto">
    <defs>
      <pattern id="map-grid" width="40" height="40" patternUnits="userSpaceOnUse">
        <path d="M 40 0 L 0 0 0 40" fill="none" stroke="rgba(0,0,0,0.06)" stroke-width="1"/>
      </pattern>
      <radialGradient id="glow" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#FF5722" stop-opacity="0.28"/>
        <stop offset="100%" stop-color="#FF5722" stop-opacity="0"/>
      </radialGradient>
    </defs>
    <rect width="500" height="400" fill="#f8f8f8" />
    <rect width="500" height="400" fill="url(#map-grid)" />

    <!-- streets abstract -->
    <path d="M 0 140 Q 180 100 500 180" stroke="rgba(0,0,0,0.08)" stroke-width="24" fill="none" />
    <path d="M 80 0 Q 200 220 180 400" stroke="rgba(0,0,0,0.08)" stroke-width="20" fill="none" />
    <path d="M 0 280 Q 250 260 500 320" stroke="rgba(0,0,0,0.08)" stroke-width="16" fill="none" />

    <!-- branches -->
    <g data-branch="1">
      <circle cx="120" cy="180" r="80" fill="url(#glow)" />
      <circle cx="120" cy="180" r="10" fill="#FF5722" />
      <text x="120" y="210" text-anchor="middle" font-size="11" font-weight="700" fill="#0a0a0a">Centro</text>
    </g>
    <g data-branch="2">
      <circle cx="380" cy="130" r="10" fill="#FF5722" opacity="0.4" />
      <text x="380" y="160" text-anchor="middle" font-size="11" font-weight="700" fill="#0a0a0a" opacity="0.5">Norte</text>
    </g>
    <g data-branch="3">
      <circle cx="340" cy="310" r="10" fill="#FF5722" opacity="0.4" />
      <text x="340" y="340" text-anchor="middle" font-size="11" font-weight="700" fill="#0a0a0a" opacity="0.5">Sur</text>
    </g>

    <!-- customer -->
    <g data-customer>
      <circle cx="220" cy="220" r="20" fill="#0a0a0a" opacity="0.1">
        <animate attributeName="r" values="12;24;12" dur="2s" repeatCount="indefinite"/>
        <animate attributeName="opacity" values="0.2;0;0.2" dur="2s" repeatCount="indefinite"/>
      </circle>
      <circle cx="220" cy="220" r="8" fill="#0a0a0a" />
      <circle cx="220" cy="220" r="3" fill="#fff" />
    </g>

    <!-- route line -->
    <path
      data-route
      d="M 220 220 Q 170 210 120 180"
      stroke="#FF5722"
      stroke-width="3"
      stroke-dasharray="6 6"
      fill="none"
    />

    <!-- badge -->
    <g data-badge transform="translate(250, 200)">
      <rect x="0" y="-20" width="120" height="36" rx="18" fill="#0a0a0a" />
      <text x="60" y="3" text-anchor="middle" font-size="12" font-weight="700" fill="#fff">3.2 km · $30</text>
    </g>
  </svg>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/mockups/MapCoverage.vue
git commit -m "feat: add MapCoverage SVG mockup"
```

---

### Task 16: `DashboardStats` mockup

**Files:**
- Create: `landing/components/mockups/DashboardStats.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/mockups/DashboardStats.vue -->
<script setup lang="ts">
const items = [
  { name: 'Taco pastor', sold: 48, revenue: 1344, profit: 720 },
  { name: 'Quesadilla', sold: 22, revenue: 990, profit: 440 },
  { name: 'Agua horchata', sold: 31, revenue: 775, profit: 465 },
  { name: 'Taco bistec', sold: 18, revenue: 576, profit: 225 },
]
const maxProfit = Math.max(...items.map(i => i.profit))
</script>

<template>
  <div class="rounded-3xl bg-white/[0.04] border border-white/10 backdrop-blur-xl p-8 text-paper">
    <div class="flex items-start justify-between mb-8">
      <div>
        <p class="text-xs uppercase tracking-widest opacity-60 mb-2">Hoy</p>
        <div class="flex items-baseline gap-3">
          <span class="text-6xl font-black text-fire tabular">
            <CountUp :to="5200" prefix="$" />
          </span>
          <span class="text-lg opacity-60">vendido</span>
        </div>
      </div>
      <div class="text-right">
        <p class="text-xs uppercase tracking-widest opacity-60 mb-2">Ganancia neta</p>
        <p class="text-4xl font-black tabular">
          <CountUp :to="1850" prefix="$" />
        </p>
        <p class="text-xs text-emerald-400 font-semibold mt-1">+12% vs ayer</p>
      </div>
    </div>
    <div class="space-y-4">
      <div v-for="(it, i) in items" :key="it.name" class="grid grid-cols-[1fr_auto] items-center gap-4">
        <div>
          <div class="flex items-center justify-between mb-1">
            <span class="text-sm font-semibold">{{ it.name }}</span>
            <span class="text-xs opacity-60 tabular">{{ it.sold }} vendidos</span>
          </div>
          <div class="h-2 rounded-full bg-white/10 overflow-hidden">
            <div
              class="h-full fire-gradient"
              :style="{ width: `${(it.profit / maxProfit) * 100}%`, transitionDelay: `${i * 80}ms` }"
              v-motion
              :initial="{ scaleX: 0 }"
              :visible="{ scaleX: 1, transition: { duration: 900, delay: i * 80 } }"
              style="transform-origin: left;"
            />
          </div>
        </div>
        <div class="text-right">
          <p class="text-sm font-bold tabular">${{ it.profit }}</p>
          <p class="text-[10px] opacity-50">ganancia</p>
        </div>
      </div>
    </div>
  </div>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/mockups/DashboardStats.vue
git commit -m "feat: add DashboardStats mockup"
```

---

## Phase 4 — Sections

### Task 17: Shared config (WhatsApp CTA URL)

**Files:**
- Create: `landing/composables/useContact.ts`

- [ ] **Step 1: Create composable**

```ts
// landing/composables/useContact.ts
// Placeholder — replace with real number before launch.
const WHATSAPP_NUMBER = '5215555555555'
const DEFAULT_MESSAGE = 'Hola, vi GuisoGo y quiero saber más sobre cómo aplicarlo a mi restaurante.'

export const useContact = () => {
  const whatsappUrl = computed(() => {
    const msg = encodeURIComponent(DEFAULT_MESSAGE)
    return `https://wa.me/${WHATSAPP_NUMBER}?text=${msg}`
  })
  return { whatsappUrl }
}
```

- [ ] **Step 2: Commit**

```bash
git add composables/useContact.ts
git commit -m "feat: add useContact composable with whatsapp url"
```

---

### Task 18: `TheHero` section

**Files:**
- Create: `landing/components/sections/TheHero.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/TheHero.vue -->
<script setup lang="ts">
const { whatsappUrl } = useContact()
</script>

<template>
  <section class="relative min-h-screen overflow-hidden bg-ink text-paper grain">
    <div class="absolute inset-0 dots-dark opacity-60" />
    <div class="absolute -top-40 -right-40 h-[600px] w-[600px] rounded-full bg-brand/30 blur-[140px]" />
    <div class="absolute -bottom-40 -left-40 h-[500px] w-[500px] rounded-full bg-ember/20 blur-[120px]" />

    <nav class="relative z-10 max-w-7xl mx-auto flex items-center justify-between px-6 py-6">
      <div class="flex items-center gap-2">
        <div class="h-8 w-8 rounded-lg fire-gradient" />
        <span class="text-lg font-black tracking-tight">GuisoGo</span>
      </div>
      <div class="hidden md:flex items-center gap-8 text-sm font-medium opacity-80">
        <a href="#features" class="hover:opacity-100">Producto</a>
        <a href="#how" class="hover:opacity-100">Cómo funciona</a>
        <a href="#pricing" class="hover:opacity-100">Planes</a>
      </div>
      <MagneticButton :href="whatsappUrl" variant="ghost">
        <span class="material-symbols-outlined text-[18px]">chat</span>
        Hablar con ventas
      </MagneticButton>
    </nav>

    <div class="relative z-10 max-w-7xl mx-auto px-6 pt-8 md:pt-16 pb-24 grid md:grid-cols-[1.1fr_1fr] gap-10 items-center">
      <div>
        <div
          class="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-1.5 text-xs font-semibold mb-6"
          v-motion
          :initial="{ opacity: 0, y: 12 }"
          :enter="{ opacity: 1, y: 0, transition: { duration: 500 } }"
        >
          <span class="h-1.5 w-1.5 rounded-full bg-brand animate-pulse" />
          Nuevo · Para restaurantes mexicanos
        </div>
        <h1 class="text-[clamp(3rem,9vw,7.5rem)] leading-[0.95] font-black tracking-[-0.04em] mb-6">
          <RevealText text="Tu restaurante," trigger="mount" />
          <br />
          <span class="text-fire">
            <RevealText text="en tiempo real." trigger="mount" :delay="0.3" />
          </span>
        </h1>
        <p
          class="text-lg md:text-xl opacity-70 max-w-xl mb-10 leading-relaxed"
          v-motion
          :initial="{ opacity: 0, y: 20 }"
          :enter="{ opacity: 0.7, y: 0, transition: { duration: 700, delay: 800 } }"
        >
          Menú digital por QR, pedidos por WhatsApp y operación en Kanban en vivo. Sin apps. Sin comisiones. Sin fricción.
        </p>
        <div
          class="flex flex-wrap items-center gap-4"
          v-motion
          :initial="{ opacity: 0, y: 20 }"
          :enter="{ opacity: 1, y: 0, transition: { duration: 700, delay: 1000 } }"
        >
          <MagneticButton :href="whatsappUrl" variant="fire">
            <span class="material-symbols-outlined text-[20px]">bolt</span>
            Empieza con WhatsApp
          </MagneticButton>
          <a href="#how" class="text-sm font-semibold opacity-70 hover:opacity-100 flex items-center gap-1.5">
            Ver cómo funciona
            <span class="material-symbols-outlined text-[16px]">arrow_downward</span>
          </a>
        </div>
      </div>

      <div
        class="flex justify-center"
        v-motion
        :initial="{ opacity: 0, y: 40, rotate: -2 }"
        :enter="{ opacity: 1, y: 0, rotate: -3, transition: { duration: 1200, delay: 600 } }"
      >
        <PhoneMockup>
          <MenuScreen />
        </PhoneMockup>
      </div>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Verify in browser**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run dev -- --port 3100
```

Modify `pages/index.vue` temporarily to render `<TheHero />` to test. Verify hero looks correct. Kill.

- [ ] **Step 3: Commit**

```bash
git add components/sections/TheHero.vue
git commit -m "feat: add hero section"
```

---

### Task 19: `TheSocialProof` section

**Files:**
- Create: `landing/components/sections/TheSocialProof.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/TheSocialProof.vue -->
<script setup lang="ts">
const stats = [
  { value: 60, suffix: 's', label: 'para que tu cliente haga un pedido desde el menú' },
  { value: 0, suffix: '%', label: 'de comisión por pedido: todo se va directo a ti' },
  { value: 24, suffix: '/7', label: 'operación en vivo con WebSockets, sin refrescar' },
]
</script>

<template>
  <section class="relative bg-gradient-to-b from-ink via-ink to-paper py-24 md:py-32 text-paper">
    <div class="max-w-7xl mx-auto px-6">
      <p class="text-xs uppercase tracking-[0.3em] opacity-60 mb-4 text-center">Hecho para México</p>
      <h2 class="text-center text-4xl md:text-6xl font-black tracking-tight mb-16 md:mb-24">
        Piensa en grande.<br />
        <span class="opacity-50">Opera en chico.</span>
      </h2>
      <div class="grid md:grid-cols-3 gap-10 md:gap-4">
        <div
          v-for="(s, i) in stats"
          :key="i"
          class="text-center md:text-left md:pr-6 md:border-r md:border-white/10 last:border-0"
        >
          <div class="text-7xl md:text-8xl font-black text-fire leading-none mb-3 tabular">
            <CountUp :to="s.value" :suffix="s.suffix" />
          </div>
          <p class="text-base opacity-70 max-w-xs md:max-w-none">{{ s.label }}</p>
        </div>
      </div>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/sections/TheSocialProof.vue
git commit -m "feat: add social proof section"
```

---

### Task 20: `FeatureMenu` section (#1 — Sin app)

**Files:**
- Create: `landing/components/sections/FeatureMenu.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/FeatureMenu.vue -->
<template>
  <section id="features" class="relative bg-paper text-ink py-24 md:py-32 overflow-hidden">
    <div class="absolute inset-0 dots-light opacity-60" />
    <div class="relative max-w-7xl mx-auto px-6 grid md:grid-cols-2 gap-16 items-center">
      <div>
        <p class="text-xs uppercase tracking-[0.3em] text-brand font-bold mb-4">Sin app · Sin cuenta</p>
        <h2 class="text-4xl md:text-6xl font-black tracking-tight mb-6 leading-[1.05]">
          <RevealText text="Tu cliente pide en" />
          <span class="text-fire"><RevealText text="60 segundos." /></span>
        </h2>
        <p class="text-lg opacity-70 mb-8 leading-relaxed">
          Escanea el QR, ve el menú, arma su pedido y confirma por WhatsApp. No hay que descargar nada. No hay que crear cuenta. Si regresa mañana, sus datos ya están listos.
        </p>
        <ul class="space-y-4">
          <li v-for="(b, i) in [
            'Menú web con tu branding, funciona en cualquier celular',
            'Cookies recuerdan su dirección y pedidos 90 días',
            'Programación de pedidos con slots cada 30 minutos',
          ]" :key="i" class="flex gap-3 items-start"
          v-motion
          :initial="{ opacity: 0, x: -12 }"
          :visible="{ opacity: 1, x: 0, transition: { duration: 500, delay: i * 120 } }">
            <span class="material-symbols-outlined text-brand mt-0.5">check_circle</span>
            <span class="text-base opacity-90">{{ b }}</span>
          </li>
        </ul>
      </div>
      <div class="relative h-[560px]">
        <div class="absolute top-0 right-0 md:-right-10 rotate-6"
          v-motion
          :initial="{ opacity: 0, y: 40, rotate: 12 }"
          :visible="{ opacity: 0.85, y: 0, rotate: 6, transition: { duration: 900 } }">
          <PhoneMockup>
            <MenuScreen />
          </PhoneMockup>
        </div>
        <div class="absolute bottom-0 left-0 -rotate-3"
          v-motion
          :initial="{ opacity: 0, y: 60, rotate: -10 }"
          :visible="{ opacity: 1, y: 0, rotate: -3, transition: { duration: 900, delay: 200 } }">
          <PhoneMockup>
            <MenuScreen />
          </PhoneMockup>
        </div>
      </div>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/sections/FeatureMenu.vue
git commit -m "feat: add feature #1 section (no-app ordering)"
```

---

### Task 21: `FeatureKanban` section (#2 — pinned, scroll-driven)

**Files:**
- Create: `landing/components/sections/FeatureKanban.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/FeatureKanban.vue -->
<script setup lang="ts">
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

const root = ref<HTMLElement | null>(null)
const reduced = useReducedMotion()

onMounted(() => {
  if (!root.value || reduced.value) return

  const card1044 = root.value.querySelector<HTMLElement>('[data-order-id="#1044"]')
  const card1042 = root.value.querySelector<HTMLElement>('[data-order-id="#1042"]')
  const card1043 = root.value.querySelector<HTMLElement>('[data-order-id="#1043"]')

  if (!card1044 || !card1042 || !card1043) return

  const tl = gsap.timeline({
    scrollTrigger: {
      trigger: root.value,
      start: 'top top',
      end: '+=1800',
      pin: true,
      scrub: 1,
    },
  })

  // move #1044 Recibido → Preparación (col 0 → col 1)
  tl.to(card1044, { x: 'calc(100% + 12px)', duration: 1 }, 0.2)
    .to(card1044, { x: 0 }, 1.1)
  // move #1042 Preparación → En camino
  tl.to(card1042, { x: 'calc(100% + 12px)', duration: 1 }, 1.2)
    .to(card1042, { x: 0 }, 2.1)
  // move #1043 En camino → Entregados
  tl.to(card1043, { x: 'calc(100% + 12px)', duration: 1 }, 2.2)
    .to(card1043, { x: 0 }, 3.1)
})
</script>

<template>
  <section ref="root" class="relative bg-ink text-paper overflow-hidden">
    <div class="absolute inset-0 dots-dark opacity-40" />
    <div class="min-h-screen flex flex-col justify-center px-6 py-16 max-w-[1600px] mx-auto">
      <div class="grid md:grid-cols-[1fr_2fr] gap-10 md:gap-16 items-center">
        <div class="relative z-10">
          <p class="text-xs uppercase tracking-[0.3em] text-brand font-bold mb-4">Operación en vivo</p>
          <h2 class="text-4xl md:text-6xl font-black tracking-tight mb-6 leading-[1.05]">
            Kanban en tiempo real.<br />
            <span class="opacity-50">Sin refrescar.</span>
          </h2>
          <p class="text-lg opacity-70 leading-relaxed mb-6">
            Los pedidos entran, se mueven entre columnas y se entregan sin que tengas que recargar la página. WebSockets nativo, milisegundos de latencia.
          </p>
          <ul class="space-y-3 text-sm opacity-80">
            <li class="flex gap-2"><span class="material-symbols-outlined text-brand text-lg">notifications_active</span> Alerta sonora al llegar un pedido</li>
            <li class="flex gap-2"><span class="material-symbols-outlined text-brand text-lg">drag_indicator</span> Arrastra entre columnas para avanzar</li>
            <li class="flex gap-2"><span class="material-symbols-outlined text-brand text-lg">edit</span> Edita el pedido mientras está en preparación</li>
          </ul>
        </div>
        <div class="rounded-3xl bg-white/[0.03] border border-white/10 p-5 md:p-8 backdrop-blur-xl min-h-[440px]">
          <KanbanBoard />
        </div>
      </div>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/sections/FeatureKanban.vue
git commit -m "feat: add feature #2 kanban section with scroll-pinned animation"
```

---

### Task 22: `FeatureMap` section (#3)

**Files:**
- Create: `landing/components/sections/FeatureMap.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/FeatureMap.vue -->
<template>
  <section class="relative bg-paper text-ink py-24 md:py-32 overflow-hidden">
    <div class="absolute inset-0 dots-light opacity-60" />
    <div class="relative max-w-7xl mx-auto px-6 grid md:grid-cols-[1fr_1.3fr] gap-16 items-center">
      <div>
        <p class="text-xs uppercase tracking-[0.3em] text-brand font-bold mb-4">Multi-sucursal inteligente</p>
        <h2 class="text-4xl md:text-6xl font-black tracking-tight mb-6 leading-[1.05]">
          La sucursal<br />
          <span class="text-fire">más cercana gana.</span>
        </h2>
        <p class="text-lg opacity-70 mb-8 leading-relaxed">
          Calculamos distancia real por calles (no en línea recta). Tu cliente pide y el pedido cae automáticamente en la sucursal correcta. Sin selección manual. Sin errores.
        </p>
        <div class="grid grid-cols-2 gap-4">
          <div class="rounded-2xl border border-black/10 p-4">
            <p class="text-3xl font-black text-fire tabular">3.2<span class="text-base opacity-60"> km</span></p>
            <p class="text-xs opacity-60 mt-1">Distancia real por calle</p>
          </div>
          <div class="rounded-2xl border border-black/10 p-4">
            <p class="text-3xl font-black text-fire tabular">$30</p>
            <p class="text-xs opacity-60 mt-1">Envío según rango configurable</p>
          </div>
        </div>
      </div>
      <div
        v-motion
        :initial="{ opacity: 0, scale: 0.9 }"
        :visible="{ opacity: 1, scale: 1, transition: { duration: 900 } }"
        class="rounded-3xl overflow-hidden border border-black/10 shadow-2xl"
      >
        <MapCoverage />
      </div>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/sections/FeatureMap.vue
git commit -m "feat: add feature #3 map coverage section"
```

---

### Task 23: `FeatureProfit` section (#4)

**Files:**
- Create: `landing/components/sections/FeatureProfit.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/FeatureProfit.vue -->
<template>
  <section class="relative py-24 md:py-32 overflow-hidden text-paper bg-gradient-to-br from-ink via-[#1a0f08] to-[#2a1508]">
    <div class="absolute top-0 right-0 h-[500px] w-[500px] rounded-full bg-brand/20 blur-[140px]" />
    <div class="relative max-w-6xl mx-auto px-6">
      <div class="text-center max-w-3xl mx-auto mb-16">
        <p class="text-xs uppercase tracking-[0.3em] text-brand font-bold mb-4">Ganancia neta real</p>
        <h2 class="text-4xl md:text-7xl font-black tracking-tight mb-6 leading-[1.05]">
          No cuánto vendiste.<br />
          <span class="text-fire">Cuánto ganaste.</span>
        </h2>
        <p class="text-lg opacity-70 leading-relaxed">
          Cada producto tiene su costo de producción (solo tú lo ves). El dashboard te muestra la utilidad exacta por día, por sucursal, por producto. Sin hojas de cálculo.
        </p>
      </div>
      <div
        v-motion
        :initial="{ opacity: 0, y: 40 }"
        :visible="{ opacity: 1, y: 0, transition: { duration: 900 } }"
      >
        <DashboardStats />
      </div>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/sections/FeatureProfit.vue
git commit -m "feat: add feature #4 profit section"
```

---

### Task 24: `TheBento` section (8 features secundarias)

**Files:**
- Create: `landing/components/sections/TheBento.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/TheBento.vue -->
<script setup lang="ts">
const features = [
  { icon: 'confirmation_number', title: 'Cupones con códigos', description: 'Fijos o porcentaje, con caducidad y límite de usos. Anti-fraude incluido.', span: 'md' as const, accent: true },
  { icon: 'bolt', title: 'WebSockets en vivo', description: 'Pedidos entran al instante. Sin polling, sin lag.', span: 'sm' as const },
  { icon: 'edit_note', title: 'Edita pedidos en vuelo', description: 'Cambia items, dirección o método de pago mientras se prepara.', span: 'sm' as const },
  { icon: 'event', title: 'Horarios y festivos', description: 'Soporte nocturno, días festivos mexicanos y fechas especiales por restaurante.', span: 'md' as const },
  { icon: 'tune', title: 'Modificadores reutilizables', description: 'Catálogo de extras compartidos entre productos. O inline por producto.', span: 'sm' as const },
  { icon: 'store', title: 'Multi-sucursal', description: 'Gestiona hasta N sucursales con tarifas de envío por zona.', span: 'sm' as const },
  { icon: 'mail', title: 'Notificaciones por email', description: 'Recibe un correo por cada pedido nuevo, configurable por restaurante.', span: 'sm' as const },
  { icon: 'insights', title: 'Reportes por periodo', description: 'KPIs, cancelaciones, ventas por sucursal, por día y por producto.', span: 'sm' as const },
]
</script>

<template>
  <section class="relative bg-paper text-ink py-24 md:py-32">
    <div class="absolute inset-0 dots-light opacity-40" />
    <div class="relative max-w-7xl mx-auto px-6">
      <div class="text-center mb-16">
        <p class="text-xs uppercase tracking-[0.3em] text-brand font-bold mb-4">Todo lo que necesitas</p>
        <h2 class="text-4xl md:text-6xl font-black tracking-tight leading-[1.05]">
          Construido como<br />
          <span class="opacity-50">un producto completo.</span>
        </h2>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-4 auto-rows-[minmax(180px,auto)] gap-4">
        <BentoCard
          v-for="f in features"
          :key="f.title"
          :icon="f.icon"
          :title="f.title"
          :description="f.description"
          :span="f.span"
          :accent="f.accent"
        />
      </div>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/sections/TheBento.vue
git commit -m "feat: add bento grid section with 8 secondary features"
```

---

### Task 25: `TheHowItWorks` section

**Files:**
- Create: `landing/components/sections/TheHowItWorks.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/TheHowItWorks.vue -->
<script setup lang="ts">
import { gsap } from 'gsap'
import { ScrollTrigger } from 'gsap/ScrollTrigger'

const root = ref<HTMLElement | null>(null)
const line = ref<SVGPathElement | null>(null)
const reduced = useReducedMotion()

onMounted(() => {
  if (!root.value || !line.value || reduced.value) return
  const length = line.value.getTotalLength()
  gsap.set(line.value, { strokeDasharray: length, strokeDashoffset: length })
  gsap.to(line.value, {
    strokeDashoffset: 0,
    ease: 'none',
    scrollTrigger: {
      trigger: root.value,
      start: 'top 60%',
      end: 'bottom 70%',
      scrub: 0.8,
    },
  })
})

const steps = [
  { n: '01', title: 'Configura tu menú', desc: 'Categorías, productos, fotos y modificadores. En minutos.' },
  { n: '02', title: 'Comparte tu QR', desc: 'Imprime el QR o manda el link por redes. Tu cliente ya puede pedir.' },
  { n: '03', title: 'Recibe por WhatsApp', desc: 'Cada pedido llega a tu WhatsApp y al Kanban en vivo. Tú decides.' },
]
</script>

<template>
  <section id="how" ref="root" class="relative bg-ink text-paper py-24 md:py-40 overflow-hidden">
    <div class="max-w-7xl mx-auto px-6">
      <div class="text-center max-w-2xl mx-auto mb-20">
        <p class="text-xs uppercase tracking-[0.3em] text-brand font-bold mb-4">Simple</p>
        <h2 class="text-4xl md:text-6xl font-black tracking-tight leading-[1.05]">Empieza en un día.</h2>
      </div>
      <div class="relative">
        <svg class="hidden md:block absolute top-12 left-0 w-full h-8 overflow-visible" viewBox="0 0 1200 32" preserveAspectRatio="none">
          <path
            ref="line"
            d="M 60 16 L 1140 16"
            stroke="#FF5722"
            stroke-width="3"
            fill="none"
            stroke-linecap="round"
          />
        </svg>
        <div class="grid md:grid-cols-3 gap-12 md:gap-8 relative z-10">
          <div v-for="(s, i) in steps" :key="s.n" class="text-center md:text-left"
            v-motion
            :initial="{ opacity: 0, y: 30 }"
            :visible="{ opacity: 1, y: 0, transition: { duration: 600, delay: i * 150 } }">
            <div class="inline-flex h-16 w-16 items-center justify-center rounded-full fire-gradient text-white font-black text-xl mb-6">
              {{ s.n }}
            </div>
            <h3 class="text-2xl font-bold mb-3">{{ s.title }}</h3>
            <p class="opacity-70 leading-relaxed max-w-xs mx-auto md:mx-0">{{ s.desc }}</p>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/sections/TheHowItWorks.vue
git commit -m "feat: add how-it-works section with scroll-drawn line"
```

---

### Task 26: `TheCTA` section (final)

**Files:**
- Create: `landing/components/sections/TheCTA.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/TheCTA.vue -->
<script setup lang="ts">
const { whatsappUrl } = useContact()
</script>

<template>
  <section id="pricing" class="relative py-32 md:py-40 overflow-hidden fire-gradient text-white">
    <div class="absolute inset-0 grain opacity-80" />
    <div class="relative max-w-4xl mx-auto px-6 text-center">
      <h2 class="text-5xl md:text-8xl font-black tracking-[-0.04em] leading-[0.95] mb-8">
        <RevealText text="Tu restaurante," />
        <br />
        <RevealText text="sin límites." :delay="0.2" />
      </h2>
      <p class="text-xl md:text-2xl opacity-90 max-w-2xl mx-auto mb-12 leading-relaxed">
        Planes flexibles según tu operación. Sin comisiones ocultas. Te configuramos los límites que necesitas.
      </p>
      <div class="flex flex-col sm:flex-row items-center justify-center gap-4">
        <a
          :href="whatsappUrl"
          target="_blank"
          rel="noopener"
          class="group relative inline-flex items-center justify-center gap-3 rounded-full bg-ink text-paper px-10 py-5 text-lg font-bold shadow-2xl transition-transform hover:scale-105"
        >
          <span class="material-symbols-outlined text-[22px]">chat</span>
          Hablar por WhatsApp
          <span class="material-symbols-outlined text-[20px] transition-transform group-hover:translate-x-1">arrow_forward</span>
        </a>
      </div>
      <p class="mt-8 text-sm opacity-80">
        o escríbenos a <a href="mailto:hola@guisogo.mx" class="underline decoration-dotted">hola@guisogo.mx</a>
      </p>
    </div>
  </section>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/sections/TheCTA.vue
git commit -m "feat: add final CTA section"
```

---

### Task 27: `TheFooter` section

**Files:**
- Create: `landing/components/sections/TheFooter.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/sections/TheFooter.vue -->
<script setup lang="ts">
const year = new Date().getFullYear()
</script>

<template>
  <footer class="bg-ink text-paper border-t border-white/10">
    <div class="max-w-7xl mx-auto px-6 py-16 grid md:grid-cols-4 gap-10">
      <div class="md:col-span-2">
        <div class="flex items-center gap-2 mb-4">
          <div class="h-8 w-8 rounded-lg fire-gradient" />
          <span class="text-lg font-black tracking-tight">GuisoGo</span>
        </div>
        <p class="opacity-60 max-w-sm leading-relaxed">
          La plataforma para restaurantes mexicanos que quieren vender por digital sin perder el control.
        </p>
      </div>
      <div>
        <p class="text-xs uppercase tracking-[0.2em] opacity-50 mb-4">Producto</p>
        <ul class="space-y-2 text-sm opacity-80">
          <li><a href="#features" class="hover:text-brand">Features</a></li>
          <li><a href="#how" class="hover:text-brand">Cómo funciona</a></li>
          <li><a href="#pricing" class="hover:text-brand">Planes</a></li>
        </ul>
      </div>
      <div>
        <p class="text-xs uppercase tracking-[0.2em] opacity-50 mb-4">Contacto</p>
        <ul class="space-y-2 text-sm opacity-80">
          <li><a href="mailto:hola@guisogo.mx" class="hover:text-brand">hola@guisogo.mx</a></li>
          <li><a href="#" class="hover:text-brand">Instagram</a></li>
          <li><a href="#" class="hover:text-brand">TikTok</a></li>
        </ul>
      </div>
    </div>
    <div class="border-t border-white/10">
      <div class="max-w-7xl mx-auto px-6 py-6 text-xs opacity-50 flex flex-col md:flex-row gap-2 md:justify-between">
        <span>© {{ year }} GuisoGo. Hecho en México.</span>
        <span>v0.1 — landing</span>
      </div>
    </div>
  </footer>
</template>
```

- [ ] **Step 2: Commit**

```bash
git add components/sections/TheFooter.vue
git commit -m "feat: add footer"
```

---

## Phase 5 — Page assembly & polish

### Task 28: Compose `pages/index.vue`

**Files:**
- Modify: `landing/pages/index.vue`

- [ ] **Step 1: Replace content**

```vue
<!-- landing/pages/index.vue -->
<script setup lang="ts">
useSeoMeta({
  title: 'GuisoGo — Menú digital y pedidos en tiempo real para restaurantes',
  description: 'Plataforma para restaurantes mexicanos: menú por QR, pedidos por WhatsApp, Kanban en vivo, cobertura por distancia real y ganancia neta en tiempo real. Sin comisiones.',
  ogTitle: 'GuisoGo — Tu restaurante, en tiempo real',
  ogDescription: 'Menú digital, pedidos por WhatsApp y operación en tiempo real para restaurantes mexicanos.',
  ogLocale: 'es_MX',
  ogType: 'website',
})
</script>

<template>
  <main>
    <TheHero />
    <TheSocialProof />
    <FeatureMenu />
    <FeatureKanban />
    <FeatureMap />
    <FeatureProfit />
    <TheBento />
    <TheHowItWorks />
    <TheCTA />
    <TheFooter />
  </main>
</template>
```

- [ ] **Step 2: Verify full page renders**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run dev -- --port 3100
```

Open http://localhost:3100. Scroll from top to bottom. Expected:
- Hero loads with headline reveal and magnetic button
- Smooth scroll (Lenis) works
- Feature sections animate in order
- Kanban section pins and cards move as you scroll
- Bento grid tilts on hover
- Footer renders

Kill.

- [ ] **Step 3: Commit**

```bash
git add pages/index.vue
git commit -m "feat: compose full long-scroll landing page"
```

---

### Task 29: Sticky mobile WhatsApp CTA bar

**Files:**
- Create: `landing/components/ui/StickyMobileCTA.vue`
- Modify: `landing/app.vue`

- [ ] **Step 1: Create component**

```vue
<!-- landing/components/ui/StickyMobileCTA.vue -->
<script setup lang="ts">
const { whatsappUrl } = useContact()
const visible = ref(false)

onMounted(() => {
  const onScroll = () => { visible.value = window.scrollY > window.innerHeight * 0.8 }
  window.addEventListener('scroll', onScroll, { passive: true })
  onBeforeUnmount(() => window.removeEventListener('scroll', onScroll))
})
</script>

<template>
  <Transition
    enter-active-class="transition duration-500"
    enter-from-class="translate-y-full opacity-0"
    enter-to-class="translate-y-0 opacity-100"
    leave-active-class="transition duration-300"
    leave-from-class="translate-y-0 opacity-100"
    leave-to-class="translate-y-full opacity-0"
  >
    <div v-if="visible" class="md:hidden fixed bottom-4 inset-x-4 z-50">
      <a
        :href="whatsappUrl"
        target="_blank"
        rel="noopener"
        class="flex items-center justify-center gap-2 rounded-full fire-gradient text-white font-bold py-4 shadow-2xl shadow-brand/40"
      >
        <span class="material-symbols-outlined text-[20px]">chat</span>
        Hablar por WhatsApp
      </a>
    </div>
  </Transition>
</template>
```

- [ ] **Step 2: Mount in `app.vue`**

```vue
<!-- landing/app.vue -->
<script setup lang="ts">
useLenis()
</script>

<template>
  <div class="min-h-screen bg-ink text-paper antialiased">
    <NuxtPage />
    <StickyMobileCTA />
  </div>
</template>
```

- [ ] **Step 3: Verify in mobile viewport (DevTools)**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run dev -- --port 3100
```

In Chrome DevTools, toggle device mode to iPhone 12. Scroll down: sticky CTA bar should slide up from bottom after 80% viewport scroll. Kill.

- [ ] **Step 4: Commit**

```bash
git add components/ui/StickyMobileCTA.vue app.vue
git commit -m "feat: add sticky mobile whatsapp cta bar"
```

---

### Task 30: Responsive & reduced-motion pass

**Files:**
- Modify: any section needing tweaks

- [ ] **Step 1: Test all breakpoints**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run dev -- --port 3100
```

In DevTools, test:
- iPhone SE (375px): hero stacks vertical, phone mockup centered, nav links hidden, hamburger placeholder OK if missing (navbar gracefully compresses)
- iPad (768px): hero 2-col, bento 2-cols
- Desktop (1440px): everything reads correctly

Fix any overflow or broken layouts you find. Typical fixes:
- Add `overflow-hidden` on sections containing rotated mockups
- Wrap long headlines in `break-words`
- Hide heavy mockup stacks under `md:block`

- [ ] **Step 2: Test reduced motion**

In DevTools → Rendering → Emulate CSS media feature → `prefers-reduced-motion: reduce`. Reload. Expected:
- No Lenis smoothing
- No ScrollTrigger pin
- Text appears statically (no reveal)
- Buttons don't magnetize
- CountUp shows final value immediately

- [ ] **Step 3: Commit any fixes**

```bash
git add -A
git commit -m "fix: responsive and reduced-motion polish"
```

---

### Task 31: Favicon and OG image placeholders

**Files:**
- Create: `landing/public/favicon.svg`
- Create: `landing/public/og-image.png` (placeholder)

- [ ] **Step 1: Create favicon**

```bash
cat > /Users/sebas/Documents/GuisoGo/landing/public/favicon.svg << 'EOF'
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 32 32">
  <defs>
    <linearGradient id="g" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" stop-color="#FF5722"/>
      <stop offset="100%" stop-color="#FFB800"/>
    </linearGradient>
  </defs>
  <rect width="32" height="32" rx="8" fill="url(#g)"/>
  <text x="16" y="22" text-anchor="middle" font-family="system-ui" font-weight="900" font-size="16" fill="white">G</text>
</svg>
EOF
```

- [ ] **Step 2: Placeholder OG image note**

Add to root README a note: OG image at `public/og-image.png` (1200×630) needs to be designed separately. For now, omit or use a solid color PNG. Skip for this task — will replace before launch.

- [ ] **Step 3: Commit**

```bash
git add public/favicon.svg
git commit -m "feat: add favicon"
```

---

### Task 32: Production build smoke test

**Files:** none

- [ ] **Step 1: Generate static build**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npm run generate
```

Expected: builds to `.output/public/` without errors. Output includes `index.html` and assets.

- [ ] **Step 2: Preview build locally**

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npx serve .output/public -l 3100
```

Open http://localhost:3100, verify everything works same as dev. Kill.

- [ ] **Step 3: Run Lighthouse (optional but recommended)**

In Chrome DevTools → Lighthouse tab → "Mobile" + "Performance/Accessibility/SEO" → Analyze. Target:
- Performance ≥ 85 (90+ ideal)
- Accessibility ≥ 95
- Best Practices ≥ 95
- SEO ≥ 95

If Performance < 85, common fixes:
- Delay GSAP imports further with dynamic import in components that need them
- Add `loading="lazy"` to all `<NuxtImg>` below the fold
- Reduce initial CSS with Tailwind v4 defaults

- [ ] **Step 4: Commit any perf fixes**

```bash
git add -A
git commit -m "perf: lighthouse polish"
```

---

### Task 33: Deploy configuration for Vercel

**Files:**
- Create: `landing/vercel.json`
- Create: `landing/.gitignore` (if not already present)

- [ ] **Step 1: Create vercel.json**

```json
{
  "buildCommand": "npm run generate",
  "outputDirectory": ".output/public",
  "framework": "nuxtjs",
  "installCommand": "npm install"
}
```

- [ ] **Step 2: Ensure `.gitignore` covers Nuxt**

Verify `landing/.gitignore` contains at least:

```
node_modules
.nuxt
.output
.env
.DS_Store
dist
```

If missing entries, add them.

- [ ] **Step 3: Commit**

```bash
git add vercel.json .gitignore
git commit -m "chore: add vercel deploy config"
```

- [ ] **Step 4: Deploy instructions (manual, user decision)**

Do not run automatically — ask user if they want to deploy now. If yes:

```bash
cd /Users/sebas/Documents/GuisoGo/landing
npx vercel
# Follow prompts: link/create project, set team, etc.
```

Otherwise, push to a GitHub repo and connect from Vercel dashboard.

---

## Self-review checklist

Covered from spec:
- ✅ Stack: Nuxt 3 + Vue 3 TS + Tailwind v4 + GSAP + Lenis + @vueuse/motion (Task 1–6)
- ✅ Warm Tech visual direction with tokens (Task 4)
- ✅ All 10 sections (Tasks 18–27)
- ✅ All mockups: PhoneMockup, MenuScreen, KanbanBoard, MapCoverage, DashboardStats (Tasks 12–16)
- ✅ UI primitives: MagneticButton, RevealText, CountUp, BentoCard (Tasks 8–11)
- ✅ Scroll-pinned Kanban animation (Task 21)
- ✅ Scroll-drawn line in HowItWorks (Task 25)
- ✅ SEO metadata (Tasks 3, 28)
- ✅ Responsive + reduced motion (Task 30)
- ✅ Sticky mobile CTA (Task 29)
- ✅ Build + deploy prep (Tasks 32–33)

**Open decisions (§12 of spec) deferred:**
- WhatsApp number → placeholder `5215555555555` in `useContact.ts`. Replace before launch.
- Logo → tipographic placeholder with gradient square. Replace if final logo exists.
- Domain → handled at Vercel deploy time, no code change needed.
- Screenshots vs Vue mockups → decision: 100% Vue mockups (done).
- Analytics → deferred entirely.
