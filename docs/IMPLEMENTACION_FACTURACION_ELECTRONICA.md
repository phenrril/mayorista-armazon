# 📋 Guía de Implementación - Facturación Electrónica ARCA (ex AFIP)

## 🎯 Resumen Ejecutivo

Esta implementación agrega facturación electrónica compatible con ARCA (ex AFIP) a tu sistema de ventas de óptica.

**Características principales:**
- ✅ Generación automática de facturas electrónicas (A, B, C)
- ✅ Detección automática del tipo de comprobante según condición IVA
- ✅ Almacenamiento de CAE (Código de Autorización Electrónica)
- ✅ Integración con tu sistema actual de ventas
- ✅ Interfaz moderna y fácil de usar
- ✅ Soporte para modo Testing y Producción

---

## 📦 Archivos Creados

### Backend PHP
- `src/classes/FacturacionElectronica.php` - Clase principal de facturación
- `src/procesar_factura.php` - Endpoint para generar facturas
- `src/obtener_factura.php` - Endpoint para consultar facturas
- `src/configuracion_facturacion.php` - Panel de configuración

### Frontend
- `assets/js/facturacion.js` - Funciones JavaScript

### Base de Datos
- `sql/setup_facturacion_electronica.sql` - Script de creación de tablas

### Configuración
- `composer.json` - Dependencias PHP

---

## 🚀 Pasos de Instalación

### 1️⃣ **Ejecutar Script SQL**

```bash
mysql -u c2880275_ventas -p c2880275_ventas < sql/setup_facturacion_electronica.sql
```

O desde phpMyAdmin:
1. Abre phpMyAdmin
2. Selecciona la base de datos `c2880275_ventas`
3. Ve a la pestaña "SQL"
4. Copia y pega el contenido de `sql/setup_facturacion_electronica.sql`
5. Ejecuta

**Tablas que se crearán:**
- `facturacion_config` - Configuración del sistema
- `facturas_electronicas` - Registro de facturas generadas
- `tipos_comprobante` - Catálogo de tipos de comprobante
- `condiciones_iva` - Condiciones frente al IVA

**Columnas agregadas a `cliente`:**
- `cuit` - CUIT del cliente
- `condicion_iva` - Condición IVA del cliente
- `tipo_documento` - Tipo de documento (DNI=96, CUIT=80)

---

### 2️⃣ **Instalar SDK de AFIP**

#### Opción A: AfipSDK (Recomendado)

```bash
cd /ruta/a/tu/proyecto
composer require afipsdk/afipsdk
```

#### Opción B: Afip.php

```bash
composer require afip-php/afip.php
```

Si no tenés Composer instalado:

```bash
# En Windows
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"
```

---

### 3️⃣ **Obtener Certificados de ARCA**

#### Paso a paso para obtener certificados:

1. **Ingresar a ARCA con CUIT y Clave Fiscal**
   - URL: https://auth.afip.gob.ar/

2. **Ir a "Administrador de Relaciones de Clave Fiscal"**

3. **Crear Solicitud de Certificado:**
   - Tipo: "Certificado para Homologación" (testing) o "Certificado de Producción"
   - Servicio: "wsfe" (Web Service de Factura Electrónica)
   - Generar archivo CSR (Certificate Signing Request)

4. **Subir CSR a ARCA:**
   - ARCA generará tu certificado (.crt)
   - Descargá el archivo .crt

5. **Guardar archivos en servidor:**

```bash
# Crear directorio seguro
mkdir -p /var/www/certificados-afip
chmod 700 /var/www/certificados-afip

# Copiar archivos
cp certificado.crt /var/www/certificados-afip/
cp clave_privada.key /var/www/certificados-afip/

# Proteger archivos
chmod 600 /var/www/certificados-afip/*
```

---

### 4️⃣ **Configurar el Sistema**

1. **Acceder al panel de configuración:**
   - URL: `http://tu-dominio.com/src/configuracion_facturacion.php`
   - Solo accesible por el administrador (id=1)

2. **Completar formulario:**
   - **CUIT**: Tu CUIT (formato: 20-12345678-9)
   - **Razón Social**: Nombre del negocio
   - **Condición IVA**: Tu condición (Responsable Inscripto, Monotributo, etc.)
   - **Punto de Venta**: Número habilitado en ARCA (ej: 1, 2, 3...)
   - **Ruta Certificado**: `/var/www/certificados-afip/certificado.crt`
   - **Ruta Clave**: `/var/www/certificados-afip/clave_privada.key`
   - **Modo**: Dejar en Testing hasta que funcione correctamente

3. **Guardar configuración**

---

### 5️⃣ **Actualizar Datos de Clientes**

Para que el sistema genere el tipo correcto de factura, necesitás actualizar los datos de tus clientes:

