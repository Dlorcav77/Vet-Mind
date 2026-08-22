<?php
// admin/certificado/pacientes/paciente.php
$camposCatalogo = $campos_permitidos_catalogo ?? [];
$camposVisiblesActuales = $campos_visibles_actuales ?? [];

$camposGenerales = ['m_solicitante', 'recinto', 'antecedentes'];

$sexos_manual = [
    'Macho' => 'Macho',
    'Macho Castrado' => 'Macho Castrado',
    'Hembra' => 'Hembra',
    'Hembra Esterilizada' => 'Hembra Esterilizada',
];

$manualDataInicial = [];
if (!empty($fila['manual_data'])) {
    $tmpManual = json_decode((string)$fila['manual_data'], true);
    if (is_array($tmpManual)) {
        $manualDataInicial = $tmpManual;
    }
}

$toggleManualInitial = !empty($toggle_manual_inicial);
$isModificarPaciente = isset($action) && $action === 'modificar';
$guardarInitial = ($isModificarPaciente && !empty($fila['manual_data'])) ? 0 : 1;

$pacienteSeleccionadoTexto = '';
if (!empty($fila['paciente_label'])) {
    $pacienteSeleccionadoTexto = $fila['paciente_label'];
} elseif (!empty($fila['paciente_id'])) {
    $pacienteSeleccionadoTexto =
        ($fila['paciente'] ?? '') .
        (isset($fila['especie']) ? ', ' . $fila['especie'] : '') .
        (isset($fila['raza']) ? ', ' . $fila['raza'] : '') .
        (isset($fila['propietario']) ? ' - Tutor: ' . $fila['propietario'] : '');
}
?>
<link rel="stylesheet" href="certificado/pacientes/css/paciente.css?v=4">
<div class="row g-2 mb-3">
    <div class="col-md-9">
        <span class="form-label fw-bold">Datos del Paciente</span>

        <div class="d-flex mt-2">
            <div class="input-group flex-grow-1" id="pacienteSeleccion">
                <input
                    type="text"
                    class="form-control"
                    id="paciente_seleccionado"
                    placeholder="Seleccione un paciente..."
                    readonly
                    value="<?= htmlspecialchars($pacienteSeleccionadoTexto) ?>"
                >
                <button type="button" class="btn btn-outline-primary">
                    <i class="fas fa-search"></i> Buscar Paciente
                </button>
            </div>

            <div class="ms-2 align-self-center">
                <input
                    type="checkbox"
                    class="btn-check"
                    id="toggle_manual"
                    name="toggle_manual"
                    value="1"
                    autocomplete="off"
                    <?= $toggleManualInitial ? 'checked' : '' ?>
                >
                <label class="btn btn-outline-secondary d-flex align-items-center gap-2" for="toggle_manual" style="min-width: 90px;">
                    <i class="fas fa-keyboard"></i>
                    <span>Manual</span>
                </label>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <label for="fecha_examen" class="form-label fw-bold">Fecha del Examen</label>
        <input
            type="date"
            class="form-control"
            name="fecha_examen"
            id="fecha_examen"
            value="<?= htmlspecialchars($fila['fecha_examen'] ?? '') ?>"
            required
        >
    </div>

    <input type="hidden" name="paciente_id" id="paciente_id" value="<?= htmlspecialchars($fila['paciente_id'] ?? '') ?>">
</div>

<?php include __DIR__ . '/paciente_manual.php'; ?>
<?php include __DIR__ . '/paciente_busqueda.php'; ?>

<script>
window.CERT_CAMPOS_VISIBLES = <?= json_encode(array_values($camposVisiblesActuales), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.CERT_CAMPOS_GENERALES = ['antecedentes', 'm_solicitante', 'recinto'];
window.MANUAL_DATA = <?php
    $md = $fila['manual_data'] ?? null;
    if ($md) {
        $arr = json_decode($md, true);
        echo json_encode($arr ?? null, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    } else {
        echo 'null';
    }
?>;
</script>
<script src="certificado/pacientes/js/paciente.js?v=14"></script>