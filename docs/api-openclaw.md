# API OpenClaw

Base: `/src/api/index.php`

Autenticacion:
- Header obligatorio: `X-API-Key: <clave>`
- La clave se define en `src/config.php` y debe sobrescribirse con `MAYORISTA_API_KEY` en produccion.

## Endpoints

### `POST /clientes`
Alta de cliente.

Body JSON o form:
```json
{
  "nombre": "Optica Centro",
  "telefono": "3515555555",
  "direccion": "Av. Siempre Viva 123",
  "dni": "30-12345678-9"
}
```

### `GET /clientes?q=texto`
Busca clientes por nombre, DNI/CUIT o telefono.

### `GET /clientes/{id}/cc`
Devuelve saldo actual y limite de credito.

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

### `GET /productos?q=texto`
Busca productos activos y devuelve stock, precio minorista y precio mayorista.

### `GET /estadisticas/resumen`
Devuelve resumen del dia:
- cantidad de ventas
- monto vendido
- saldo pendiente total en cuenta corriente

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
