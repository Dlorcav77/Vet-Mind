<div class="mb-3">
  <label for="select_especie" class="form-label">Especie:</label>
  <select id="select_especie" class="form-select">
    <option value="">-- Selecciona --</option>
    <?php
      $res = $mysqli->query("SELECT id, nombre FROM especies ORDER BY nombre ASC");
      while ($row = $res->fetch_assoc()) {
        echo "<option value='{$row['id']}'>".htmlspecialchars($row['nombre'])."</option>";
      }
    ?>
  </select>
</div>
<!-- Tabla de razas -->
<div class="mb-4" id="contenedor_tabla_razas" style="display: none;">
  <hr>
  <h4>Razas asociadas</h4>
  <table class="table table-bordered table-striped" id="tabla_razas">
    <thead>
      <tr>
        <!-- <th>ID</th> -->
        <th>Nombre</th>
        <th>Tamaño</th>
        <th>Estado</th>
        <th>Acciones</th>
      </tr>
    </thead>
    <tbody></tbody>
  </table>
  <hr>
</div>
<!-- Formulario agregar raza -->
<div class="card bg-light p-3 mt-4 shadow-xl" id="contenedor_formulario_raza" style="display: none;">
  <h5>Agregar nueva raza</h5>
  <form id="form_agregar_raza">
    <input type="hidden" name="especie_id" id="especie_id">
    <div class="mb-2">
      <label for="nombre_raza" class="form-label">Nombre de la raza</label>
      <input type="text" id="nombre_raza" name="nombre_raza" class="form-control" required>
    </div>
    <div class="mb-2" id="tamano_wrapper" style="display: none;">
      <label for="tamano_raza" class="form-label">Tamaño</label>
      <select id="tamano_raza" name="tamano_raza" class="form-select">
        <option value="">Seleccionar</option>
        <option value="miniatura">Miniatura</option>
        <option value="pequeño">Pequeño</option>
        <option value="mediano">Mediano</option>
        <option value="grande">Grande</option>
        <option value="gigante">Gigante</option>
      </select>
    </div>

    <!-- Campo oculto que toma el valor 'normal' si no es canino -->
    <input type="hidden" id="tamano_fijo" name="tamano_raza" value="normal">
    <input type="hidden" name="raza_id" id="raza_id">

    <button type="submit" class="btn btn-primary" id="btn_guardar_raza">Agregar Raza</button>
  </form>
</div>
<script>


$(function () {

  // Función reutilizable para recargar la tabla de razas
  function recargarRazas() {
    const especie_id = $('#select_especie').val();
    if (!especie_id) return;

    // Destruye el DataTable si ya está inicializado
    if ($.fn.DataTable.isDataTable('#tabla_razas')) {
      $('#tabla_razas').DataTable().destroy();
    }

    $.get('raza_parametros/componentes/crud/get_razas.php', { especie_id }, function (res) {
      $('#tabla_razas tbody').html(res);

      // Solo inicializar si hay al menos un <tr>
      if ($('#tabla_razas tbody tr').length > 0) {
        $('#tabla_razas').DataTable({
          responsive: true,
          language: {
            url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-CL.json'
          }
        });
      }
    });
  }

  // Cambio de especie
  $('#select_especie').on('change', function () {
    const especie_id = $(this).val();
    $('#especie_id').val(especie_id);

    if (especie_id == 1) {
      $('#tamano_wrapper').show();
      $('#tamano_raza').prop('disabled', false);
      $('#tamano_fijo').prop('disabled', true);
    } else {
      $('#tamano_wrapper').hide();
      $('#tamano_raza').prop('disabled', true);
      $('#tamano_fijo').prop('disabled', false);
    }

    if (!especie_id) {
      $('#tabla_razas tbody').html('');
      $('#contenedor_tabla_razas').hide();
      $('#contenedor_formulario_raza').hide();
      return;
    }

    $('#contenedor_tabla_razas').show();
    $('#contenedor_formulario_raza').show();

    recargarRazas();
  });

  // Agregar o actualizar raza
  $('#form_agregar_raza').on('submit', function (e) {
    e.preventDefault();

    const especie_id = $('#especie_id').val();
    const nombre_raza = $('#nombre_raza').val().trim();
    const raza_id = $('#raza_id').val(); // <-- importante

    if (!especie_id) {
      Swal.fire({
        icon: 'warning',
        title: 'Especie no seleccionada',
        text: 'Debes seleccionar una especie antes de agregar una raza.'
      });
      return;
    }

    if (!nombre_raza) {
      Swal.fire({
        icon: 'warning',
        title: 'Nombre requerido',
        text: 'Debes ingresar el nombre de la raza.'
      });
      return;
    }

    $.post('raza_parametros/componentes/crud/add_raza.php', $(this).serialize(), function (res) {
      if (res.status === 'ok') {
        Swal.fire({
          icon: 'success',
          title: raza_id ? 'Raza actualizada' : 'Raza agregada',
          text: res.message
        });

        $('#form_agregar_raza')[0].reset();
        $('#raza_id').val('');
        $('#btn_guardar_raza').text('Agregar Raza');

        recargarRazas();
      } else {
        Swal.fire({
          icon: 'error',
          title: 'Error',
          text: res.message
        });
      }
    }, 'json');
  });

  // Editar
  $(document).on('click', '.btn-editar', function () {
    const id = $(this).data('id');
    const nombre = $(this).data('nombre');
    const tamano = $(this).data('tamano');

    $('#raza_id').val(id);
    $('#nombre_raza').val(nombre);
    $('#btn_guardar_raza').text('Actualizar Raza');

    if ($('#select_especie').val() == 1) {
      $('#tamano_raza').val(tamano);
    }
  });

  // Eliminar
  $(document).on('click', '.btn-eliminar', function () {
    const id = $(this).data('id');

    Swal.fire({
      title: '¿Estás seguro?',
      text: 'Esta acción eliminará la raza.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then((result) => {
      if (result.isConfirmed) {
        $.post('raza_parametros/componentes/crud/delete_raza.php', { id }, function (res) {
          Swal.fire({
            icon: res.status === 'ok' ? 'success' : 'error',
            title: res.status === 'ok' ? 'Eliminado' : 'Error',
            text: res.message
          });
          recargarRazas();
        }, 'json');
      }
    });
  });

});
</script>
