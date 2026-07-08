<?php
//admin/control_ia/control_ia.php
###########################################
require_once("../config.php");
credenciales('control_ia', 'listar');
###########################################

$mysqli = conn();
global $acceso_aplicaciones;

// Una fila por certificado (agrupa informe + revision).
// Las filas sin certificado_id van sueltas al final, marcadas "sin informe".
$sql = "
SELECT
    r.id,
    r.rid,
    r.tipo,
    r.certificado_id,
    r.plantilla_id,
    r.provider,
    r.model,
    r.total_tokens,
    r.cost_usd,
    r.created_at
FROM ia_requests r
ORDER BY r.created_at DESC
";

$stmt = $mysqli->prepare($sql);
$stmt->execute();
$res = $stmt->get_result();

// Agrupar: con certificado -> por certificado_id; sin certificado -> fila suelta por request.
$grupos = [];      // certificado_id => ['informe'=>row, 'revision'=>row, 'cost'=>float, ...]
$sueltos = [];     // requests sin certificado_id

while ($f = $res->fetch_assoc()) {
    if ($f['certificado_id'] === null) {
        $sueltos[] = $f;
        continue;
    }
    $cid = (int)$f['certificado_id'];
    if (!isset($grupos[$cid])) {
        $grupos[$cid] = [
            'certificado_id' => $cid,
            'informe'        => null,
            'revision'       => null,
            'cost'           => 0.0,
            'tokens'         => 0,
            'created_at'     => $f['created_at'],
            'plantilla_id'   => $f['plantilla_id'],
        ];
    }
    $grupos[$cid][$f['tipo']] = $f;
    $grupos[$cid]['cost']   += (float)$f['cost_usd'];
    $grupos[$cid]['tokens'] += (int)$f['total_tokens'];
}

$puedeModificar = in_array('modificar', $acceso_aplicaciones['control_ia'] ?? []);
$puedeEliminar  = in_array('eliminar',  $acceso_aplicaciones['control_ia'] ?? []);
$puedeIngresar  = in_array('ingresar',  $acceso_aplicaciones['control_ia'] ?? []);

