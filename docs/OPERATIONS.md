# PideAquí — Manual de Operaciones

> Runbooks para operar el backend en producción: billing, incidentes, backups, rotación de secrets.
> Audiencia: operador de guardia / DevOps / soporte.

---

## 1. Billing — Cron jobs y su efecto

Corren automáticamente via `schedule:run`. Si detectas que un restaurante terminó en el estado equivocado, es casi siempre porque uno de estos no corrió.

| Cron | Frecuencia | Efecto si NO corre |
|---|---|---|
| `billing:check-grace` | Diario 06:00 | Restaurantes con gracia expirada siguen operando gratis |
| `billing:check-canceled` | Diario 06:05 | Suscripciones canceladas cuyo período terminó siguen activas |
| `billing:send-reminders` | Diario 08:00 | Clientes no reciben recordatorio antes de expirar gracia |
| `billing:reconcile` | Diario 03:00 | Drift entre estado local y Stripe se acumula silenciosamente |
| `billing:apply-pending-downgrades` | Cada hora | Downgrades programados no se aplican al inicio del ciclo |

### Verificar que el scheduler está vivo

```bash
./vendor/bin/sail artisan schedule:list
```

En Laravel Cloud: en el compute cluster, toggle **"Scheduler"** debe estar ON.
En servidor propio:

```bash
crontab -l | grep schedule:run
# debe mostrar: * * * * * cd /var/www/pideaqui-back && php artisan schedule:run >> /dev/null 2>&1
```

### Ejecutar un cron manualmente

```bash
./vendor/bin/sail artisan billing:check-grace
./vendor/bin/sail artisan billing:reconcile
```

---

## 2. Stripe — Problemas comunes

### 2.1 Webhook no aplicado

Síntoma: usuario pagó pero su restaurante sigue en `grace_period`.

```bash
# Ver últimos eventos en la tabla de dedup
./vendor/bin/sail artisan tinker
>>> StripeWebhookEvent::latest()->take(10)->get(['stripe_event_id', 'type', 'processed_at']);

# Ver si Stripe tiene el evento como "delivered"
# stripe.com → Developers → Webhooks → [tu endpoint] → ver historial
```

**Si el evento llegó pero no se aplicó:** revisar logs (`storage/logs/laravel.log`) para el fallo.

**Si el evento no llegó:** usar Stripe CLI para reenviar:

```bash
stripe events retrieve evt_xxx
stripe events resend evt_xxx
```

### 2.2 Webhook llegando duplicado

Está manejado. La tabla `stripe_webhook_events` tiene unique constraint en `stripe_event_id`. Si Stripe reenvía el mismo evento, el segundo insert falla y se ignora silenciosamente.

### 2.3 Subscription "huérfana" (sin plan local)

Síntoma: webhook crea suscripción pero el `stripe_price` no coincide con ningún plan local.

Comportamiento actual (desde Abr 2026): se crea `BillingAudit` con acción `subscription_without_plan` visible al SuperAdmin en `/super/billing-audits` (si existe) o vía tinker:

```bash
>>> BillingAudit::where('action', 'subscription_without_plan')->latest()->first();
```

Remedio: correr `billing:sync-stripe` para crear Products/Prices que falten, luego `billing:reconcile`.

### 2.4 Rotación de `STRIPE_WEBHOOK_SECRET`

```
1. stripe.com → Webhooks → [endpoint de producción] → Reveal signing secret
2. Copiar el nuevo whsec_...
3. Actualizar STRIPE_WEBHOOK_SECRET en el env de producción
4. Redeploy o reload config
5. Probar con: stripe events resend <un_evento_reciente>
6. Verificar que el evento se procesó (tabla stripe_webhook_events)
```

No desactives el endpoint viejo hasta confirmar que el nuevo funciona.

---

## 3. Reverb (WebSockets) — Incidentes

### 3.1 Tablero no se actualiza en vivo

El admin envuelve `broadcast()` en try/catch desde Mar 2026. **El status del pedido se persiste igual** aunque Reverb esté caído. Solo se pierde la actualización instantánea en otros navegadores; un refresh resuelve.

### 3.2 Reverb no levanta

**Desarrollo local:**

```bash
./vendor/bin/sail artisan reverb:start
# Debe exponer :8080. Si el puerto está ocupado:
lsof -i :8080
kill <PID>
```

**Producción (systemd):**

```bash
sudo systemctl status pideaqui-reverb
sudo journalctl -u pideaqui-reverb -n 100
sudo systemctl restart pideaqui-reverb
```

**Laravel Cloud:** revisa el estado del WebSocket cluster en el canvas.

### 3.3 Eventos no llegan al cliente

1. Verificar que `BROADCAST_CONNECTION=reverb` en env.
2. Verificar que el usuario autorizado se suscribe al canal correcto (`restaurant.{id}` o `.pos`).
3. Revisar `routes/channels.php` — la lógica de autorización debe retornar `true` o un objeto usuario.
4. Revisar consola del navegador: Echo debe estar `connected` (no `disconnected` ni `error`).

