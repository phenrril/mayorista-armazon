# Checklist de Deploy Mayorista

## Base de datos

- Ejecutar `sql/2026_mayorista_armazones.sql`.
- Si querés dejar la base alineada con la sanitización final, ejecutar despues `sql/2026_sanitizacion_mayorista.sql`.
- Verificar que existan:
  - `producto.precio_mayorista`
  - `producto.tipo`
  - `ventas.tipo_venta`
  - `ventas.precio_modificado`
  - `ventas.monto_cc`
  - `ventas.saldo_cc_cliente`
  - `detalle_venta.precio_personalizado`
  - `detalle_venta.tipo_precio`
  - tablas `cuenta_corriente` y `movimientos_cc`
- Confirmar altas en `permisos`:
  - `cuenta_corriente`
  - `reportes`
  - `api_config`
  - `estadisticas`
- Si se ejecuta la sanitización final, confirmar bajas en `permisos`:
  - `historia_clinica`
  - `idcristal`
  - `calendario`

## Configuracion

- Definir `MAYORISTA_API_KEY` en el entorno productivo.
- Revisar credenciales de `conexion.php`.
- Confirmar que `src/api/index.php` quede accesible desde el hosting.

## Permisos y menus

- Asignar permisos nuevos desde `src/rol.php`.
- Verificar que solo admin pueda editar el limite de credito desde `src/cuenta_corriente.php`.
- Confirmar que usuarios no admin vean:
  - `ventas.php`
  - `lista_ventas.php`
  - `productos.php`
  - `cuenta_corriente.php`
  - `estadisticas.php`
  - `reporte.php`
  - `api_config.php` solo si corresponde
- Confirmar que ya no existan accesos publicos a historia clinica, calendario o cristal.

## Pruebas funcionales

- Crear producto con precio minorista y mayorista.
- Crear venta minorista y verificar PDF.
- Crear venta mayorista y verificar precio por defecto.
- Editar precio unitario en carrito una sola vez y validar `precio_modificado`.
- Intentar editar el mismo item una segunda vez y confirmar bloqueo.
- Generar venta parcial y confirmar cargo en cuenta corriente.
- Intentar una venta que supere el limite configurado y confirmar rechazo.
- Registrar pago manual desde `cuenta_corriente.php`.
- Exportar PDF de cuenta corriente.
- Revisar dashboard en `estadisticas.php` con filtro de fechas.
- Revisar filtros y PDF en `reporte.php`.
- Probar endpoints API con `X-API-Key`.

## Integracion OpenClaw

- Validar `docs/api-openclaw.md`.
- Cargar las skills sugeridas en `docs/openclaw-skills.md`.
- Hacer una prueba real de:
  - alta de cliente
  - consulta de cuenta corriente
  - busqueda de producto
  - registro de pago
