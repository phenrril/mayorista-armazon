# 🚀 Instalación Automática - Facturación Electrónica

## 📋 ¿Qué hace el instalador automático?

El botón de **"Instalar Ahora"** en la página de Configuración realiza todas estas tareas automáticamente:

### ✅ Tareas que ejecuta:

1. **📊 Crear Tablas de Base de Datos**
   - `facturacion_config`
   - `facturas_electronicas`
   - `tipos_comprobante`
   - `condiciones_iva`
   - Modifica tabla `cliente` (agrega campos CUIT, condición IVA)

2. **📁 Crear Directorios**
   - `/storage/afip_ta` (para tickets de acceso)
   - `/storage/logs` (para logs del sistema)
   - `/storage/facturas_pdf` (para PDFs generados)
   - `/certificados-afip` (para certificados digitales)

3. **📦 Instalar Composer**
   - Detecta si ya está instalado
   - Si no, lo descarga automáticamente
   - Funciona con instalaciones globales o locales

4. **📚 Instalar Dependencias**
   - Ejecuta `composer install`
   - Descarga las librerías necesarias
   - Configura autoload de clases

5. **⚙️ Configuración Inicial**
   - Crea registro inicial en `facturacion_config`
   - Genera `.gitignore` si no existe
   - Crea archivo de estado `.facturacion_installed`

---

## 🎯 Cómo Usar

### Paso 1: Acceder a Configuración

```
Sistema → Configuración → [Botón "Instalar Ahora"]
```

### Paso 2: Click en "Instalar Ahora"

Se abrirá un modal de confirmación mostrando:
- Qué se instalará
- Tiempo estimado (2-3 minutos)
- Advertencia de un solo uso

### Paso 3: Confirmar Instalación

Click en **"Sí, Instalar"**

El proceso comenzará y verás:
- Indicador de carga
- Log en tiempo real (texto verde en fondo negro)
- Progreso de cada tarea

### Paso 4: Resultado

**Si todo sale bien:**
- ✅ Mensaje de éxito
- ✅ Lista de próximos pasos
- ✅ Botón se oculta (ya no puede usarse)

**Si hay advertencias:**
- ⚠️ Se muestra qué falló
- 📋 Log detallado disponible
- 🔄 Posibilidad de reintentar

---

## 🔍 Verificación Post-Instalación

Después de la instalación, verificá:

### 1. Base de Datos

```sql
-- Verificar que las tablas existan
SHOW TABLES LIKE '%factur%';

-- Debería mostrar:
-- facturacion_config
-- facturas_electronicas
```

### 2. Directorios

```
Proyecto/
├── storage/
│   ├── afip_ta/      ✅
│   ├── logs/         ✅
│   └── facturas_pdf/ ✅
├── certificados-afip/ ✅
└── vendor/            ✅ (si Composer instaló correctamente)
```

### 3. Archivos

```
✅ .facturacion_installed (marca que está instalado)
✅ .gitignore (protege archivos sensibles)
✅ vendor/autoload.php (si hay dependencias)
```

---

## 🐛 Solución de Problemas

### Error: "No se puede crear directorio"

**Causa:** Permisos insuficientes

**Solución:**
```bash
# Dar permisos al directorio del proyecto
chmod -R 755 /ruta/al/proyecto
chown -R www-data:www-data /ruta/al/proyecto
```

---

### Error: "No se pudo descargar Composer"

**Causa:** El servidor no permite descargas externas

**Solución manual:**
```bash
# En el directorio del proyecto
cd /ruta/al/proyecto
curl -sS https://getcomposer.org/installer | php
php composer.phar install
```

---

### Error: "composer install falló"

**Causa:** Falta extensión PHP o problema de red

**Solución:**
1. Verificar extensiones PHP necesarias:
```bash
php -m | grep -E 'curl|json|mbstring|openssl'
```

2. Instalar manualmente:
```bash
composer install --no-dev
```

---

### Error: "Ya existe la tabla"

**Causa:** Instalación previa incompleta

**Solución:**
El instalador ignora estos errores automáticamente. Si querés reinstalar:

```sql
-- CUIDADO: Esto borra todo
DROP TABLE IF EXISTS facturas_electronicas;
DROP TABLE IF EXISTS facturacion_config;
DROP TABLE IF EXISTS tipos_comprobante;
DROP TABLE IF EXISTS condiciones_iva;

-- Luego ejecutar el instalador nuevamente
```

---

### Instalación se queda "cargando"

**Causa:** Timeout del servidor

