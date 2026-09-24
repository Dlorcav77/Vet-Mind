<div class="modal fade" id="modalCompartirInforme" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-user-friends me-2"></i>
                    Compartir informe
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">

                <input type="hidden" id="compartir_certificado_id">
                <input type="hidden" id="compartir_usuario_id">

                <label class="form-label">Correo del usuario</label>

                <div class="input-group mb-3">
                    <input
                        type="email"
                        id="compartir_email"
                        class="form-control"
                        autocomplete="off"
                        placeholder="usuario@correo.cl"
                    >
                    <button
                        type="button"
                        class="btn btn-outline-primary"
                        id="btnBuscarUsuarioCompartir"
                    >
                        Buscar
                    </button>
                </div>

                <div id="compartir_mensaje" class="small mb-3"></div>

                <div id="compartir_usuario_resultado" class="d-none">
                    <div class="border rounded p-3 mb-3">
                        <div class="fw-bold" id="compartir_usuario_nombre"></div>
                        <div class="text-muted small" id="compartir_usuario_email"></div>
                    </div>

                    <div class="fw-bold mb-2">Permisos</div>

                    <div class="form-check mb-2">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="compartir_puede_ver"
                            checked
                            disabled
                        >
                        <label class="form-check-label" for="compartir_puede_ver">
                            Ver y comentar
                        </label>
                    </div>

                    <div class="form-check mb-3">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="compartir_puede_editar"
                        >
                        <label class="form-check-label" for="compartir_puede_editar">
                            Editar informe
                        </label>
                    </div>

                    <hr>

                    <div class="form-check mb-1">
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="compartir_clonar"
                        >
                        <label class="form-check-label fw-bold" for="compartir_clonar">
                            Clonar informe
                        </label>
                    </div>

                    <div class="small text-muted ms-4 mb-2">
                        Crea una copia independiente del informe, paciente y tutor en la cuenta seleccionada.
                    </div>

                    <div
                        class="form-check ms-4 mb-2 d-none"
                        id="compartir_clonar_comentarios_wrap"
                    >
                        <input
                            class="form-check-input"
                            type="checkbox"
                            id="compartir_copia_comentarios"
                            checked
                        >
                        <label class="form-check-label" for="compartir_copia_comentarios">
                            Incluir comentarios existentes
                        </label>
                    </div>
                </div>

            </div>

            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    Cancelar
                </button>

                <button
                    type="button"
                    class="btn btn-primary"
                    id="btnGuardarCompartir"
                    disabled
                >
                    Compartir
                </button>
            </div>

        </div>
    </div>
</div>
