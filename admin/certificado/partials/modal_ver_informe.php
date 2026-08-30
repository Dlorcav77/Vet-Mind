<?php
// admin/certificado/partials/modal_ver_informe.php
?>

<div
    class="modal fade"
    id="modalVerInforme"
    tabindex="-1"
    aria-labelledby="modalVerInformeLabel"
    aria-hidden="true"
>
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content">

            <div class="modal-header vm-ver-header">

                <h5
                    class="modal-title fw-bold mb-0 vm-ver-header-title"
                    id="modalVerInformeLabel"
                >
                    <i class="fas fa-eye me-2"></i>

                    <span id="verInformeTitulo">
                        Informe
                    </span>
                </h5>

                <div
                    class="btn-group vm-ver-tabs vm-ver-header-center"
                    role="group"
                    aria-label="Vista del informe"
                >
                    <button
                        type="button"
                        class="btn vm-btn-accent"
                        id="btnVerInformeSistema"
                    >
                        <i class="fas fa-eye me-1"></i>
                        Informe
                    </button>

                    <button
                        type="button"
                        class="btn vm-btn-accent-outline"
                        id="btnVerInformePdf"
                    >
                        <i class="fas fa-file-pdf me-1"></i>
                        PDF
                    </button>
                </div>

                <div class="vm-ver-header-close">
                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Cerrar"
                    ></button>
                </div>

            </div>

            <div class="modal-body">
                <div
                    id="verInformeLoading"
                    class="text-center py-4"
                >
                    <div
                        class="spinner-border text-info"
                        role="status"
                    >
                        <span class="visually-hidden">
                            Cargando...
                        </span>
                    </div>

                    <div class="text-muted mt-2 small">
                        Cargando informe...
                    </div>
                </div>

                <div
                    id="verInformeVistaSistema"
                    style="display:none;"
                >
                    <div
                        class="vm-ver-resumen-grid mb-3"
                        id="verInformeResumenGrid"
                    >
                        <div
                            id="verInformePacienteWrap"
                            class="vm-ver-seccion"
                            style="display:none;"
                        >
                            <div class="vm-ver-seccion-titulo">
                                <i class="fas fa-paw me-1"></i>
                                Paciente
                            </div>

                            <div class="vm-ver-card rounded-3 p-3">
                                <div
                                    id="verInformeCamposPaciente"
                                    class="row"
                                ></div>
                            </div>
                        </div>

                        <div
                            id="verInformeGeneralWrap"
                            class="vm-ver-seccion"
                            style="display:none;"
                        >
                            <div class="vm-ver-seccion-titulo">
                                <i class="fas fa-file-medical me-1"></i>
                                Informe
                            </div>

                            <div class="vm-ver-card rounded-3 p-3">
                                <div
                                    id="verInformeCamposGeneral"
                                    class="row"
                                ></div>
                            </div>
                        </div>

                        <div
                            id="verInformeDestacadoWrap"
                            class="vm-ver-referencia rounded-3 p-3"
                            style="display:none;"
                        >
                            <div class="d-flex align-items-start gap-2">

                                <i class="fas fa-bookmark mt-1"></i>

                                <div>
                                    <div class="fw-bold small">
                                        Caso de referencia
                                    </div>

                                    <div
                                        id="verInformeDestacadoTitulo"
                                        class="mt-1"
                                    ></div>
                                </div>

                            </div>
                        </div>
                    </div>
                    <div>
                        <div class="fw-bold small mb-1">
                            <i class="fas fa-file-medical me-1"></i>
                            Contenido
                        </div>

                        <div
                            id="verInformeContenido"
                            class="border rounded-3 bg-white"
                        ></div>
                    </div>

                </div>

                <div
                    id="verInformeVistaPdf"
                    style="display:none;"
                >
                    <div
                        id="verInformePdfContenido"
                        class="border rounded overflow-hidden bg-light"
                    ></div>
                </div>

            </div>

            <div
                class="modal-footer d-flex justify-content-between align-items-center flex-wrap gap-2"
            >

                <div
                    class="d-flex align-items-center gap-2"
                    id="verInformeNavegacion"
                >
                    <button
                        type="button"
                        class="btn btn-sm vm-btn-accent-outline"
                        id="btnVerInformeAnterior"
                        title="Informe anterior"
                        aria-label="Informe anterior"
                        disabled
                    >
                        <i class="fas fa-chevron-left"></i>
                    </button>

                    <span
                        id="verInformePosicion"
                        class="small text-muted text-nowrap"
                    >
                        - / -
                    </span>

                    <button
                        type="button"
                        class="btn btn-sm vm-btn-accent-outline"
                        id="btnVerInformeSiguiente"
                        title="Informe siguiente"
                        aria-label="Informe siguiente"
                        disabled
                    >
                        <i class="fas fa-chevron-right"></i>
                    </button>
                </div>


                <div class="d-flex align-items-center gap-2">

                    <a
                        href="#"
                        class="btn btn-sm vm-btn-accent-outline"
                        id="btnVerInformeDescargar"
                    >
                        <i class="fas fa-download me-1"></i>
                        Descargar PDF
                    </a>

                    <button
                        type="button"
                        class="btn btn-sm btn-success"
                        id="btnVerInformeEnviar"
                    >
                        <i class="fas fa-envelope me-1"></i>
                        Enviar
                    </button>

                    <a
                        href="#"
                        class="btn btn-sm vm-btn-accent ajax-link"
                        id="btnVerInformeEditar"
                    >
                        <i class="fas fa-edit me-1"></i>
                        Editar
                    </a>

                    <button
                        type="button"
                        class="btn btn-sm btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cerrar
                    </button>

                </div>

            </div>

        </div>
    </div>
</div>