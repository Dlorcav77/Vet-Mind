<?php
// admin/certificado/pacientes/paciente_manual.php
$manualDataInicial = $manualDataInicial ?? [];

function manual_value(array $data, string $key): string {
    return htmlspecialchars((string)($data[$key] ?? ''), ENT_QUOTES, 'UTF-8');
}
?>
<style>
    #paciente-manual .campo-requerido-manual.is-invalid,
    #paciente-manual .form-control.is-invalid,
    #paciente-manual .form-select.is-invalid,
    #paciente-manual .select2-selection.is-invalid {
        border-color: #dc3545 !important;
    }

    #paciente-manual .invalid-feedback-manual {
        display: none;
        width: 100%;
        margin-top: .25rem;
        font-size: .875em;
        color: #dc3545;
    }

    #paciente-manual .invalid-feedback-manual.d-block {
        display: block;
    }
</style>

<div id="paciente-manual" class="my-3 border rounded p-3 bg-light" style="<?= $toggleManualInitial ? '' : 'display:none;' ?>">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="fw-bold mb-0">Ingreso Manual</h5>

        <div class="form-check form-switch mb-0">
            <input
                class="form-check-input"
                type="checkbox"
                id="guardarMascota"
                name="guardar_mascota"
                value="1"
                <?= $guardarInitial ? 'checked' : '' ?>
                data-initial="<?= $guardarInitial ? '1' : '0' ?>"
            >
            <label class="form-check-label fw-semibold ms-2" for="guardarMascota">
                Guardar
            </label>
        </div>
    </div>

    <div class="row">
        <?php foreach ($camposCatalogo as $campo): ?>
            <?php
                if (in_array($campo['campo'], $camposGenerales, true)) {
                    continue;
                }

                $campoKey = $campo['campo'];
                $campoLabel = $campo['etiqueta'];
                $visibleInicial = in_array($campoKey, $camposVisiblesActuales, true);
                $esObligatorioManual = in_array($campoKey, ['paciente', 'propietario'], true);
                $inputId = 'manual_' . $campoKey;
                $valorInicial = (string)($manualDataInicial[$campoKey] ?? '');
            ?>
            <div
                class="col-md-4 mb-3 campo-manual-item"
                data-campo="<?= htmlspecialchars($campoKey) ?>"
                style="<?= $visibleInicial ? '' : 'display:none;' ?>"
            >
                <label class="form-label fw-semibold" for="<?= htmlspecialchars($inputId) ?>">
                    <?= htmlspecialchars($campoLabel) ?>
                    <?php if ($esObligatorioManual): ?>
                        <span class="text-danger">*</span>
                    <?php endif; ?>
                </label>

                <?php if ($campoKey === 'raza'): ?>
                    <select
                        id="manual_raza_select"
                        class="select2 form-select"
                        style="width:100%;"
                        data-current-text="<?= manual_value($manualDataInicial, 'raza') ?>"
                    >
                        <option value="">Seleccione raza...</option>
                        <?php lisRazas(); ?>
                    </select>
                    <input
                        type="hidden"
                        id="manual_raza"
                        name="manual_raza"
                        value="<?= manual_value($manualDataInicial, 'raza') ?>"
                    >

                <?php elseif ($campoKey === 'sexo'): ?>
                    <select class="form-select" id="manual_sexo" name="manual_sexo">
                        <option value="">Seleccione...</option>
                        <?php foreach ($sexos_manual as $val => $label): ?>
                            <option value="<?= htmlspecialchars($val) ?>" <?= $valorInicial === $val ? 'selected' : '' ?>>
                                <?= htmlspecialchars($label) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                <?php elseif ($campoKey === 'fecha_nacimiento'): ?>
                    <input
                        type="date"
                        class="form-control"
                        name="manual_fecha_nacimiento"
                        id="manual_fecha_nacimiento"
                        value="<?= manual_value($manualDataInicial, 'fecha_nacimiento') ?>"
                    >

                <?php else: ?>
                    <input
                        type="text"
                        class="form-control <?= $esObligatorioManual ? 'campo-requerido-manual' : '' ?>"
                        name="manual_<?= htmlspecialchars($campoKey) ?>"
                        id="manual_<?= htmlspecialchars($campoKey) ?>"
                        data-required-manual="<?= $esObligatorioManual ? '1' : '0' ?>"
                        data-label="<?= htmlspecialchars($campoLabel) ?>"
                        autocomplete="off"
                        value="<?= manual_value($manualDataInicial, $campoKey) ?>"
                    >
                    <?php if ($esObligatorioManual): ?>
                        <div class="invalid-feedback-manual" id="feedback_<?= htmlspecialchars($inputId) ?>">
                            <?= htmlspecialchars($campoLabel) ?> es obligatorio.
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<script>
(function () {
    if (window.__PACIENTE_MANUAL_VALIDATION_LOADED__) {
        return;
    }
    window.__PACIENTE_MANUAL_VALIDATION_LOADED__ = true;

    function getManualContainer() {
        return document.getElementById('paciente-manual');
    }

    function isManualVisible() {
        const box = getManualContainer();
        if (!box) return false;
        return box.offsetParent !== null;
    }

    function isManualModeActive() {
        const toggle = document.getElementById('toggle_manual');
        if (toggle) {
            return toggle.checked || toggle.value === '1';
        }
        return isManualVisible();
    }

    function setFieldValidState(input, isValid) {
        if (!input) return;

        const feedbackId = 'feedback_' + input.id;
        const feedback = document.getElementById(feedbackId);

        if (isValid) {
            input.classList.remove('is-invalid');
            if (feedback) {
                feedback.classList.remove('d-block');
            }
        } else {
            input.classList.add('is-invalid');
            if (feedback) {
                feedback.classList.add('d-block');
            }
        }
    }

    function validateManualField(input) {
        if (!input) return true;

        const isRequired = input.dataset.requiredManual === '1';
        if (!isRequired) return true;

        if (!isManualModeActive() || !isManualVisible()) {
            setFieldValidState(input, true);
            return true;
        }

        const value = (input.value || '').trim();
        const ok = value !== '';
        setFieldValidState(input, ok);
        return ok;
    }

    function validateManualRequiredFields() {
        const container = getManualContainer();
        if (!container) return true;

        const fields = container.querySelectorAll('.campo-requerido-manual[data-required-manual="1"]');
        let allOk = true;

        fields.forEach(function (input) {
            const ok = validateManualField(input);
            if (!ok) {
                allOk = false;
            }
        });

        return allOk;
    }

    document.addEventListener('input', function (e) {
        if (e.target && e.target.classList.contains('campo-requerido-manual')) {
            validateManualField(e.target);
        }
    });

    document.addEventListener('blur', function (e) {
        if (e.target && e.target.classList.contains('campo-requerido-manual')) {
            validateManualField(e.target);
        }
    }, true);

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!(form instanceof HTMLFormElement)) return;

        const container = getManualContainer();
        if (!container || !container.contains(form) && !form.contains(container)) {
            const hasManualFields = form.querySelector('#paciente-manual');
            if (!hasManualFields) return;
        }

        if (!validateManualRequiredFields()) {
            e.preventDefault();
            e.stopPropagation();

            const firstInvalid = document.querySelector('#paciente-manual .campo-requerido-manual.is-invalid');
            if (firstInvalid) {
                firstInvalid.focus();
            }

            if (window.Swal) {
                Swal.fire('Faltan datos', 'Debes completar Paciente y Propietario en ingreso manual.', 'warning');
            }
        }
    }, true);

    window.validarPacienteManualAntesDeGuardar = validateManualRequiredFields;
})();
</script>