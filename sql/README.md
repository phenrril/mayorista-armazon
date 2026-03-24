# Scripts SQL

## Scripts principales del proyecto

- `2026_mayorista_armazones.sql`: alta de columnas, tablas y permisos del flujo mayorista.
- `2026_sanitizacion_mayorista.sql`: limpieza opcional de tablas, permisos y columnas legacy removidas del sistema activo.
- `setup_facturacion_electronica.sql`: instalación del módulo de facturación electrónica.

---

# Facturación Electrónica

## 📄 Archivo: `setup_facturacion_electronica.sql`

Este archivo contiene todas las estructuras SQL necesarias para el sistema de facturación electrónica.

---

## 🎯 Dos Formas de Ejecutar

### ✅ Opción 1: Instalador Automático (RECOMENDADO)

El sistema incluye un botón de instalación automática que ejecuta este script por ti:

```
1. Acceder como administrador
2. Ir a: Configuración
3. Click en: "🚀 Instalar Ahora"
```

**Ventajas:**
- ✅ Un solo click
- ✅ Instala Composer automáticamente
- ✅ Crea directorios necesarios
- ✅ Configura todo el sistema
- ✅ Muestra log de progreso
- ✅ Maneja errores automáticamente

---

### 📝 Opción 2: Ejecución Manual

Si preferís ejecutar manualmente:

#### Desde línea de comandos:

```bash
# Linux/Mac
mysql -u usuario -p nombre_base_datos < sql/setup_facturacion_electronica.sql

# Windows
mysql -u usuario -p nombre_base_datos < sql\setup_facturacion_electronica.sql
```

#### Desde phpMyAdmin:

1. Acceder a phpMyAdmin
2. Seleccionar tu base de datos
3. Click en pestaña "SQL"
4. Copiar contenido de `setup_facturacion_electronica.sql`
5. Pegar y ejecutar

#### Desde MySQL Workbench:

1. Abrir MySQL Workbench
2. Conectar a tu base de datos
3. File → Run SQL Script
4. Seleccionar `setup_facturacion_electronica.sql`
5. Ejecutar

---

## 📊 ¿Qué hace este script?

### Tablas que crea:

1. **`facturacion_config`**
   - Configuración del sistema de facturación
   - CUIT, razón social, certificados, etc.

2. **`facturas_electronicas`**
   - Registro de todas las facturas generadas
   - Almacena CAE, tipo, número, estado, etc.

3. **`tipos_comprobante`**
   - Catálogo de tipos de comprobante
   - Factura A, B, C, Notas de Crédito, etc.

4. **`condiciones_iva`**
   - Condiciones frente al IVA
   - Responsable Inscripto, Monotributo, etc.

### Modificaciones en tablas existentes:

- **`cliente`**: Agrega campos `cuit`, `condicion_iva`, `tipo_documento`

### Vistas que crea:

- **`vista_facturas_completas`**: Para reportes y consultas

### Triggers:

- **`before_insert_factura`**: Valida que no se dupliquen facturas

### Datos iniciales:

- Catálogo completo de tipos de comprobante
- Catálogo de condiciones IVA
- Configuración inicial de ejemplo

---

## 🔍 Verificar Instalación

Después de ejecutar, verificá que todo se creó correctamente:

```sql
-- Ver todas las tablas de facturación
SHOW TABLES LIKE '%factur%';

-- Deberías ver:
-- facturacion_config
-- facturas_electronicas

-- Ver tipos de comprobante
SELECT * FROM tipos_comprobante;

-- Ver condiciones IVA
SELECT * FROM condiciones_iva;

-- Verificar columnas agregadas a cliente
DESCRIBE cliente;
-- Deberías ver: cuit, condicion_iva, tipo_documento
```

---

## ⚠️ Importante

### Antes de ejecutar:

1. **Hacer backup de la base de datos**
   ```bash
   mysqldump -u usuario -p nombre_bd > backup_antes.sql
   ```

2. **Verificar nombre de base de datos**
   - El script usa `c2880275_ventas` por defecto
   - Si tu BD tiene otro nombre, editá la línea:
     ```sql
     USE c2880275_ventas;
     ```

3. **Verificar permisos**
   - El usuario de MySQL debe tener permisos para:
     - CREATE TABLE
     - ALTER TABLE
     - CREATE VIEW
     - CREATE TRIGGER
     - INSERT

