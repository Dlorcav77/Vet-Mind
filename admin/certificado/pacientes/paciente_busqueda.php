<?php
// admin/certificado/pacientes/paciente_busqueda.php
?>
<div class="modal fade" id="modalBuscarPaciente" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-search mx-2"></i> Buscar Paciente</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>

            <div class="modal-body">
                <input
                    type="text"
                    class="form-control mb-3"
                    id="buscarPacienteInput"
                    placeholder="Ingrese código, RUT, nombre del Tutor o Mascota..."
                >
                <div id="resultadosBuscarPaciente" class="table-responsive">
                    <p class="text-muted">Comience a escribir para ver resultados.</p>
                </div>
            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times"></i> Cerrar
                </button>
            </div>
        </div>
    </div>
</div>