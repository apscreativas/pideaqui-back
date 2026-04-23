# Contribuir a PideAquí Backend

> Reglas mínimas para contribuir al repo `pideaqui-back`. Si vas a hacer cambios significativos, lee también `CLAUDE.md` (reglas de agentes y convenciones detalladas).

---

## 1. Setup local

Sigue [README.md](./README.md). En resumen: Laravel Herd (PHP nativo) + PostgreSQL servido como servicio de Herd. El sitio queda en `https://pideaqui-backend.test`.

---

## 2. Ramas

| Branch | Rol |
|---|---|
| `main` | Rama de producción. PRs hacen merge aquí. |
| `pagos_stripe` | Histórica del trabajo de billing (Mar 2026). No mergear ni borrar sin acordar. |
| `test` | Experimental. No se despliega. |

Para trabajar:

```bash
git checkout main
git pull
git checkout -b feat/<feature-corta>
```

Prefiere nombres como `feat/coupons-history`, `fix/pos-rounding`, `docs/architecture-v3`.

---

## 3. Convenciones de código

### PHP

Definidas en `CLAUDE.md` (Laravel Boost guidelines). Puntos clave:

- Usar `php`, `artisan` y `composer` **directamente** — el entorno lo sirve Herd (PHP 8.4 nativo, sin Docker).
- Middleware en `bootstrap/app.php` (Laravel 12, no `Http/Kernel.php`).
- Crear archivos con `php artisan make:...` (nunca a mano).
- Siempre pasar `--no-interaction` a comandos Artisan.
- Validación en **Form Request classes**, nunca inline.
- API Resources para todos los endpoints REST.
- Eloquent relationships tipadas; evitar `DB::` facade.
- Constructor property promotion + explicit return types.
- Casts en método `casts()`, no propiedad `$casts` (Laravel 12 convention).

### Vue

- Siempre Composition API con `<script setup>` (salvo si el archivo existente ya usa Options API).
- Tailwind v4 para estilos; sin CSS propio salvo casos justificados.
- Inertia para admin/superadmin, no mezclar con `axios` a rutas internas.

### Commit messages

En inglés, imperativo, ≤72 caracteres en la primera línea. Ejemplos existentes:

- `Align dashboard period with Subscription screen`
- `Recalculate coupon discount when editing order items`
- `Block canceled restaurants in real-time instead of relying on cron`

---

## 4. Tests

**Obligatorio** al agregar/modificar features:

```bash
# Suite completa
php artisan test --compact

# Archivo específico
php artisan test --compact tests/Feature/CouponTest.php

# Por filtro
php artisan test --compact --filter=test_discount_applies_only_to_subtotal
```

- Usa **PHPUnit**, no Pest. Si ves un test en Pest, conviértelo a PHPUnit.
- Prefiere Feature tests. Unit tests solo para clases aisladas (ej. `HaversineService`).
- Cada feature debe tener tests de happy path + failure path + edge case.
- Tenant isolation: todo recurso nuevo debe tener un test que verifique que un admin de otro restaurante recibe `404`.

### No bajar cobertura

Hoy tenemos **619 tests**. No se aceptan PRs que dejen features nuevas sin test.

---

## 5. Linter / Formatter

Laravel Pint. Después de modificar PHP:

```bash
vendor/bin/pint --dirty --format agent
```

No usar `--test`. Solo ejecutar para fixear.

---

## 6. Documentación obligatoria

> Estas reglas vienen de `CLAUDE.md` y son vinculantes.

Si tu cambio:

| Impacta a… | Actualizar… |
|---|---|
| Una feature documentada en `docs/modules/XX-*.md` | Ese archivo |
| El schema de BD (nueva tabla, columna, FK, índice) | `docs/DATABASE.md` |
| Arquitectura (nuevo servicio, middleware, pattern) | `docs/ARCHITECTURE.md` |
| Agrega feature nueva | Crear `docs/modules/19-*.md` + enlazar en `INDEX.md` |
| Una capacidad del roadmap | `docs/ROADMAP.md` |

Toda sesión de trabajo debe cerrar con:

- `CHANGELOG.md` actualizado con una entrada para el cambio (fecha + resumen).
- Si es un cambio operacional (cron, secret, deploy), también `docs/OPERATIONS.md`.

**Nunca dejar documentación desincronizada.**

---

## 7. Cómo abrir un PR

1. Crea la rama desde `main` actualizada.
2. Haz commits atómicos con mensajes en imperativo.
3. Corre `pint` y los tests relevantes.
4. Actualiza la documentación según la tabla anterior.
5. Push y abre PR a `main`.
6. En la descripción del PR, incluye:
   - Qué cambia y por qué.
   - Tests relevantes que agregaste/modificaste.
   - Archivos de documentación tocados.
   - Si afecta operaciones/secrets, nota específica.

---

## 8. No hacer sin acordar

- No correr `config:cache` en desarrollo (ha borrado DBs de producción en el pasado — ver memoria del equipo).
- No bypass de Pint/tests con `--no-verify`.
- No modificar `compose.yaml` ni dependencias de `composer.json`/`package.json` sin aprobar.
- No subir archivos con datos reales a `.env.example` (emails, tokens, números).
- No borrar tests del directorio `tests/`.
- No hacer `force push` a `main`.

---

_Contributing Guide — PideAquí Backend — v1.0 — Abril 2026_
