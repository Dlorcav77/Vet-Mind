<?php
###########################################
require_once("../../config.php");
###########################################

$mysqli = conn();
$action = $_GET['action'] ?? 'ingresar';

if ($action === "modificar") {
  credenciales('certificado', 'modificar');
  $accion = "Modificar";

  $id = $_GET['id'];
  $stmt = $mysqli->prepare("
      SELECT 
          c.*, 
          p.nombre AS paciente, 
          p.especie, 
          p.raza, 
          p.sexo,
          t.nombre_completo AS propietario
      FROM certificados c
      LEFT JOIN pacientes p ON c.paciente_id = p.id
      LEFT JOIN tutores t ON p.tutor_id = t.id
      WHERE c.id = ?
  ");
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $fila = $res->fetch_assoc();

  
} else {
  credenciales('certificado', 'ingresar');
  $accion = "Subir Informe";
  $fila = [
    'paciente_id'         => '',
    'medico_solicitante'  => '',
    'recinto'             => '',
    'tipo_estudio'        => '',
    'archivo_pdf'         => '',
    'manual_data'         => '',
    'fecha_examen'        => date('Y-m-d'),

  ];
}

// Obtener tipos de estudio para el select
$tipos = [];
$stmt = $mysqli->prepare("
  SELECT id, nombre 
  FROM plantilla_informe 
  WHERE estado = 'activo' AND deleted_at IS NULL AND veterinario_id = ? 
  ORDER BY nombre ASC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();
while ($row = $res->fetch_assoc()) {
  $tipos[] = $row;
}
?>

<div class="card" id="subir_informe" data-page-id="subir_informe">
  <div class="card-header">
    <h1 class="h3 mb-3"><strong><?= $accion ?></strong></h1>
  </div>

  <div class="card-body">
    <form id="formSubirInforme" method="post" action="certificado/subir_informe/updSubirInforme.php" enctype="multipart/form-data">
      <div class="row mb-3">
        <!-- Buscar paciente -->
        <?php include '../pacientes/paciente.php'; ?>
        <hr>
        <!-- Médico solicitante -->
        <div class="col-md-4 mb-3">
          <label for="medico_solicitante" class="form-label">Médico solicitante</label>
          <input type="text" id="medico_solicitante" name="medico_solicitante" class="form-control" value="<?= htmlspecialchars($fila['medico_solicitante']) ?>">
        </div>
        <div class="col-md-4 mb-3">
          <label for="recinto" class="form-label">Recinto</label>
          <input type="text" id="recinto" name="recinto" class="form-control" value="<?= htmlspecialchars($fila['recinto']) ?>">
        </div>

        <!-- Tipo de estudio -->
        <div class="col-md-4 mb-3">
          <label for="tipo_estudio" class="form-label">Tipo de estudio</label>
          <select id="tipo_estudio" name="tipo_estudio" class="form-select" required>
            <option value="">Selecciona un estudio</option>
            <?php foreach ($tipos as $t): ?>
              <option value="<?= $t['id'] ?>" <?= ($t['id'] == $fila['tipo_estudio']) ? 'selected' : '' ?>>
                <?= htmlspecialchars($t['nombre']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <!-- PDF -->
        <div class="col-md-12 mb-3">
          <label for="archivo_pdf" class="form-label">Archivo PDF del informe</label>
          <input type="file" id="archivo_pdf" name="archivo_pdf" class="form-control" accept="application/pdf" <?= $action !== 'modificar' ? 'required' : '' ?>>
          <?php if ($action === 'modificar' && !empty($fila['archivo_pdf'])): ?>
            <p class="text-muted mb-1">
              Archivo actual: 
              <a href="../../<?= htmlspecialchars($fila['archivo_pdf']) ?>" target="_blank">
                Ver PDF actual
              </a>
            </p>
          <?php endif; ?>

        </div>
      </div>

      <?php if ($action === 'modificar'): ?>
        <input type="hidden" name="id" value="<?= $id ?>">
      <?php endif; ?>
      <input type="hidden" name="action" value="<?= $action ?>">

      <button type="button" id="btnSubirInforme" class="btn btn-primary"><?= $accion ?></button>
    </form>
  </div>
<script>
(() => {
  const form = document.getElementById('formSubirInforme');
  const btn = document.getElementById('btnSubirInforme');

  if (!form || !btn || btn.dataset.listenerAttached === "true") return;
  btn.dataset.listenerAttached = "true";

  btn.addEventListener('click', () => {
    const formData = new FormData(form);

    if (!formData.get('paciente_id') && !formData.get('manual_nombre')) {
      Swal.fire('Paciente requerido', 'Debes seleccionar o ingresar un paciente.', 'warning');
      return;
    }

    Swal.fire({
      title: 'Subiendo informe...',
      text: 'Por favor espera un momento.',
      allowOutsideClick: false,
      didOpen: () => Swal.showLoading()
    });

    fetch('certificado/subir_informe/updSubirInforme.php', {
      method: 'POST',
      body: formData
    })
    .then(response => {
      // console.log(response);
      if (!response.ok) throw new Error();
      return response.json();
    })
    .then(data => {
      // console.log(data);
      Swal.close();
      if (data.status === 'success') {
        Swal.fire({
          icon: 'success',
          title: '¡Éxito!',
          text: data.message,
          confirmButtonText: 'Ir a lista'
        }).then(() => {
          $('#content').load('certificado/lisCertificados.php', function() {
            inicializarEditorContenido(); 
          });
        });
      } else {
        Swal.fire('Error', data.message || 'Ocurrió un problema al guardar.', 'error');
      }
    })
    .catch(() => {
      Swal.close();
      Swal.fire('Error', 'No se pudo conectar con el servidor.', 'error');
    });
  });
})();
</script>
