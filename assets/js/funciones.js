function formatCurrency(value) {
    return '$' + Number(value || 0).toLocaleString('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function showCenteredAlert(options) {
    return Swal.fire(Object.assign({
        position: 'center',
        toast: false,
        target: 'body',
        backdrop: 'rgba(2, 2, 3, 0.72)',
        heightAuto: false,
        allowOutsideClick: true,
        showConfirmButton: false
    }, options));
}

function btnCambiar(e) {
    e.preventDefault();
    const actual = $('#actual').val();
    const nueva = $('#nueva').val();

    if (!actual || !nueva) {
        showCenteredAlert({
            icon: 'error',
            title: 'Completa ambos campos',
            timer: 2000
        });
        return;
    }

    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: {
            actual: actual,
            nueva: nueva,
            cambio: true
        },
        success: function (response) {
            if (response === 'ok') {
                showCenteredAlert({
                    icon: 'success',
                    title: 'Contraseña actualizada',
                    timer: 2000
                });
                $('#frmPass')[0].reset();
                $('#nuevo_pass').modal('hide');
            } else if (response === 'dif') {
                showCenteredAlert({
                    icon: 'error',
                    title: 'La contraseña actual es incorrecta',
                    timer: 2200
                });
            } else {
                showCenteredAlert({
                    icon: 'error',
                    title: 'No se pudo actualizar la contraseña',
                    timer: 2200
                });
            }
        }
    });
}

function actualizarPanelCliente(idCliente) {
    if (!idCliente) {
        $('#cc_saldo_actual').text(formatCurrency(0));
        $('#cc_limite').text(formatCurrency(0));
        $('#cc_saldo_resultante').text(formatCurrency(0));
        return;
    }

    $.getJSON('ajax.php', { cliente_cc: idCliente }, function (response) {
        $('#cc_saldo_actual').text(formatCurrency(response.saldo_actual || 0));
        $('#cc_limite').text(formatCurrency(response.limite_credito || 0));
        const montoCc = parseFloat($('#monto_cc').val()) || 0;
        $('#cc_saldo_resultante').text(formatCurrency((response.saldo_actual || 0) + montoCc));
    });
}

function obtenerTotalVenta() {
    let total = 0;
    $('#detalle_venta tr').each(function () {
        const subtotal = parseFloat($(this).find('.subtotal-item').data('subtotal'));
        if (!isNaN(subtotal)) {
            total += subtotal;
        }
    });

    return total;
}

function actualizarResumenCobro(total, abona, montoCc) {
    $('#abona').val(Number(abona || 0).toFixed(2));
    $('#monto_cc').val(Number(montoCc || 0).toFixed(2));
    $('#total-amount').text(formatCurrency(total));
    $('#total_tabla').text(formatCurrency(total));

    const saldoActualTexto = $('#cc_saldo_actual').text().replace(/\./g, '').replace('$', '').replace(',', '.').trim();
    const saldoActual = parseFloat(saldoActualTexto) || 0;
    $('#cc_saldo_resultante').text(formatCurrency(saldoActual + montoCc));
}

function calcularVenta(origen = 'abona') {
    const total = obtenerTotalVenta();

    const abonaInput = $('#abona');
    const montoCcInput = $('#monto_cc');
    let abona = parseFloat(abonaInput.val());
    let montoCc = parseFloat(montoCcInput.val());

    if (origen === 'cc') {
        if (isNaN(montoCc) || montoCc < 0) {
            montoCc = 0;
        }
        if (montoCc > total) {
            montoCc = total;
        }
        abona = Math.max(0, total - montoCc);
    } else {
        if (isNaN(abona) || abona < 0) {
            abona = 0;
        }
        if (abona > total) {
            abona = total;
        }
        montoCc = Math.max(0, total - abona);
    }

    actualizarResumenCobro(total, abona, montoCc);
}

