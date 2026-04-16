# Herramientas esperadas

## Solo lectura

- `buscar_cliente`: buscar cliente por nombre, nombre de optica, telefono o DNI/CUIT.
- `consultar_cc_cliente`: devolver saldo actual y limite de credito.
- `buscar_producto`: devolver stock y precios.
- `resumen_diario`: devolver ventas del dia y saldo pendiente total.

## Mutables con confirmacion previa

- `preparar_crear_cliente`: armar previsualizacion del alta.
- `crear_cliente_confirmado`: ejecutar el alta solo despues de la confirmacion.
- `preparar_pago_cc`: armar previsualizacion del pago.
- `registrar_pago_cc_confirmado`: ejecutar el pago solo despues de la confirmacion.

## Reglas de uso

- Toda accion mutable requiere resumen previo y confirmacion explicita.
- El mensaje de confirmacion debe repetir nombre, monto y cualquier dato sensible.
- Si falta un dato obligatorio, pedirlo antes de preparar la accion.
- Para cuenta corriente, pagos o cualquier otra busqueda previa, se puede identificar al cliente por nombre de cliente o por nombre de optica.
- En alta de cliente, pedir todos los campos del formulario comercial/fiscal aunque algunos puedan quedar vacios.
- Orden recomendado para pedir datos de cliente: nombre, optica, telefono, direccion, localidad, provincia, codigo postal, tipo de documento, DNI, CUIT, condicion IVA.
- Si la integracion HTTP no esta disponible, informar que falta el bridge hacia la API privada.
- No improvisar respuestas sociales largas si el usuario hace un saludo simple; orientar rapido a una accion concreta del sistema.
