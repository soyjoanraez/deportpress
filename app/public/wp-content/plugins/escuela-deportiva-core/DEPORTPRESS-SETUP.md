# DeportPress — checklist Fase 0 (entorno)

Completar en Local / staging / producción según el informe técnico.

## Plugins obligatorios

1. **ACF Pro** — instalar desde [advancedcustomfields.com](https://www.advancedcustomfields.com/) y activar la licencia. El core registra grupos locales con `acf_add_local_field_group` (compatible con ACF free para pruebas básicas; Pro recomendado para el proyecto).
2. **WooCommerce** — instalar desde el directorio de plugins de WordPress y configurar divisa EUR.
3. **LiteSpeed Cache** — en producción/staging con LiteSpeed; configurar exclusiones (el plugin core también fuerza no-cache en rutas dinámicas vía API de LiteSpeed cuando el plugin está activo).

## Ajustes de WordPress

- **Enlaces permanentes**: `Ajustes → Enlaces permanentes` → estructura **Nombre de la entrada** (`/%postname%/`).
- **Página panel familiar**: crear una página con slug `panel-familiar` e insertar el shortcode `[ed_panel_familiar]`.
- **Página panel entrenador**: crear una página con slug `panel-entrenador` e insertar el shortcode `[ed_panel_entrenador]` (visible para administradores, coordinadores y entrenadores asignados a la sesión).

## Fase 2 — dependencias y pagos

1. **Composer (Stripe PHP)** — en el directorio del plugin:
   - `cd wp-content/plugins/escuela-deportiva-core && composer install`
   - Debe existir `vendor/autoload.php` para que los endpoints Stripe funcionen.
2. **Constantes en `wp-config.php`** (ejemplos en el comentario del bloque DeportPress):
   - `ED_STRIPE_SECRET_KEY` — clave secreta de la cuenta Stripe.
   - `ED_STRIPE_WEBHOOK_SECRET` — secreto del endpoint de webhook (Dashboard Stripe → Webhooks).
   - `ED_REDSYS_SECRET_KEY`, `ED_REDSYS_MERCHANT_CODE`, `ED_REDSYS_TERMINAL` — datos del comercio Redsys (validar firma y entorno de pruebas antes de producción).
3. **URLs REST** (para webhooks / IPN):
   - Stripe: `https://TU-DOMINI/wp-json/ed/v1/stripe/webhook`
   - Redsys: `https://TU-DOMINI/wp-json/ed/v1/redsys/notificacion`
4. **Cron de WordPress** — el plugin programa `ed_cron_cobro_plazos` y `ed_cron_recordatorios`; en entornos reales asegurar que el cron del sistema o un servicio externo dispara `wp-cron.php` con la frecuencia adecuada.
5. **LiteSpeed** — el core marca no-cache en `/wp-json/` y rutas de panel; si hiciera falta, también en las URLs explícitas de webhook anteriores.

## Git (repos separados)

- Un repositorio para el child theme (`escolesesportivesondara`) y otro para `escuela-deportiva-core`. No versionar todo el núcleo de WordPress.
- Ramas sugeridas: `main`, `staging`, `develop`, `feature/*`.

## Producción

- Activar `DISALLOW_FILE_EDIT` en `wp-config.php`.
- Definir `WP_ENVIRONMENT_TYPE` como `production` o `staging` según el servidor.

## Fase 4 — Push (OneSignal), votación MVP, rankings, PWA

1. **Constantes en `wp-config.php`**
   - `ED_ONESIGNAL_APP_ID` — ID de la aplicación OneSignal (Web Push).
   - `ED_ONESIGNAL_API_KEY` — clave REST para enviar notificaciones desde WordPress (`ED_Push`). Sin API key no se llama a la API; sin App ID tampoco se carga el SDK en el front.
2. **Páginas**
   - Crear una página con slug `votar-mvp` e insertar `[ed_votar_mvp]` (admite `?partido=ID` en la URL).
   - Shortcode rankings: `[ed_rankings torneo_id="0" tipo="goles" limit="15"]` (`torneo_id="0"` = ranking global del tipo indicado).
3. **URLs públicas**
   - Torneos: `/torneo/{slug-torneo}/`
   - Partido en vivo: `/torneo/{slug}/partido/{slug-local}-vs-{slug-visitante}/` (tras guardar enlaces permanentes / reactivar el plugin).
4. **PWA / `manifest.json`**
   - El plugin sirve `https://tu-dominio/manifest.json` y añade `<link rel="manifest">` en `wp_head`.
   - Iconos: colocar `icon-192.png` e `icon-512.png` en el child theme en `assets/icons/` (rutas usadas por el manifest).
5. **OneSignal / LiteSpeed**
   - El core marca no-cache en rutas Fase 4 y en archivos típicos del worker (`/OneSignalSDKWorker.js`, etc.) vía `ED_LiteSpeed`. Revisar exclusiones en el plugin LiteSpeed si hiciera falta.
6. **Privacidad**
   - El voto MVP anónimo usa un *fingerprint* derivado de IP + User-Agent (heurística); conviene mencionarlo en la política de privacidad del sitio.

## Fase 5 — Crónicas IA, publicidad y directorio

1. **OpenAI**
   - Definir `ED_OPENAI_API_KEY` en `wp-config.php` para generar crónicas automáticas tras cerrar el MVP (cola `ed_cron_cronicas`, cada 10 min).
   - Sin clave, la cola seguirá existiendo pero `generar_cronica` fallará (reintentos máx. 3).
2. **Cron**
   - Tras actualizar el plugin, comprobar en el sitio que exista el evento `ed_cron_cronicas` (intervalo `ed_cada_10_min`). Al desactivar el plugin se limpia.
3. **WooCommerce**
   - Crear productos con SKU que empiecen por `pub-` (publicidad) o `dir-` (directorio). En el pedido, guardar metadatos de pedido `_ed_anuncio_id` o `_ed_empresa_id` según corresponda; al completar el pago se activa el anuncio o se publica la ficha.
   - Si usas WooCommerce Subscriptions, el hook `woocommerce_subscription_status_updated` sincroniza el campo ACF `ed_anuncio_activo` con la suscripción (meta `_ed_anuncio_id` en la suscripción).
4. **Contenido**
   - CPT **Anuncios** y **Directorio** (`/directorio/`) desde el menú Escola esportiva. Campos ACF con prefijo `ed_anuncio_*` y `ed_emp_dir_*`.
   - La crónica del partido se guarda en `ed_par_cronica` y puede mostrarse en la plantilla de partido en vivo.
5. **LiteSpeed**
   - El core fuerza no-cache en `/directorio/` y en rutas REST de directorio/publicidad; revisar exclusiones adicionales si hiciera falta.

## Fase 6 — Entradas QR, rifa y bar

1. **Base de datos** — Tras actualizar el plugin, `ed_db_version` debe pasar a `5` y crearse las tablas `ed_entradas`, `ed_rifa_numeros`, `ed_bar_productos`, `ed_bar_pedidos`, `ed_bar_lineas` (vía `ED_Activator::maybe_upgrade()` al cargar el sitio).

2. **ACF en el torneo** — Pestañas *Entrades i rifa* y *Bar* con campos `ed_tor_*` (venta activa, precio, aforo, producto WC de entrada, rifa, franjas del bar, etc.).

3. **WooCommerce**
   - Producto simple por torneo con SKU `entrada-{ID_TORNEO}` (ej. `entrada-42`), vinculado al campo *ID producte WC (entrada)*.
   - Opcional: stock del producto = aforo máximo; el plugin también bloquea el carrito si se supera el aforo según entradas ya generadas.
   - Al completar el pago se generan filas en `ed_entradas`, números de rifa si la rifa está activa, y se envía un correo con enlaces al QR (REST `ed/v1/entrada/{token}/qr`).

4. **Páginas sugeridas**
   - `escaner-qr` con `[ed_escaner_qr torneo_id="X"]` (o `?torneo_id=`): operador, coordinador o administración.
   - `panel-bar` con `[ed_panel_bar torneo_id="X"]`: coordinador o administración.
   - Página pública con `[ed_pedir_bar torneo_id="X"]` cuando el bar del torneo esté activo.
   - Opcional: página con URL `/validar-entrada/` (el QR codifica `?token=` sobre esa ruta).

5. **REST** (`/wp-json/ed/v1/…`) — Entradas: `GET /entradas`, `POST /entrada/validar`, `GET /entrada/{token}/qr`, `GET /torneo/{id}/entradas/stats`. Rifa: ganadores y sorteo (coordinador / `manage_escuela_deportiva` / admin). Bar: productos, franjas, crear pedido, cola staff, estado de pedido, `GET /bar/mis-pedidos`.

6. **LiteSpeed** — El core marca no-cache en `/escaner-qr/`, `/panel-bar/` y `/validar-entrada/` además de `/wp-json/` (ya incluido).

## Fase 7 — FFCV / Novanet (scraping)

1. **Base de datos** — Tras actualizar el plugin, `ed_db_version` debe ser `6` y existir `ed_fed_partidos`, `ed_fed_clasificacion`, `ed_fed_mapeo`.

2. **Sin Composer** — El scraper usa `DOMDocument` / `DOMXPath` nativos. Si Novanet cambia el HTML, hay que ajustar los selectores en `includes/class-ed-scraper-ffcv.php`.

3. **Administración** — Menú **Escola esportiva → FFCV**: nombre del club (`ed_nombre_club`), temporada (`ed_temporada_actual`), activar cron cada 6 h, JSON de categorías (`competicion_id`, `grupo_id`, `nombre`, etc.) y mapeo FFCV → ID de categoría local.

4. **Cron** — Hook `ed_cron_sync_federacion` (intervalo `sixhourly`). Solo corre si la sync está activa en opciones. **Sincronizar ahora** en admin y `POST /wp-json/ed/v1/federacion/sync` fuerzan la sync aunque el toggle esté apagado.

5. **REST público** — `GET /federacion/proximos`, `GET /federacion/resultados`, `GET /federacion/clasificacion/{categoria_id}`.

6. **Front** — La plantilla `templates/torneo.php` del tema muestra bloques de próximos partidos y resultados FFCV si hay datos en BD.

7. **Ética / legal** — Peticiones espaciadas (`sleep` entre categorías), `User-Agent` identificativo, transients ~30 min. Revisar `robots.txt` del portal antes de producción.

## Fase 8 — Calendario unificado, resultados y ficha de equipo

1. **REST** (`/wp-json/ed/v1/…`) — `GET /calendario/semana` (`fecha`, `categoria_id`, `jugador_id`), `GET /calendario/mes` (`year`, `month`, `categoria_id`), `GET /resultados/semana` (`fecha`). Los eventos mezclan CPT entrenamiento, partidos de torneo y filas FFCV (`ed_fed_partidos` con `es_nuestro_equipo = 1`).

2. **Shortcode** — `[ed_calendario]` opcionalmente `jugador_id` y `categoria_id` para filtrar en páginas públicas.

3. **Panel familiar** — Pestaña *Calendari*: usa el mismo widget; el filtro por jugador sigue al selector de *Assistències* (o al primer jugador del núcleo).

4. **URLs** (tras **reactivar el plugin** o **Guardar** en *Ajustes → Enlaces permanentes*) — `/resultados/` (resultados semanales; `?semana=YYYY-MM-DD` opcional, lunes de referencia) y `/equipo/{slug-equipo}/` (CPT equipo).

5. **LiteSpeed** — El core marca no-cache en `/resultados` y rutas `/equipo/…` además de las rutas anteriores.

## Fase 9 — Comunicaciones internas

1. **Base de datos** — Tras actualizar el plugin, `ed_db_version` debe ser `7` y crearse `ed_mensajes` y `ed_msg_recepciones` (vía `ED_Activator::maybe_upgrade()`).

2. **REST** (`/wp-json/ed/v1/…`) — `GET /mensajes`, `GET /mensajes/no-leidos`, `POST /mensajes/{id}/leer`, `POST /mensajes/enviar` (staff), `GET /mensajes/enviados` (staff), `GET /mensajes/destinos?tipo=deporte|categoria|nucleo|jugador` (staff).

3. **Panel familiar** — Pestaña *Missatges* con bandeja; al cargar la lista los mensajes se marcan como leídos (como en la spec). Badge de no leídos en la pestaña.

4. **OneSignal** — El panel añade tags `es_familia`, `categoria_{id}` y `deporte_{id}` además de `nucleo_` y `equipo_`, para segmentar pushes por mensaje a club / deporte / categoría / núcleo.

5. **Página staff** — Crear una página con slug `enviar-mensaje` (o usar otra) e insertar `[ed_enviar_mensaje]`. Visible solo para administración, coordinador o entrenador. Los entrenadores solo pueden enviar a **deporte / categoría / jugador** acotados a sus sesiones de entrenamiento (no «todo el club» ni núcleo arbitrario).

6. **LiteSpeed** — El core marca no-cache en `/enviar-mensaje/`.

## Fase 10 — Dashboard d’administració (KPIs, gràfiques, CSV)

1. **Menú** — **Escola esportiva → Dashboard** (`admin.php?page=ed-dashboard`) per a usuaris amb `manage_options` o `manage_escuela_deportiva`. El primer ítem del menú redirigeix al dashboard si tenen accés; si no, a la llista de jugadors.

2. **REST** (`/wp-json/ed/v1/…`, mateixa autenticació que la resta de l’admin) — `GET /admin/kpis`, `/admin/ingresos-mes`, `/admin/inscripciones-deporte`, `/admin/asistencia-categoria`, `/admin/impagos`, `/admin/baja-asistencia`. Exportació: `GET /admin/exportar/{jugadores|pagos|asistencias|impagos|baja_asistencia}` (CSV amb BOM UTF-8, separador `;`).

3. **Cache** — Els agregats de KPIs i ingressos per mes es guarden en transients (~10 min). En pagar un plazo (`ed_plazo_pagado`) s’invalida aquesta cache per reflectir ingressos i percentatges al moment.

4. **LiteSpeed** — Les rutes `/wp-json/` ja es marquen com a no cachejables al core; no cal exclusió addicional específica per al dashboard.