```sql
-- Ejemplo: Actualizar cliente con CUIT (para Factura A)
UPDATE cliente 
SET cuit = '20-12345678-9',
    condicion_iva = 'IVA Responsable Inscripto',
    tipo_documento = 80
WHERE idcliente = 123;

-- Ejemplo: Cliente Monotributo (Factura A o B según emisor)
UPDATE cliente 
SET cuit = '20-98765432-1',
    condicion_iva = 'Responsable Monotributo',
    tipo_documento = 80
WHERE idcliente = 456;

-- Ejemplo: Consumidor Final (Factura B)
UPDATE cliente 
SET condicion_iva = 'Consumidor Final',
    tipo_documento = 96
WHERE idcliente = 789;
```

---

### 6️⃣ **Integrar SDK Real**

El archivo `src/classes/FacturacionElectronica.php` tiene una función `simularRespuestaAFIP()` que es solo para desarrollo. Debes reemplazarla con llamadas reales al SDK.

#### Ejemplo con AfipSDK:

```php
private function inicializarSDK() {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $this->afip = new \AfipSDK\Afip([
        'CUIT' => $this->config['cuit'],
        'cert' => $this->config['cert_path'],
        'key'  => $this->config['key_path'],
        'production' => (bool) $this->config['produccion']
    ]);
    
    return true;
}

// En el método generarFactura(), reemplazar la simulación:
// ANTES (simulación):
$resultado = $this->simularRespuestaAFIP($comprobante_data);

// DESPUÉS (real):
$resultado = $this->afip->ElectronicBilling->CreateVoucher([
    'CantReg'    => 1,
    'PtoVta'     => $comprobante_data['PtoVta'],
    'CbteTipo'   => $comprobante_data['CbteTipo'],
    'Concepto'   => $comprobante_data['Concepto'],
    'DocTipo'    => $comprobante_data['DocTipo'],
    'DocNro'     => $comprobante_data['DocNro'],
    'CbteDesde'  => $comprobante_data['CbteDesde'],
    'CbteHasta'  => $comprobante_data['CbteHasta'],
    'CbteFch'    => $comprobante_data['CbteFch'],
    'ImpTotal'   => $comprobante_data['ImpTotal'],
    'ImpTotConc' => $comprobante_data['ImpTotConc'],
    'ImpNeto'    => $comprobante_data['ImpNeto'],
    'ImpOpEx'    => $comprobante_data['ImpOpEx'],
    'ImpIVA'     => $comprobante_data['ImpIVA'],
    'ImpTrib'    => $comprobante_data['ImpTrib'],
    'MonId'      => $comprobante_data['MonId'],
    'MonCotiz'   => $comprobante_data['MonCotiz'],
    'Iva'        => $comprobante_data['Iva'] ?? []
]);
```

#### Ejemplo con Afip.php:

```php
private function inicializarSDK() {
    require_once __DIR__ . '/../../vendor/autoload.php';
    
    $this->afip = new \Afip([
        'CUIT' => $this->config['cuit'],
        'production' => (bool) $this->config['produccion'],
        'cert' => $this->config['cert_path'],
        'key' => $this->config['key_path']
    ]);
    
    return true;
}
```

---

## 🎨 Uso del Sistema

### Generar Factura Electrónica

1. **Ir a "Lista de Ventas"**
   - URL: `http://tu-dominio.com/src/lista_ventas.php`

2. **Ver botón "Facturar" en cada venta**
   - Las ventas sin factura muestran botón azul "Facturar"
   - Las ventas facturadas muestran botón verde "Facturado"

3. **Click en "Facturar"**
   - Se abre un modal de confirmación
   - El sistema determina automáticamente el tipo de comprobante
   - Se genera la factura y obtiene el CAE de ARCA

4. **Ver resultado**
   - Se muestra el número de comprobante y CAE
   - La factura queda registrada en la base de datos

### Ver Detalles de Factura

1. Click en botón "Facturado" (verde)
2. Se muestra un modal con:
   - Tipo de comprobante (A, B o C)
   - Número completo
   - CAE y fecha de vencimiento
   - Totales (Neto, IVA, Total)

---

## 📊 Tipos de Comprobante

El sistema determina automáticamente el tipo de factura:

| Emisor | Cliente | Factura |
|--------|---------|---------|
| Responsable Inscripto | Responsable Inscripto | **A** |
| Responsable Inscripto | Monotributo | **A** |
| Responsable Inscripto | Exento | **A** |
| Responsable Inscripto | Consumidor Final | **B** |
| Monotributo | Cualquiera | **C** |
| Exento | Cualquiera | **C** |

### Factura A
- Discrimina IVA
- Requiere CUIT del cliente
- Para operaciones entre inscriptos

### Factura B
- Discrimina IVA
- Para Consumidores Finales
- Puede usar DNI

### Factura C
- No discrimina IVA
- Para Monotributos y Exentos
- Total incluye IVA

---

## 🔧 Configuración de IVA

Por defecto, el sistema asume **IVA 21%**. Si tus productos tienen diferentes alícuotas:

1. Agregá un campo `iva_alicuota` a la tabla `producto`:

