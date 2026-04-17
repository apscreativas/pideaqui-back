# GuisoGo — Landing Page Promocional (Diseño)

**Fecha:** 2026-04-17
**Autor:** Claude + Sebas
**Estado:** Aprobado (pendiente plan de implementación)

## 1. Contexto y objetivo

GuisoGo es un SaaS multi-tenant para restaurantes mexicanos (menú digital + gestión de pedidos + delivery). Actualmente no existe un sitio público de marketing. Se requiere una landing page promocional que:

- Posicione el producto en Google para búsquedas tipo "sistema de pedidos para restaurante", "menú digital QR", etc.
- Convierta visitantes en leads vía WhatsApp al negocio.
- Muestre el producto con calidad visual de producto consumer tech (referencia: apple.com).

La landing vive como **proyecto independiente** en `/Users/sebas/Documents/GuisoGo/landing/`, hermano de `admin/` (Laravel) y `client/` (cliente SPA). No comparte build ni dependencias con ellos.

## 2. Alcance

**Dentro del alcance:**
- Single-page long-scroll con 10 secciones (detalladas en §5).
- Mockups animados de las pantallas clave del producto (Kanban admin, menú cliente, dashboard, mapa de cobertura).
- Animaciones scroll-driven, micro-interacciones, motion design.
- CTA único: WhatsApp del negocio con mensaje pre-llenado.
- Responsive: desktop (≥1024px) prioridad, tablet y mobile con layouts adaptados.
- SEO básico: meta tags, Open Graph, sitemap, structured data.
- i18n: español México (único idioma).

**Fuera del alcance:**
- Blog, casos de estudio, páginas legales (términos/privacidad) — salen después.
- Formulario de contacto, waitlist, signup integrado.
- Tabla de precios pública (decisión: planes flexibles → WhatsApp).
- Autenticación, backend propio (el sitio es 100% estático).
- Integración con analytics avanzado (se agrega en iteración siguiente).

## 3. Stack técnico

| Capa | Tecnología | Razón |
|---|---|---|
| Framework | Nuxt 3 (SSG mode vía `nuxt generate`) | SEO, consistencia con Vue del resto del proyecto |
| UI | Vue 3 `<script setup>` + TypeScript | Tipado, API moderna |
| Estilos | Tailwind CSS v4 via `@nuxtjs/tailwindcss` | Consistencia con admin/client |
| Animación scroll | GSAP 3 + ScrollTrigger | Estándar para scroll-driven (Apple, Stripe) |
| Motion declarativo | @vueuse/motion | Micro-interacciones simples |
| Smooth scroll | Lenis | Momentum suave tipo Apple |
| Imágenes | @nuxt/image | WebP/AVIF responsive automático |
| Fuentes | @nuxt/fonts (Inter Variable self-hosted) | Sin FOUT, performance |
| Deploy | Vercel (primario) o Netlify | CDN global, preview deploys, SSG nativo |

## 4. Dirección visual — "Warm Tech"

Rechazamos Apple puro (monocromo frío) porque no conecta con el público (restauranteros mexicanos). Buscamos **rigor estructural de Apple + calor del producto**.

### Paleta

- `#0a0a0a` — negro profundo (fondo hero y secciones dramáticas)
- `#ffffff` — blanco puro (secciones de producto, contraste)
- `#FF5722` — naranja primario (acento, CTAs, highlights)
- `#FFB800` — ámbar (gradiente "fuego" con naranja)
- `#D84315` — naranja oscuro (hover states, depth)
- `rgba(255,255,255,0.10)` — bordes sutiles sobre negro
- `rgba(0,0,0,0.06)` — bordes sutiles sobre blanco

### Tipografía

- **Fuente única:** Inter Variable (self-hosted, pesos 100–900).
- **Escala hero:** `clamp(3rem, 10vw, 9rem)` con `font-weight: 900` y `letter-spacing: -0.04em`.
- **Escala cuerpo:** 18px base, 22px destacados, 15px micro.
- **Números tabulares** (`font-variant-numeric: tabular-nums`) en stats y dashboard.

### Detalles visuales "tech"

- Grid de puntos de fondo (SVG pattern sutil, opacidad 0.04 sobre negro, 0.03 sobre blanco).
- Glow radial naranja detrás de elementos hero.
- Noise texture muy sutil (SVG filter, opacidad 0.02) para evitar el look "demasiado limpio".
- Bordes de 1px consistentes.
- Gradiente "fuego": `linear-gradient(135deg, #FF5722 0%, #FFB800 100%)` para CTAs primarios.

## 5. Estructura de secciones

Single-page long-scroll, 10 secciones en orden:

