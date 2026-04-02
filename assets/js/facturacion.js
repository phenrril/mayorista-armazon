/**
 * Script para manejar la facturación electrónica
 */

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

function formatearMoneda(value) {
    return '$' + Number(value || 0).toLocaleString('es-AR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
    });
}

function parseJsonResponse(response) {
    return response.text().then(text => {
        try {
            const data = JSON.parse(text);
            if (!data.success) {
                let msg = data.message || 'Error desconocido';
                if (data.debug_file) {
                    msg += ' (' + data.debug_file + ')';
                }
                throw new Error(msg);
            }
            return data;
        } catch (jsonError) {
            if (jsonError.message && jsonError.message.startsWith('Error:')) {
                throw jsonError;
            }
            if (!text.startsWith('<')) {
                throw jsonError;
            }
            throw new Error('Error del servidor (HTTP ' + response.status + '). Revisá los logs del contenedor.');
        }
    });
}

function buildFacturacionBody(idVenta, overrideData = {}) {
    const params = new URLSearchParams();
    params.append('id_venta', idVenta);

    Object.entries(overrideData).forEach(([key, value]) => {
        if (value !== undefined && value !== null && String(value).trim() !== '') {
            params.append(key, String(value).trim());
        }
    });

    return params.toString();
}

function obtenerDatosFacturacion(idVenta, overrideData = {}) {
    return fetch('obtener_datos_facturacion.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: buildFacturacionBody(idVenta, overrideData)
    }).then(parseJsonResponse);
}

function procesarFacturaElectronica(idVenta, overrideData = {}) {
    return fetch('procesar_factura.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: buildFacturacionBody(idVenta, overrideData)
    }).then(parseJsonResponse);
}

function renderResumenFacturacion(data) {
    const cliente = data.cliente;
    const tipo = data.tipo_comprobante;
    const documento = cliente.numero_documento_display || 'S/D';
    const fechaEmision = data.fecha_emision?.display || '';

    return `
        <div class="text-left">
            <p class="mb-2"><strong>Venta:</strong> #${escapeHtml(data.id_venta)}</p>
            <p class="mb-2"><strong>Total:</strong> ${escapeHtml(formatearMoneda(data.total))}</p>
            <p class="mb-2"><strong>Fecha de emisión:</strong> ${escapeHtml(fechaEmision)}</p>
            <hr>
            <p class="mb-2"><strong>Nombre / Razón social:</strong> ${escapeHtml(cliente.nombre || 'CONSUMIDOR FINAL')}</p>
            <p class="mb-2"><strong>CUIT:</strong> ${escapeHtml(cliente.cuit || 'S/D')}</p>
            <p class="mb-2"><strong>DNI:</strong> ${escapeHtml(cliente.dni || 'S/D')}</p>
            <p class="mb-2"><strong>Tipo de factura:</strong> ${escapeHtml(tipo.descripcion)}</p>
            <p class="mb-2"><strong>Condición IVA:</strong> ${escapeHtml(cliente.condicion_iva || 'Consumidor Final')}</p>
            <p class="mb-2"><strong>Documento a informar:</strong> ${escapeHtml(cliente.tipo_documento_desc)} ${escapeHtml(documento)}</p>
            <p class="text-muted small mb-0">Si necesitás corregir algo, podés modificar los datos antes de emitir. Los campos que dejes vacíos toman los datos cargados actualmente.</p>
        </div>
    `;
}

function renderOpcionesTipoFactura(tiposDisponibles, tipoActualId) {
    const opciones = ['<option value="">Usar tipo actual</option>'];

    tiposDisponibles.forEach(tipo => {
        const actual = Number(tipo.id) === Number(tipoActualId) ? ' (actual)' : '';
        opciones.push(`<option value="${escapeHtml(tipo.codigo)}">${escapeHtml(tipo.descripcion + actual)}</option>`);
    });

    return opciones.join('');
}

function renderOpcionesTipoDocumento(tipoActual) {
    return `
        <option value="">Usar documento actual</option>
        <option value="DNI">DNI${tipoActual === 'DNI' ? ' (actual)' : ''}</option>
        <option value="CUIT">CUIT${tipoActual === 'CUIT' ? ' (actual)' : ''}</option>
        <option value="CF">Consumidor Final${tipoActual === 'CF' ? ' (actual)' : ''}</option>
    `;
}

