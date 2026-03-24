# 🏗️ Arquitectura del Sistema de Facturación Electrónica

## 📊 Diagrama de Flujo General

```
┌─────────────────────────────────────────────────────────────────┐
│                    USUARIO DEL SISTEMA                           │
└────────────────────────────┬────────────────────────────────────┘
                             │
                             ↓
┌─────────────────────────────────────────────────────────────────┐
│                    INTERFAZ WEB (Frontend)                       │
│  ┌──────────────┐  ┌──────────────┐  ┌──────────────┐          │
│  │ ventas.php   │  │lista_ventas  │  │configuracion │          │
│  │(Punto Venta) │  │    .php      │  │_facturacion  │          │
│  └──────┬───────┘  └──────┬───────┘  └──────┬───────┘          │
│         │                  │                  │                   │
└─────────┼──────────────────┼──────────────────┼──────────────────┘
          │                  │                  │
          ↓                  ↓                  ↓
┌─────────────────────────────────────────────────────────────────┐
│               CAPA DE APLICACIÓN (Backend PHP)                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │              ajax.php (Procesar Venta)                    │   │
│  └───────────────────────┬──────────────────────────────────┘   │
│                          │                                       │
│                          ↓                                       │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │          procesar_factura.php (Facturación)              │   │
│  └───────────────────────┬──────────────────────────────────┘   │
│                          │                                       │
│                          ↓                                       │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │      FacturacionElectronica.php (Lógica de Negocio)      │   │
│  │  • Validar datos                                          │   │
│  │  • Determinar tipo de comprobante                         │   │
│  │  • Calcular IVA                                           │   │
│  │  • Preparar request                                       │   │
│  └───────────────────────┬──────────────────────────────────┘   │
│                          │                                       │
└──────────────────────────┼───────────────────────────────────────┘
                           │
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│                    SDK AFIP (Capa de Integración)                │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │              AfipSDK / Afip.php Library                   │   │
│  │  • Autenticación WSAA                                     │   │
│  │  • Firma digital                                          │   │
│  │  • Comunicación SOAP                                      │   │
│  └───────────────────────┬──────────────────────────────────┘   │
└──────────────────────────┼───────────────────────────────────────┘
                           │
                           ↓
┌─────────────────────────────────────────────────────────────────┐
│                      WEBSERVICES ARCA                            │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │  WSAA (Autenticación)                                     │   │
│  │    ↓                                                      │   │
│  │  WSFE (Factura Electrónica)                              │   │
│  │  • Validar datos                                          │   │
│  │  • Generar CAE                                            │   │
│  │  • Registrar comprobante                                  │   │
│  └───────────────────────┬──────────────────────────────────┘   │
└──────────────────────────┼───────────────────────────────────────┘
                           │
                           ↓ (CAE + Metadata)
┌─────────────────────────────────────────────────────────────────┐
│                      BASE DE DATOS MySQL                         │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ facturas_electronicas                                     │   │
│  │  • id, id_venta, cae, vencimiento_cae                    │   │
│  │  • tipo_comprobante, numero, estado                       │   │
│  │  • xml_request, xml_response                              │   │
│  └──────────────────────────────────────────────────────────┘   │
│                                                                   │
│  ┌──────────────────────────────────────────────────────────┐   │
│  │ ventas → detalle_venta → cliente → producto              │   │
│  └──────────────────────────────────────────────────────────┘   │
└─────────────────────────────────────────────────────────────────┘
```

## 🔄 Flujo de Facturación Detallado

### 1️⃣ Usuario genera venta

```
Usuario completa venta
    ↓
Se guarda en tabla 'ventas'
    ↓
Se guarda detalle en 'detalle_venta'
    ↓
Usuario ve botón "Facturar" en lista_ventas.php
```

### 2️⃣ Proceso de facturación

```
Click en "Facturar"
    ↓
JavaScript: facturacion.js → generarFacturaElectronica()
    ↓
AJAX POST → procesar_factura.php
    ↓
Instanciar FacturacionElectronica($conexion)
    ↓
generarFactura($id_venta)
```

### 3️⃣ Preparación de datos

```
Obtener datos de venta + cliente + items
    ↓
Determinar tipo de comprobante (A/B/C)
    ↓
Calcular IVA y totales
    ↓
Obtener próximo número de comprobante
    ↓
Preparar array de datos
```

### 4️⃣ Comunicación con ARCA

```
Inicializar SDK
    ↓
Cargar certificados digitales (.crt + .key)
    ↓
Autenticación WSAA (obtener Token + Sign)
    ↓
Llamar WSFE → CreateVoucher()
    ↓
Esperar respuesta
```

### 5️⃣ Procesamiento de respuesta