**Solución:**
1. Esperar 3-5 minutos
2. Recargar la página
3. Si el botón desapareció = instalación exitosa
4. Si el botón sigue = revisar logs del servidor

---

## 🔄 Reinstalación

### ¿Cuándo reinstalar?

- Instalación falló a mitad
- Borraste tablas por error
- Querés empezar de cero

### Cómo forzar reinstalación:

```bash
# Borrar archivo de estado
rm .facturacion_installed

# Recargar página de Configuración
# El botón reaparecerá
```

O desde SQL:
```sql
DROP TABLE IF EXISTS facturacion_config;
-- Recargar página
```

---

## 📊 Log de Instalación

El log muestra con colores:

| Color | Significado |
|-------|-------------|
| 🟢 Verde | Acción completada exitosamente |
| 🟡 Naranja | Advertencia (no crítico) |
| 🔴 Rojo | Error (requiere atención) |
| 🔵 Cyan | Información adicional |

### Ejemplo de log exitoso:

```
📊 Creando tablas de base de datos...
✅ Base de datos configurada (45 queries ejecutadas)
📁 Creando directorios necesarios...
✅ Directorio creado: afip_ta
✅ Directorio creado: logs
✅ Directorio creado: facturas_pdf
✅ Directorio creado: certificados-afip
📦 Verificando Composer...
✅ Composer encontrado (instalación global)
📚 Instalando dependencias PHP...
✅ Dependencias instaladas correctamente
⚙️ Configurando datos iniciales...
✅ Configuración inicial creada
✅ Archivo de instalación creado
```

---

## 🎯 Próximos Pasos

Después de instalar, debés:

### 1. Obtener Certificados ARCA
- Ingresar a https://auth.afip.gob.ar/
- Administrador de Relaciones
- Crear certificado para WSFE
- Descargar .crt y .key

### 2. Configurar Datos Fiscales
- Ir a: `Configuración → Configuración de Facturación`
- Completar:
  - CUIT
  - Razón Social
  - Punto de Venta
  - Rutas de certificados
  - Condición IVA

### 3. Actualizar Clientes
```sql
-- Ejemplo: Cliente con CUIT (Factura A)
UPDATE cliente 
SET cuit = '20-12345678-9',
    condicion_iva = 'IVA Responsable Inscripto',
    tipo_documento = 80
WHERE idcliente = X;

-- Ejemplo: Consumidor Final (Factura B)
UPDATE cliente 
SET condicion_iva = 'Consumidor Final',
    tipo_documento = 96
WHERE idcliente = Y;
```

### 4. Probar en Testing
- Modo Testing PRIMERO (no gastar CAE reales)
- Generar facturas de prueba
- Verificar que funcione correctamente

### 5. Pasar a Producción
- Obtener certificados de producción
- Cambiar a "Modo Producción"
- Generar primera factura real

---

## ⚠️ Importante

### Seguridad

- ✅ El botón solo puede usarse UNA vez
- ✅ Solo el administrador (ID=1) puede ejecutarlo
- ✅ Requiere confirmación antes de instalar
- ✅ No sobrescribe datos existentes

### Backups

**Antes de instalar:**
```bash
# Backup de base de datos
mysqldump -u usuario -p base_datos > backup_antes_instalacion.sql
```

**Después de instalar:**
- Los certificados son críticos → Guardar backup
- La configuración es importante → Exportar

---

## 📞 Soporte

### Si algo sale mal:

1. **Revisar el log** en la pantalla
2. **Verificar permisos** de directorios
3. **Consultar logs del servidor** (`/var/log/apache2/error.log`)
4. **Intentar instalación manual** (ver guía completa)

### Archivos de log:

```bash
# Log de Apache
tail -f /var/log/apache2/error.log

# Log de PHP
tail -f /var/log/php/error.log

# Verificar permisos
ls -la /ruta/al/proyecto
```

---

## ✅ Checklist Final

Después de instalar, verificá:

- [ ] Tablas creadas en base de datos
- [ ] Directorios existen y tienen permisos
- [ ] Composer instalado
- [ ] Vendor directory existe
- [ ] Archivo `.facturacion_installed` existe
- [ ] Botón de instalación ya no aparece
- [ ] Link a "Configuración de Facturación" visible

**Si todo está ✅ → ¡Instalación exitosa!** 🎉

---

## 🔗 Más Información

- **Guía Completa**: `IMPLEMENTACION_FACTURACION_ELECTRONICA.md`
- **Configurar Sistema**: `src/configuracion_facturacion.php`
- **FAQ**: `docs/PREGUNTAS_FRECUENTES.md`

---

**Instalación automatizada creada para facilitar el setup inicial** 🚀