function listar() {
    if (!$('#detalle_venta').length) {
        return;
    }

    $.getJSON('ajax.php', { detalle: true }, function (response) {
        let html = '';

        if (!response.length) {
            html = '<tr><td colspan="6"><div class="empty-state"><i class="fas fa-box-open fa-2x mb-2"></i><div>No hay productos cargados.</div></div></td></tr>';
        } else {
            response.forEach(function (row) {
                const precioPersonalizado = !!row.precio_editado;
                html += `
                    <tr>
                        <td><span class="badge badge-light">${row.codigo}</span></td>
                        <td>${row.descripcion}</td>
                        <td>${row.cantidad}</td>
                        <td>
                            <input
                                type="number"
                                class="precio-editable-input"
                                value="${parseFloat(row.precio_venta).toFixed(2)}"
                                min="0"
                                step="0.01"
                                onchange="actualizarPrecio(${row.id}, this.value, this)"
                            >
                            <small class="d-block mt-1 ${precioPersonalizado ? 'text-warning' : 'text-muted'}">
                                ${precioPersonalizado ? 'Precio personalizado para este pedido' : 'Se puede editar libremente hasta generar la venta'}
                            </small>
                        </td>
                        <td class="subtotal-item" data-subtotal="${row.sub_total}">${formatCurrency(row.sub_total)}</td>
                        <td>
                            <button class="btn btn-sm btn-danger" type="button" onclick="deleteDetalle(${row.id})">
                                <i class="fas fa-trash-alt"></i>
                            </button>
                        </td>
                    </tr>
                `;
            });
        }

        $('#detalle_venta').html(html);
        calcularVenta();
    });
}

function registrarDetalleManual(id, cant, precio) {
    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        dataType: 'json',
        data: {
            id: id,
            cant: cant,
            precio: precio,
            tipo_venta: $('#tipo_venta').val(),
            action: 'agregar'
        },
        success: function (response) {
            const normalized = String(response).replace(/"/g, '');
            if (normalized === 'registrado' || normalized === 'actualizado') {
                $('#producto').val('').focus();
                listar();
                return;
            }

            if (normalized === 'stock_insuficiente') {
                showCenteredAlert({
                    icon: 'warning',
                    title: 'Stock insuficiente',
                    timer: 2200
                });
                return;
            }

            showCenteredAlert({
                icon: 'error',
                title: 'No se pudo agregar el producto',
                timer: 2200
            });
        }
    });
}

function actualizarPrecio(id, nuevoPrecio, inputEl) {
    const precio = parseFloat(nuevoPrecio);
    if (isNaN(precio) || precio < 0) {
        showCenteredAlert({
            icon: 'warning',
            title: 'Precio inválido',
            timer: 2000
        });
        listar();
        return;
    }

    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        data: {
            update_precio: true,
            id: id,
            precio: precio
        },
        success: function (response) {
            if (response === 'ok') {
                const fila = $(inputEl).closest('tr');
                const cantidad = parseFloat(fila.find('td:nth-child(3)').text()) || 1;
                const subtotal = precio * cantidad;
                fila.find('.subtotal-item').data('subtotal', subtotal).text(formatCurrency(subtotal));
                calcularVenta();
                listar();
            } else {
                listar();
            }
        },
        error: function () {
            listar();
        }
    });
}

function deleteDetalle(id) {
    $.ajax({
        url: 'ajax.php',
        data: {
            id: id,
            delete_detalle: true
        },
        success: function () {
            listar();
        }
    });
}