```
Recibir CAE de ARCA
    ↓
Guardar en facturas_electronicas
    ↓
Retornar resultado a frontend
    ↓
Mostrar mensaje de éxito con CAE
    ↓
Cambiar botón "Facturar" → "Facturado"
```

## 📦 Componentes del Sistema

### Frontend (JavaScript)

```javascript
facturacion.js
├── generarFacturaElectronica(idVenta)    // Generar factura
├── verDetallesFactura(idVenta)           // Ver info
└── descargarPDFFactura(idVenta)          // Descargar
```

### Backend (PHP)

```
src/
├── classes/
│   ├── FacturacionElectronica.php
│   │   ├── __construct()
│   │   ├── generarFactura()
│   │   ├── determinarTipoComprobante()
│   │   ├── inicializarSDK()
│   │   ├── obtenerProximoNumero()
│   │   └── guardarFactura()
│   │
│   └── FacturacionElectronicaAfipSDK.php (hereda de la anterior)
│       ├── inicializarSDK() [override]
│       ├── generarFactura() [override]
│       ├── consultarComprobante()
│       └── generarQR()
│
├── procesar_factura.php              // Endpoint para generar
├── obtener_factura.php               // Endpoint para consultar
└── configuracion_facturacion.php     // Panel de config
```

### Base de Datos

```sql
Tablas principales:

facturacion_config
├── id (PK)
├── cuit
├── razon_social
├── punto_venta
├── cert_path
├── key_path
└── produccion

facturas_electronicas
├── id (PK)
├── id_venta (FK)
├── tipo_comprobante
├── punto_venta
├── numero_comprobante
├── cae
├── vencimiento_cae
├── total
├── iva_total
├── neto_gravado
├── estado
├── xml_request
└── xml_response

tipos_comprobante
├── id (PK)
├── codigo (FA, FB, FC)
└── descripcion

cliente (modificaciones)
├── ... (campos existentes)
├── cuit
├── condicion_iva
└── tipo_documento
```

## 🔐 Seguridad y Autenticación

### Flujo de Autenticación con ARCA

```
1. Sistema lee certificados
   cert.crt + key.key
        ↓
2. SDK genera TRA (Ticket Request de Acceso)
   XML firmado con certificado
        ↓
3. Envía TRA a WSAA (Web Service de Autenticación)
   https://wsaa.afip.gov.ar/ws/services/LoginCms
        ↓
4. WSAA valida certificado y firma
        ↓
5. WSAA devuelve TA (Ticket de Acceso)
   contiene: Token + Sign + expiration
        ↓
6. SDK usa Token + Sign en cada request a WSFE
        ↓
7. TA dura 12 horas (se renueva automáticamente)
```

### Almacenamiento Seguro

```
Servidor
├── /var/www/certificados-afip/     (chmod 700)
│   ├── certificado.crt             (chmod 600)
│   └── clave_privada.key           (chmod 600)
│
└── /var/www/html/proyecto/
    └── storage/
        └── afip_ta/                (chmod 755)
            └── TA-*.xml            (tickets temporales)
```

## 🎯 Tipos de Comprobante y Lógica

### Matriz de Decisión

```
┌────────────────────────┬─────────────────┬─────────────┐
│ Emisor                 │ Cliente         │ Comprobante │
├────────────────────────┼─────────────────┼─────────────┤
│ Resp. Inscripto        │ Resp. Inscripto │ FACTURA A   │
│ Resp. Inscripto        │ Monotributo     │ FACTURA A   │
│ Resp. Inscripto        │ Exento          │ FACTURA A   │
│ Resp. Inscripto        │ Cons. Final     │ FACTURA B   │
│ Monotributo            │ Cualquiera      │ FACTURA C   │
│ Exento                 │ Cualquiera      │ FACTURA C   │
└────────────────────────┴─────────────────┴─────────────┘
```

### Cálculo de IVA

#### Factura A y B (discriminan IVA)

```php
// Entrada: Total con IVA = $121.00
$total = 121.00;
$alicuota_iva = 0.21; // 21%

// Cálculo inverso
$neto = $total / (1 + $alicuota_iva);
// $neto = 121 / 1.21 = $100.00

$iva = $total - $neto;
// $iva = 121 - 100 = $21.00

// Resultado:
// Neto: $100.00
// IVA:  $ 21.00
// Total: $121.00
```

#### Factura C (no discrimina)

```php
// Entrada: Total con IVA incluido = $121.00
$total = 121.00;

// Resultado:
// Neto: $121.00
// IVA:  $  0.00 (incluido en el neto)
// Total: $121.00
```

## 📡 Estructura de Request a ARCA

### Ejemplo de Request WSFE

