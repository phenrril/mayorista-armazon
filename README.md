# Sistema Mayorista de Armazones

Aplicación PHP + MySQL para gestión comercial mayorista/minorista con cuenta corriente, reportes, estadísticas y facturación electrónica ARCA.

## Alcance actual

### Comercial
- Punto de venta con tipo `mayorista` y `minorista`
- Carrito con edición única de precio por ítem
- Cuenta corriente por cliente con límite de crédito
- Listado de ventas con saldo de CC y precio modificado
- Catálogo de productos con precio minorista y mayorista

### Reportes y análisis
- Dashboard comercial con filtros por fecha
- Ranking de productos por cantidad y monto
- Ranking de clientes por volumen de compra
- Reportes de ventas, cuenta corriente, productos y tesorería
- Exportación PDF de ventas, cuenta corriente y reportes

### Integraciones
- Facturación electrónica ARCA
- API REST para OpenClaw con `X-API-Key`
- Stack OpenClaw unico para Docker Desktop en Windows

## Módulos principales

- `src/ventas.php`: alta de ventas con cuenta corriente
- `src/lista_ventas.php`: historial comercial
- `src/cuenta_corriente.php`: saldo, límite, pagos manuales y PDF
- `src/productos.php`: ABM y ajuste masivo de precios
- `src/estadisticas.php`: dashboard con filtros
- `src/reporte.php`: reportes con exportación PDF
- `src/api/index.php`: endpoints para OpenClaw

## Instalación base

1. Importar la base inicial del sistema.
2. Configurar `conexion.php`.
3. Ejecutar la migración mayorista:

```bash
mysql -u usuario -p nombre_bd < sql/2026_mayorista_armazones.sql
```

4. Si querés una limpieza completa del legado removido, ejecutar también:

```bash
mysql -u usuario -p nombre_bd < sql/2026_sanitizacion_mayorista.sql
```

5. Si usás facturación electrónica, instalar dependencias:

```bash
composer install
```

6. Si usás ARCA, ejecutar además:

```bash
mysql -u usuario -p nombre_bd < sql/setup_facturacion_electronica.sql
```

## Deploy recomendado

- Revisar `docs/deploy-mayorista.md`
- Configurar `MAYORISTA_API_KEY` en producción
- Asignar permisos desde `src/rol.php`
- Validar ventas, cuenta corriente, reportes y API antes de publicar

## Documentación útil

- `docs/deploy-mayorista.md`
- `docs/api-openclaw.md`
- `docs/openclaw-skills.md`
- `docs/openclaw.md`
- `IMPLEMENTACION_FACTURACION_ELECTRONICA.md`
- `docs/PDF_FACTURA_ELECTRONICA.md`

## Notas de sanitización

El proyecto ya no incluye historia clínica, calendario, cristal ni el flujo de graduaciones como parte del sistema activo. Para dejar la base y el código alineados, usar la migración `sql/2026_sanitizacion_mayorista.sql` después de validar que no necesitás conservar esos datos legacy.
4. Realizar backups regulares
5. Monitorear logs

### Backup Automático

Configurá un cron job para backups diarios:

```bash
0 2 * * * mysqldump -u usuario -ppassword c2880275_ventas > /backup/optica_$(date +\%Y\%m\%d).sql
```

## 🔄 Actualizaciones

Para actualizar a la última versión:

```bash
git pull origin main
composer update
# Ejecutar scripts SQL de migración si los hay
```

---

**Desarrollado con ❤️ para facilitar la gestión de ópticas**

