<?php
require_once("../config.php");
$mysqli = conn();

$plantilla_id = intval($_GET['plantilla_id'] ?? 0);
if (!$plantilla_id) {
    echo '<div class="alert alert-danger">ID de plantilla inválido.</div>';
    exit;
}

$query = "SELECT id, ejemplo FROM plantilla_informe_ejemplo WHERE plantilla_informe_id = ? ORDER BY id ASC";
$stmt = $mysqli->prepare($query);
$stmt->bind_param('i', $plantilla_id);
$stmt->execute();
$res = $stmt->get_result();

$ejemplos = [];
while ($row = $res->fetch_assoc()) {
    $ejemplos[] = $row;
}

$max_ejemplos = 2; // Cambia si quieres más
$num_ejemplos = count($ejemplos);

// Lista y edición de ejemplos existentes
if ($num_ejemplos > 0) {
    echo '<div class="list-group mb-3">';
    $n = 1;
    foreach ($ejemplos as $ej) {
        ?>
        <div class="list-group-item mb-3 p-3" id="ejemplo-item-<?= $ej['id'] ?>">
            <form class="formEditarEjemplo" data-id="<?= $ej['id'] ?>" style="margin-bottom:0;">
                <div class="mb-2 d-flex justify-content-between align-items-center">
                    <strong>Ejemplo <?= $n ?>:</strong>
                    <button type="button" class="btn btn-danger btn-sm ms-2" onclick="eliminarEjemplo(<?= $ej['id'] ?>, <?= $plantilla_id ?>)">
                        <i class="fas fa-trash"></i> Eliminar
                    </button>
                </div>
                <textarea class="form-control mb-2" name="ejemplo" rows="3"><?= htmlspecialchars($ej['ejemplo']) ?></textarea>
                <input type="hidden" name="id" value="<?= $ej['id'] ?>">
                <button type="submit" class="btn btn-primary btn-sm">Guardar</button>
                <span class="ms-2 small text-success d-none" id="msg-ok-<?= $ej['id'] ?>">✔ Guardado</span>
            </form>
        </div>
        <?php
        $n++;
    }
    echo "</div>";
} else {
    echo '<div class="alert alert-warning">No hay ejemplos cargados para esta plantilla.</div>';
}

// Formulario para agregar nuevo ejemplo (si no alcanzó el máximo)
if ($num_ejemplos < $max_ejemplos) {
    $next_n = $num_ejemplos + 1;
    echo "<form id='formEjemploAgregar' class='border-top pt-3 mt-3'>";
    echo "<div class='mb-2'><strong>Agregar Ejemplo {$next_n}:</strong></div>";
    echo "<textarea class='form-control mb-2' name='ejemplo' rows='3' required></textarea>";
    echo "<input type='hidden' name='plantilla_informe_id' value='$plantilla_id'>";
    echo "<button type='submit' class='btn btn-success'>Agregar Ejemplo</button>";
    echo "</form>";
}

echo '<div class="alert alert-info d-flex align-items-start my-3" role="alert" style="font-size:0.8rem;">
  
  <div>
    <strong><i class="fas fa-info-circle fa-lg me-2 mt-1"></i>¿Para qué sirven estos ejemplos?</strong><br>
    Los ejemplos que escribas acá ayudarán a la IA a entender cómo te gusta redactar tus informes. Así, podrás generar informes automáticos más detallados, eficientes y con tu propio estilo.<br>
    <b>¡Mientras mejor expliques, mejores resultados tendrás!</b>
  </div>
</div>';

?>
<script>
$('#formEjemplosEditar').on('submit', function(e){
    e.preventDefault();
    $.ajax({
        url: 'plantilla_informe/ajax_guardar_ejemplo.php',
        type: 'POST',
        data: $(this).serialize() + '&action=editar',
        success: function(resp){
            let res = JSON.parse(resp);
            Swal.fire(res.status === 'success' ? '¡Guardado!' : 'Error', res.message, res.status);
            if(res.status === 'success') {
                verEjemplos(<?= $plantilla_id ?>, $('#ejemploTitulo').text());
            }
        }
    });
});

$('#formEjemploAgregar').on('submit', function(e){
    e.preventDefault();
    $.ajax({
        url: 'plantilla_informe/guardar_ejemplo.php',
        type: 'POST',
        data: $(this).serialize() + '&action=agregar',
        success: function(resp){
            let res = JSON.parse(resp);
            Swal.fire(res.status === 'success' ? '¡Agregado!' : 'Error', res.message, res.status);
            if(res.status === 'success') {
                verEjemplos(<?= $plantilla_id ?>, $('#ejemploTitulo').text());
            }
        }
    });
});

function eliminarEjemplo(id, plantilla_id) {
    Swal.fire({
        title: '¿Eliminar ejemplo?',
        icon: 'warning',
        showCancelButton: true,
        confirmButtonText: 'Sí, eliminar'
    }).then(res => {
        if(res.isConfirmed) {
            $.ajax({
                url: 'plantilla_informe/guardar_ejemplo.php',
                type: 'POST',
                data: { id: id, action: 'eliminar' },
                success: function(resp){
                    let r = JSON.parse(resp);
                    Swal.fire(r.status === 'success' ? '¡Eliminado!' : 'Error', r.message, r.status);
                    if(r.status === 'success') {
                        verEjemplos(plantilla_id, $('#ejemploTitulo').text());
                    }
                }
            });
        }
    });
}

// Guardar ejemplo individual
$('.formEditarEjemplo').on('submit', function(e){
    e.preventDefault();
    var $form = $(this);
    var id = $form.data('id');
    var data = $form.serialize() + '&action=editar_individual';
    $.ajax({
        url: 'plantilla_informe/guardar_ejemplo.php',
        type: 'POST',
        data: data,
        success: function(resp){
            let res = JSON.parse(resp);
            if(res.status === 'success') {
                $form.find('#msg-ok-' + id).removeClass('d-none').fadeIn();
                setTimeout(function() {
                    $form.find('#msg-ok-' + id).fadeOut();
                }, 1500);
            } else {
                Swal.fire('Error', res.message, 'error');
            }
        }
    });
});

</script>
