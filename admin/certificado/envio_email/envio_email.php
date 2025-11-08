<style>
  .section-title{
    font-size: .9rem;
    letter-spacing: .04em;
    color: #6c757d;            /* text-muted */
    text-transform: uppercase;
    border-bottom: 1px solid rgba(0,0,0,.06);
    padding-bottom: .4rem;
    margin-bottom: .6rem;
    font-weight: 600;
  }
  .bg-light-subtle{ background-color: rgba(108,117,125,.06); } /* similar a text-muted suave */
</style>


<!-- Modal Enviar Correo -->
<div class="modal fade" id="modalEnviarCorreo" tabindex="-1" aria-labelledby="modalCorreoLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header border-0">
        <h5 class="modal-title d-flex align-items-center gap-2" id="modalCorreoLabel">
          <i class="fas fa-envelope"></i>
          <span>Enviar informe por correo</span>
        </h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
      </div>

      <div class="modal-body pt-0">
        <form id="formCorreo">
          <input type="hidden" name="certificado_id" id="correo_certificado_id">
          <div class="card bg-light-subtle border-0 mb-3">
            <div class="card-body py-3">
              <div class="row g-2 text-center text-md-start">
                <div class="col-md-4 col-12">
                  <div class="text-muted fw-semibold text-uppercase mb-1">Paciente</div>
                  <div id="info_paciente" class=""></div>
                </div>
                <div class="col-md-4 col-12">
                  <div class="text-muted fw-semibold text-uppercase mb-1">Propietario</div>
                  <div id="info_propietario" class=""></div>
                </div>
                <div class="col-md-4 col-12">
                  <div class="text-muted fw-semibold text-uppercase mb-1">Tipo de examen</div>
                  <div id="info_tipo_examen" class=""></div>
                </div>
              </div>
            </div>
          </div>
          <div class="section-title">Destinatarios</div>
          <label class="form-label fw-bold mt-2 mb-1">Propietario</label>
          <div class="input-group mb-3">
            <span class="input-group-text">
              <input class="form-check-input mt-0" type="checkbox" id="chk_propietario" checked aria-label="Enviar a propietario">
            </span>
            <input type="email" class="form-control" id="correo_propietario" name="correo_propietario" readonly>
          </div>
          <label class="form-label fw-bold mb-1">Clínica</label>
          <div class="input-group mb-3">
            <span class="input-group-text">
              <input class="form-check-input mt-0" type="checkbox" id="chk_clinica" aria-label="Enviar a clínica">
            </span>
            <select id="selectClinica" class="form-select" disabled>
              <option value="">— Selecciona una clínica —</option>
            </select>
          </div>
          <label class="form-label fw-bold mb-1">Correo Adicional</label>
          <div class="input-group">
            <span class="input-group-text">
              <input class="form-check-input mt-0" type="checkbox" id="chk_adicionales" aria-label="Enviar a correo adicional">
            </span>
            <input type="email" id="correo_adicional" class="form-control" placeholder="correo@dominio.cl" disabled>
          </div>
        </form>
      </div>
      <div class="modal-footer border-0">
        <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-success" onclick="enviarCorreoCertificado()">
          <i class="fas fa-paper-plane me-2"></i>Enviar
        </button>
      </div>
    </div>
  </div>
</div>

<script>
function isEmail(v) {
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
}

async function ensureClinicasCache() {
  if (Array.isArray(window.CLINICAS)) return window.CLINICAS;
  const r = await fetch('certificado/envio_email/listado_clinicas.php');
  const j = await r.json();
  window.CLINICAS = j.clinicas || [];
  return window.CLINICAS;
}

function renderClinicasSelect(correoPropietario) {
  const sel = $('#selectClinica');
  const wasChecked = $('#chk_clinica').is(':checked');
  sel.empty();
  sel.append(`<option value="">— Selecciona una clínica —</option>`);

  const prop = (correoPropietario || '').trim().toLowerCase();
  if (Array.isArray(window.CLINICAS)) {
    window.CLINICAS.forEach(c => {
      const correo = (c.correo || '').trim();
      if (!correo) return;
      if (prop && correo.toLowerCase() === prop) return; // evitar duplicar propietario
      sel.append(`<option value="${correo}">${c.nombre_clinica} (${correo})</option>`);
    });
  }
  sel.prop('disabled', !wasChecked);
}

