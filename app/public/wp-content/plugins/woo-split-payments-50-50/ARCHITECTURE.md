# Woo Split Payments 50/50

## Enfoque elegido

Este plugin no intenta "partir" un cobro dentro de Stripe o Redsys directamente. En lugar de eso:

- el primer pedido de WooCommerce cobra el 50% ahora;
- el plugin genera uno o varios pedidos hijo con el saldo restante;
- cada pedido hijo tiene su propio enlace `order-pay`;
- los recordatorios de 7 días y 1 día apuntan a ese enlace;
- Stripe y Redsys funcionan siempre que ya estén activos como pasarelas de WooCommerce.

Esto hace el sistema más estable, más auditable y más compatible con HPOS.

## Qué cubre esta base

- Activación 50/50 por producto.
- Modo `opcional` u `obligatorio`.
- Vencimiento del segundo pago por:
  - fecha fija;
  - días después de la compra.
- Agrupación del saldo por fecha: si un pedido contiene productos con fechas distintas, se crean varios pedidos hijo.
- Recordatorios automáticos a 7 días y 1 día.
- Estado de pedido `Pago 50/50` para el pedido padre.
- Panel de administración en WooCommerce con paginación, búsqueda, filtros y reenvío manual de emails.
- Emails nativos `WC_Email` configurables desde WooCommerce.
- Bloqueo de la misma pasarela en el segundo pago.
- UI de producto variable alineada con la variación seleccionada.

## Límites actuales

- No soporta aún cupones de importe fijo (`fixed_cart`, `fixed_product`).
- El segundo pago se realiza mediante enlace de pago, no mediante auto-cobro tokenizado.
- No añade todavía una UI para reprogramar la fecha de vencimiento.
- No hay reglas avanzadas para prorratear envío, fees o casos complejos de descuentos.
- Falta validación funcional end-to-end en un WordPress con base de datos accesible desde CLI.

## Siguiente fase recomendada

1. Añadir UI para reprogramar la fecha de vencimiento desde el pedido o desde el panel.
2. Definir reglas cerradas para envío, fees y descuentos complejos.
3. Añadir modo avanzado de auto-cobro:
   - Stripe: usando payment method reutilizable/off-session.
   - Redsys: sólo si el comercio tiene tokenización/COF habilitada y validada.
4. Añadir pruebas funcionales reales con Stripe y Redsys en entorno de staging.
