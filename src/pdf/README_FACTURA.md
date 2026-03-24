# 📄 Generadores de PDF

Este directorio contiene los generadores de PDF del sistema.

## Archivos

### `generar.php`
- **Uso**: Recibo/comprobante tradicional
- **URL**: `pdf/generar.php?cl=ID_CLIENTE&v=ID_VENTA`
- **Incluye**: 
  - Datos de la venta
  - Cliente
  - Productos
  - Tipo de venta
  - Datos de cuenta corriente si aplica
  - Totales

### `generar_factura_electronica.php` ✨ **NUEVO**
- **Uso**: Factura electrónica oficial con CAE
- **URL**: `pdf/generar_factura_electronica.php?v=ID_VENTA`
- **Requisitos**: La venta debe tener factura aprobada
- **Incluye**:
  - Formato oficial ARCA/AFIP
  - CAE (Código de Autorización Electrónica)
  - Código QR para verificación
  - IVA discriminado (si corresponde)
  - Leyendas legales obligatorias

## ¿Cuál usar?

### Usa `generar.php` si:
- ✅ Querés un recibo simple
- ✅ No necesitás validez fiscal
- ✅ Es para uso interno

### Usa `generar_factura_electronica.php` si:
- ✅ Necesitás factura válida fiscalmente
- ✅ La venta fue facturada en ARCA
- ✅ El cliente necesita factura oficial
- ✅ Querés incluir código QR

## Diferencias Clave

| Característica | generar.php | generar_factura_electronica.php |
|----------------|-------------|----------------------------------|
| Validez fiscal | ❌ No | ✅ Sí |
| CAE | ❌ No | ✅ Sí |
| Código QR | ❌ No | ✅ Sí |
| Formato oficial | ❌ No | ✅ Sí |
| Requiere facturación previa | ❌ No | ✅ Sí |
| Discrimina IVA | ❌ No | ✅ Sí (A y B) |

## Documentación Completa

Ver: `docs/PDF_FACTURA_ELECTRONICA.md`

