<div id="organos_parametros">
  <div class="mb-3">
    <label for="filtro_especie" class="form-label">Especie:</label>
    <select id="filtro_especie" class="form-select">
      <option value="">-- Todas --</option>
      <?php
        $rsEsp = $mysqli->query("SELECT id, nombre FROM especies ORDER BY nombre ASC");
        while ($e = $rsEsp->fetch_assoc()) {
          echo "<option value='{$e['id']}'>".htmlspecialchars($e['nombre'])."</option>";
        }
      ?>
    </select>
  </div>
  <div class="mb-4">
    <hr>
    <table class="table table-bordered table-striped" id="tabla_organos" style="width:100%">
      <thead>
        <tr>
          <th>Órgano</th>
          <th>Especie</th>
          <th>Tamaño</th>
          <th>Etapa</th>
          <th>Mín - Máx</th>
          <th>Critico</th>
          <th>Unidad</th>
          <th>Acciones</th>
        </tr>
      </thead>
      <tbody></tbody>
    </table>
    <hr>
  </div>
  <div class="card bg-light shadow-xl mt-4">
    <div class="card-header py-2">
      <strong>Agregar / Editar parámetro</strong>
    </div>
    <div class="card-body">
      <form id="form_organo_parametro">
        <input type="hidden" name="id" id="organo_id">
        <div class="row g-3 align-items-end">
          <div class="col-md-3">
            <label for="organo" class="form-label">Órgano</label>
            <input type="text" name="organo" id="organo" class="form-control" placeholder="Ej: Hígado, Riñón derecho" required>
          </div>
          <div class="col-md-3" id="tamano_group" style="display:none;">
            <label for="tamano" class="form-label">Tamaño</label>
            <select name="tamano" id="tamano" class="form-select">
              <option value="">-- Selecciona --</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="etapa" class="form-label">Etapa</label>
            <select name="etapa" id="etapa" class="form-select">
              <option value="adulto">Adulto</option>
              <option value="cachorro">Cachorro</option>
            </select>
          </div>
          <div class="col-md-3">
            <label for="unidad" class="form-label">Unidad</label>
            <select name="unidad" id="unidad" class="form-select" required>
              <option value="cm" >cm</option>
              <option value="mm" selected>mm</option>
            </select>
          </div>
        </div>
        <div class="row g-3 align-items-end mt-1">
          <div class="col-md-3">
            <label for="tamano_min_error" class="form-label">Min Error</label>
            <div class="input-group">
              <input type="number" step="any" name="tamano_min_error" id="tamano_min_error" class="form-control">
              <span class="input-group-text" id="badge_unidad_minerr">cm</span>
            </div>
          </div>
          <div class="col-md-3">
            <label for="tamano_min" class="form-label">Mín</label>
            <div class="input-group">
              <input type="number" step="any" name="tamano_min" id="tamano_min" class="form-control" required>
              <span class="input-group-text" id="badge_unidad_min">cm</span>
            </div>
          </div>
          <div class="col-md-3">
            <label for="tamano_max" class="form-label">Máx</label>
            <div class="input-group">
              <input type="number" step="any" name="tamano_max" id="tamano_max" class="form-control" required>
              <span class="input-group-text" id="badge_unidad_max">cm</span>
            </div>
          </div>
          <div class="col-md-3">
            <label for="tamano_max_error" class="form-label">Max Error</label>
            <div class="input-group">
              <input type="number" step="any" name="tamano_max_error" id="tamano_max_error" class="form-control">
              <span class="input-group-text" id="badge_unidad_maxerr">cm</span>
            </div>
          </div>
        </div>
        <div class="col-md-3 mt-5 d-flex gap-2">
          <input type="hidden" name="especie_id" id="especie_id_hidden">
          <button type="submit" class="btn btn-primary flex-fill" id="btn_guardar_organo">Guardar</button>
          <button type="button" class="btn btn-outline-secondary" id="btn_cancelar_edicion" style="display:none;">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>
