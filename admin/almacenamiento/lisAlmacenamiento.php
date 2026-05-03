<?php
// admin/almacenamiento/lisAlmacenamiento.php

require_once("../config.php");

// Por ahora usamos el mismo permiso de informes.
// Cuando creemos el módulo formal en permisos/menú, lo cambiamos a almacenamiento/listar.
credenciales('certificado', 'listar');

$mysqli = conn();
global $usuario_id;

$usuarioActual = (int)($usuario_id ?? ($_SESSION['usuario_id'] ?? 0));
?>

<style>
  .alm-card-kpi {
    border: 1px solid #e9ecef;
    border-radius: 14px;
    padding: 16px;
    background: #fff;
    height: 100%;
    box-shadow: 0 2px 10px rgba(0,0,0,.04);
  }

  .alm-card-kpi .alm-kpi-icon {
    width: 38px;
    height: 38px;
    border-radius: 12px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    background: #f1f5ff;
    color: #0d6efd;
    margin-bottom: 10px;
  }

  .alm-card-kpi .alm-kpi-label {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 4px;
  }

  .alm-card-kpi .alm-kpi-value {
    font-size: 22px;
    font-weight: 700;
    color: #212529;
    line-height: 1.1;
  }

  .alm-card-kpi .alm-kpi-sub {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
  }

  .alm-toolbar {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    align-items: end;
    justify-content: space-between;
  }

  .alm-toolbar .form-control,
  .alm-toolbar .form-select {
    min-height: 38px;
  }

  .alm-badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 5px 9px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 600;
    white-space: nowrap;
  }

  .alm-badge-pdf {
    background: #fdecec;
    color: #b42318;
  }

  .alm-badge-img {
    background: #eaf7ef;
    color: #146c43;
  }

  .alm-badge-ok {
    background: #eaf7ef;
    color: #146c43;
  }

  .alm-badge-missing {
    background: #fff3cd;
    color: #8a6d1d;
  }

  .alm-badge-total {
    background: #eef2ff;
    color: #3730a3;
  }

  #tablaAlmacenamiento tbody td {
    vertical-align: middle;
    font-size: 12px;
  }

  #tablaAlmacenamiento thead th {
    font-size: 13px;
  }

  .alm-paciente-title {
    font-weight: 700;
    color: #212529;
  }

  .alm-paciente-sub {
    font-size: 12px;
    color: #6c757d;
  }

  .alm-modal-scroll {
    max-height: 65vh;
    overflow-y: auto;
  }

  .alm-img-card {
    border: 1px solid #e9ecef;
    border-radius: 12px;
    overflow: hidden;
    background: #fff;
    height: 100%;
  }

  .alm-img-card img {
    width: 100%;
    height: 150px;
    object-fit: cover;
    background: #f8f9fa;
  }

  .alm-img-card-body {
    padding: 10px;
  }

  .alm-img-name {
    font-size: 12px;
    font-weight: 600;
    word-break: break-all;
  }

  .alm-img-meta {
    font-size: 11px;
    color: #6c757d;
  }
</style>

