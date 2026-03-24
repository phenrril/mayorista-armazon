# ⚡ Inicio Rápido - Facturación Electrónica

## 🆕 Novedades

### 1. **Instalador Automático de 1 Click** 
Ahora podés instalar todo el sistema de facturación con un solo botón.

### 2. **PDF de Factura Electrónica**
Se genera automáticamente con CAE y código QR.

---

## 🚀 Instalación Rápida (1 click)

### Paso 1: Ejecutar Instalador
```
Sistema → Configuración → [🚀 Instalar Ahora]
```
- Click en el botón
- Confirmar
- Esperar 2-3 minutos
- ¡Listo! Todo configurado automáticamente

**El instalador hace:**
- ✅ Crea todas las tablas necesarias
- ✅ Instala Composer y dependencias
- ✅ Crea directorios requeridos
- ✅ Configura el sistema

---

## 🎯 Cómo Usar (después de instalar)

### 1. Configurar Datos Fiscales
```
Sistema → Configuración de Facturación
```
- Completar CUIT, Razón Social, etc.
- Subir certificados de ARCA
- Configurar punto de venta

### 2. Realizar Venta y Facturar
```
Ventas → Nueva Venta → Lista de Ventas → [🔵 Facturar]
```
- Se genera automáticamente el tipo correcto (A, B o C)
- Se obtiene el CAE de ARCA
- Botón cambia a "🟢 Facturado"

### 3. Descargar PDF
```
Click en botón [🔴 PDF]
```
- Se abre en nueva ventana
- PDF con CAE, código QR y formato oficial
- Listo para imprimir o enviar al cliente

---

## 📋 ¿Qué incluye el PDF?

✅ Logo de tu negocio  
✅ Letra del comprobante (A, B o C)  
✅ Número de factura (0001-00000123)  
✅ Datos fiscales completos  
✅ Detalle de productos  
✅ IVA discriminado (si corresponde)  
✅ **CAE destacado en grande**  
✅ **Código QR** para verificar  
✅ Leyendas legales  

---

## 🎨 Interfaz

### Venta SIN facturar:
```
[🔵 Facturar]  [Ver PDF recibo]
```

### Venta facturada:
```
[🟢 Facturado] [🔴 PDF]  [Ver PDF recibo]
```

**Dos PDF diferentes:**
1. **Recibo tradicional** (Ver PDF) → Sin CAE, uso interno
2. **Factura electrónica** (🔴 PDF) → Con CAE, validez fiscal

---

## 🔧 Archivos Creados

```
src/
└── pdf/
    └── generar_factura_electronica.php  ← Genera el PDF

assets/
└── js/
    └── facturacion.js  ← Botón para descargar

docs/
└── PDF_FACTURA_ELECTRONICA.md  ← Documentación completa
```

---

## ⚠️ Importante

1. **Solo funciona con ventas facturadas**
   - Primero click en "Facturar"
   - Luego aparece botón "PDF"

2. **Requiere configuración previa**
   - Certificados de ARCA
   - Datos fiscales configurados
   - Ver: `IMPLEMENTACION_FACTURACION_ELECTRONICA.md`

3. **Diferentes tipos de factura**
   - **A**: Para inscriptos (discrimina IVA)
   - **B**: Para consumidor final (discrimina IVA)
   - **C**: Para monotributo (no discrimina IVA)

---

## 📖 Más Información

- **Guía Completa**: `IMPLEMENTACION_FACTURACION_ELECTRONICA.md`
- **Sobre el PDF**: `docs/PDF_FACTURA_ELECTRONICA.md`
- **Interfaz**: `docs/INTERFAZ_USUARIO.md`
- **FAQ**: `docs/PREGUNTAS_FRECUENTES.md`

---

## 🎉 ¡Listo!

Ahora podés:
1. ✅ Facturar en ARCA con un click
2. ✅ Generar PDF oficial automáticamente
3. ✅ Entregar al cliente factura válida
4. ✅ Incluir código QR para verificación

**Todo integrado en tu sistema actual sin romper nada.** 🚀

