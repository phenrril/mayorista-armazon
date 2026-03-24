# ❓ Preguntas Frecuentes - Facturación Electrónica

## General

### ¿Qué es ARCA?
ARCA (Agencia de Recaudación y Control Aduanero) es el nuevo nombre de la AFIP desde 2024. La facturación electrónica sigue funcionando igual, solo cambió el nombre de la institución.

### ¿Es obligatorio facturar electrónicamente?
Sí, para la mayoría de los comercios en Argentina es obligatorio emitir facturas electrónicas. Consultá con tu contador sobre tu situación específica.

### ¿Puedo usar este sistema sin facturación electrónica?
Sí, el sistema funciona perfectamente sin la facturación electrónica. Simplemente no ejecutes el script SQL de facturación y seguí usando los recibos normales.

---

## Configuración

### ¿Dónde obtengo los certificados digitales?

1. Ingresá a https://auth.afip.gob.ar/
2. Ir a "Administrador de Relaciones de Clave Fiscal"
3. Crear certificado para "Factura Electrónica" o "WSFE"
4. Descargar el .crt que ARCA genera
5. Guardá también tu clave privada .key

### ¿Qué diferencia hay entre Testing y Producción?

- **Testing**: Ambiente de prueba de ARCA. Los CAE generados NO son válidos para uso fiscal.
- **Producción**: Ambiente real. Los CAE generados SON válidos y deben usarse en las facturas reales.

**Siempre probá primero en Testing.**

### ¿Puedo cambiar de Testing a Producción?

Sí, pero necesitás:
1. Un certificado diferente (el de testing NO funciona en producción)
2. Cambiar la configuración en el panel
3. Verificar que todo funcione correctamente primero

### ¿Qué es el Punto de Venta?

Es un número que te asigna ARCA para identificar cada terminal o punto desde donde emitís facturas. Si tenés un solo local, probablemente sea el punto de venta 1.

Lo configurás en el sitio de ARCA bajo "Administración de Puntos de Venta".

---

## Tipos de Facturas

### ¿Cuándo uso Factura A, B o C?

| Tu condición | Cliente | Factura |
|-------------|---------|---------|
| Responsable Inscripto | Responsable Inscripto | A |
| Responsable Inscripto | Monotributo | A |
| Responsable Inscripto | Exento | A |
| Responsable Inscripto | Consumidor Final | B |
| Monotributo | Cualquiera | C |
| Exento | Cualquiera | C |

### ¿Qué diferencia hay entre las facturas?

- **Factura A**: Discrimina IVA. Para operaciones entre inscriptos. Requiere CUIT.
- **Factura B**: Discrimina IVA. Para consumidores finales. Puede usar DNI.
- **Factura C**: NO discrimina IVA. Total incluye IVA. Para monotributistas.

### ¿Puedo elegir manualmente el tipo de factura?

No, el sistema lo determina automáticamente según:
1. Tu condición IVA (configuración del sistema)
2. La condición IVA del cliente

Esto garantiza que siempre uses el tipo correcto.

---

## Uso del Sistema

### ¿Cómo facturo una venta?

1. Realizá la venta normalmente
2. Ir a "Lista de Ventas"
3. Click en el botón "Facturar" de la venta
4. El sistema genera automáticamente la factura y obtiene el CAE

### ¿Puedo facturar una venta antigua?

Sí, pero tené en cuenta que:
- La fecha de la factura será la fecha actual (no la de la venta)
- Puede haber diferencias con tus registros contables

Recomendamos facturar las ventas el mismo día.

### ¿Qué hago si la facturación falla?

1. Revisá el mensaje de error
2. Verificá que el cliente tenga datos correctos (especialmente CUIT si es Factura A)
3. Revisá que tus certificados no estén vencidos
4. Consultá los logs de error en la tabla `facturas_electronicas`

Si el error persiste, intenta nuevamente. El sistema no duplicará facturas.

### ¿Puedo re-facturar una venta?

No. Una vez que una venta tiene una factura aprobada, no se puede volver a facturar.

Para corregir errores:
- Si es el mismo día y antes de entregar: podés anular en ARCA y volver a facturar
- Si ya se entregó: deberás emitir una Nota de Crédito

---

## Errores Comunes

### Error: "Ya existe una factura aprobada para esta venta"

**Causa**: Estás intentando facturar una venta que ya fue facturada.

**Solución**: Verificá en la lista de ventas. Si ya tiene el botón verde "Facturado", no necesitás hacer nada.

### Error: "CUIT inválido"

**Causa**: El CUIT del cliente está mal formateado o es incorrecto.

**Solución**: 
1. Editá el cliente
2. Verificá que el CUIT tenga formato: 20-12345678-9
3. Verificá que sea un CUIT válido (tiene dígito verificador)

### Error: "Certificado no encontrado"

**Causa**: La ruta al certificado en la configuración es incorrecta.

**Solución**:
1. Verificá que el archivo .crt exista en la ruta especificada
2. Verificá permisos del archivo (debe ser legible por el servidor web)
3. Usá la ruta absoluta completa (ej: /var/www/certs/cert.crt)

