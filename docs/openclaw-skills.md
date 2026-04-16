# OpenClaw bridge logic

Este archivo define la logica de negocio que OpenClaw debe seguir cuando se conecte con la API privada del sistema.

No asume `exec` sobre el host. El mecanismo recomendado es un bridge HTTP controlado desde OpenClaw hacia `src/api/index.php`.

## Configuracion comun

- Base URL dev: `http://host.docker.internal:8000/src/api/index.php`
- Base URL prod: `https://tu-dominio.com/src/api/index.php`
- Header fijo:
  - `X-API-Key: TU_API_KEY`
- Content-Type:
  - `application/json` para `POST`

## Operaciones de solo lectura

### `buscar_cliente`

- Metodo: `GET`
- URL: `/clientes?q={{consulta}}`
- Uso: resolver clientes antes de consultar CC o registrar pagos. La consulta puede ser por nombre del cliente o por nombre de optica.

### `consultar_cc_cliente`

- Metodo: `GET`
- URL: `/clientes/{{id_cliente}}/cc`
- Uso: responder saldo y limite.

### `buscar_producto`

- Metodo: `GET`
- URL: `/productos?q={{consulta}}`
- Uso: responder stock, precio minorista y precio mayorista.

### `resumen_diario`

- Metodo: `GET`
- URL: `/estadisticas/resumen`
- Uso: responder el estado general del dia.

## Operaciones mutables con confirmacion

### `preparar_crear_cliente`

- No llama a la API.
- Solo arma la previsualizacion.
- Mensaje sugerido:
  - `Voy a crear el cliente {{nombre}}. Datos: optica {{optica}}, telefono {{telefono}}, direccion {{direccion}}, localidad {{localidad}}, provincia {{provincia}}, CP {{codigo_postal}}, tipo documento {{tipo_documento}}, DNI {{dni}}, CUIT {{cuit}}, condicion IVA {{condicion_iva}}. ¿Confirmas?`
 - Antes de esta previsualizacion, pedir siempre todos los datos del cliente.

### `crear_cliente_confirmado`

- Metodo: `POST`
- URL: `/clientes`
- Body:

```json
{
  "nombre": "{{nombre}}",
  "optica": "{{optica}}",
  "telefono": "{{telefono}}",
  "direccion": "{{direccion}}",
  "localidad": "{{localidad}}",
  "provincia": "{{provincia}}",
  "codigo_postal": "{{codigo_postal}}",
  "tipo_documento": "{{tipo_documento}}",
  "dni": "{{dni}}",
  "cuit": "{{cuit}}",
  "condicion_iva": "{{condicion_iva}}"
}
```

- Regla: ejecutar solo despues de confirmacion explicita.

### `preparar_pago_cc`

- No llama a la API.
- Busca primero el cliente y arma la previsualizacion.
- Mensaje sugerido:
  - `Voy a registrar un pago de ${{monto}} a {{cliente}}. ¿Confirmas?`

### `registrar_pago_cc_confirmado`

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

- Regla: ejecutar solo despues de confirmacion explicita.

## Politica conversacional

- Lectura: puede consultar directo.
- Mutacion: siempre debe pedir confirmacion.
- Si hay multiples clientes o productos, pedir aclaracion antes de confirmar.
- Para ubicar clientes antes de consultar cuenta corriente o registrar pagos, se puede buscar por nombre del cliente o por nombre de la optica.
- Si falta un dato obligatorio, pedirlo antes de armar la previsualizacion.
- En alta de cliente, pedir siempre: nombre, optica, telefono, direccion, localidad, provincia, codigo postal, tipo de documento, DNI, CUIT y condicion IVA.
- Si un campo opcional no existe o no aplica, aceptar vacio, pero no omitir la pregunta.
- Si el usuario responde `no`, `cancelar` o similar, abortar la accion.
- Si el usuario responde ambiguo, volver a pedir confirmacion.

## Orden recomendado

- Buscar cliente antes de consultar cuenta corriente o registrar pagos.
- Buscar producto cuando el usuario pregunte por stock o precios.
- Usar `resumen_diario` para consultas de estado general del negocio.
- Despues de una mutacion exitosa, responder con el dato mas importante devuelto por la API.