function fmtUsd($v){ return '$' . number_format((float)$v, 6, '.', ','); }
?>
<style>
    table.dataTable thead th, table.dataTable tfoot th { font-family: Arial, sans-serif; font-size: 14px; }
    table.dataTable tbody td { font-family: Arial, sans-serif; font-size: 12px; }
    .dataTables_wrapper .dt-buttons { float: none; text-align: center; }
    .badge-tipo { font-size: 11px; padding: 3px 8px; border-radius: 6px; }
    .badge-informe  { background:#e0f2fe; color:#075985; }
    .badge-revision { background:#fef3c7; color:#92400e; }
    .badge-sininf   { background:#fee2e2; color:#991b1b; }
    .badge-ok       { background:#dcfce7; color:#166534; }

    .mpill {
      display:inline-flex; align-items:center; gap:6px;
      padding:5px 11px; border-radius:999px;
      background:#fff; border:1px solid #e2e8f0;
      font-size:12px; line-height:1;
    }
    .mpill i { font-size:11px; opacity:.85; }
    .mpill-k { color:#94a3b8; font-size:10px; text-transform:uppercase; letter-spacing:.03em; }
    .mpill-v { font-weight:700; color:#1e293b; }
    .mpill-inf  { border-color:#bae6fd; background:#f0f9ff; }
    .mpill-inf i { color:#0284c7; }
    .mpill-cons { border-color:#ddd6fe; background:#f5f3ff; }
    .mpill-cons i { color:#7c3aed; }
    .mpill-rev  { border-color:#fde68a; background:#fffbeb; }
    .mpill-rev i { color:#d97706; }
    .mpill-tok  { border-color:#a7f3d0; background:#ecfdf5; }
    .mpill-tok i { color:#059669; }
    .mpill-cost { border-color:#fecaca; background:#fef2f2; }
    .mpill-cost i { color:#dc2626; }

    .mpill-prov { border-color:#e2e8f0; background:#f8fafc; }
    .mpill-prov i { color:#475569; }
</style>

<div id="control_ia" data-page-id="control_ia">
  <h1 class="h3 mb-3"><strong>Control IA</strong></h1>
  <div class="card">
    <div class="card-header">
      <div class="col-xl-12 col-xxl-12 d-flex">
        <div class="w-100">
          <div class="row mb-1 align-items-center">
            <div class="col-md-3 d-flex">
              <button type="button" class="btn btn-outline-secondary align-self-start" id="btnVerPrecios">
                <i style="width:18px;height:18px;" data-feather="dollar-sign"></i> Precios
              </button>
            </div>
            <div class="col-md-9">
              <div class="card border-0 shadow-sm" style="background:#f8fafc">
                <div class="card-body py-2">
                  <div class="d-flex flex-wrap align-items-center justify-content-end gap-2">
                    <div class="btn-group btn-group-sm" role="group" id="metricasRango">
                      <button type="button" class="btn btn-outline-info" data-rango="hoy">Hoy</button>
                      <button type="button" class="btn btn-outline-info" data-rango="semana">Semana</button>
                      <button type="button" class="btn btn-outline-info" data-rango="mes">Mes</button>
                      <button type="button" class="btn btn-info active" data-rango="todo">Todo</button>
                    </div>
                    <span class="mpill mpill-inf">
                      <i class="fas fa-file-medical"></i>
                      <span class="mpill-k">Informes</span><span id="m_informes" class="mpill-v">-</span>
                    </span>
                    <span class="mpill mpill-cons">
                      <i class="fas fa-paper-plane"></i>
                      <span class="mpill-k">Consultas</span><span id="m_consultas" class="mpill-v">-</span>
                    </span>
                    <span class="mpill mpill-rev">
                      <i class="fas fa-search"></i>
                      <span class="mpill-k">Revisiones</span><span id="m_revisiones" class="mpill-v">-</span>
                    </span>
                    <span class="mpill mpill-tok">
                      <i class="fas fa-coins"></i>
                      <span class="mpill-k">Tokens</span><span id="m_tokens" class="mpill-v">-</span>
                    </span>
                    <span id="m_costo_prov" class="d-inline-flex flex-wrap gap-2"></span>
                    <span class="mpill mpill-cost">
                      <i class="fas fa-dollar-sign"></i>
                      <span class="mpill-k">Costo total</span><span id="m_costo" class="mpill-v">-</span>
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="table-responsive">
            <table id="tablaControlIa" class="table table-striped table-bordered dt-responsive nowrap datatable" style="width:100%">
              <thead>
                <tr>
                  <th width="40">N</th>
                  <th>Certificado</th>
                  <th>Estado</th>
                  <th>Plantilla</th>
                  <th>Proveedor(es)</th>
                  <th>Tokens</th>
                  <th>Costo (USD)</th>
                  <th>Fecha</th>
                  <th>Acciones</th>
                </tr>
              </thead>
              <tbody>
              <?php $i = 1; ?>

              <?php foreach ($grupos as $g):
                    $inf = $g['informe'];
                    $rev = $g['revision'];
                    $provs = [];
                    if ($inf) $provs[] = $inf['provider'].' ('.$inf['model'].')';
                    if ($rev) $provs[] = $rev['provider'].' ('.$rev['model'].')';
              ?>
                <tr>
                  <td><?= $i ?></td>
                  <td><strong>#<?= (int)$g['certificado_id'] ?></strong></td>
                  <td>
                    <span class="badge-tipo badge-ok">Con informe</span>
                    <?php if(!$rev): ?><span class="badge-tipo badge-sininf">sin revisión</span><?php endif; ?>
                  </td>
                  <td><?= $g['plantilla_id'] !== null ? (int)$g['plantilla_id'] : '-' ?></td>
                  <td><?= htmlspecialchars(implode(' + ', $provs)) ?></td>
                  <td><?= number_format((int)$g['tokens'], 0, '.', ',') ?></td>
                  <td><?= fmtUsd($g['cost']) ?></td>
                  <td><?= htmlspecialchars($g['created_at']) ?></td>
                  <td align="center">
                    <button class="btn btn-sm btn-outline-info btn-ver-detalle" title="Ver"
                        data-certificado="<?= (int)$g['certificado_id'] ?>"
                        data-informe-rid="<?= $inf ? htmlspecialchars($inf['rid']) : '' ?>"
                        data-revision-rid="<?= $rev ? htmlspecialchars($rev['rid']) : '' ?>">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($puedeEliminar): ?>
                    <button class="btn btn-sm btn-outline-danger btn-eliminar-grupo" title="Eliminar"
                        data-certificado="<?= (int)$g['certificado_id'] ?>">
                        <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                  </td>
                  
                </tr>
              <?php $i++; endforeach; ?>

              <?php foreach ($sueltos as $s): ?>
                <tr>
                  <td><?= $i ?></td>
                  <td><span class="text-muted">—</span></td>
                  <td>
                    <span class="badge-tipo badge-sininf">Sin informe</span>
                    <span class="badge-tipo <?= $s['tipo']==='informe'?'badge-informe':'badge-revision' ?>"><?= htmlspecialchars($s['tipo']) ?></span>
                  </td>
                  <td><?= $s['plantilla_id'] !== null ? (int)$s['plantilla_id'] : '-' ?></td>
                  <td><?= htmlspecialchars($s['provider'].' ('.$s['model'].')') ?></td>
                  <td><?= number_format((int)$s['total_tokens'], 0, '.', ',') ?></td>
                  <td><?= fmtUsd($s['cost_usd']) ?></td>
                  <td><?= htmlspecialchars($s['created_at']) ?></td>
                  <td align="center">
                    <button class="btn btn-sm btn-outline-info btn-ver-suelto" title="Ver" data-id="<?= (int)$s['id'] ?>">
                      <i class="fas fa-eye"></i>
                    </button>
                    <?php if ($puedeEliminar): ?>
                    <button class="btn btn-sm btn-outline-danger btn-eliminar-suelto" title="Eliminar" data-id="<?= (int)$s['id'] ?>">
                      <i class="fas fa-trash"></i>
                    </button>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php $i++; endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php require __DIR__ . '/partials/modal_detalle.php'; ?>
<?php require __DIR__ . '/partials/modal_precios.php'; ?>

<script src="control_ia/js/control_ia.js?v=1"></script>