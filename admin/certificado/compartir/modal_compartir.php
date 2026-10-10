<style>
.vm-compartir-favorito-label {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 34px;
    height: 34px;
    margin: 0;
    border-radius: 50%;
    color: #adb5bd;
    cursor: pointer;
    font-size: 18px;
    transition: transform .12s ease, color .12s ease;
}

.vm-compartir-favorito-label:hover {
    transform: scale(1.08);
}

#compartir_agregar_usuario:checked
+ .vm-compartir-favorito-label {
    color: #ffc107;
}
</style>

<div class="modal fade" id="modalCompartirInforme" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
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
                        <div class="d-flex justify-content-between align-items-center gap-3">

                            <div>
                                <div
                                    class="fw-bold"
                                    id="compartir_usuario_nombre"
                                ></div>

                                <div
                                    class="text-muted small"
                                    id="compartir_usuario_email"
                                ></div>
                            </div>

                            <div class="vm-compartir-favorito">
                                <input
                                    class="visually-hidden"
                                    type="checkbox"
                                    id="compartir_agregar_usuario"
                                    checked
                                >

                                <label
                                    for="compartir_agregar_usuario"
                                    class="vm-compartir-favorito-label"
                                    title="Agregar usuario"
                                    aria-label="Agregar usuario"
                                >
                                    <i class="fas fa-star"></i>
                                </label>
                            </div>

                        </div>
                    </div>

                    <div class="row g-3 mb-2">

                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-bold mb-3">
                                    <i class="fas fa-user-friends me-1 text-info"></i>
                                    Compartir
                                </div>

                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="compartir_puede_ver"
                                        checked
                                        disabled
                                    >
                                    <label
                                        class="form-check-label"
                                        for="compartir_puede_ver"
                                    >
                                        Ver y comentar
                                    </label>
                                </div>

                                <div class="form-check">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="compartir_puede_editar"
                                    >
                                    <label
                                        class="form-check-label"
                                        for="compartir_puede_editar"
                                    >
                                        Editar informe
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="border rounded p-3 h-100">
                                <div class="fw-bold mb-3">
                                    <i class="fas fa-copy me-1 text-primary"></i>
                                    Clonar
                                </div>

                                <div class="form-check mb-2">
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="compartir_clonar"
                                    >
                                    <label
                                        class="form-check-label"
                                        for="compartir_clonar"
                                    >
                                        Crear copia independiente
                                    </label>
                                </div>

                                <div class="small text-muted mb-2">
                                    Copia el informe, paciente y tutor a la cuenta seleccionada.
                                </div>

                                <div
                                    class="form-check d-none"
                                    id="compartir_clonar_comentarios_wrap"
                                >
                                    <input
                                        class="form-check-input"
                                        type="checkbox"
                                        id="compartir_copia_comentarios"
                                        checked
                                    >
                                    <label
                                        class="form-check-label"
                                        for="compartir_copia_comentarios"
                                    >
                                        Incluir comentarios
                                    </label>
                                </div>
                            </div>
                        </div>

                    </div>
                </div>

                <div class="row g-3 mt-3">

                    <div
                        class="col-md-6"
                        id="compartir_agregados_wrap"
                    >
                        <div class="fw-bold mb-2">
                            Usuarios agregados
                        </div>

                        <div
                            id="compartir_usuarios_agregados"
                            class="d-flex flex-column gap-2"
                        >
                            <div class="small text-muted p-2 border rounded">
                                Sin usuarios agregados.
                            </div>
                        </div>
                    </div>

                    <div
                        class="col-md-6"
                        id="compartir_favoritos_wrap"
                    >
                        <div class="fw-bold mb-2">
                            Favoritos
                        </div>

                        <div
                            id="compartir_usuarios_favoritos"
                            class="d-flex flex-column gap-2"
                        >
                            <div class="small text-muted p-2 border rounded">
                                Sin favoritos.
                            </div>
                        </div>
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
