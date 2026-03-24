# 📄 PDF de Factura Electrónica

## 🎯 Descripción

El sistema genera un PDF con formato oficial de factura electrónica según la normativa de ARCA/AFIP. Este PDF incluye toda la información requerida legalmente y puede ser entregado al cliente.

---

## ✨ Características del PDF

### ✅ Incluye:

1. **Datos del Emisor**
   - Logo del negocio
   - Razón social
   - CUIT
   - Dirección, teléfono, email
   - Condición IVA
   - Inicio de actividades

2. **Letra del Comprobante**
   - Letra grande en recuadro central (A, B o C)
   - Código de tipo de comprobante

3. **Datos del Comprobante**
   - Punto de venta
   - Número completo (formato: 0001-00000123)
   - Fecha de emisión

4. **Datos del Cliente**
   - Nombre/Razón social
   - CUIT/DNI
   - Condición IVA
   - Dirección
   - Teléfono

5. **Detalle de Productos**
   - **Factura A y B**: Cantidad, Descripción, P.Unit, Neto, IVA, Subtotal
   - **Factura C**: Cantidad, Descripción, P.Unit, Subtotal

6. **Totales**
   - **Factura A y B**: 
     - Neto Gravado
     - IVA 21%
     - **TOTAL** (destacado)
   - **Factura C**:
     - Nota: "IVA incluido en el precio"
     - **TOTAL** (destacado)

7. **CAE (Código de Autorización Electrónica)**
   - Número de CAE destacado en grande
   - Fecha de vencimiento del CAE
   - Fondo amarillo para destacar

8. **Código QR**
   - Código QR según especificación de ARCA
   - Permite verificar la factura escaneando con celular
   - Link directo a AFIP para validación

9. **Pie de Página**
   - Leyendas legales requeridas
   - Información sobre validación

---

## 🔍 Diferencias por Tipo de Factura

### Factura A
```
┌─────────────────────────────────────────┐
│          EMISOR        │ A │   DATOS    │
├─────────────────────────────────────────┤
│  Cliente: Resp. Inscripto  CUIT: XXX    │
├──────────────────────────────────────────┤
│ Cant │ Desc │ P.Unit │ Neto │ IVA │ Sub │
├──────────────────────────────────────────┤
│  1   │ Prod │ 121.00 │ 100 │ 21 │ 121  │
├──────────────────────────────────────────┤
│                    Neto Gravado: $100.00 │
│                    IVA 21%:      $ 21.00 │
│                    TOTAL:        $121.00 │
├──────────────────────────────────────────┤
│  CAE: 12345678901234    Venc: 15/12/2024 │
│  [QR CODE]  Escaneá para verificar       │
└──────────────────────────────────────────┘
```

### Factura B
```
┌─────────────────────────────────────────┐
│          EMISOR        │ B │   DATOS    │
├─────────────────────────────────────────┤
│  Cliente: Consumidor Final  DNI: XXX    │
├──────────────────────────────────────────┤
│ Cant │ Desc │ P.Unit │ Neto │ IVA │ Sub │
├──────────────────────────────────────────┤
│  1   │ Prod │ 121.00 │ 100 │ 21 │ 121  │
├──────────────────────────────────────────┤
│                    Neto Gravado: $100.00 │
│                    IVA 21%:      $ 21.00 │
│                    TOTAL:        $121.00 │
├──────────────────────────────────────────┤
│  CAE: 12345678901234    Venc: 15/12/2024 │
│  [QR CODE]  Escaneá para verificar       │
└──────────────────────────────────────────┘
```

### Factura C
```
┌─────────────────────────────────────────┐
│          EMISOR        │ C │   DATOS    │
├─────────────────────────────────────────┤
│  Cliente: Monotributo  CUIT: XXX        │
├──────────────────────────────────────────┤
│ Cant │  Descripción       │ P.Unit │ Sub │
├──────────────────────────────────────────┤
│  1   │  Producto X        │ 121.00 │ 121 │
├──────────────────────────────────────────┤
│              (IVA incluido en el precio) │
│                    TOTAL:        $121.00 │
├──────────────────────────────────────────┤
│  CAE: 12345678901234    Venc: 15/12/2024 │
│  [QR CODE]  Escaneá para verificar       │
└──────────────────────────────────────────┘
```

---

## 🚀 Cómo Usar

### Desde Lista de Ventas

1. **Ventas facturadas** muestran 2 botones:
   - 🟢 **"Facturado"** → Ver detalles
   - 🔴 **"PDF"** → Descargar PDF

2. Click en **"PDF"** abre el PDF en nueva pestaña

3. El cliente puede:
   - Ver en pantalla
   - Descargar
   - Imprimir

### Desde Modal de Detalles

1. Click en **"Facturado"** (botón verde)
2. Se abre modal con información
3. Click en **"Descargar PDF"**
4. Se abre el PDF en nueva pestaña

---

## 📋 Información del Código QR

El código QR contiene datos en formato JSON según especificación de AFIP:

```json
{
  "ver": 1,
  "fecha": "2024-12-06",
  "cuit": 20123456789,
  "ptoVta": 1,
  "tipoCmp": 6,
  "nroCmp": 123,
  "importe": 121.00,
  "moneda": "PES",
  "ctz": 1,
  "tipoDocRec": 99,
  "nroDocRec": 0,
  "tipoCodAut": "E",
  "codAut": 67123456789012
}
```

Este JSON se codifica en Base64 y se usa para:
1. Generar el código QR
2. Validar en sitio de AFIP: `https://www.afip.gob.ar/fe/qr/`

---

## 🔧 Personalización

### Cambiar Logo

Reemplazar archivo:
```
assets/img/logo.png
```

Requisitos:
- Formato: PNG con transparencia
- Tamaño recomendado: 300x300 px
- Peso: menos de 100KB

