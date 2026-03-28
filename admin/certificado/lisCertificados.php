<?php
###########################################
require_once("../config.php");
credenciales('certificado', 'listar');
###########################################

$mysqli = conn();
global $usuario_id, $acceso_aplicaciones;

// Traer certificados del veterinario actual
$sel = "SELECT 
        c.id, 
        p.nombre AS paciente, 
        t.nombre_completo AS propietario, 
        t.email AS email,  
        c.fecha_examen, 
        c.created_at, 
        c.medico_solicitante, 
        c.recinto, 
        pi.nombre AS tipo_examen,
        c.archivo_pdf,
        c.manual_data,
        c.tipo_ingreso 
      FROM certificados c
      LEFT JOIN pacientes p ON c.paciente_id = p.id
      LEFT JOIN tutores t ON p.tutor_id = t.id
      LEFT JOIN plantilla_informe pi ON c.tipo_estudio = pi.id
      WHERE c.veterinario_id = ?
      ORDER BY c.created_at DESC
      ";

$stmt = $mysqli->prepare($sel);
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
?>
<style>
    .dataTables_wrapper .dt-buttons {
        float: none;
        text-align: center;
    }
    table.dataTable thead th, table.dataTable tfoot th {
        font-family: Arial, sans-serif;
        font-size: 14px;
    }
    table.dataTable tbody td {
        font-family: Arial, sans-serif;
        font-size: 12px;
    }
</style>

<div id="certificado" data-page-id="certificado">
  <h1 class="h3 mb-3"><strong>Informes Generados</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-4">
            <div class="col-12 d-flex justify-content-between align-items-center">
              <a href="certificado/subir_informe/subir_informe.php" class="btn btn-outline-primary ajax-link">
                <i style="width:20px;height:20px;" data-feather="upload"></i> Subir Informe
              </a>
              <?php if (in_array('ingresar', $acceso_aplicaciones['certificado'] ?? [])): ?>
                <a href="certificado/certificados.php" class="btn btn-primary ajax-link">
                  <i style="width:20px;height:20px;" data-feather="plus"></i> Nuevo Informe
                </a>
              <?php endif; ?>
            </div>
          </div>

          <div class="table-responsive">
            <table id="tablaCertificados" class="table table-striped table-bordered dt-responsive nowrap datatable" style="width:100%">
              <thead>
                <tr>
                  <th>#</th>
                  <th>Paciente</th>
                  <th>Propietario</th>
                  <th>Tipo Examen</th>
                  <th>Médico Solicitante</th>
                  <th>Recinto</th>
                  <th>Fecha Examen</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
                <?php $i = 1;
                while ($fila = $res->fetch_assoc()):
                    // Si no hay paciente, intenta sacar de manual_data
                    $paciente = $fila['paciente'] ?? '';
                    $propietario = $fila['propietario'] ?? '';
                    $tipo_ingreso = $fila['tipo_ingreso'] ?? 'sistema';
                    if (empty($paciente) && !empty($fila['manual_data'])) {
                        $manual = json_decode($fila['manual_data'], true);
                        $paciente = $manual['paciente'] ?? 'Sin nombre';
                    }
                    if (empty($propietario) && !empty($fila['manual_data'])) {
                        $manual = $manual ?? json_decode($fila['manual_data'], true);
                        $propietario = $manual['propietario'] ?? '-';
                    }
                  ?>
                  <tr>
                    <td><?= $i++ ?></td>
                    <td><?= htmlspecialchars($paciente) ?></td>
                    <td><?= htmlspecialchars($propietario) ?></td>
                    <td><?= htmlspecialchars($fila['tipo_examen'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($fila['medico_solicitante'] ?? '-') ?></td>
                    <td><?= htmlspecialchars($fila['recinto'] ?? '-') ?></td>
                    <td><?= date('d-m-Y', strtotime($fila['fecha_examen'])) ?></td>
                    <td>
                      <div class="dropdown">
                        <button  class="btn btn-outline-info dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                          <i class="fas fa-ellipsis-v"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <?php if ($tipo_ingreso === 'manual'): ?>
                              <a class="dropdown-item ajax-link" href="certificado/subir_informe/subir_informe.php?action=modificar&id=<?= $fila['id'] ?>">
                                <i class="fas fa-edit me-2 text-primary"></i>Editar
                              </a>
                            <?php else: ?>
                              <a class="dropdown-item ajax-link" href="certificado/certificados.php?action=modificar&id=<?= $fila['id'] ?>">
                                <i class="fas fa-edit me-2 text-primary"></i>Editar
                              </a>
                            <?php endif; ?>

                            <a class="dropdown-item" href="#" onclick="confirmDelete(<?= $fila['id'] ?>, '<?= $tipo_ingreso ?>')">
                              <i class="fas fa-trash-alt me-2 text-danger"></i> Eliminar
                            </a>

                            <a class="dropdown-item" href="certificado/descargar.php?id=<?= (int)$fila['id'] ?>" target="_blank">
                              <i class="fas fa-file-pdf me-2 text-danger"></i>Ver PDF
                            </a>

                            <a class="dropdown-item" href="certificado/descargar.php?id=<?= (int)$fila['id'] ?>&dl=1">
                              <i class="fas fa-download me-2 text-primary"></i>Descargar PDF
                            </a>

                            <div class="dropdown-divider"></div>
                            <a class="dropdown-item" href="#"
                              onclick="abrirModalCorreo(this, <?= (int)$fila['id'] ?>)"
                              data-id="<?= (int)$fila['id'] ?>"
                              data-paciente="<?= htmlspecialchars($paciente) ?>"
                              data-propietario="<?= htmlspecialchars($propietario) ?>"
                              data-tipo_examen="<?= htmlspecialchars($fila['tipo_examen'] ?? '-') ?>"
                              data-email="<?= htmlspecialchars($fila['email'] ?? '') ?>">
                              <i class="fas fa-envelope me-2 text-success"></i> Enviar por correo
                            </a>
                        </div>
                      </div>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php 
include 'envio_email/envio_email.php'; 
?>

<script>
  function confirmDelete(id, tipo) {
    Swal.fire({
      title: '¿Eliminar Informe?',
      text: 'Esta acción no se puede deshacer',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonColor: '#d33',
      cancelButtonColor: '#3085d6',
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        const url = tipo === 'manual'
          ? 'certificado/subir_informe/updSubirInforme.php'
          : 'certificado/updCertificados.php';

        $.ajax({
          url: url,
          type: 'POST',
          data: { action: 'eliminar', id: id },
          success: function(response) {
            let jsonResponse = JSON.parse(response);
            if (jsonResponse.status === 'success') {
              $('#content').load('certificado/lisCertificados.php');
              Swal.fire('Eliminado', jsonResponse.message, 'success');
            } else {
              Swal.fire('Error', jsonResponse.message, 'error');
            }
          },
          error: function() {
            Swal.fire('Error', 'No se pudo eliminar el Informe.', 'error');
          }
        });
      }
    });
  }

if (!window.ajaxLinkEventRegistered) {
    $(document).on('click', '.ajax-link', function (e) {
        e.preventDefault();
        cargarConEditor($(this).attr('href'));
    });

    window.ajaxLinkEventRegistered = true; // ⚠️ clave
}

</script>