async function solicitarModificacionFacturacion(previewData) {
    const cliente = previewData.cliente;
    const fechaEmision = previewData.fecha_emision?.db || '';

    const result = await Swal.fire({
        title: 'Modificar datos antes de facturar',
        html: `
            <div class="text-left">
                <div class="form-group mb-2">
                    <label for="swal-nombre-cliente" style="color: #000;">Nombre / Razón social</label>
                    <input id="swal-nombre-cliente" class="swal2-input" placeholder="${escapeHtml(cliente.nombre || 'CONSUMIDOR FINAL')}" style="margin: 0; width: 100%;">
                </div>
                <div class="form-group mb-2">
                    <label for="swal-cuit" style="color: #000;">CUIT</label>
                    <input id="swal-cuit" class="swal2-input" placeholder="${escapeHtml(cliente.cuit || 'Sin CUIT cargado')}" style="margin: 0; width: 100%;">
                </div>
                <div class="form-group mb-2">
                    <label for="swal-dni" style="color: #000;">DNI</label>
                    <input id="swal-dni" class="swal2-input" placeholder="${escapeHtml(cliente.dni || 'Sin DNI cargado')}" style="margin: 0; width: 100%;">
                </div>
                <div class="form-group mb-2">
                    <label for="swal-tipo-factura" style="color: #000;">Tipo de factura</label>
                    <select id="swal-tipo-factura" class="swal2-select" style="margin: 0; width: 100%; background: #fff; color: #000; border: 1px solid #ced4da;">
                        ${renderOpcionesTipoFactura(previewData.tipos_disponibles || [], previewData.tipo_comprobante.id)}
                    </select>
                </div>
                <div class="form-group mb-2">
                    <label for="swal-fecha-emision" style="color: #000;">Fecha de emisión</label>
                    <input id="swal-fecha-emision" type="date" class="swal2-input" value="${escapeHtml(fechaEmision)}" style="margin: 0; width: 100%;">
                </div>
                <div class="form-group mb-0">
                    <label for="swal-tipo-documento" style="color: #000;">Documento a informar</label>
                    <select id="swal-tipo-documento" class="swal2-select" style="margin: 0; width: 100%; background: #fff; color: #000; border: 1px solid #ced4da;">
                        ${renderOpcionesTipoDocumento(cliente.tipo_documento_preferido)}
                    </select>
                </div>
                <p class="text-muted small mt-3 mb-0">Campo vacío = usar dato ya cargado en el sistema.</p>
            </div>
        `,
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Aplicar cambios',
        cancelButtonText: 'Volver',
        focusConfirm: false,
        preConfirm: () => {
            const overrides = {};
            const nombre = document.getElementById('swal-nombre-cliente').value.trim();
            const cuit = document.getElementById('swal-cuit').value.trim();
            const dni = document.getElementById('swal-dni').value.trim();
            const tipoFactura = document.getElementById('swal-tipo-factura').value.trim();
            const fechaEmisionNueva = document.getElementById('swal-fecha-emision').value.trim();
            const tipoDocumento = document.getElementById('swal-tipo-documento').value.trim();

            if (nombre !== '') {
                overrides.nombre_cliente = nombre;
            }
            if (cuit !== '') {
                overrides.cuit = cuit;
            }
            if (dni !== '') {
                overrides.dni = dni;
            }
            if (tipoFactura !== '') {
                overrides.tipo_factura = tipoFactura;
            }
            if (fechaEmisionNueva !== '' && fechaEmisionNueva !== fechaEmision) {
                overrides.fecha_emision = fechaEmisionNueva;
            }
            if (tipoDocumento !== '') {
                overrides.tipo_documento = tipoDocumento;
            }

            return overrides;
        }
    });

    return result.isConfirmed ? result.value : null;
}

async function generarFacturaElectronica(idVenta, overrideData = {}) {
    try {
        const preview = await obtenerDatosFacturacion(idVenta, overrideData);

        const confirmacion = await Swal.fire({
            title: '¿Generar Factura Electrónica?',
            html: renderResumenFacturacion(preview.data),
            icon: 'question',
            width: 650,
            showCancelButton: true,
            showDenyButton: true,
            confirmButtonColor: '#667eea',
            denyButtonColor: '#f0ad4e',
            cancelButtonColor: '#d33',
            confirmButtonText: '<i class="fas fa-file-invoice mr-2"></i>Sí, generar',
            denyButtonText: '<i class="fas fa-edit mr-2"></i>Modificar datos',
            cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar'
        });

        if (confirmacion.isDenied) {
            const nuevosOverrides = await solicitarModificacionFacturacion(preview.data);
            if (nuevosOverrides) {
                await generarFacturaElectronica(idVenta, nuevosOverrides);
            }
            return;
        }

        if (!confirmacion.isConfirmed) {
            return;
        }

        Swal.fire({
            title: 'Generando factura...',
            html: 'Esperá un momento mientras se solicita el CAE',
            allowOutsideClick: false,
            didOpen: () => {
                Swal.showLoading();
            }
        });

        const resultado = await procesarFacturaElectronica(idVenta, overrideData);
        Swal.close();

        await Swal.fire({
            icon: 'success',
            title: '¡Factura Generada!',
            html: `
                <div class="text-left">
                    <p><strong>Comprobante:</strong> ${escapeHtml(resultado.data.comprobante)}</p>
                    <p><strong>CAE:</strong> ${escapeHtml(resultado.data.cae)}</p>
                    <p><strong>Vencimiento CAE:</strong> ${escapeHtml(resultado.data.vencimiento_cae)}</p>
                </div>
            `,
            confirmButtonColor: '#667eea',
            confirmButtonText: 'Aceptar'
        });

        location.reload();
    } catch (error) {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: 'No se pudo generar la factura: ' + error.message,
            confirmButtonColor: '#d33'
        });
    }
}

