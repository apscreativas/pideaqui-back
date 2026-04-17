# PRD — PideAqui

## Product Requirements Document

### Plataforma SaaS de Menú Digital y Gestión de Pedidos Multi-Restaurante

---

| Campo          | Detalle           |
| -------------- | ----------------- |
| **Versión**    | 2.2 — MVP         |
| **Fecha**      | Febrero 2026      |
| **Estado**     | En revisión       |
| **Responsivo** | Sí — Mobile first |

---

## Tabla de Contenidos

1. [Resumen Ejecutivo](#1-resumen-ejecutivo)
2. [Objetivos del Producto](#2-objetivos-del-producto)
3. [Usuarios y Roles](#3-usuarios-y-roles)
4. [Las Tres Interfaces](#4-las-tres-interfaces)
5. [Conceptos Clave del Sistema](#5-conceptos-clave-del-sistema)
6. [Módulos y Funcionalidades — MVP](#6-módulos-y-funcionalidades--mvp)
7. [Pantallas de la Aplicación](#7-pantallas-de-la-aplicación)
8. [Reglas de Negocio](#8-reglas-de-negocio)
9. [Servicios Externos Requeridos](#9-servicios-externos-requeridos)

---

## 1. Resumen Ejecutivo

PideAqui es una **plataforma SaaS multi-restaurante** de menú digital y gestión de pedidos para negocios de comida en México.

El sistema se compone de **tres interfaces**:

1. **Interfaz del Cliente Final** — Proyecto frontend independiente. Se despliega una instancia por restaurante y se comunica con el backend mediante API.
2. **Panel del Administrador del Restaurante** — Gestión de menú, sucursales, pedidos y configuración. Solo accesible por el administrador del restaurante (no hay roles adicionales de staff/operador).
3. **Panel del SuperAdmin (SaaS)** — Creación de restaurantes, configuración de límites y monitoreo global.

Cada restaurante puede tener **múltiples sucursales**. El menú es compartido entre todas las sucursales, pero cada una tiene su propia dirección, teléfono WhatsApp, horarios y zona de cobertura.

El cliente final **no necesita registrarse ni crear cuenta**. Si ha pedido anteriormente, sus datos se guardan en cookies del navegador para agilizar futuros pedidos.

El diferenciador principal es la simplicidad del flujo para el cliente (3 pasos), la integración nativa con WhatsApp, el soporte multi-sucursal con detección automática de la sucursal más cercana, y el modelo **sin comisiones por venta**.

Se utiliza **Google Maps** para mostrar el mapa interactivo, pin y obtener coordenadas, y **Google Distance Matrix API** para calcular la distancia real por calles entre el cliente y la sucursal. Se emplea un **pre-filtro Haversine** (sin costo) para minimizar las llamadas a la API de Google. La dirección del cliente **se ingresa manualmente** (no se obtiene por geocoding inverso del pin).

---

## 2. Objetivos del Producto

### Objetivo General

Proveer a los restaurantes una herramienta accesible, rápida y sin comisiones para digitalizar su menú, recibir pedidos y gestionar entregas con soporte multi-sucursal, con la mejor experiencia posible para el cliente final.

### Objetivos Específicos

- Reducir el flujo del cliente a exactamente **3 pasos**: elegir productos, entrega y ubicación, pago y confirmación.
- **No requerir registro ni cuenta** del cliente. Guardar sus datos en cookies para pedidos futuros.
- Soportar **múltiples sucursales** por restaurante con detección automática de la más cercana.
- Calcular **distancia real por calles** (no en línea recta) entre cliente y sucursal usando Google Distance Matrix API, con pre-filtro Haversine para minimizar costos.
- Permitir al SuperAdmin **limitar pedidos mensuales y cantidad de sucursales** de manera manual por cada restaurante.
- **Restringir pedidos por distancia**: si la sucursal más cercana supera el radio de cobertura, informar al cliente que no hay servicio en su zona.
- Validar **horarios de operación** y mostrar mensaje claro si el cliente intenta pedir fuera de horario.
- Permitir al cliente **programar su pedido**: lo antes posible o seleccionar una hora futura en intervalos de 30 minutos.
- Automatizar el envío de la comanda al WhatsApp de la sucursal correspondiente.
- Usar **Google Maps** para el mapa interactivo con pin y coordenadas, y **Google Distance Matrix** para cálculo de distancias reales.
- Ser responsivo y funcionar correctamente en móvil, tablet y escritorio.

---

## 3. Usuarios y Roles

| Rol                    | Tipo    | Interfaz                             | Descripción                                                                                                                                                                                                                                                               |
| ---------------------- | ------- | ------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Super Admin**        | Interno | Panel SuperAdmin                     | Administra la plataforma completa. Crea restaurantes, configura límites de pedidos mensuales y sucursales manualmente por restaurante.                                                                                                                                    |
| **Admin Restaurante**  | Tenant  | Panel Admin Restaurante              | Dueño o encargado. Es la **única persona** que accede al panel. Configura el menú (compartido entre sucursales), crea sucursales si los límites lo permiten, visualiza y gestiona el estatus de pedidos, y configura métodos de pago. No puede crear pedidos manualmente. |
| **Cliente / Comensal** | Público | Frontend del cliente (independiente) | Usuario final. Accede al menú, se le asigna la sucursal más cercana y realiza pedidos. La comunicación post-pedido es directamente por WhatsApp. **No requiere cuenta ni registro.**                                                                                      |

---

## 4. Las Tres Interfaces

### 4.1 Interfaz del Cliente Final (Frontend Independiente)

- Es un **proyecto frontend completamente separado** del backend. Se comunica exclusivamente mediante API.
- Se despliega **una instancia por restaurante**. Las configuraciones del restaurante (nombre, token de acceso, logo) se definen en las variables de entorno del proyecto.
- El cliente accede desde un link o código QR en su navegador. **No necesita descargar ninguna app.**
- El frontend no tiene concepto de multitenancy; el backend identifica al restaurante por el token de acceso.

### 4.2 Panel del Administrador del Restaurante

- Interfaz web dentro del sistema principal.
- Acceso por correo y contraseña.
- Permite gestionar: menú, sucursales, pedidos, métodos de pago, configuración general.
- **Solo el administrador del restaurante** accede a este panel. No existen roles adicionales de staff u operador.
- Cada administrador solo ve los datos de **su propio restaurante**.

### 4.3 Panel del SuperAdmin

- Interfaz web dentro del sistema principal.
- Acceso restringido, sin registro público.
- Permite: crear restaurantes, configurar límites manualmente, monitorear uso, activar/desactivar restaurantes, ver estadísticas globales.

---

## 5. Conceptos Clave del Sistema

### 5.1 Restaurantes y Sucursales

- Un **restaurante** (tenant) es la entidad principal. Cada restaurante tiene un nombre, slug y logo.
- Cada restaurante puede tener **una o más sucursales**.
- **El menú es global por restaurante**: categorías, productos y modificadores se comparten entre todas las sucursales. No se configura por sucursal.
- Cada **sucursal** tiene atributos independientes:
  - Nombre descriptivo (ej. "Sucursal Centro", "Sucursal Norte").
  - Dirección y coordenadas geográficas.
  - **Número de WhatsApp propio** — los pedidos se envían al WhatsApp de la sucursal asignada.
  - Horarios de operación por día de la semana (apertura y cierre).
  - Estado activo/inactivo.

### 5.1.1 Tarifas de Envío por Rangos de Distancia

Las tarifas de envío se configuran **a nivel de restaurante** (no por sucursal) mediante **rangos de distancia con precio fijo**. El administrador define tramos de kilómetros y el costo correspondiente.

**Ejemplo de configuración:**

| Desde (km) | Hasta (km) | Costo de envío |
| ---------- | ---------- | -------------- |
| 0          | 2          | $0 (gratis)    |
| 2          | 5          | $30            |
| 5          | 10         | $60            |
| 10         | 15         | $90            |

- El administrador puede crear **tantos rangos como necesite**.
- Los rangos deben ser **contiguos y sin huecos** (el "Desde" de un rango es el "Hasta" del anterior).
- Si la distancia del cliente excede el último rango configurado, se considera **fuera de cobertura**.
- El radio máximo de cobertura se determina automáticamente por el límite superior del último rango.
- El costo de envío se determina buscando en qué rango cae la distancia real calculada.

### 5.2 Límites por Restaurante (SaaS)

El SuperAdmin configura **manualmente** para cada restaurante los siguientes límites:

| Límite                        | Descripción                                                          |
| ----------------------------- | -------------------------------------------------------------------- |
| **Pedidos mensuales máximos** | Cantidad máxima de pedidos que el restaurante puede recibir por mes. |
| **Sucursales máximas**        | Cantidad máxima de sucursales que el restaurante puede crear.        |

- No existen planes ni features. Los límites se asignan de forma individual a cada restaurante.
- Cuando un restaurante **alcanza su límite mensual de pedidos**, se bloquean nuevos pedidos y se muestra un mensaje informativo al cliente.
- El conteo de pedidos se **reinicia el día 1 de cada mes**.
- Si el restaurante intenta crear más sucursales de las permitidas, el sistema lo impide.

### 5.3 Clientes sin Registro

- El cliente **nunca necesita crear cuenta ni registrarse**.
- Al completar un pedido, sus datos se guardan en **cookies del navegador**:
  - Nombre completo.
  - Teléfono.
  - Dirección completa.
  - Referencias de entrega.
  - Coordenadas de su ubicación.
  - Un token identificador para reconocerlo en futuros pedidos.
- Expiración sugerida de las cookies: **90 días**.
- En pedidos posteriores, los datos se **pre-rellenan automáticamente** desde las cookies.
- En la base de datos se guarda un registro ligero del cliente vinculado al token de la cookie.

### 5.4 Detección de Sucursal Más Cercana

La detección de sucursal **no ocurre al cargar el menú**, sino en el **Paso 2** del flujo de pedido, cuando el cliente elige su tipo de entrega. Esto minimiza las llamadas a la API de Google.

**Si el restaurante tiene una sola sucursal activa**, se asigna automáticamente sin necesidad de geolocalización ni cálculo de distancia.

**Si el restaurante tiene múltiples sucursales activas:**

1. Al llegar al Paso 2, se solicita permiso de geolocalización al navegador.
2. Si el cliente otorga permiso, se obtienen sus coordenadas.
3. Se realiza un **pre-filtro por distancia en línea recta** (fórmula Haversine, sin costo de API) para descartar sucursales lejanas y quedarse con las 2-3 más cercanas.
4. Solo con esas candidatas se llama a **Google Distance Matrix API** para obtener la **distancia real por calles**.
5. Se asigna automáticamente la **sucursal más cercana por ruta real**.
6. Si la distancia a la sucursal más cercana **excede el radio de cobertura para domicilio**, se muestra un mensaje: _"Lamentablemente no tenemos servicio de entrega en tu zona. La sucursal más cercana se encuentra a X km."_ El cliente aún puede elegir "Recoger en local" o "Comer aquí".

> **Importante:** Se usa Haversine (sin costo) como pre-filtro y Google Distance Matrix solo para las sucursales candidatas finales, minimizando llamadas a la API de Google.

### 5.5 Horarios de Operación

- Cada sucursal configura sus horarios de apertura y cierre **por día de la semana**.
- Puede marcar días como **cerrado**.
- Antes de permitir al cliente hacer un pedido, se verifican los horarios de la sucursal asignada.
- **Si está fuera de horario**, se muestra: _"En este momento estamos cerrados. Nuestro horario de hoy es de HH:MM a HH:MM. ¡Te esperamos!"_
- Si el restaurante permite pedidos programados, se ofrece programar para una hora dentro del horario.
- Sin horario configurado para la sucursal, se asume **siempre disponible**.

### 5.6 Programación de Pedidos

El cliente puede elegir cuándo quiere recibir su pedido:

- **Lo antes posible** — Opción por defecto.
- **Programar para más tarde** — Se despliega un selector de horario en **intervalos de 30 minutos** dentro del horario de operación de la sucursal.
  - Ejemplo: si son las 2:15 PM y la sucursal cierra a las 10:00 PM, las opciones serían: 3:00 PM, 3:30 PM, 4:00 PM... hasta 9:30 PM.
  - Solo se muestran horarios futuros (no pasados).
- La hora programada se incluye en la comanda enviada por WhatsApp.

---

## 6. Módulos y Funcionalidades — MVP

### 6.1 Flujo de Pedido Online — 3 Pasos (Cliente Final)

El cliente accede al menú desde un link o QR en su navegador móvil, **sin login, sin registro, sin descargar ninguna app**.

---

#### PASO 1 — Seleccionar del Menú

- El cliente accede al menú directamente. **No se solicita geolocalización en este paso** — el menú es compartido entre todas las sucursales, por lo que no se necesita determinar la sucursal aún.
- El cliente navega por categorías y productos del menú.
- Al tocar un producto se abre la **vista del producto** (modal o pantalla completa):
  - Foto grande, descripción y precio base.
  - **Grupos de modificadores** con precio adicional, por ejemplo:
    - Tipo de tortilla: `Maíz $0` / `Harina +$15`
    - Tamaño: `Normal $0` / `Grande +$20`
  - **Campo de nota libre** en texto (`sin aguacate`, `extra picante`, `sin cebolla`). No afecta el precio.
  - Selector de cantidad.
  - Botón **Agregar al carrito**.
- Botón flotante o barra inferior siempre visible con total del carrito y número de productos.
- El cliente puede seguir navegando y agregando más productos.
- Botón **Continuar** para avanzar al Paso 2.

---

#### PASO 2 — ¿A dónde te lo llevamos?

El cliente selecciona el tipo de entrega:

| Opción                  | Comportamiento                                                                                                                    |
| ----------------------- | --------------------------------------------------------------------------------------------------------------------------------- |
| 🛵 **A domicilio**      | Activa flujo de ubicación GPS + mapa + detección de sucursal + cálculo de envío                                                   |
| 🏃 **Recoger en local** | Muestra dirección y teléfono de la sucursal (si hay varias, se selecciona la más cercana por GPS o el cliente elige manualmente). |
| 🍽️ **Comer aquí**       | Si hay varias sucursales, el cliente selecciona en cuál se encuentra. Avanza al Paso 3.                                           |

**Flujo de ubicación para domicilio:**

1. Si ya hay datos guardados en cookies (dirección, coordenadas), se pre-rellenan y se usa la coordenada guardada para detectar la sucursal más cercana.
2. Si no hay cookies, se solicita permiso de GPS al dispositivo.
3. Muestra mapa interactivo (Google Maps) con pin en la ubicación actual del cliente.
4. El cliente puede mover el pin para ajustar su ubicación exacta.
5. **La dirección se ingresa manualmente** en el formulario. No se obtiene automáticamente del pin (sin geocoding inverso). El pin sirve únicamente para obtener las coordenadas.
6. El cliente llena: dirección completa (calle, número, colonia, ciudad).
7. Campo de **referencias**: entre calles, color de casa, número de depto, etc.
8. Con las coordenadas del pin, se **detecta la sucursal más cercana** (pre-filtro Haversine + Google Distance Matrix solo para las candidatas finales).
9. Se muestra al cliente: sucursal asignada, distancia real por calles en km, tiempo estimado de entrega y **costo de envío según los rangos configurados por el restaurante**.
10. **Validación de cobertura**: si el cliente está fuera de la zona de cobertura de la sucursal más cercana, se le notifica y no puede continuar con domicilio. Se ofrecen alternativas (recoger en local, comer aquí).

**Programación del pedido** (se muestra en este paso):

- **"¿Para cuándo tu pedido?"**
  - 🕐 **Lo antes posible** (opción por defecto).
  - 📅 **Programar para más tarde** — Selector de horario en intervalos de 30 minutos dentro del horario de operación de la sucursal.

---

#### PASO 3 — ¿Cómo pagas?

- Datos del cliente: **nombre completo y teléfono**. Pre-rellenados si hay cookies guardadas.
- Selección del método de pago entre los **habilitados por el restaurante**.
- Si el restaurante activó transferencia bancaria: se muestran sus datos bancarios (CLABE, banco, titular, alias).
- Resumen final: productos con modificadores y notas, subtotal, costo de envío, hora programada (si aplica) y total.
- Botón **Confirmar y enviar por WhatsApp**.
- La app abre WhatsApp con mensaje preestructurado con todos los detalles del pedido, dirigido al **número de WhatsApp de la sucursal asignada**.
- A partir de ese momento, **la comunicación continúa directamente entre el cliente y la sucursal por WhatsApp** (confirmación, estatus, dudas, etc.). No hay link de seguimiento ni pantalla de tracking.
- Se guardan/actualizan los datos del cliente en cookies para futuros pedidos.

---

### 6.2 Menú Digital

- Crear, editar y eliminar categorías del menú.
- Crear, editar y eliminar productos dentro de cada categoría.
- Agregar foto, nombre, descripción, precio base y **costo de producción** a cada producto.
- Activar o desactivar productos y categorías individualmente.
- Configurar **grupos de modificadores** por producto:
  - Nombre del grupo (ej. `Tipo de tortilla`, `Tamaño`, `Extras`).
  - Opciones con nombre y precio adicional (puede ser `$0`).
  - Selección **única** (radio) o **múltiple** (checkbox) por grupo.
  - Los modificadores son **reutilizables** entre productos.
- **Notas libres**: texto libre por producto sin afectar el precio.
- Generar y descargar código QR del menú.
- Obtener link único del menú para compartir.
- **El menú es compartido entre todas las sucursales del restaurante.** No se configura por sucursal.
- **Costo de producción**: cada producto tiene un campo de costo de producción visible solo para el administrador. Este dato se utiliza para calcular la **ganancia neta** en reportes y estadísticas del restaurante. El cliente nunca ve este campo.

---

### 6.3 Gestión de Sucursales (Panel Restaurante)

- Crear nuevas sucursales **si los límites del restaurante lo permiten** (validar contra el límite de sucursales configurado por el SuperAdmin).
- Cada sucursal tiene:
  - Nombre descriptivo.
  - Dirección completa.
  - Ubicación en mapa (Google Maps) con pin arrastrable para coordenadas exactas.
  - **Número de WhatsApp propio**.
  - Horarios de operación por día de la semana (apertura y cierre).
  - Estado activo/inactivo.
- Activar o desactivar sucursales sin eliminarlas.
- Ver indicador de sucursales creadas vs. límite configurado.

---

### 6.4 Gestión de Pedidos (Panel Restaurante)

- Recepción del pedido en WhatsApp (de la sucursal asignada) y en el panel **simultáneamente**.
- Alerta visual y sonora de pedidos nuevos.
- Filtro por sucursal en la lista de pedidos.
- En el tablero Kanban de pedidos, **cada tarjeta muestra la sucursal** a la que pertenece el pedido.
- Comanda completa: productos, modificadores, notas del cliente, datos del cliente, método de entrega, método de pago, dirección, distancia, tiempo estimado, costo de envío, **hora programada** (si aplica), y **sucursal asignada**.
- Cambio de estatus del pedido:

```
Recibido → En preparación → En camino → Entregado
```

- Historial de pedidos con filtros por fecha, estatus y sucursal.
- Impresión de comandas.
- **Conteo de pedidos del mes** visible, con indicador de progreso hacia el límite configurado.

---

### 6.5 Delivery — Ubicación y Cálculo de Envío

- Integración con GPS del dispositivo del cliente.
- Mapa interactivo con pin arrastrable (usando **Google Maps JavaScript API**).
- **La dirección se ingresa manualmente por el cliente** en los campos del formulario. El pin en el mapa sirve únicamente para obtener coordenadas.
- Campos del formulario: dirección completa (calle, número, colonia, ciudad).
- Campo libre de **referencias adicionales**: entre calles, color de casa, número de depto.
- Cálculo de distancia **real por calles** usando **Google Distance Matrix API** (no en línea recta). Se usa pre-filtro Haversine para minimizar llamadas a la API.
- Cálculo de tiempo estimado de entrega.
- Cálculo del costo de envío: se busca en qué **rango de distancia** cae la distancia real y se aplica el precio fijo configurado para ese rango.
- Link de mapa con la ubicación del cliente incluido en la comanda para el repartidor.
- **Validación de zona de cobertura**: si la distancia real excede el último rango configurado, se bloquea domicilio con mensaje informativo.

> ⚠️ **No se implementa tracking en tiempo real del repartidor en el MVP.**

---

### 6.6 Métodos de Pago Configurables

El administrador activa o desactiva cada método desde **Configuración → Métodos de Pago**. El cliente solo ve los métodos habilitados. Si solo hay uno activo, se preselecciona automáticamente.

| Método                     | Configuración adicional                                            |
| -------------------------- | ------------------------------------------------------------------ |
| **Efectivo**               | Ninguna.                                                           |
| **Terminal física**        | Ninguna. El repartidor lleva la terminal al momento de la entrega. |
| **Transferencia bancaria** | Banco, nombre del titular, CLABE interbancaria, alias opcional.    |

> Los datos bancarios configurados se muestran al cliente en el Paso 3 y se incluyen en la comanda enviada por WhatsApp.

---

### 6.7 Panel SuperAdmin (Gestión del SaaS)

- **Crear restaurantes**: nombre, slug, logo, datos de contacto. Se genera automáticamente un token de acceso para el frontend del cliente.
- **Configurar límites manualmente** para cada restaurante: pedidos mensuales máximos y sucursales máximas.
- **Monitorear uso**: ver cuántos pedidos lleva cada restaurante en el mes y cuántas sucursales tiene activas vs. su límite.
- **Bloquear pedidos** automáticamente cuando un restaurante alcanza su límite mensual.
- **Activar/desactivar restaurantes** completos.
- **Dashboard** con KPIs globales: restaurantes activos, pedidos totales del mes, nuevos registros.
- **Estadísticas globales**: gráficas de pedidos por día, nuevos registros por mes, top restaurantes por pedidos.

---

## 7. Pantallas de la Aplicación

> Todas las pantallas deben ser responsivas (mobile first).

### 7.1 Pantallas del Cliente (Frontend Independiente)

| Pantalla                        | Descripción                                                                                                                                                                                                                                                                       |
| ------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Límite de Pedidos Alcanzado** | Si el restaurante alcanzó su límite mensual: mensaje informativo al cliente.                                                                                                                                                                                                      |
| **Inicio del Menú**             | Menú principal. Logo, nombre del restaurante, categorías navegables y productos. No se solicita GPS en este paso.                                                                                                                                                                 |
| **Vista del Producto**          | Modal: foto, descripción, precio, modificadores con precios, nota libre, cantidad, botón agregar al carrito.                                                                                                                                                                      |
| **Carrito de Compras**          | Productos seleccionados con modificadores, notas, cantidades y precios. Editar o eliminar. Botón continuar.                                                                                                                                                                       |
| **Tipo de Entrega y Ubicación** | Selector de entrega. Si domicilio: solicita GPS, mapa con pin (Google Maps), formulario de dirección manual, referencias, detección de sucursal más cercana, cálculo distancia/costo por rangos. Selector de programación de hora. Validación de horario de la sucursal asignada. |
| **Fuera de Horario**            | Si la sucursal asignada está cerrada: mensaje con horarios del día. Opción de programar pedido si aplica.                                                                                                                                                                         |
| **Fuera de Cobertura**          | Mensaje si la distancia excede el máximo: _"No tenemos servicio en tu zona"_. Opciones: recoger en local, comer aquí.                                                                                                                                                             |
| **Pago y Confirmación**         | Nombre y teléfono (pre-rellenados de cookies). Métodos de pago habilitados. Resumen final con hora programada. Confirmar y enviar por WhatsApp.                                                                                                                                   |
| **Confirmación del Pedido**     | Pantalla de éxito con número de pedido y resumen breve. La comunicación continúa directamente por WhatsApp con la sucursal.                                                                                                                                                       |

---

### 7.2 Pantallas del Panel Administrador (Restaurante)

#### Autenticación

| Pantalla                   | Descripción                                                 |
| -------------------------- | ----------------------------------------------------------- |
| **Login**                  | Correo y contraseña. Link a recuperar contraseña.           |
| **Recuperar Contraseña**   | Formulario de correo para recibir link de restablecimiento. |
| **Restablecer Contraseña** | Formulario para nueva contraseña.                           |

#### Dashboard

| Pantalla                | Descripción                                                                                                                                                        |
| ----------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **Dashboard Principal** | Pedidos del día por sucursal, pedidos por estatus, pedidos del mes vs. límite, **ganancia neta del período** (ventas menos costos de producción), accesos rápidos. |

#### Pedidos

| Pantalla                      | Descripción                                                                                                                                                                                       |
| ----------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Lista de Pedidos (Kanban)** | Tablero Kanban con tarjetas por estatus. Cada tarjeta muestra la **sucursal asignada**. Filtros por estatus, fecha y sucursal. Alerta de pedidos nuevos. Indicador de pedidos del mes vs. límite. |
| **Detalle del Pedido**        | Comanda completa: productos, modificadores, notas, cliente, entrega, pago, sucursal asignada, dirección con link al mapa, distancia, tiempo, costo, hora programada. Cambiar estatus e imprimir.  |

#### Menú Digital

| Pantalla                     | Descripción                                                                                                   |
| ---------------------------- | ------------------------------------------------------------------------------------------------------------- |
| **Gestión del Menú**         | Categorías y productos con opciones de activar/desactivar, editar y eliminar.                                 |
| **Crear / Editar Categoría** | Nombre, descripción, imagen, orden y estado.                                                                  |
| **Crear / Editar Producto**  | Nombre, descripción, precio base, **costo de producción**, foto, categoría, modificadores asignados y estado. |
| **Gestión de Modificadores** | Grupos reutilizables con opciones y precios. Selección única o múltiple.                                      |

#### Sucursales

| Pantalla                    | Descripción                                                                                                                                              |
| --------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Lista de Sucursales**     | Todas las sucursales del restaurante con estado, dirección y teléfono. Indicador de sucursales usadas vs. límite. Botón crear (si los límites permiten). |
| **Crear / Editar Sucursal** | Nombre, dirección, mapa (Google Maps) para ubicar coordenadas, teléfono WhatsApp.                                                                        |
| **Horarios de Sucursal**    | Días y horarios de apertura y cierre por día de la semana para la sucursal.                                                                              |

#### Configuración

| Pantalla                  | Descripción                                                                                                                                                                |
| ------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Configuración General** | Nombre del restaurante, logo, redes sociales.                                                                                                                              |
| **Métodos de Entrega**    | Activar/desactivar: domicilio, recoger, comer aquí.                                                                                                                        |
| **Tarifas de Envío**      | Configurar rangos de distancia con precio fijo (ej. 0-2 km: gratis, 2-5 km: $30, 5-10 km: $60). Rangos contiguos y sin huecos. El último rango define la cobertura máxima. |
| **Métodos de Pago**       | Toggle por método: efectivo, terminal, transferencia. Si transferencia: datos bancarios.                                                                                   |
| **Código QR y Link**      | Vista previa, descarga del QR y link al frontend del cliente del restaurante.                                                                                              |
| **Mi Cuenta**             | Editar nombre, correo y contraseña.                                                                                                                                        |
| **Mis Límites**           | Ver límites configurados por el SuperAdmin (pedidos mensuales y sucursales) y uso actual.                                                                                  |

---

### 7.3 Pantallas del SuperAdmin

| Pantalla                     | Descripción                                                                                                                                                          |
| ---------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| **Login Super Admin**        | Acceso restringido. Sin opción de registro público.                                                                                                                  |
| **Dashboard**                | KPIs globales: restaurantes activos, pedidos totales del mes, nuevos registros. Feed de actividad reciente.                                                          |
| **Lista de Restaurantes**    | Todos los restaurantes con filtros por estatus. Pedidos del mes, sucursales activas. Acciones: activar/desactivar.                                                   |
| **Detalle del Restaurante**  | Info completa del restaurante, límites configurados, uso del mes (pedidos y sucursales vs. límites), token de acceso. Acciones: activar, desactivar, editar límites. |
| **Estadísticas Globales**    | Gráficas de pedidos por día, nuevos registros por mes, top restaurantes por pedidos.                                                                                 |
| **Ajustes de la Plataforma** | Nombre de la plataforma, dominio base, logo, configuración general de la plataforma.                                                                                 |

---

## 8. Reglas de Negocio

### Multitenancy

- Ningún restaurante puede ver ni modificar datos de otro restaurante. Esta regla es absoluta y sin excepciones.
- El frontend del cliente se identifica por un token de acceso; el backend resuelve el restaurante internamente.

### Sucursales

- Cada restaurante debe tener **al menos 1 sucursal activa** para poder recibir pedidos.
- No se pueden crear más sucursales que el límite configurado por el SuperAdmin.
- **El menú es global** para el restaurante. No se configura por sucursal.
- Cada sucursal tiene su **propio número de WhatsApp** y horarios. Las tarifas de envío se configuran a nivel de restaurante, no por sucursal.
- La sucursal más cercana se determina por **distancia real por calles** (Google Distance Matrix), con pre-filtro Haversine para minimizar costos de API.

### Flujo del Cliente

- El cliente **no necesita cuenta ni registro** para hacer un pedido.
- Los datos del cliente se persisten en **cookies del navegador** (nombre, teléfono, dirección, referencias, coordenadas, token identificador). Expiración: 90 días.
- El flujo es siempre de **3 pasos**. El Paso 2 de ubicación solo aparece si el cliente elige domicilio.
- Los **modificadores** afectan el precio final del producto. Las **notas** no afectan el precio.
- El cliente solo ve los métodos de pago **habilitados por el restaurante**.
- Si solo hay un método de pago activo, se **preselecciona automáticamente**.

### Horarios

- Antes de permitir pedidos, se validan los horarios de la **sucursal asignada**.
- **Si está fuera de horario**, se muestra un mensaje claro con los horarios del día.
- El cliente puede **programar su pedido** para una hora futura dentro del horario de operación.
- Los intervalos de programación son de **30 minutos**, solo dentro del horario de la sucursal.
- Sin horario configurado, se asume **siempre disponible**.

### Cobertura y Distancia

- Si la sucursal más cercana está a más del radio máximo de cobertura (determinado por el último rango de tarifa configurado), se muestra: _"Lamentablemente no tenemos servicio de entrega en tu zona."_
- El cliente aún puede elegir "Recoger en local" o "Comer aquí" aunque esté fuera de cobertura.
- La distancia se calcula por **calles reales** (Google Distance Matrix), nunca en línea recta. Se usa pre-filtro Haversine para minimizar llamadas a la API.
- El costo de envío se determina por **rangos de distancia con precio fijo**, configurados a nivel de restaurante. Ejemplo: 0-2 km gratis, 2-5 km $30, 5-10 km $60.

### Límites del Restaurante (SaaS)

- El SuperAdmin configura **manualmente** los límites de cada restaurante: pedidos mensuales máximos y sucursales máximas.
- Cuando un restaurante **alcanza su límite mensual de pedidos**, se bloquean nuevos pedidos con mensaje informativo.
- El conteo de pedidos se **reinicia el día 1 de cada mes**.
- El Admin del Restaurante puede ver su uso actual vs. límites en el panel.

### Métodos de Pago

- No se puede activar transferencia bancaria sin tener los datos bancarios completos configurados.
- Los datos bancarios se muestran al cliente solo si el método de transferencia está activo y seleccionado.

### Pedidos

- El pedido se registra en la base de datos cuando el cliente confirma en el Paso 3.
- El mensaje de WhatsApp lo envía el cliente manualmente. La app solo abre WhatsApp con el mensaje listo, dirigido al **número de la sucursal asignada**.
- El restaurante puede cambiar el estatus solo **hacia adelante**, no puede revertirlo.
- Si el pedido es programado, se registra la hora solicitada. Si no, se asume "lo antes posible".

### Dirección del Cliente

- La dirección del cliente **se ingresa manualmente** en el formulario. No se usa geocoding inverso para obtener la dirección desde el pin del mapa.
- El pin en el mapa (Google Maps) sirve únicamente para obtener las **coordenadas** del cliente, que se usan para calcular la distancia y el costo de envío.
- El cliente debe llenar: dirección completa y campo de referencias (entre calles, color de casa, número de depto, etc.).

### Costo de Producción y Ganancia Neta

- Cada producto tiene un **costo de producción** que solo es visible para el administrador del restaurante. El cliente nunca lo ve.
- La **ganancia neta** se calcula como: `precio de venta − costo de producción` por cada producto vendido.
- El dashboard y reportes del restaurante muestran la ganancia neta acumulada por período (día, semana, mes) y por sucursal.
- El costo de producción es un campo obligatorio al crear o editar un producto.

---

## 9. Servicios Externos Requeridos

| Servicio                        | Uso                                                   | Costo  |
| ------------------------------- | ----------------------------------------------------- | ------ |
| **Google Maps JavaScript API**  | Mapa interactivo con pin arrastrable para coordenadas | Pago   |
| **Google Distance Matrix API**  | Distancia real por calles y tiempo estimado de viaje  | Pago   |
| **Geolocalización (navegador)** | Obtener coordenadas GPS del dispositivo del cliente   | Gratis |
| **WhatsApp (wa.me link)**       | Mensaje preestructurado con el pedido completo        | Gratis |
| **Almacenamiento en la nube**   | Almacenamiento de imágenes en producción              | Pago   |

> ⚠️ **Se usa Google Maps para el mapa/pin/coordenadas y Google Distance Matrix para cálculo de distancias reales por calles.** Se emplea pre-filtro Haversine (sin costo) para minimizar llamadas a la API de Google. La dirección del cliente se ingresa manualmente; no se usa geocoding inverso.

---

_PRD v2.2 — PideAqui — Febrero 2026_
