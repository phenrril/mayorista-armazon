# 🖥️ Interfaz de Usuario - Facturación Electrónica

## Vista General: Lista de Ventas

```
┌─────────────────────────────────────────────────────────────────────┐
│  📋 Lista de Ventas                    💰 Total de Ventas: 150      │
│  📅 Historial de todas las ventas      💵 Total General: $45,000    │
└─────────────────────────────────────────────────────────────────────┘

┌──────────────────────────────────────────────────────────────────────┐
│ ID  │ Cliente      │ Total     │ Fecha        │ Factura   │ Recibo  │
├──────────────────────────────────────────────────────────────────────┤
│ #50 │ Juan Pérez   │ $1,210.00 │ 06/12 14:30 │ [🔵 Facturar] │ [Ver PDF] │
│                                                                        │
│ #49 │ María López  │ $2,420.00 │ 06/12 10:15 │ [🟢 Facturado][🔴 PDF] │ [Ver PDF] │
│                                                                        │
│ #48 │ Carlos Díaz  │ $  850.00 │ 05/12 16:45 │ [🟢 Facturado][🔴 PDF] │ [Ver PDF] │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 🎨 Estados de Facturación

### 1️⃣ Venta SIN Facturar

```
┌──────────────────────┐
│  🔵 Facturar         │  ← Botón AZUL/MORADO
└──────────────────────┘
```

**Significado:**
- Venta registrada pero no facturada en ARCA
- Click genera la factura electrónica
- Obtiene CAE automáticamente

**Cuándo usar:**
- Cliente solicita factura oficial
- Necesitás validez fiscal
- Requiere CAE

---

### 2️⃣ Venta Facturada

```
┌────────────────────────────────────┐
│  🟢 Facturado   │  🔴 PDF          │
└────────────────────────────────────┘
```

**Significado:**
- Factura electrónica generada y aprobada
- Tiene CAE válido
- PDF disponible para descarga

**Botones:**
- **🟢 Facturado**: Ver detalles (CAE, tipo, etc.)
- **🔴 PDF**: Descargar factura oficial

---

## 🔍 Flujo de Uso Completo

### Escenario A: Cliente necesita factura

```
1. Realizar Venta
   ↓
   Se guarda en sistema
   ↓
2. Cliente pide factura
   ↓
3. [Lista de Ventas]
   ↓
   Click en "🔵 Facturar"
   ↓
4. [Confirmación]
   ┌─────────────────────────────────┐
   │ ¿Generar Factura Electrónica?   │
   │                                  │
   │ Se determinará automáticamente   │
   │ el tipo según condición IVA      │
   │                                  │
   │  [❌ Cancelar]  [✅ Generar]     │
   └─────────────────────────────────┘
   ↓
5. [Generando...]
   Sistema envía a ARCA
   ↓
6. [✅ Éxito]
   ┌─────────────────────────────────┐
   │ ✅ ¡Factura Generada!            │
   │                                  │
   │ Comprobante: FACTURA B           │
   │              0001-00000123       │
   │ CAE: 67123456789012              │
   │ Vencimiento: 16/12/2024          │
   │                                  │
   │           [Aceptar]              │
   └─────────────────────────────────┘
   ↓
7. Botones cambian a:
   [🟢 Facturado] [🔴 PDF]
   ↓
8. Click en "🔴 PDF"
   ↓
   Se abre PDF oficial con CAE
```

---

### Escenario B: Ver detalles de factura existente

```
1. [Lista de Ventas]
   ↓
   Venta con [🟢 Facturado]
   ↓
2. Click en "🟢 Facturado"
   ↓
3. [Modal de Detalles]
   ┌──────────────────────────────────┐
   │ Detalles de Factura Electrónica │
   │                                  │
   │ 🟢 Estado: APROBADO              │
   │ ──────────────────────────       │
   │ Tipo:         FACTURA B          │
   │ Número:       0001-00000123      │
   │ Fecha:        06/12/2024         │
   │ CAE:          67123456789012     │
   │ Venc. CAE:    16/12/2024         │
   │ ──────────────────────────       │
   │ Neto Gravado: $1,000.00          │
   │ IVA:          $  210.00          │
   │ ───────────────────────          │
   │ Total:        $1,210.00          │
   │                                  │
   │  [Cerrar]  [📄 Descargar PDF]   │
   └──────────────────────────────────┘
```

---

### Escenario C: Solo recibo (sin facturar)

```
1. [Lista de Ventas]
   ↓
   Venta con [🔵 Facturar]
   ↓
2. No hacer click en "Facturar"
   ↓
3. Click en [Ver PDF] (columna Recibo)
   ↓
   Se abre recibo tradicional
   (sin CAE, no válido fiscalmente)