### 01. HERO (bg: negro)
- Headline gigante (clamp, 900 weight): ej. *"Tu restaurante, en tiempo real"*.
- Subheadline (22px, blanco 80%): una frase explicando el producto.
- CTA primario: botón magnético gradiente fuego → WhatsApp.
- CTA secundario: link "Ver cómo funciona" ancla a §8.
- Mockup de teléfono flotando a la derecha (desktop) o abajo (mobile) con menú cliente animado en loop.
- Background: grid de puntos + glow naranja radial en esquina.

### 02. SOCIAL PROOF / STATS (bg: transición negro→blanco)
- Headline mediano: *"Hecho para restaurantes mexicanos"*.
- 3 stats grandes con count-up al entrar viewport:
  - "60s" — tiempo promedio de pedido del cliente.
  - "0%" — comisión por pedido (directo a WhatsApp, sin marketplace).
  - "24/7" — tiempo real con WebSockets.

### 03. FEATURE HERO #1 — "Sin app, sin fricción" (bg: blanco, split 50/50)
- Izquierda: eyebrow naranja, headline, párrafo, 3 bullets animados (stagger).
- Derecha: stack 3D de 3 pantallas (QR → menú → carrito) que se despliegan al scrollear.

### 04. FEATURE HERO #2 — "Kanban tiempo real" (bg: negro, full-width pin)
- `ScrollTrigger pin + scrub`: la sección se queda fija mientras el scroll mueve la animación interna.
- Mockup del tablero Kanban a pantalla completa.
- Timeline de animación (scroll-scrubbed):
  1. Aparece tarjeta nueva en "Recibido" con pulse rojo.
  2. Se arrastra a "Preparación".
  3. Se mueve a "En camino".
  4. Aparece 2ª tarjeta simultánea.
- Copy lateral pequeño explicando.

### 05. FEATURE HERO #3 — "Sucursal automática + mapa" (bg: blanco)
- Mapa estilizado SVG (no Google Maps real) con 3 pines de sucursal + 1 pin de cliente.
- Animación scroll: cliente aparece → línea punteada se dibuja a la sucursal más cercana → aparece badge "3.2 km · $30 envío".
- Copy explicativo al lado.

### 06. FEATURE HERO #4 — "Ganancia neta real" (bg: gradiente negro → naranja oscuro)
- Dashboard mockup grande al centro.
- Números con odometer count-up: "$5,200 vendido" → "$1,850 ganancia neta".
- Desglose animado por producto (barras que crecen).

### 07. BENTO GRID — Features secundarias (bg: blanco)
- Grid asimétrico de 8 cards (tamaños mixtos, estilo Apple bento).
- Cada card: icono (Material Symbols), título, 1 línea descripción.
- Features: Cupones, WebSockets tiempo real, Edición pedidos, Horarios + fechas especiales, Modificadores reutilizables, Multi-sucursal, Email notificaciones, Reportes y estadísticas.
- Hover: tilt 3D sutil (10° max), border glow naranja.

### 08. CÓMO FUNCIONA (bg: negro)
- Timeline horizontal de 3 pasos:
  1. Configura tu menú.
  2. Comparte tu QR o link.
  3. Recibe pedidos por WhatsApp.
- Línea naranja que se dibuja horizontalmente al scrollear (SVG stroke-dashoffset animado).
- En mobile: timeline vertical.

### 09. CTA FINAL (bg: gradiente naranja fuego)
- Headline: *"Tu restaurante, sin límites"*.
- Subcopy: *"Planes flexibles según tu operación. Sin comisiones ocultas."*.
- Botón WhatsApp gigante magnético, centrado.
- Texto micro: *"O escríbenos: hola@guisogo.mx"* (placeholder email).

### 10. FOOTER (bg: negro)
- Logo GuisoGo.
- 3 columnas: Producto (anclas a secciones), Legal (placeholders), Contacto (WhatsApp, email).
- Copyright + año dinámico.
- Redes sociales (iconos placeholder).

## 6. Arquitectura de componentes

```
landing/
├─ app.vue                    # layout raíz, Lenis init, cursor custom
├─ nuxt.config.ts             # modules, CSS, app meta
├─ assets/
│  ├─ css/tailwind.css        # config Tailwind v4 + custom tokens
│  ├─ fonts/                  # Inter variable woff2
│  └─ img/                    # imágenes, patrones SVG
├─ components/
│  ├─ ui/
│  │  ├─ MagneticButton.vue   # botón con pull al cursor
│  │  ├─ CountUp.vue          # odometer para stats
│  │  ├─ RevealText.vue       # text reveal con GSAP SplitText
│  │  ├─ ScrollPinSection.vue # wrapper para secciones pinned
│  │  └─ BentoCard.vue        # card del grid bento con tilt
│  ├─ mockups/
│  │  ├─ PhoneMockup.vue      # frame de teléfono con slot interno
│  │  ├─ MenuScreen.vue       # pantalla del menú cliente
│  │  ├─ KanbanBoard.vue      # tablero Kanban animado
│  │  ├─ MapCoverage.vue      # mapa SVG con pines
│  │  └─ DashboardStats.vue   # dashboard con números
│  └─ sections/
│     ├─ TheHero.vue
│     ├─ TheSocialProof.vue
│     ├─ FeatureMenu.vue
│     ├─ FeatureKanban.vue
│     ├─ FeatureMap.vue
│     ├─ FeatureProfit.vue
│     ├─ TheBento.vue
│     ├─ TheHowItWorks.vue
│     ├─ TheCTA.vue
│     └─ TheFooter.vue
├─ composables/
│  ├─ useLenis.ts             # smooth scroll setup
│  ├─ useScrollReveal.ts      # helper GSAP ScrollTrigger
│  ├─ useMagnetic.ts          # magnetic effect para botones
│  └─ useReducedMotion.ts     # respeta prefers-reduced-motion
├─ pages/
│  └─ index.vue               # compone todas las secciones
├─ public/
│  ├─ favicon.svg
│  └─ og-image.png
└─ package.json
```

