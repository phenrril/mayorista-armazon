# API privada para OpenClaw

Base relativa: `src/api/index.php`

Ejemplos de base URL:
- desarrollo local desde OpenClaw en Docker Desktop: `http://host.docker.internal:8000/src/api/index.php`
- despliegue publicado: `https://tu-dominio.com/src/api/index.php`

## Autenticacion

- Header obligatorio: `X-API-Key: <clave>`
- La clave real se toma de `MAYORISTA_API_KEY`
- Si `MAYORISTA_API_KEY` no esta definida, la API responde `503`

## Flujo recomendado con OpenClaw

La API actual expone endpoints de negocio directos. La confirmacion previa no ocurre en PHP sino en OpenClaw.

Flujo esperado:
1. OpenClaw recibe texto o audio.
2. Si entra audio, lo transcribe.
3. Interpreta la intencion del usuario.
4. Si la operacion es mutable, arma una previsualizacion y pide confirmacion.
5. Solo despues de una respuesta afirmativa llama a la API privada.
6. Devuelve el resultado al usuario.

Regla operativa:
- lectura: puede consultar directo
- mutacion: debe pedir confirmacion antes del `POST`

## Clasificacion de operaciones

### Solo lectura

- `GET /clientes?q=texto`
- `GET /clientes/{id}/cc`
- `GET /productos?q=texto`
- `GET /estadisticas/resumen`

### Mutables

- `POST /clientes`
- `POST /clientes/{id}/cc/pago`

## Endpoints

### `POST /clientes`

Alta de cliente.

Body JSON o form:

```json
{
  "nombre": "Optica Centro",
  "optica": "Optica Centro",
  "telefono": "3515555555",
  "direccion": "Av. Siempre Viva 123",
  "localidad": "Cordoba",
  "provincia": "Cordoba",
  "codigo_postal": "5000",
  "tipo_documento": 96,
  "dni": "30123456",
  "cuit": "",
  "condicion_iva": "Consumidor Final"
}
```

Campos obligatorios:
- `nombre`
- `telefono`
- `direccion`
- `localidad`
- `provincia`
- `codigo_postal`

Campos pedidos siempre por el bot:
- `nombre`
- `optica`
- `telefono`
- `direccion`
- `localidad`
- `provincia`
- `codigo_postal`
- `tipo_documento`
- `dni`
- `cuit`
- `condicion_iva`

Reglas:
- si `tipo_documento=96`, `dni` es obligatorio
- si `tipo_documento=80`, `cuit` es obligatorio
- `optica`, `dni`, `cuit` y `condicion_iva` pueden viajar vacios si no aplican o no se conocen

Respuesta exitosa:

```json
{
  "success": true,
  "cliente": {
    "id": 15,
    "nombre": "Optica Centro",
    "optica": "Optica Centro",
    "telefono": "3515555555",
    "direccion": "Av. Siempre Viva 123",
    "localidad": "Cordoba",
    "codigo_postal": "5000",
    "provincia": "Cordoba",
    "dni": "30123456",
    "cuit": "",
    "condicion_iva": "Consumidor Final",
    "tipo_documento": 96
  }
}
```

### `GET /clientes?q=texto`

Busca clientes por nombre, nombre de optica, DNI/CUIT o telefono.

Respuesta:

```json
{
  "success": true,
  "clientes": [
    {
      "idcliente": "15",
      "nombre": "Optica Centro",
      "optica": "Optica Centro",
      "telefono": "3515555555",
      "direccion": "Av. Siempre Viva 123",
      "dni": "30-12345678-9"
    }
  ]
}
```

### `GET /clientes/{id}/cc`

Devuelve saldo actual y limite de credito del cliente.

Respuesta:

```json
{
  "success": true,
  "cuenta_corriente": {
    "id": 9,
    "saldo_actual": 120000,
    "limite_credito": 250000,
    "activo": 1
  }
}
```

### `POST /clientes/{id}/cc/pago`

Registra un pago manual de cuenta corriente.

Body JSON o form:

```json
{
  "monto": 25000,
  "descripcion": "Pago recibido por transferencia",
  "metodo_pago": 4
}
```

Respuesta:

```json
{
  "success": true,
  "message": "Pago registrado",
  "saldo_actual": 95000
}
```

### `GET /productos?q=texto`

Busca productos activos y devuelve stock, precio minorista y precio mayorista.

Respuesta:

```json
{
  "success": true,
  "productos": [
    {
      "id": 22,
      "codigo": "AR-100",
      "nombre": "Ray Brown RB100 negro",
      "precio_minorista": 45000,
      "precio_mayorista": 32000,
      "stock": 12
    }
  ]
}
```

### `GET /estadisticas/resumen`

Devuelve resumen del dia:
- cantidad de ventas
- monto vendido
- saldo pendiente total en cuenta corriente

Respuesta:

```json
{
  "success": true,
  "resumen": {
    "fecha": "2026-04-16",
    "ventas_operaciones": 8,
    "ventas_monto": 315000,
    "cuenta_corriente_pendiente": 210000
  }
}
```

## Respuesta base

Exito:

```json
{
  "success": true
}
```

Error:

```json
{
  "success": false,
  "message": "Descripcion del error"
}
```

## Notas operativas

- La API actual no implementa `dry-run` ni `preview`; esa capa vive en OpenClaw.
- La API actual tampoco implementa llaves de idempotencia; para pagos y altas, la confirmacion en OpenClaw debe ser estricta.
- Antes de publicar, validar manualmente todos los endpoints con `X-API-Key`.
