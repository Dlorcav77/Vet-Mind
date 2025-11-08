<?php
###########################################
require_once("../config.php");
###########################################

$mysqli = conn();
$action = $_GET['action'] ?? 'ingresar';

if ($action == "modificar") {
    credenciales('plantilla_informe', 'modificar');
    $accion = "Modificar";

    $id = $_GET['id'];
    $sel = "SELECT * FROM plantilla_informe WHERE id = ?";
    $stmt = $mysqli->prepare($sel);
    $stmt->bind_param('i', $id);
    $stmt->execute();
    $res = $stmt->get_result();
    $fila = $res->fetch_assoc();
} else {
    credenciales('plantilla_informe', 'ingresar');
    $accion = "Ingresar";
    $fila = [
        'tipo_examen_id' => '',
        'nombre'         => '',
        'contenido'      => '',
        'estado'         => 'activo'
    ];
}

// Cargar tipos de examen para el select
$tipos_examen = [];
$stmt = $mysqli->prepare("SELECT id, nombre FROM tipo_examen WHERE estado = 'activo' AND veterinario_id = ?");
$stmt->bind_param('i', $usuario_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $tipos_examen[] = $row;
}
?>
<script src="../assets/ckeditor/ckeditor.js"></script>
<div class="card" id="plantilla_informe" data-page-id="plantilla_informe">
    <div class="card-header">
        <h1 class="h3 mb-3"><strong><?= $accion; ?> Plantilla de Informe</strong></h1>
        <div class="col-xl-12 col-xxl-10 d-flex">
            <div class="card-body">
                <form method="post" action="plantilla_informe/updPlantillaInforme.php">
                    <div class="row mb-3">
                        <!-- Tipo de Examen -->
                        <div class="col-md-6 mb-2">
                            <label for="tipo_examen_id" class="form-label">Tipo de Examen</label>
                            <select id="tipo_examen_id" name="tipo_examen_id" class="form-control" required>
                                <option value="">Seleccione un tipo</option>
                                <?php foreach ($tipos_examen as $examen): ?>
                                    <option value="<?= $examen['id'] ?>" <?= $examen['id'] == $fila['tipo_examen_id'] ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($examen['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Nombre Plantilla -->
                        <div class="col-md-6 mb-2">
                            <label for="nombre" class="form-label">Nombre de la Plantilla</label>
                            <input type="text" class="form-control" id="nombre" name="nombre" maxlength="100" value="<?= htmlspecialchars($fila['nombre']); ?>" required>
                        </div>

                        <!-- Contenido -->
                        <div class="col-12 mb-2">
                            <label for="contenido" class="form-label">Contenido</label>
                            <textarea id="contenido" name="contenido" class="form-control" rows="8"><?= htmlspecialchars($fila['contenido']); ?></textarea>
                        </div>

                        <!-- Estado -->
                        <div class="col-md-6 mb-2">
                            <label for="estado" class="form-label">Estado</label>
                            <select id="estado" name="estado" class="form-control" required>
                                <option value="activo" <?= $fila['estado'] === 'activo' ? 'selected' : ''; ?>>Activo</option>
                                <option value="inactivo" <?= $fila['estado'] === 'inactivo' ? 'selected' : ''; ?>>Inactivo</option>
                            </select>
                        </div>
                    </div>

                    <?php if ($action == 'modificar'): ?>
                        <input type="hidden" name="id" value="<?= $id; ?>">
                    <?php endif; ?>
                    <input type="hidden" name="veterinario_id" value="<?= $usuario_id; ?>">
                    <input type="hidden" name="action" value="<?= $action; ?>">
                    <button type="submit" class="btn btn-primary"><?= $accion; ?></button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    CKEDITOR.replace('contenido', { height: 300 });

    $('form').on('submit', function(e) {
        e.preventDefault();

        // Sincronizar CKEditor
        for (instance in CKEDITOR.instances) {
            CKEDITOR.instances[instance].updateElement();
        }

        var formData = $(this).serialize();

        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: formData,
success: function(response) {
    // console.log(response);
    let jsonResponse = JSON.parse(response);
    if (jsonResponse.status === 'success') {
        // 🔥 Destruye CKEditor antes de recargar
        for (instance in CKEDITOR.instances) {
            CKEDITOR.instances[instance].destroy(true);
        }

        // Ahora carga la lista
        $('#content').load('plantilla_informe/lisPlantillaInforme.php');
        
        Swal.fire({
            icon: 'success',
            title: '¡Éxito!',
            text: jsonResponse.message,
            confirmButtonText: 'OK'
        });
    } else {
        Swal.fire({
            icon: 'error',
            title: 'Error',
            text: jsonResponse.message,
            confirmButtonText: 'OK'
        });
    }
},

            error: function() {
                Swal.fire({
                    icon: 'error',
                    title: 'Error',
                    text: 'Hubo un problema al guardar la plantilla.',
                    confirmButtonText: 'OK'
                });
            }
        });
    });
});
</script>