**Principios:**
- Cada sección es un componente autónomo, se puede reordenar cambiando imports en `index.vue`.
- Los mockups NO dependen de las secciones — se pueden reutilizar o probar aislados.
- Composables encapsulan lógica de animación (no duplicar GSAP setup en cada componente).

## 7. Animación — patrones y reglas

**Inicialización:**
- Lenis se inicializa una sola vez en `app.vue`, se conecta al ticker de GSAP.
- GSAP se importa solo en cliente (`plugins/gsap.client.ts` para evitar SSR issues).

**Patrones reutilizables:**
- **RevealText**: text split por palabra, fade + blur → sharp, stagger 0.04s.
- **CountUp**: número animado con `gsap.to({ val: 0 }, { val: target })` y update del DOM.
- **ScrollPin**: section con `ScrollTrigger.create({ pin: true, scrub: 1 })`.
- **MagneticButton**: sigue cursor en radio de 80px con `gsap.quickTo`.
- **Bento tilt**: perspectiva 1000px, rotateX/Y según mouse position.

**Performance:**
- `will-change: transform` solo mientras anima, se limpia después.
- ScrollTriggers se refrescan en resize (`ScrollTrigger.refresh()`).
- Imágenes lazy con `@nuxt/image` + `loading="lazy"` default.
- `prefers-reduced-motion: reduce` → desactiva Lenis, pin, scrub; mantiene fades simples cortos (0.2s).

## 8. Responsive

- **Desktop-first breakpoints:** ≥1280 (full), ≥1024 (tablet landscape), ≥768 (tablet portrait), <768 (mobile).
- Layouts split 50/50 en desktop → stack vertical en <1024.
- Mockups se escalan con clamp, no se rompen.
- Animaciones pin en mobile se convierten a fades estándar (menos pesadas).
- CTA WhatsApp siempre accesible: sticky bottom bar en mobile desde la sección 02 en adelante.

## 9. SEO y metadata

- `<title>`: "GuisoGo — Menú digital y pedidos en tiempo real para restaurantes".
- Meta description: 155 chars con keywords clave.
- Open Graph: image 1200x630 del hero.
- Twitter Card: `summary_large_image`.
- JSON-LD structured data: `SoftwareApplication` schema.
- `sitemap.xml` generado por Nuxt (módulo `@nuxtjs/sitemap`).
- `robots.txt` público.

## 10. Criterios de éxito

- **Lighthouse Performance ≥ 90** en mobile y desktop.
- **Lighthouse Accessibility ≥ 95** (contraste, alt text, ARIA donde aplique).
- **First Contentful Paint < 1.5s** en 4G simulado.
- **Cumulative Layout Shift < 0.1**.
- Landing responsive funciona en iPhone SE (375px) y desktop 1440px.
- CTAs WhatsApp funcionan con mensaje pre-llenado en español.
- Build SSG termina sin errores (`nuxt generate`).

## 11. Riesgos y mitigaciones

| Riesgo | Mitigación |
|---|---|
| GSAP + Nuxt SSR → hydration errors | GSAP solo en `.client.ts` plugins, usar `<ClientOnly>` donde sea necesario |
| Animaciones pesadas en mobile low-end | Detectar `prefers-reduced-motion` + feature-detect device memory API |
| Mockups se ven genéricos | Usar screenshots reales del admin/client como referencia, no inventar |
| Copy débil | Copy review con usuario antes de implementar (no genera texto marketing solo) |
| Tailwind v4 beta features | Fijar versión específica, no usar features experimentales |

## 12. Decisiones abiertas (para resolver en plan)

- Dominio final (ej. guisogo.mx, getguisogo.com).
- Número de WhatsApp real para el CTA.
- Logo final de GuisoGo (¿ya existe? si no, placeholder tipográfico).
- Screenshots reales vs mockups 100% recreados en Vue.
- Analytics (Plausible, Google Analytics, ninguno por ahora).