```sql
ALTER TABLE producto 
ADD COLUMN iva_alicuota DECIMAL(5,2) DEFAULT 21.00 COMMENT 'Alícuota de IVA (21, 10.5, 27, 0)';
```

2. Modificá `FacturacionElectronica.php` para usar esta alícuota al calcular IVA.

---

## 🐛 Solución de Problemas

### Error: "No se encontró configuración de facturación electrónica"

**Solución:**
1. Verificá que ejecutaste el script SQL
2. Completá la configuración en `configuracion_facturacion.php`

### Error: "Error al conectar con AFIP"

**Solución:**
1. Verificá que las rutas de certificados sean correctas
2. Verificá que los archivos .crt y .key existan
3. Verificá permisos de archivos (chmod 600)
4. Asegurate de estar en modo Testing primero

### Error: "CAE no generado"

**Posibles causas:**
1. CUIT del cliente inválido (para Factura A)
2. Punto de venta no habilitado
3. Certificado vencido
4. Modo producción sin certificado válido

**Solución:**
- Revisá los logs en `xml_response` de la tabla `facturas_electronicas`
- Verificá estado en columna `observaciones`

### Facturas con estado "error"

**Revisar:**

```sql
SELECT id, id_venta, estado, observaciones, xml_response 
FROM facturas_electronicas 
WHERE estado = 'error' 
ORDER BY created_at DESC 
LIMIT 10;
```

---

## 📈 Reportes y Consultas

### Listar todas las facturas del mes

```sql
SELECT 
    CONCAT(LPAD(punto_venta, 4, '0'), '-', LPAD(numero_comprobante, 8, '0')) as numero,
    tc.descripcion as tipo,
    fecha_emision,
    total,
    cae,
    estado
FROM facturas_electronicas fe
LEFT JOIN tipos_comprobante tc ON fe.tipo_comprobante = tc.id
WHERE MONTH(fecha_emision) = MONTH(CURRENT_DATE())
AND YEAR(fecha_emision) = YEAR(CURRENT_DATE())
ORDER BY fecha_emision DESC;
```

### Total facturado por tipo

```sql
SELECT 
    tc.descripcion as tipo_factura,
    COUNT(*) as cantidad,
    SUM(total) as total_facturado
FROM facturas_electronicas fe
INNER JOIN tipos_comprobante tc ON fe.tipo_comprobante = tc.id
WHERE estado = 'aprobado'
AND YEAR(fecha_emision) = YEAR(CURRENT_DATE())
GROUP BY fe.tipo_comprobante
ORDER BY total_facturado DESC;
```

---

## 🔐 Seguridad

### Recomendaciones:

1. **Proteger certificados:**
```bash
chmod 600 /var/www/certificados-afip/*.{crt,key}
chown www-data:www-data /var/www/certificados-afip/*
```

2. **Restringir acceso a configuración:**
   - Solo el administrador (ID=1) puede acceder
   - Ya implementado en `configuracion_facturacion.php`

3. **Backup de certificados:**
   - Hacé backup de los archivos .crt y .key
   - Guardalos en un lugar seguro

4. **No commitear certificados:**
   - Agregá a `.gitignore`:
```
/certificados-afip/
*.crt
*.key
```

---

## 📚 Recursos Adicionales

- **Manual WSFE ARCA:** https://www.afip.gob.ar/ws/documentacion/ws-factura-electronica.asp
- **SDK AfipSDK:** https://github.com/afipsdk/afip.php
- **Tipos de Comprobante:** https://www.afip.gob.ar/factura-electronica/
- **Obtener Certificados:** https://www.afip.gob.ar/ws/WSAA/certificados.asp

---

## ✅ Checklist de Implementación

- [ ] Ejecutar script SQL
- [ ] Instalar Composer y SDK
- [ ] Obtener certificados de ARCA
- [ ] Configurar sistema (configuracion_facturacion.php)
- [ ] Actualizar datos de clientes (CUIT, condición IVA)
- [ ] Integrar SDK real (reemplazar simulación)
- [ ] Probar en modo Testing
- [ ] Validar que se generen CAE correctamente
- [ ] Pasar a modo Producción
- [ ] Configurar backup de certificados

---

## 🆘 Soporte

Si tenés problemas con la implementación:

1. Revisá los logs del servidor: `tail -f /var/log/apache2/error.log`
2. Revisá errores en la tabla: `SELECT * FROM facturas_electronicas WHERE estado = 'error'`
3. Consultá la documentación oficial de ARCA
4. Verificá que tu punto de venta esté habilitado en ARCA

---

## 📝 Notas Finales

- **Modo Testing:** Usá siempre primero para probar
- **Certificados diferentes:** Testing y Producción usan certificados distintos
- **CAE válido 10 días:** Después hay que pedir uno nuevo
- **Numeración correlativa:** No se puede saltar números
- **Backup importante:** Guardá certificados y configuración

---

**¡Listo! Tu sistema ahora tiene facturación electrónica integrada con ARCA.** 🎉