async function abrirModalCorreo(el, certificadoId) {
  const { paciente, propietario, tipo_examen, email } = el.dataset || {};

  $('#correo_certificado_id').val(certificadoId);
  $('#info_paciente').text(paciente || '-');
  $('#info_propietario').text(propietario || '-');
  $('#info_tipo_examen').text(tipo_examen || '-');

  $('#correo_propietario').val('');
  $('#selectClinica').empty().append(`<option value="">— Selecciona una clínica —</option>`);
  $('#correo_adicional').val('').prop('disabled', true);

  $('#chk_propietario').prop('checked', true).prop('disabled', false);
  $('#chk_clinica').prop('checked', false);
  $('#chk_adicionales').prop('checked', false);

  $('#selectClinica').prop('disabled', true);

  const showModal = () => {
    const modal = new bootstrap.Modal(document.getElementById('modalEnviarCorreo'));
    modal.show();
  };

  window.CLINICAS = null;
  const setPropietarioYClinicas = async (correo) => {
    const c = (correo || '').trim();
    $('#correo_propietario').val(c);

    if (!c) {
      $('#chk_propietario').prop('checked', false).prop('disabled', true);
    }

    await ensureClinicasCache();
    renderClinicasSelect(c);
    showModal();
  };

  if (email && email.trim() !== '') {
    await setPropietarioYClinicas(email);
    return;
  }

  $.post('certificado/envio_email/get_email_certificado.php', { id: certificadoId }, async function(res) {
    try {
      const data = JSON.parse(res);
      if (data.status === 'success') {
        await setPropietarioYClinicas(data.correo || '');
      } else {
        await setPropietarioYClinicas('');
        Swal.fire('Aviso', data.message || 'No se encontró el correo del propietario.', 'warning');
      }
    } catch (e) {
      Swal.fire('Error', 'Respuesta inválida del servidor.', 'error');
    }
  });
}

$(document).on('change', '#chk_clinica', function () {
  $('#selectClinica').prop('disabled', !$(this).is(':checked'));
});

$(document).on('change', '#chk_adicionales', function () {
  $('#correo_adicional').prop('disabled', !$(this).is(':checked'));
});

function getDestinatariosSeleccionados() {
  const dest = [];

  if ($('#chk_propietario').is(':checked')) {
    const c = ($('#correo_propietario').val() || '').trim();
    if (c && isEmail(c)) dest.push(c);
  }

  if ($('#chk_clinica').is(':checked')) {
    const clin = ($('#selectClinica').val() || '').trim();
    if (clin && isEmail(clin)) dest.push(clin);
  }

  if ($('#chk_adicionales').is(':checked')) {
    const extra = ($('#correo_adicional').val() || '').trim();
    if (extra && isEmail(extra)) dest.push(extra);
  }

  return [...new Set(dest)];
}

async function enviarCorreoCertificado() {
  const certificado_id = $('#correo_certificado_id').val();
  const destinatarios = getDestinatariosSeleccionados();

  if (!certificado_id) {
    Swal.fire('Error', 'No se encontró el ID del certificado.', 'error');
    return;
  }
  if (!destinatarios.length) {
    Swal.fire('Atención', 'Selecciona al menos un destinatario válido.', 'warning');
    return;
  }

  Swal.fire({
    title: 'Enviando...',
    allowOutsideClick: false,
    didOpen: () => Swal.showLoading()
  });

  $.ajax({
    url: 'certificado/envio_email/send_certificado.php',
    type: 'POST',
    dataType: 'json',
    data: {
      certificado_id: certificado_id,
      destinatarios: destinatarios
    },
    success: function(resp) {
      Swal.close();
      if (resp && resp.status === 'success') {
        Swal.fire('Listo', resp.message || 'Correo enviado correctamente.', 'success');
        // Cierra el modal
        const modalEl = document.getElementById('modalEnviarCorreo');
        const modal = bootstrap.Modal.getInstance(modalEl);
        modal && modal.hide();
      } else {
        Swal.fire('Error', (resp && resp.message) || 'No se pudo enviar el correo.', 'error');
      }
    },
    error: function() {
      Swal.close();
      Swal.fire('Error', 'Error de red o del servidor al enviar.', 'error');
    }
  });
}
</script>