---

## 4. Base de datos — Backups y restore

### 4.1 Backups

**En Laravel Cloud**: los backups del cluster PostgreSQL son automáticos. Verifica en Resources → Database → Backups.

**En VPS propio**: configurar manualmente con `pg_dump`. Ejemplo con cron:

```bash
0 2 * * * docker compose -f docker-compose.prod.yml exec -T pgsql \
  pg_dump -U $DB_USERNAME $DB_DATABASE | gzip > /backups/pideaqui-$(date +\%F).sql.gz
```

Rotación recomendada: mantener últimos 30 días diarios + último de cada mes (12 meses).

### 4.2 Restore

```bash
gunzip -c /backups/pideaqui-2026-04-01.sql.gz | \
  docker compose -f docker-compose.prod.yml exec -T pgsql psql -U $DB_USERNAME $DB_DATABASE
```

**Antes de restaurar sobre producción**: siempre hacer backup de la DB actual primero.

### 4.3 Consultas útiles para soporte

```sql
-- Restaurante por access_token
SELECT id, name, status, billing_mode FROM restaurants WHERE access_token = '<token>';

-- Pedidos de un restaurante en las últimas 24h
SELECT id, status, delivery_type, total, created_at
FROM orders
WHERE restaurant_id = ? AND created_at > now() - interval '24 hours'
ORDER BY created_at DESC;

-- Eventos Stripe procesados para un customer
SELECT stripe_event_id, type, processed_at
FROM stripe_webhook_events
WHERE payload::text LIKE '%<stripe_customer_id>%'
ORDER BY processed_at DESC LIMIT 20;

-- Audit de ediciones de un pedido
SELECT action, reason, user_id, created_at, changes
FROM order_audits
WHERE order_id = ?
ORDER BY created_at DESC;
```

---

## 5. Rotación de secrets

### 5.1 `access_token` del restaurante

```
1. SuperAdmin → Restaurantes → [restaurante] → "Regenerar token"
2. Confirmar en modal.
3. Copiar el nuevo token.
4. Enviarlo al dueño del restaurante: debe actualizar el VITE_RESTAURANT_TOKEN
   en el .env de su cliente SPA y re-desplegar.
```

El token viejo queda inválido **inmediatamente**. Pedidos en vuelo que usen el token viejo fallarán con `401`.

### 5.2 Password del admin del restaurante

Desde SuperAdmin: Restaurantes → [restaurante] → "Reset password". Genera una contraseña temporal y la envía por email (si `MAIL_MAILER` está configurado).

### 5.3 `APP_KEY`

**No rotar** salvo que haya compromiso confirmado. Rotar `APP_KEY` invalida todas las sesiones y **rompe los tokens encriptados previamente** (ej. `remember_me`, datos de `encrypt()`).

Si hay que rotar:

```bash
php artisan key:generate --show      # muestra sin persistir
# pegar en .env → APP_KEY=base64:...
# redeploy
```

### 5.4 Claves de Google Maps

Frontend (`VITE_GOOGLE_MAPS_KEY`): restringida por HTTP referrer. Backend (`GOOGLE_MAPS_API_KEY`): restringida por IP del servidor. Si una se expone:

```
1. Google Cloud Console → APIs & Services → Credentials
2. Regenerar la key
3. Actualizar la variable en el env correspondiente
4. Redeploy
```

---

## 6. Monitoreo básico (qué revisar diariamente)

- `storage/logs/laravel.log` — warnings de billing (`subscription_without_plan`, fallos de `broadcast()`)
- Tabla `billing_audits` — acciones administrativas recientes
- Dashboard de Stripe — eventos fallidos, intentos de pago rechazados
- Tabla `stripe_webhook_events` — `WHERE processed_at IS NULL` no debería crecer

---

## 7. Contactos y accesos

> Completar esta sección con los datos reales del equipo. Placeholder:

| Servicio | Rol | Responsable |
|---|---|---|
| Laravel Cloud | Admin principal | _(completar)_ |
| Stripe Dashboard | Admin comercial | _(completar)_ |
| Google Cloud (Maps) | Facturación | _(completar)_ |
| Dominio + DNS | — | _(completar)_ |
| Vercel (landing) | — | _(completar)_ |

---

## 8. Procedimiento de incidente estándar

1. **Detectar**: error reportado por cliente / alert en log / métrica fuera de rango.
2. **Aislar**: ¿afecta a un restaurante o a todos? Con el `restaurant_id` en mano, reproducir en logs.
3. **Contener**: si es billing/pago, marcar el restaurante en `grace_period` manualmente para no bloquearlo mientras diagnostico.
4. **Resolver**: aplicar fix, correr tests afectados, desplegar.
5. **Post-mortem**: documentar en `CHANGELOG.md` con fecha + descripción del fix + tests nuevos.

---

_Operations Manual — PideAquí Backend — v1.0 — Abril 2026_