<div id="almacenamiento" data-page-id="almacenamiento">
  <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-3">
    <div>
      <h1 class="h3 mb-1">
        <strong>Administrador de archivos</strong>
      </h1>
      <div class="text-muted small">
        Revisión agrupada de imágenes y PDFs generados en informes.
      </div>
    </div>

    <a href="certificado/lisCertificados.php" class="btn btn-outline-secondary ajax-link">
      <i class="fas fa-arrow-left me-2"></i>Volver a informes
    </a>
  </div>

  <div class="row g-3 mb-4">
    <div class="col-6 col-lg-3">
      <div class="alm-card-kpi">
        <div class="alm-kpi-icon">
          <i class="fas fa-hdd"></i>
        </div>
        <div class="alm-kpi-label">Espacio total usado</div>
        <div class="alm-kpi-value" id="almTotalPeso">-</div>
        <div class="alm-kpi-sub" id="almTotalArchivos">- archivos</div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="alm-card-kpi">
        <div class="alm-kpi-icon">
          <i class="fas fa-file-pdf"></i>
        </div>
        <div class="alm-kpi-label">PDFs</div>
        <div class="alm-kpi-value" id="almTotalPdf">-</div>
        <div class="alm-kpi-sub" id="almPesoPdf">-</div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="alm-card-kpi">
        <div class="alm-kpi-icon">
          <i class="fas fa-images"></i>
        </div>
        <div class="alm-kpi-label">Imágenes</div>
        <div class="alm-kpi-value" id="almTotalImagenes">-</div>
        <div class="alm-kpi-sub" id="almPesoImagenes">-</div>
      </div>
    </div>

    <div class="col-6 col-lg-3">
      <div class="alm-card-kpi">
        <div class="alm-kpi-icon">
          <i class="fas fa-users"></i>
        </div>
        <div class="alm-kpi-label">Pacientes / propietarios</div>
        <div class="alm-kpi-value" id="almTotalGrupos">-</div>
        <div class="alm-kpi-sub" id="almTotalFaltantes">- faltantes</div>
      </div>
    </div>
  </div>

  <div class="card shadow-sm">
    <div class="card-body">
      <div class="alm-toolbar mb-3">
        <div>
          <h5 class="mb-1 fw-bold text-primary">
            <i class="fas fa-folder-open me-2"></i>Archivos agrupados
          </h5>
          <div class="text-muted small">
            Información obtenida desde <code>certificados.archivo_pdf</code> e <code>certificados.imagenes_json</code>.
          </div>
        </div>

        <div class="d-flex gap-2 flex-wrap">
          <select id="filtroEstadoArchivo" class="form-select form-select-sm" style="width: 210px;">
            <option value="">Todos los grupos</option>
            <option value="con_pdf">Con PDFs</option>
            <option value="con_imagen">Con imágenes</option>
            <option value="con_faltantes">Con faltantes</option>
          </select>

          <button type="button" id="btnRecargarAlmacenamiento" class="btn btn-outline-primary btn-sm">
            <i class="fas fa-sync-alt me-1"></i> Recargar
          </button>
        </div>
      </div>

      <div class="table-responsive">
        <table id="tablaAlmacenamiento" class="table table-striped table-bordered nowrap" style="width:100%">
          <thead>
            <tr>
              <th>Paciente / Propietario</th>
              <th>Último informe</th>
              <th>Informes</th>
              <th>PDFs</th>
              <th>Imágenes</th>
              <th>Peso total</th>
              <th>Faltantes</th>
              <th>Acciones</th>
              <th>Fecha sort</th>
              <th>ID sort</th>
            </tr>
          </thead>
          <tbody></tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalAlmPdfs" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">
            <i class="fas fa-file-pdf text-danger me-2"></i>PDFs del paciente
          </h5>
          <div class="small text-muted" id="modalAlmPdfsSubtitulo"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead>
              <tr>
                <th>Informe</th>
                <th>Fecha</th>
                <th>Archivo</th>
                <th>Tamaño</th>
                <th>Estado</th>
                <th style="width: 150px;">Acciones</th>
              </tr>
            </thead>
            <tbody id="modalAlmPdfsBody"></tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalAlmImagenes" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">
            <i class="fas fa-images text-success me-2"></i>Imágenes del paciente
          </h5>
          <div class="small text-muted" id="modalAlmImagenesSubtitulo"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="row g-3" id="modalAlmImagenesBody"></div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalAlmInformes" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <div>
          <h5 class="modal-title mb-0">
            <i class="fas fa-file-medical text-primary me-2"></i>Informes del paciente
          </h5>
          <div class="small text-muted" id="modalAlmInformesSubtitulo"></div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body">
        <div class="table-responsive">
          <table class="table table-sm table-bordered align-middle">
            <thead>
              <tr>
                <th>Informe</th>
                <th>Fecha</th>
                <th>Tipo ingreso</th>
                <th style="width: 110px;">Acción</th>
              </tr>
            </thead>
            <tbody id="modalAlmInformesBody"></tbody>
          </table>
        </div>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cerrar</button>
      </div>
    </div>
  </div>
</div>

<script>
  window.ALMACENAMIENTO_USUARIO_ID = <?= (int)$usuarioActual ?>;
</script>

<script src="almacenamiento/js/almacenamiento.js?v=3"></script>