```json
{
  "CantReg": 1,
  "PtoVta": 1,
  "CbteTipo": 6,
  "Concepto": 1,
  "DocTipo": 96,
  "DocNro": 12345678,
  "CbteDesde": 1,
  "CbteHasta": 1,
  "CbteFch": "20241206",
  "ImpTotal": 121.00,
  "ImpTotConc": 0,
  "ImpNeto": 100.00,
  "ImpOpEx": 0,
  "ImpIVA": 21.00,
  "ImpTrib": 0,
  "MonId": "PES",
  "MonCotiz": 1,
  "Iva": [
    {
      "Id": 5,
      "BaseImp": 100.00,
      "Importe": 21.00
    }
  ]
}
```

### Ejemplo de Response de ARCA

```json
{
  "CAE": "67123456789012",
  "CAEFchVto": "20241216",
  "Resultado": "A",
  "CbteTipo": 6,
  "PtoVta": 1,
  "CbteDesde": 1,
  "CbteHasta": 1,
  "ImpTotal": 121.00,
  "Observaciones": []
}
```

## 🔍 Códigos Importantes

### Tipos de Comprobante

```
1  = Factura A
6  = Factura B
11 = Factura C
3  = Nota de Crédito A
8  = Nota de Crédito B
13 = Nota de Crédito C
```

### Tipos de Documento

```
80 = CUIT
86 = CUIL
96 = DNI
99 = Consumidor Final (sin documento)
```

### Condiciones IVA

```
1 = IVA Responsable Inscripto
4 = IVA Sujeto Exento
5 = Consumidor Final
6 = Responsable Monotributo
```

### Alícuotas de IVA

```
3 = 0%
4 = 10.5%
5 = 21%
6 = 27%
```

### Conceptos de Comprobante

```
1 = Productos
2 = Servicios
3 = Productos y Servicios
```

## 📈 Escalabilidad

### Optimizaciones Implementadas

1. **Cache de Tickets de Acceso**
   - TA se guarda en disco
   - Reutilización durante 12 horas
   - Reduce llamadas a WSAA

2. **Índices de Base de Datos**
   ```sql
   INDEX idx_id_venta ON facturas_electronicas(id_venta)
   INDEX idx_fecha ON facturas_electronicas(fecha_emision)
   INDEX idx_estado ON facturas_electronicas(estado)
   ```

3. **Transacciones**
   - Uso de BEGIN/COMMIT
   - Rollback en caso de error
   - Integridad de datos

### Límites y Consideraciones

- **ARCA**: ~5 requests/segundo máximo
- **CAE**: Válido por 10 días
- **Certificados**: Renovar anualmente
- **Numeración**: Correlativa y sin saltos

## 🧪 Testing

### Ambientes

```
Testing (Homologación)
├── URL: https://wsaahomo.afip.gov.ar/
├── Certificados: Testing
└── Datos: No válidos fiscalmente

Producción
├── URL: https://wsaa.afip.gov.ar/
├── Certificados: Producción
└── Datos: Válidos fiscalmente (¡cuidado!)
```

### Script de Prueba

```bash
# Ejecutar test
php src/test_facturacion.php

# Opciones:
# 1. Verificar configuración
# 2. Probar generación (modo simulado)
# 3. Ver tipos de comprobante
# 4. Salir
```

## 🛠️ Mantenimiento

### Tareas Periódicas

1. **Diarias**
   - Verificar facturas con estado "error"
   - Backup de base de datos

2. **Semanales**
   - Revisar logs de errores
   - Verificar vencimiento de CAE (informativo)

3. **Mensuales**
   - Conciliar con reportes contables
   - Verificar espacio en disco

4. **Anuales**
   - Renovar certificados digitales
   - Actualizar SDK de AFIP
   - Revisar cambios en normativa

### Monitoreo

```sql
-- Facturas con error
SELECT * FROM facturas_electronicas 
WHERE estado = 'error' 
ORDER BY created_at DESC;

-- Facturas del día
SELECT COUNT(*), SUM(total) 
FROM facturas_electronicas 
WHERE DATE(fecha_emision) = CURDATE() 
AND estado = 'aprobado';

-- Rendimiento
SELECT 
    AVG(TIMESTAMPDIFF(SECOND, created_at, updated_at)) as tiempo_promedio
FROM facturas_electronicas 
WHERE DATE(created_at) = CURDATE();
```

## 📚 Referencias

- **ARCA WSFE**: https://www.afip.gob.ar/ws/
- **Manual Técnico**: https://www.afip.gob.ar/ws/documentacion/
- **SDK AfipPHP**: https://github.com/afipsdk/afip.php
- **Normativa**: RG AFIP sobre Factura Electrónica

---

**Diagrama creado**: Diciembre 2025  
**Versión**: 1.0

