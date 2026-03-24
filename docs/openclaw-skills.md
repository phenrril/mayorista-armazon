# OpenClaw Skills

Usa estos endpoints como tools HTTP en OpenClaw.

## Configuracion comun

- Base URL: `https://tu-dominio.com/src/api/index.php`
- Header fijo:
  - `X-API-Key: TU_API_KEY`
- Content-Type:
  - `application/json` para `POST`

## Skills sugeridas

### `crear_cliente`
- Metodo: `POST`
- URL: `/clientes`
- Body:
```json
{
  "nombre": "{{nombre}}",
  "telefono": "{{telefono}}",
  "direccion": "{{direccion}}",
  "dni": "{{dni}}"
}
```

### `buscar_cliente`
- Metodo: `GET`
- URL: `/clientes?q={{consulta}}`

### `consultar_cc_cliente`
- Metodo: `GET`
- URL: `/clientes/{{id_cliente}}/cc`

### `registrar_pago_cc`
- Metodo: `POST`
- URL: `/clientes/{{id_cliente}}/cc/pago`
- Body:
```json
{
  "monto": "{{monto}}",
  "descripcion": "{{descripcion}}",
  "metodo_pago": 4
}
```

### `buscar_producto`
- Metodo: `GET`
- URL: `/productos?q={{consulta}}`

### `resumen_diario`
- Metodo: `GET`
- URL: `/estadisticas/resumen`

## Recomendacion de uso en OpenClaw

- Buscar cliente antes de consultar cuenta corriente o registrar pagos.
- Buscar producto cuando el usuario pregunte por stock o precios.
- Usar `resumen_diario` para consultas de estado general del negocio.
