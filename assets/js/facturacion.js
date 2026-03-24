/**
 * Script para manejar la facturación electrónica
 */

// Función para generar factura electrónica
function generarFacturaElectronica(idVenta) {
    // Confirmación antes de generar
    Swal.fire({
        title: '¿Generar Factura Electrónica?',
        html: `
            <p>Se generará la factura electrónica para esta venta.</p>
            <p class="text-muted small">El sistema determinará automáticamente el tipo de comprobante (A, B o C) según la condición IVA del cliente.</p>
        `,
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#667eea',
        cancelButtonColor: '#d33',
        confirmButtonText: '<i class="fas fa-file-invoice mr-2"></i>Sí, generar',
        cancelButtonText: '<i class="fas fa-times mr-2"></i>Cancelar',
        showLoaderOnConfirm: true,
        preConfirm: () => {
            return fetch('procesar_factura.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'id_venta=' + idVenta
            })
            .then(response => {
                // Intentar parsear como JSON siempre, incluso si el status es 500
                return response.text().then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (!data.success) {
                            let msg = data.message || 'Error desconocido';
                            if (data.debug_file) msg += ' (' + data.debug_file + ')';
                            throw new Error(msg);
                        }
                        return data;
                    } catch (jsonError) {
                        if (jsonError.message.startsWith('Error:') || !text.startsWith('<')) {
                            throw jsonError;
                        }
                        // Si la respuesta es HTML (error de PHP sin capturar), mostrar status
                        throw new Error('Error del servidor (HTTP ' + response.status + '). Revisá los logs del contenedor: docker logs optica_web');
                    }
                });
            })
            .catch(error => {
                Swal.showValidationMessage(`Error: ${error.message}`);
            });
        },
        allowOutsideClick: () => !Swal.isLoading()
    }).then((result) => {
        if (result.isConfirmed && result.value.success) {
            // Mostrar resultado exitoso
            Swal.fire({
                icon: 'success',
                title: '¡Factura Generada!',
                html: `
                    <div class="text-left">
                        <p><strong>Comprobante:</strong> ${result.value.data.comprobante}</p>
                        <p><strong>CAE:</strong> ${result.value.data.cae}</p>
                        <p><strong>Vencimiento CAE:</strong> ${result.value.data.vencimiento_cae}</p>
                    </div>
                `,
                confirmButtonColor: '#667eea',
                confirmButtonText: 'Aceptar'
            }).then(() => {
                // Recargar la página o actualizar la tabla
                location.reload();
            });
        }
    });
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
                            
                            <div class="row mb-2">
                                <div class="col-6"><strong>Neto Gravado:</strong></div>
                                <div class="col-6">$${parseFloat(f.neto_gravado).toFixed(2)}</div>
                            </div>
                            
                            <div class="row mb-2">
                                <div class="col-6"><strong>IVA:</strong></div>
                                <div class="col-6">$${parseFloat(f.iva_total).toFixed(2)}</div>
                            </div>
                            
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