function guardarNuevoCliente() {
    const nombre = $('#nombre_cliente').val().trim();
    const telefono = $('#telefono_cliente').val().trim();
    const direccion = $('#direccion_cliente').val().trim();
    const opticaInput = $('#optica_cliente');
    const localidadInput = $('#localidad_cliente');
    const provinciaInput = $('#provincia_cliente');
    const codigoPostalInput = $('#codigo_postal_cliente');
    const optica = opticaInput.length ? opticaInput.val().trim() : '';
    const localidad = localidadInput.length ? localidadInput.val().trim() : '';
    const provincia = provinciaInput.length ? provinciaInput.val().trim() : '';
    const codigoPostal = codigoPostalInput.length ? codigoPostalInput.val().trim() : '';
    const tipoDocumento = $('#tipo_documento_cliente').val() || '96';
    const dni = $('#dni_cliente').val().trim();
    const cuit = $('#cuit_cliente').val().trim();

    if (!nombre || !telefono || !direccion) {
        showCenteredAlert({
            icon: 'warning',
            title: 'Completa nombre, teléfono y dirección',
            timer: 2200
        });
        return;
    }

    if ((opticaInput.length && !optica) || (localidadInput.length && !localidad) || (provinciaInput.length && !provincia) || (codigoPostalInput.length && !codigoPostal)) {
        showCenteredAlert({
            icon: 'warning',
            title: 'Completá óptica, localidad, provincia y código postal',
            timer: 2400
        });
        return;
    }

    if (tipoDocumento === '80' && !cuit) {
        showCenteredAlert({
            icon: 'warning',
            title: 'Para tipo CUIT, cargá el CUIT',
            timer: 2200
        });
        return;
    }

    if (tipoDocumento === '96' && !dni) {
        showCenteredAlert({
            icon: 'warning',
            title: 'Para tipo DNI, cargá el DNI',
            timer: 2200
        });
        return;
    }

    $.ajax({
        url: 'ajax.php',
        type: 'POST',
        dataType: 'json',
        data: {
            nuevo_cliente: true,
            nombre_cliente: nombre,
            telefono_cliente: telefono,
            direccion_cliente: direccion,
            optica_cliente: optica,
            localidad_cliente: localidad,
            provincia_cliente: provincia,
            codigo_postal_cliente: codigoPostal,
            tipo_documento_cliente: tipoDocumento,
            dni_cliente: dni,
            cuit_cliente: cuit,
            condicion_iva_cliente: $('#condicion_iva_cliente').val()
        },
        success: function (response) {
            if (!response.success) {
                showCenteredAlert({
                    icon: 'error',
                    title: response.mensaje || 'No se pudo guardar el cliente',
                    timer: 2200
                });
                return;
            }

            $('#nuevo_cliente_venta').modal('hide');
            $('#form_nuevo_cliente')[0].reset();
            $('#idcliente').val(response.cliente.id);
            $('#nom_cliente').val(response.cliente.label);
            $('#tel_cliente').val(response.cliente.telefono);
            $('#dir_cliente').val(response.cliente.direccion);
            actualizarPanelCliente(response.cliente.id);
            showCenteredAlert({
                icon: 'success',
                title: response.mensaje,
                timer: 1800
            });
        }
    });
}

function generarPDF(cliente, idVenta) {
    window.open('pdf/generar.php?cl=' + cliente + '&v=' + idVenta, '_blank');
}

