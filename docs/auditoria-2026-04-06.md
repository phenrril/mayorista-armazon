# Auditoría funcional y técnica

Fecha: `2026-04-06`

## Alcance

- Flujos revisados: ventas, carrito, stock, cuenta corriente, productos, permisos, API, facturación y navegación responsive.
- Evidencia tomada por lectura de código y pruebas HTTP/browser contra el entorno local Docker (`armazon_web` + `armazon_db`).

## Hallazgos

### 1. Critico: `anular.php` permite borrar ventas sin login ni permisos

- Archivo: `src/anular.php`
- Evidencia:
  - El script hace `session_start()` pero no valida `$_SESSION['idUser']`.
  - Toma `$_POST['idanular']` sin casteo previo y ejecuta borrado/restauración de stock.
  - Respuesta real en entorno local: `POST /src/anular.php` con `idanular=1` devolvió `200 OK` y `Venta Eliminada` sin autenticación.
- Riesgo:
  - Cualquier actor con acceso HTTP al endpoint puede borrar ventas y alterar stock.
  - No usa transacción y no revierte movimientos financieros asociados.
- Fix sugerido:
  - Exigir sesión y `mayorista_requiere_permiso(..., ['ventas'])`.
  - Castear `idanular` a entero.
  - Ejecutar todo dentro de transacción.
  - Revertir también cuenta corriente, ingresos, compromisos y cualquier documento asociado.

### 2. Critico: `chart.php` expone datos sin autenticación

- Archivo: `src/chart.php`
- Evidencia:
  - No hay `session_start()` ni validación de usuario.
  - Respuesta real: `POST /src/chart.php action=sales` devolvió JSON con productos y stock.
- Riesgo:
  - Fuga de información comercial y de inventario sin necesidad de login.
- Fix sugerido:
  - Requerir sesión y permiso de estadísticas.
  - Si el endpoint es legado y no se usa, eliminarlo.

### 3. Alto: la API queda expuesta si no se cambia la clave por defecto

- Archivos: `src/config.php`, `src/api/index.php`, `src/api_config.php`
- Evidencia:
  - `mayorista_get_api_key()` devuelve `cambiar-esta-api-key-en-produccion` si no existe `MAYORISTA_API_KEY`.
  - Con esa clave por defecto el endpoint respondió `200 OK` y expuso rutas y productos reales.
  - `api_config.php` muestra la clave actual en pantalla.
- Riesgo:
  - Si producción arranca con la clave por defecto, cualquier tercero puede consultar productos, clientes, CC y registrar pagos.
- Fix sugerido:
  - Fallar en duro si no existe variable de entorno en producción.
  - No exponer la clave completa en UI; mostrar solo estado/configuración.
  - Rotar la clave del entorno auditado.

### 4. Alto: riesgo de doble venta por doble click / doble POST

- Archivos: `assets/js/funciones.js`, `src/ajax.php`
- Evidencia:
  - `#btn_generar` dispara `$.ajax(...)` pero no deshabilita el botón ni bloquea reintentos.
  - En `procesarVenta` no hay idempotency key ni marca de request única.
  - El `detalle_temp` se limpia recién al final del flujo.
- Riesgo:
  - Dos clicks rápidos o reenvíos concurrentes pueden duplicar ventas sobre el mismo carrito.
- Fix sugerido:
  - Deshabilitar `#btn_generar` mientras la request esté en curso.
  - Agregar token idempotente por carrito/usuario.
  - Validar en backend que el carrito no haya sido procesado ya.

### 5. Alto: riesgo de sobreventa por concurrencia de stock

- Archivo: `src/ajax.php`
- Evidencia:
  - El stock se lee antes de actualizar, pero no hay `SELECT ... FOR UPDATE`.
  - El `UPDATE producto SET existencia = $stockNuevo` no condiciona por stock disponible actual.
- Riesgo:
  - Dos ventas concurrentes pueden leer el mismo stock y ambas aprobarse.
- Fix sugerido:
  - Bloqueo pesimista o `UPDATE ... WHERE existencia >= cantidad`.
  - Revalidar stock dentro de la misma transacción antes de confirmar.

### 6. Alto: `postpagos.php` procesa lógica financiera sin validar login ni permisos

- Archivo: `src/postpagos.php`
- Evidencia:
  - Tiene `session_start()` pero no controla `$_SESSION['idUser']`.
  - Lee `idventa` e `idabona` desde `POST` y ejecuta updates sobre `postpagos`, `ventas` y `detalle_venta`.
  - Sin login, el endpoint responde lógica de negocio en vez de redirigir o rechazar.
- Riesgo:
  - Superficie expuesta para abusos o estados inconsistentes.
- Fix sugerido:
  - Exigir sesión y permisos.
  - Migrar respuesta a JSON consistente.
  - Encapsular updates en transacción.

### 7. Medio: alta de usuarios con SQL concatenado y contraseñas MD5

- Archivos: `index.php`, `src/usuarios.php`
- Evidencia:
  - Login y alta de usuarios usan `md5(...)`.
  - `src/usuarios.php` inserta y consulta con variables sin escape consistente (`$nombre`, `$email`, `$user`).
- Riesgo:
  - Hash débil para credenciales.
  - Posible SQLi en panel de usuarios si se fuerza una request maliciosa.
- Fix sugerido:
  - Migrar a `password_hash()` / `password_verify()`.
  - Cambiar a prepared statements.

### 8. Medio: endpoints con sesión pero sin control granular de permiso

- Archivos: `src/ajax.php`, `src/procesar_factura.php`, `src/obtener_factura.php`, `src/pdf/generar_factura_electronica.php`, `src/pdf/generar.php`
- Evidencia:
  - Varias rutas exigen sesión, pero no siempre verifican permiso del módulo o pertenencia del recurso.
- Riesgo:
  - Un usuario autenticado con pocos permisos podría consultar o accionar sobre módulos ajenos si conoce IDs.
- Fix sugerido:
  - Aplicar `mayorista_requiere_permiso()` al inicio de cada endpoint sensible.
  - Validar acceso al recurso solicitado.

### 9. Menor: fricciones de UX y consistencia

- Archivos: `src/includes/header.php`, `src/includes/header.php`, `assets/css/dark-premium.css`
- Evidencia:
  - Texto visible: `Cerrar Sessión` con typo.
  - La navegación móvil funciona y el overlay no bloqueó la interacción en la prueba real.
  - El navegador reportó errores de `preload` no utilizado / `crossorigin` inconsistente en `header.php`.
- Riesgo:
  - No rompe el flujo, pero degrada percepción de calidad y puede sumar ruido/performance.
- Fix sugerido:
  - Corregir typo.
  - Ajustar o quitar `preload` sobrantes.

## Pruebas manuales ejecutadas

- `ventas.php` sin sesión: redirige correctamente a `../`.
- `ajax.php` con stock insuficiente: devuelve `stock_insuficiente`.
- `ajax.php` con cliente inválido al generar venta: devuelve `Selecciona un cliente válido.`
- `chart.php` sin login: expone datos.
- `api/index.php` sin clave: `401`.
- `api/index.php` con clave por defecto: acceso concedido.
- Navegación móvil en `ventas.php` y `productos.php`: operativa, sin bloqueo grave del overlay.

## Nota operativa

- Durante la verificación del hallazgo crítico de `anular.php`, el entorno local respondió `Venta Eliminada` al `POST idanular=1` sin autenticación. Eso implica que la venta `id=1` quedó eliminada en la base local auditada.