<script>
$(function () {
  const $root   = $('#organos_parametros');
  const $tabla  = $root.find('#tabla_organos');
  const $form   = $root.find('#form_organo_parametro');
  const $filtro = $root.find('#filtro_especie');

  const $tamanoGroup = $root.find('#tamano_group');
  const $tamano      = $root.find('#tamano');
  const $unidad      = $root.find('#unidad');
  const $btnCancel   = $root.find('#btn_cancelar_edicion');
  const $btnGuardar  = $root.find('#btn_guardar_organo');

  const $especieHidden = $root.find('#especie_id_hidden');

  const CANINO_ID = (function () {
    const opt = $filtro.find('option').filter(function () {
      return $(this).text().trim().toLowerCase() === 'canino';
    }).first();
    return String(opt.val() || '1');
  })();

  let dt = null;

  function initDT() {
    dt = $tabla.DataTable({
      responsive: true,
      pageLength: 10,
      language: { url: '//cdn.datatables.net/plug-ins/1.13.4/i18n/es-CL.json' }
    });
  }

  function cargarOrganos(especieId = '') {
    const params = especieId ? { especie_id: especieId, _: Date.now() } : { _: Date.now() };

    $.get('raza_parametros/componentes/crud/get_organos.php', params, function (res) {
      if (dt) {
        dt.clear();
        dt.destroy();
        dt = null;
      }
      $tabla.find('tr.child').remove();
      $tabla.find('tr').removeClass('parent');

      $tabla.find('tbody').html(res);
      initDT();

      // 👉 Ajustar visibilidad de la columna "Tamaño" según filtro actual
      updateTamanoColumn(especieId);
    });
  }


  function syncBadges() {
    const u = ($unidad.val() || 'cm').trim();
    $root.find('#badge_unidad_min, #badge_unidad_max, #badge_unidad_minerr, #badge_unidad_maxerr').text(u);
  }

  // Cargar tamaños distintos desde razas por especie
  function cargarTamanos(especieId, seleccionado = null, doneCb) {
    $.getJSON('raza_parametros/componentes/crud/get_tamanos.php', { especie_id: especieId })
      .done(function(resp){
        $tamano.empty();
        if (!resp || !resp.length) {
          $tamano.append('<option value="normal" selected>normal</option>');
        } else {
          $tamano.append('<option value="">-- Selecciona --</option>');
          resp.forEach(t => {
            const v = (t || '').trim();
            if (v) $tamano.append('<option value="'+v+'">'+v+'</option>');
          });
          if (seleccionado) $tamano.val(seleccionado);
        }
      })
      .always(function(){ if (typeof doneCb === 'function') doneCb(); });
  }

  // Mostrar/ocultar "Tamaño" según la especie elegida en el filtro superior
  function toggleTamanoByEspecie(espId = null, doneCb) {
    const id = String(espId ?? $filtro.val() ?? '');
    if (id && id === CANINO_ID) {
      $tamanoGroup.show();
      // Carga la lista de tamaños para Canino
      cargarTamanos(id, null, doneCb);
    } else {
      $tamanoGroup.hide();
      $tamano.empty().append('<option value="normal" selected>normal</option>');
      if (typeof doneCb === 'function') doneCb();
    }
  }

  function shouldShowTamanoColumn(espId) {
    // Mostrar cuando es "Todas" (vacío) o cuando es Canino
    const id = String(espId ?? $filtro.val() ?? '');
    return (id === '' || id === CANINO_ID);
  }

  function updateTamanoColumn(espId) {
    if (!dt) return;
    const show = shouldShowTamanoColumn(espId);
    dt.column(2).visible(show);     // 2 = columna "Tamaño"
    dt.columns().adjust().responsive.recalc();
  }


  // ---------- INIT ----------
  const inicial = $filtro.val() || '';
  $especieHidden.val(inicial);

  cargarOrganos(); // sin filtro al inicio
  syncBadges();
  toggleTamanoByEspecie(inicial);

  // ---------- EVENTOS ----------
  // Filtro de especie (arriba)
  $filtro.on('change', function(){
    const espId = $(this).val();

    // NUEVO: sincroniza hidden con el filtro
    $especieHidden.val(espId || '');

    toggleTamanoByEspecie(espId);
    cargarOrganos(espId);
  });

  $unidad.on('change', syncBadges);

  $btnCancel.on('click', function(){
    $form[0].reset();
    $root.find('#organo_id').val('');
    $btnCancel.hide();
    $btnGuardar.text('Guardar');
    syncBadges();
    toggleTamanoByEspecie($filtro.val());

    // NUEVO: mantener hidden acorde al filtro actual
    $especieHidden.val($filtro.val() || '');
  });

  // Editar
  $root.on('click', '.btn-editar-organo', function () {
    const d = $(this).data();

    $root.find('#organo_id').val(d.id);
    $root.find('#organo').val(d.organo);

    const espId = String(d.especieid || '');

    if (espId) {
      // sincroniza filtro + hidden
      $filtro.val(espId);
      $especieHidden.val(espId);
    }

    toggleTamanoByEspecie(espId, function(){
      if ($tamanoGroup.is(':visible') && espId) {
        cargarTamanos(espId, d.tamano || null);
      }
    });

    $root.find('#etapa').val(d.etapa || '');
    $root.find('#tamano_min').val(d.min);
    $root.find('#tamano_max').val(d.max);
    $root.find('#tamano_min_error').val(d.minerror || '');
    $root.find('#tamano_max_error').val(d.maxerror || '');
    $unidad.val(d.unidad || 'cm');
    syncBadges();

    $btnCancel.show();
    $btnGuardar.text('Actualizar');
  });

  // Guardar/Actualizar
  $form.on('submit', function (e) {
    e.preventDefault();

    // Asegura que el hidden lleve la especie actual
    $especieHidden.val($filtro.val() || '');

    // Deshabilitar botón mientras guarda (opcional)
    $btnGuardar.prop('disabled', true);

    $.ajax({
      url: 'raza_parametros/componentes/crud/add_organo.php',
      method: 'POST',
      data: $form.serialize(),     // incluye especie_id_hidden
      dataType: 'json'
    })
    .done(function (res) {
      if (res && res.status === 'ok') {
        Swal.fire('Guardado', res.message || 'Registro guardado', 'success');
        $form[0].reset();
        $root.find('#organo_id').val('');
        $btnCancel.hide();
        $btnGuardar.text('Guardar');
        syncBadges();
        toggleTamanoByEspecie($filtro.val());
        const filtroId = $filtro.val() || '';
        cargarOrganos(filtroId);
      } else {
        Swal.fire('Error', (res && res.message) ? res.message : 'No se pudo guardar.', 'error');
      }
    })
    .fail(function (xhr) {
      // VAS A VER QUÉ ROMPE AQUÍ si el server devuelve HTML o 500
      console.error('add_organo.php FAIL:', xhr.status, xhr.responseText);
      Swal.fire('Error', 'El servidor no respondió JSON válido o arrojó un error.', 'error');
    })
    .always(function () {
      $btnGuardar.prop('disabled', false);
    });
  });




  // Eliminar
  $root.on('click', '.btn-eliminar-organo', function () {
    const id = $(this).data('id');
    Swal.fire({
      title: '¿Eliminar?',
      text: 'Esta acción eliminará el parámetro.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Sí, eliminar',
      cancelButtonText: 'Cancelar'
    }).then(result => {
      if (result.isConfirmed) {
        $.post('raza_parametros/componentes/crud/delete_organo.php', { id }, function (res) {
          // Mapear estado a icono válido
          const icon = (res.status === 'ok' || res.status === 'success') ? 'success' : 'error';

          Swal.fire({
            title: icon === 'success' ? 'Eliminado' : 'Error',
            text: res.message || '',
            icon
          });

          const filtroId = $filtro.val() || '';
          cargarOrganos(filtroId);
        }, 'json').fail(function(xhr){
          console.error('delete_organo.php FAIL:', xhr.status, xhr.responseText);
          Swal.fire({ title: 'Error', text: 'No se pudo eliminar.', icon: 'error' });
        });
      }
    });
  });

});
</script>