$(function () {
    if ($('#tbl').length && !$('#tbl').hasClass('custom-dt-init')) {
        $('#tbl').DataTable();
    }

    $('.confirmar').on('submit', function (e) {
        e.preventDefault();
        const form = this;
        Swal.fire({
            title: '¿Eliminar registro?',
            icon: 'warning',
            showCancelButton: true,
            confirmButtonText: 'Sí, eliminar',
            cancelButtonText: 'Cancelar'
        }).then(function (result) {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });

    if ($('#nom_cliente').length) {
        const clienteAutocomplete = $('#nom_cliente').autocomplete({
            minLength: 2,
            source: function (request, response) {
                $.getJSON('ajax.php', { q: request.term }, function (items) {
                    const resultados = Array.isArray(items) ? items : [];
                    const termino = String(request.term || '').trim();

                    if (termino.length >= 3 && resultados.length === 0) {
                        response([{
                            id: 0,
                            label: 'No hay coincidencias',
                            value: termino,
                            noMatch: true
                        }]);
                        return;
                    }

                    response(resultados);
                });
            },
            appendTo: '#layoutSidenav_content',
            classes: {
                'ui-autocomplete': 'cliente-autocomplete-menu'
            },
            position: {
                my: 'left top+8',
                at: 'left bottom',
                collision: 'fit'
            },
            open: function () {
                const instance = $(this).autocomplete('instance');
                if (!instance || !instance.menu || !instance.menu.element) {
                    return;
                }

                instance.menu.element.outerWidth($(this).outerWidth());
            },
            focus: function (event, ui) {
                if (ui.item && ui.item.noMatch) {
                    event.preventDefault();
                    return false;
                }
            },
            select: function (event, ui) {
                if (ui.item && ui.item.noMatch) {
                    event.preventDefault();
                    return false;
                }

                $('#idcliente').val(ui.item.id);
                $('#nom_cliente').val(ui.item.label);
                $('#tel_cliente').val(ui.item.telefono || '');
                $('#dir_cliente').val(ui.item.direccion || '');
                $('#cc_saldo_actual').text(formatCurrency(ui.item.saldo_cc || 0));
                $('#cc_limite').text(formatCurrency(ui.item.limite_credito || 0));
                calcularVenta();
                return false;
            }
        });

        const clienteInstance = clienteAutocomplete.autocomplete('instance');
        if (clienteInstance) {
            clienteInstance._renderItem = function (ul, item) {
                if (item.noMatch) {
                    return $('<li>')
                        .addClass('autocomplete-empty-state')
                        .append('<div class="ui-menu-item-wrapper">No hay coincidencias</div>')
                        .appendTo(ul);
                }

                return $('<li>')
                    .append(
                        $('<div class="ui-menu-item-wrapper">').append(
                            $('<div class="autocomplete-client-name">').text(item.label),
                            $('<small class="autocomplete-client-meta">').text(item.telefono || item.direccion || '')
                        )
                    )
                    .appendTo(ul);
            };
        }
    }

    if ($('#producto').length) {
        $('#producto').autocomplete({
            minLength: 2,
            source: function (request, response) {
                $.getJSON('ajax.php', {
                    pro: request.term,
                    tipo_venta: $('#tipo_venta').val()
                }, function (items) {
                    const resultados = Array.isArray(items) ? items : [];
                    const termino = String(request.term || '').trim();

                    if (termino.length >= 3 && resultados.length === 0) {
                        response([{
                            id: 0,
                            label: 'No hay coincidencias',
                            value: termino,
                            noMatch: true
                        }]);
                        return;
                    }

                    response(resultados);
                });
            },
            appendTo: '.product-search-box',
            classes: {
                'ui-autocomplete': 'producto-autocomplete-menu'
            },
            position: {
                my: 'left top+8',
                at: 'left bottom',
                collision: 'fit'
            },
            open: function () {
                const instance = $(this).autocomplete('instance');
                if (!instance || !instance.menu || !instance.menu.element) {
                    return;
                }

                instance.menu.element.outerWidth($(this).outerWidth());
            },
            focus: function (event, ui) {
                if (ui.item && ui.item.noMatch) {
                    event.preventDefault();
                    return false;
                }
            },
            select: function (event, ui) {
                if (ui.item && ui.item.noMatch) {
                    event.preventDefault();
                    return false;
                }

                $('#producto').val(ui.item.label);
                registrarDetalleManual(ui.item.id, 1, ui.item.precio);
                return false;
            }
        });

        const productoInstance = $('#producto').autocomplete('instance');
        if (productoInstance) {
            productoInstance._renderItem = function (ul, item) {
                if (item.noMatch) {
                    return $('<li>')
                        .addClass('autocomplete-empty-state')
                        .append('<div class="ui-menu-item-wrapper">No hay coincidencias</div>')
                        .appendTo(ul);
                }

                return $('<li>')
                    .append(
                        $('<div class="ui-menu-item-wrapper">').append(
                            $('<div class="autocomplete-client-name">').text(item.label),
                            $('<small class="autocomplete-client-meta">').text(
                                [
                                    item.marca ? 'Marca: ' + item.marca : '',
                                    item.modelo ? 'Modelo: ' + item.modelo : '',
                                    item.tipo_material ? 'Material: ' + item.tipo_material : '',
                                    'Stock: ' + (item.existencia || 0)
                                ].filter(Boolean).join(' | ')
                            )
                        )
                    )
                    .appendTo(ul);
            };
        }
    }

    $('#tipo_venta').on('change', function () {
        const tipoVenta = $(this).val();
        $('#producto').attr('placeholder',
            tipoVenta === 'mayorista'
                ? 'Buscando con precio mayorista...'
                : 'Buscando con precio minorista...'
        );

        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            dataType: 'json',
            data: {
                actualizar_tipo_venta: true,
                tipo_venta: tipoVenta
            },
            complete: function () {
                listar();
            }
        });
    });

    $('#abona').on('input', function () {
        calcularVenta('abona');
    });
    $('#monto_cc').on('input', function () {
        calcularVenta('cc');
    });
    $('#btn_recalcular').on('click', calcularVenta);

    function actualizarCamposCheque() {
        const esCheque = $('input[name="pago"]:checked').val() === '5';
        $('#cheque_fields').toggle(esCheque);
        if (!esCheque) {
            return;
        }

        const fechaBase = $('#cheque_fecha_base').val() || new Date().toISOString().slice(0, 10);
        const plazo = parseInt($('#cheque_plazo_dias').val(), 10) || 30;
        const fecha = new Date(fechaBase + 'T00:00:00');
        fecha.setDate(fecha.getDate() + plazo);
        const yyyy = fecha.getFullYear();
        const mm = String(fecha.getMonth() + 1).padStart(2, '0');
        const dd = String(fecha.getDate()).padStart(2, '0');
        $('#cheque_fecha_deposito').val(yyyy + '-' + mm + '-' + dd);
    }

    $('input[name="pago"]').on('change', actualizarCamposCheque);
    $('#cheque_plazo_dias, #cheque_fecha_base').on('change', actualizarCamposCheque);
    actualizarCamposCheque();

    $('#btn_generar').on('click', function () {
        const idCliente = $('#idcliente').val();
        const metodoPago = $('input[name="pago"]:checked').val();
        const abona = parseFloat($('#abona').val()) || 0;

        if (!idCliente) {
            showCenteredAlert({
                icon: 'warning',
                title: 'Selecciona un cliente',
                timer: 2200
            });
            return;
        }

        $.ajax({
            url: 'ajax.php',
            type: 'POST',
            dataType: 'json',
            data: {
                procesarVenta: true,
                id: idCliente,
                abona: abona,
                tipo_venta: $('#tipo_venta').val(),
                metodo_pago: metodoPago,
                modo_despacho: $('#modo_despacho').val(),
                observacion: $('#observacion_venta').val(),
                cheque_plazo_dias: $('#cheque_plazo_dias').val(),
                cheque_fecha_base: $('#cheque_fecha_base').val(),
                cheque_fecha_deposito: $('#cheque_fecha_deposito').val()
            },
            success: function (response) {
                if (response.mensaje === 'error') {
                    showCenteredAlert({
                        icon: 'error',
                        title: response.detalle || 'No se pudo generar la venta',
                        timer: 3200
                    });
                    return;
                }

                showCenteredAlert({
                    icon: 'success',
                    title: 'Venta generada',
                    text: 'Venta #' + response.id_venta,
                    timer: 2000
                });

                generarPDF(response.id_cliente, response.id_venta);
                setTimeout(function () {
                    window.location.reload();
                }, 600);
            },
            error: function (xhr) {
                const detalle = xhr.responseJSON && xhr.responseJSON.detalle
                    ? xhr.responseJSON.detalle
                    : 'No se pudo generar la venta.';
                showCenteredAlert({
                    icon: 'error',
                    title: detalle,
                    timer: 3200
                });
            }
        });
    });

    listar();
    calcularVenta();
});