```

---

## 📱 Vista Móvil

### Desktop (>768px)
```
┌─────────────────────────────────────────────────┐
│ ID │ Cliente │ Total │ Fecha │ Factura │ Recibo │
│ 50 │ Juan... │ $1,210│ 06/12 │ [🟢][🔴]│ [PDF] │
└─────────────────────────────────────────────────┘
```

### Mobile (<576px)
```
┌──────────────────────┐
│ ID: 50               │
│ Cliente: Juan...     │
│ Total: $1,210        │
│ Fecha: 06/12         │
│                      │
│ Factura:             │
│ ┌──────────────────┐ │
│ │ 🟢 Facturado     │ │
│ ├──────────────────┤ │
│ │ 🔴 PDF           │ │
│ └──────────────────┘ │
│                      │
│ Recibo:              │
│ [Ver PDF]            │
└──────────────────────┘
```

Los botones se apilan verticalmente para mejor usabilidad.

---

## 🎯 Colores y Significado

### Botones de Facturación

| Color | Gradiente | Acción | Estado |
|-------|-----------|--------|--------|
| 🔵 Azul/Morado | `#667eea → #764ba2` | **Facturar** | Sin factura |
| 🟢 Verde | `#11998e → #38ef7d` | **Facturado** | Con CAE aprobado |
| 🔴 Rojo | `#eb3349 → #f45c43` | **PDF** | Descargar factura |

### Iconos

| Icono | Significado |
|-------|-------------|
| 📋 | Lista/Documento |
| 💰 | Dinero/Total |
| 📅 | Fecha/Calendario |
| 🔵 | Acción pendiente |
| 🟢 | Éxito/Aprobado |
| 🔴 | PDF/Descarga |
| ✅ | Confirmado |
| ❌ | Cancelar |
| 📄 | Documento |

---

## 🖱️ Interacciones

### Hover (Desktop)

```
┌──────────────┐         ┌──────────────┐
│ 🔵 Facturar  │  hover  │ 🔵 Facturar  │
└──────────────┘   →     └──────────────┘
                         ↑ (se eleva)
                         💫 (con sombra)
```

**Efecto:**
- Se eleva ligeramente
- Sombra más pronunciada
- Cursor: pointer

### Click

```
Click → Loading... → Resultado
         ⏳             ✅ o ❌
```

---

## 📊 Estadísticas en Encabezado

```
┌─────────────────────────────────────────────────┐
│ 📋 Lista de Ventas                              │
│ 📅 Historial de todas las ventas realizadas    │
│                                                 │
│              ┌──────────────┐                   │
│              │ 📊 150       │                   │
│              │ Total Ventas │                   │
│              └──────────────┘                   │
│              ┌──────────────┐                   │
│              │ 💵 $45,000   │                   │
│              │ Total General│                   │
│              └──────────────┘                   │
└─────────────────────────────────────────────────┘
```

---

## 🎨 Paleta de Colores

### Gradientes Principales

**Header:**
```css
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

**Botón Facturar:**
```css
background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
```

**Botón Facturado:**
```css
background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
```

**Botón PDF:**
```css
background: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
```

---

## 💡 Tips de UX

### ✅ Buenas Prácticas

1. **Colores distintivos**: Cada estado tiene color único
2. **Iconos claros**: Fácil identificar la acción
3. **Confirmación**: Antes de facturar se pide confirmación
4. **Feedback**: Loading y mensajes de éxito/error
5. **Responsive**: Adaptado a móviles
6. **Tooltips**: Al pasar mouse se muestra descripción

### 🎯 Flujo Intuitivo

1. **Ver** → Lista de ventas
2. **Identificar** → Color del botón indica estado
3. **Actuar** → Click realiza acción apropiada
4. **Confirmar** → Ver resultado inmediato
5. **Descargar** → PDF siempre disponible

---

## 🔄 Estados de Loading

### Durante Generación de Factura

```
┌─────────────────────────────┐
│ Generando Factura...        │
│                             │
│         ⏳                  │
│                             │
│ Comunicando con ARCA        │
│ Por favor espere...         │
└─────────────────────────────┘
```

### Durante Consulta

```
┌─────────────────────────────┐
│ Cargando...                 │
│                             │
│ Obteniendo datos            │
│ de la factura               │
└─────────────────────────────┘
```

---

## 📸 Capturas de Flujo

### 1. Lista de Ventas
```
[Tabla con ventas mezcladas: algunas facturadas, otras no]
```

### 2. Click en "Facturar"
```
[Modal de confirmación con detalles]
```

### 3. Generando
```
[Spinner con mensaje "Comunicando con ARCA"]
```

### 4. Éxito
```
[Modal mostrando CAE obtenido]
```

### 5. Botones Actualizados
```
[Ahora muestra "Facturado" + "PDF"]
```

### 6. Ver Detalles
```
[Modal con información completa]
```

### 7. Descargar PDF
```
[Se abre PDF en nueva pestaña]
```

---

**La interfaz está diseñada para ser:**
- ✅ Intuitiva
- ✅ Rápida
- ✅ Clara
- ✅ Moderna
- ✅ Responsive
- ✅ Accesible

¡Todo desde una sola pantalla! 🎉