// Función para ver detalles de una factura electrónica
function verDetallesFactura(idVenta) {
    // Mostrar loader
    Swal.fire({
        title: 'Cargando...',
        html: 'Obteniendo datos de la factura',
        allowOutsideClick: false,
        didOpen: () => {
            Swal.showLoading();
        }
    });
    
    // Obtener datos de la factura
    fetch('obtener_factura.php?id_venta=' + idVenta)
        .then(response => response.json())
        .then(data => {
            if (data.success && data.factura) {
                const f = data.factura;
                
                // Determinar color según estado
                let estadoColor = 'success';
                let estadoIcon = 'check-circle';
                const discriminaIva = parseInt(f.tipo_comprobante, 10) === 1;
                if (f.estado === 'rechazado' || f.estado === 'error') {
                    estadoColor = 'danger';
                    estadoIcon = 'times-circle';
                } else if (f.estado === 'pendiente') {
                    estadoColor = 'warning';
                    estadoIcon = 'clock';
                }
                
                Swal.fire({
                    title: 'Detalles de Factura Electrónica',
                    html: `
                        <div class="text-left p-3">
                            <div class="mb-3">
                                <h5 class="text-${estadoColor}">
                                    <i class="fas fa-${estadoIcon} mr-2"></i>
                                    Estado: ${f.estado.toUpperCase()}
                                </h5>
                            </div>
                            
                            <hr>
                            
                            <div class="row mb-2">
                                <div class="col-6"><strong>Tipo:</strong></div>
                                <div class="col-6">${f.tipo_comprobante_desc}</div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-6"><strong>Número:</strong></div>
                                <div class="col-6">${String(f.punto_venta).padStart(4, '0')}-${String(f.numero_comprobante).padStart(8, '0')}</div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-6"><strong>Fecha:</strong></div>
                                <div class="col-6">${f.fecha_emision}</div>
                            </div>
                            
                            ${f.cae ? `
                                <div class="row mb-2">
                                    <div class="col-6"><strong>CAE:</strong></div>
                                    <div class="col-6"><code>${f.cae}</code></div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-6"><strong>Venc. CAE:</strong></div>
                                    <div class="col-6">${f.vencimiento_cae}</div>
                                </div>
                            ` : ''}
                            
                            <hr>
                            
                            ${discriminaIva ? `
                                <div class="row mb-2">
                                    <div class="col-6"><strong>Neto Gravado:</strong></div>
                                    <div class="col-6">$${parseFloat(f.neto_gravado).toFixed(2)}</div>
                                </div>
                                
                                <div class="row mb-2">
                                    <div class="col-6"><strong>IVA:</strong></div>
                                    <div class="col-6">$${parseFloat(f.iva_total).toFixed(2)}</div>
                                </div>
                            ` : `
                                <div class="alert alert-light border mb-3">
                                    En ${f.tipo_comprobante_desc} el IVA no se discrimina visualmente.
                                </div>
                            `}
                            
                            <div class="row mb-2">
                                <div class="col-6"><strong><h5>Total:</h5></strong></div>
                                <div class="col-6"><strong><h5>$${parseFloat(f.total).toFixed(2)}</h5></strong></div>
                            </div>
                            
                            ${f.observaciones ? `
                                <hr>
                                <div class="alert alert-warning">
                                    <strong>Observaciones:</strong><br>
                                    ${f.observaciones}
                                </div>
                            ` : ''}
                        </div>
                    `,
                    width: '600px',
                    showCancelButton: true,
                    confirmButtonColor: '#eb3349',
                    cancelButtonColor: '#667eea',
                    confirmButtonText: '<i class="fas fa-file-pdf mr-2"></i>Descargar PDF',
                    cancelButtonText: 'Cerrar'
                }).then((result) => {
                    if (result.isConfirmed) {
                        // Abrir PDF en nueva ventana
                        window.open('pdf/generar_factura_electronica.php?v=' + idVenta, '_blank');
                    }
                });
            } else {
                Swal.fire({
                    icon: 'info',
                    title: 'Sin Factura',
                    text: 'Esta venta aún no tiene factura electrónica generada',
                    confirmButtonColor: '#667eea'
                });
            }
        })
        .catch(error => {
            Swal.fire({
                icon: 'error',
                title: 'Error',
                text: 'No se pudieron obtener los datos de la factura: ' + error.message,
                confirmButtonColor: '#d33'
            });
        });
}

// Función para descargar PDF de factura electrónica
function descargarPDFFactura(idVenta) {
    window.open('pdf/generar_factura.php?v=' + idVenta, '_blank');
}

// Inicializar tooltips para botones de facturación
$(document).ready(function() {
    $('[data-toggle="tooltip"]').tooltip();
});