### Cambiar Colores

Editar `src/pdf/generar_factura_electronica.php`:

```php
// Fondo de sección CAE
$pdf->SetFillColor(255, 255, 200); // RGB: amarillo claro

// Fondo de totales
$pdf->SetFillColor(220, 220, 220); // RGB: gris claro

// Color de texto
$pdf->SetTextColor(0, 0, 0); // RGB: negro
```

### Agregar Campos Adicionales

```php
// Después de los datos del cliente
$pdf->SetFont('Arial', '', 9);
$pdf->Cell(50, 5, 'Nuevo Campo:', 0, 0, 'L');
$pdf->SetFont('Arial', 'B', 9);
$pdf->Cell(140, 5, 'Valor del campo', 0, 1, 'L');
```

---

## 🐛 Solución de Problemas

### Error: "No se encontró factura electrónica"

**Causa**: La venta no tiene factura generada.

**Solución**: 
1. Ir a lista de ventas
2. Click en "Facturar"
3. Una vez aprobada, el PDF estará disponible

---

### Error: "La factura no está aprobada"

**Causa**: La factura tiene estado diferente a "aprobado".

**Solución**:
```sql
-- Verificar estado
SELECT estado, observaciones 
FROM facturas_electronicas 
WHERE id_venta = 123;

-- Si está en error, revisar observaciones
-- Puede necesitar re-facturar
```

---

### El código QR no aparece

**Causa**: El servidor no puede descargar imágenes externas.

**Solución temporal**: El sistema muestra el enlace de verificación como alternativa.

**Solución permanente**: Instalar librería QR local.

#### Opción 1: Usar librería PHP QR Code

```bash
composer require endroid/qr-code
```

```php
// En generar_factura_electronica.php
use Endroid\QrCode\QrCode;

$qrCode = new QrCode($qr_url_afip);
$qrCode->writeFile('temp_qr.png');
$pdf->Image('temp_qr.png', 15, $y_qr, 35, 35);
unlink('temp_qr.png');
```

---

### El PDF no muestra caracteres especiales (tildes)

**Causa**: FPDF no maneja UTF-8 nativamente.

**Solución**: Ya implementada con `utf8_decode()` en todos los textos.

Si sigue fallando:
```php
// Verificar que la codificación de la BD sea UTF-8
$pdf->Cell(50, 5, utf8_decode($texto), 0, 0, 'L');
```

---

## 📊 Validación Legal

### Elementos Obligatorios ✅

El PDF incluye todos los elementos requeridos por ARCA:

- ✅ Letra del comprobante (A, B o C)
- ✅ Número de comprobante
- ✅ Fecha de emisión
- ✅ CUIT del emisor
- ✅ Razón social del emisor
- ✅ Datos del cliente
- ✅ Detalle de productos/servicios
- ✅ IVA discriminado (A y B)
- ✅ Total
- ✅ **CAE**
- ✅ **Vencimiento del CAE**
- ✅ **Código QR**

### Leyendas Obligatorias ✅

- ✅ "Este documento es una representación impresa..."
- ✅ "La validez puede verificarse en..."
- ✅ Link de verificación de AFIP

---

## 🎨 Formato del PDF

### Especificaciones

- **Tamaño**: A4 (210 x 297 mm)
- **Orientación**: Vertical (Portrait)
- **Márgenes**: 10mm
- **Fuente**: Arial
- **Colores**: Escala de grises + amarillo para CAE

### Secciones

1. **Encabezado** (0-75mm)
   - Logo, datos emisor, letra, datos comprobante

2. **Cliente** (75-105mm)
   - Datos completos del cliente

3. **Detalle** (105-200mm variable)
   - Tabla de productos

4. **Totales** (variable)
   - Neto, IVA, Total

5. **CAE y QR** (variable)
   - Información de autorización

6. **Pie** (últimos 25mm)
   - Leyendas legales

---

## 🔄 Actualizaciones Futuras

### Mejoras Planificadas

- [ ] Generación de QR local (sin servicio externo)
- [ ] Soporte para múltiples alícuotas de IVA
- [ ] Exportar a formato PDF/A (archivo)
- [ ] Envío automático por email
- [ ] Logo personalizable desde panel
- [ ] Pie de página personalizable
- [ ] Watermark para testing

---

## 📧 Envío por Email

Para implementar envío automático:

```php
// Agregar en generar_factura_electronica.php

// Guardar PDF en variable
$pdf_content = $pdf->Output('S');

// Enviar por email
$mail = new PHPMailer();
$mail->Subject = 'Factura Electrónica';
$mail->AddStringAttachment($pdf_content, $nombre_archivo);
$mail->Send();
```

---

## 💾 Almacenamiento

### Opción 1: Generación bajo demanda (actual)

✅ No ocupa espacio en servidor
✅ Siempre actualizado si cambias el formato
❌ Requiere regenerar cada vez

### Opción 2: Guardar al generar factura

```php
// En procesar_factura.php después de obtener CAE
$pdf_path = '../facturas_pdf/' . $id_venta . '.pdf';
// Generar y guardar PDF
```

✅ Más rápido al consultar
✅ No depende de BD para regenerar
❌ Ocupa espacio en disco
❌ Hay que hacer backup de PDFs

---

## 📖 Referencias

- **AFIP - Factura Electrónica**: https://www.afip.gob.ar/factura-electronica/
- **Código QR AFIP**: https://www.afip.gob.ar/fe/qr/
- **FPDF Manual**: http://www.fpdf.org/
- **Normativa**: RG 4291/2018 y complementarias

---

**¡El PDF está listo para usar!** 🎉

Solo necesitás:
1. Facturar una venta
2. Click en el botón "PDF"
3. El cliente recibe su factura oficial