### Error: "CAE no generado"

**Causa**: ARCA rechazó el comprobante.

**Solución**:
1. Revisá el campo `observaciones` en la tabla `facturas_electronicas`
2. Causas comunes:
   - Datos del cliente incorrectos
   - Certificado vencido
   - Punto de venta no habilitado
   - Totales incorrectos

### Error: "No se pudo conectar con AFIP"

**Causa**: Problema de conectividad o certificados.

**Solución**:
1. Verificá tu conexión a internet
2. Verificá que los certificados sean los correctos
3. Verificá que estés en el ambiente correcto (testing vs producción)
4. Intentá nuevamente en unos minutos

---

## CAE (Código de Autorización Electrónica)

### ¿Qué es el CAE?

Es un código de 14 dígitos que ARCA te da para cada factura. Es obligatorio imprimirlo en la factura junto con su fecha de vencimiento.

### ¿Cuánto dura un CAE?

10 días corridos desde su emisión. Después de ese tiempo, si querés emitir otra factura, obtendrás un nuevo CAE.

### ¿Qué pasa si vence el CAE?

Nada. El CAE es para la fecha de emisión de la factura, no afecta su validez. Es solo un control de ARCA.

### ¿Puedo usar el mismo CAE para varias facturas?

No. Cada factura tiene su propio CAE único.

---

## Datos del Cliente

### ¿Es obligatorio cargar el CUIT del cliente?

- **Factura A**: Sí, obligatorio
- **Factura B**: No, podés usar DNI o dejar en blanco (Consumidor Final)
- **Factura C**: No obligatorio

### ¿Qué pongo si el cliente no tiene CUIT?

Si es Consumidor Final (Factura B), podés usar:
- DNI
- O dejarlo como "Consumidor Final" sin documento

### ¿Cómo actualizo la condición IVA de un cliente?

1. Ir a "Clientes"
2. Editar el cliente
3. Actualizar los campos:
   - CUIT (si tiene)
   - Condición IVA
   - Tipo de Documento

---

## Reportes y Consultas

### ¿Dónde veo todas las facturas emitidas?

```sql
SELECT * FROM vista_facturas_completas 
WHERE estado = 'aprobado' 
ORDER BY fecha_emision DESC;
```

O crear un reporte en el panel web.

### ¿Cómo sé cuánto facturé en el mes?

```sql
SELECT 
    SUM(total) as total_facturado,
    COUNT(*) as cantidad_facturas
FROM facturas_electronicas
WHERE MONTH(fecha_emision) = MONTH(CURRENT_DATE())
AND YEAR(fecha_emision) = YEAR(CURRENT_DATE())
AND estado = 'aprobado';
```

### ¿Puedo exportar las facturas?

Sí, desde phpMyAdmin podés exportar la tabla `facturas_electronicas` a CSV o Excel.

---

## Seguridad

### ¿Dónde guardo los certificados?

En un directorio seguro del servidor:
```bash
/var/www/certificados-afip/
```

Con permisos restrictivos:
```bash
chmod 600 *.crt *.key
chown www-data:www-data *
```

### ¿Qué hago si me roban los certificados?

1. Revocá inmediatamente el certificado en el sitio de ARCA
2. Generá un nuevo certificado
3. Actualizá la configuración del sistema

### ¿Debo hacer backup de los certificados?

Sí, siempre. Guardalos en un lugar seguro fuera del servidor también.

---

## Integración con Contador

### ¿Qué información necesita mi contador?

Tu contador necesitará:
- Reporte de facturas emitidas (con CAE)
- Totales por tipo de factura
- Detalle de IVA discriminado

Podés exportar desde la tabla `vista_facturas_completas`.

### ¿Los CAE están respaldados?

Sí, todos los CAE se guardan en la base de datos en la tabla `facturas_electronicas` con toda la información de la respuesta de ARCA.

---

## Desarrollo y Testing

### ¿Cómo pruebo sin gastar CAE reales?

Usá el ambiente de Testing de ARCA:
1. Obtené certificados de testing
2. Configurá el sistema en modo Testing
3. Los CAE generados son de prueba

### ¿Los datos de testing son diferentes?

Sí:
- URLs diferentes
- Certificados diferentes
- CUIT de testing (podés usar cualquiera en testing)
- Los CAE no son válidos fiscalmente

### ¿Puedo ver las llamadas a ARCA?

Sí, están guardadas en:
- `xml_request`: Lo que enviaste
- `xml_response`: Lo que ARCA respondió

En la tabla `facturas_electronicas`.

---

## Contacto y Soporte

### ¿Dónde obtengo más información sobre facturación electrónica?

- **ARCA**: https://www.afip.gob.ar/factura-electronica/
- **Manual WSFE**: https://www.afip.gob.ar/ws/documentacion/ws-factura-electronica.asp
- **Tu contador**: Siempre consultá con tu contador para casos específicos

### ¿Este sistema tiene soporte oficial de ARCA?

No. Este es un sistema desarrollado independientemente. Para consultas oficiales, contactá a ARCA.

---

**Última actualización**: Diciembre 2025