### Durante la ejecución:

- ⏱️ Toma aproximadamente 10-20 segundos
- 📊 Ejecuta ~50-60 queries
- ⚠️ Algunos warnings de "tabla ya existe" son normales

### Después de ejecutar:

1. **Verificar** que las tablas existan
2. **Completar** configuración en el panel web
3. **Obtener** certificados de ARCA
4. **Probar** en modo Testing

---

## 🔄 Reinstalación

### Si necesitás reinstalar:

```sql
-- CUIDADO: Esto borra todo
DROP TABLE IF EXISTS facturas_electronicas;
DROP TABLE IF EXISTS facturacion_config;
DROP TABLE IF EXISTS tipos_comprobante;
DROP TABLE IF EXISTS condiciones_iva;
DROP VIEW IF EXISTS vista_facturas_completas;

-- Luego ejecutar el script nuevamente
```

### Reinstalación selectiva:

Si solo querés recrear una tabla:

```sql
-- Ejemplo: Solo recrear tipos_comprobante
DROP TABLE IF EXISTS tipos_comprobante;

-- Luego ejecutar solo esa parte del script
```

---

## 🐛 Solución de Problemas

### Error: "Table already exists"

**Es normal** si estás reinstalando. El script usa `IF NOT EXISTS`.

**Solución:** Ignorar o usar `DROP TABLE` antes.

---

### Error: "Column already exists"

**Causa:** Ya agregaste las columnas a `cliente` antes.

**Solución:** Es seguro ignorar. El script usa `ADD COLUMN IF NOT EXISTS`.

---

### Error: "Access denied"

**Causa:** El usuario no tiene permisos suficientes.

**Solución:**
```sql
-- Como root, dar permisos
GRANT ALL PRIVILEGES ON nombre_bd.* TO 'usuario'@'localhost';
FLUSH PRIVILEGES;
```

---

### Error: "Cannot add foreign key constraint"

**Causa:** La tabla `ventas` no existe o no tiene el campo `id`.

**Solución:** Verificar que la BD principal esté creada correctamente.

---

### Script se interrumpe a mitad

**Causa:** Timeout o error de sintaxis.

**Solución:**
1. Verificar logs de MySQL
2. Ejecutar queries por partes
3. Aumentar timeout:
   ```sql
   SET SESSION wait_timeout = 3600;
   ```

---

## 📝 Estructura del Script

El script está organizado en secciones:

```sql
-- Sección 1: Tabla de configuración
CREATE TABLE facturacion_config...

-- Sección 2: Tabla de facturas
CREATE TABLE facturas_electronicas...

-- Sección 3: Tablas auxiliares
CREATE TABLE tipos_comprobante...
CREATE TABLE condiciones_iva...

-- Sección 4: Modificar tabla cliente
ALTER TABLE cliente...

-- Sección 5: Vistas
CREATE VIEW vista_facturas_completas...

-- Sección 6: Triggers
CREATE TRIGGER before_insert_factura...

-- Sección 7: Datos iniciales
INSERT INTO tipos_comprobante...
INSERT INTO condiciones_iva...
INSERT INTO facturacion_config...
```

Podés ejecutar cada sección por separado si lo necesitás.

---

## 🔗 Más Información

- **Instalador automático**: Ver `docs/INSTALACION_AUTOMATICA.md`
- **Configuración**: Ver `IMPLEMENTACION_FACTURACION_ELECTRONICA.md`
- **Uso del sistema**: Ver `INICIO_RAPIDO.md`

---

## ✅ Checklist de Instalación

Después de ejecutar el script:

- [ ] Tabla `facturacion_config` existe
- [ ] Tabla `facturas_electronicas` existe
- [ ] Tabla `tipos_comprobante` con 9 registros
- [ ] Tabla `condiciones_iva` con 10 registros
- [ ] Cliente tiene columnas: `cuit`, `condicion_iva`, `tipo_documento`
- [ ] Vista `vista_facturas_completas` existe
- [ ] Trigger `before_insert_factura` existe

**Si todo está ✅ → Instalación SQL exitosa!** 🎉

---

**Script SQL creado para facilitar la instalación del sistema de facturación electrónica** 📊

