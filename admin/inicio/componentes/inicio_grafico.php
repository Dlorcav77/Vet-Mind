<?php
// INFORMES ÚLTIMOS 7 DÍAS
$stmt = $mysqli->prepare("
  SELECT DATE(created_at) AS fecha, COUNT(*) AS total
  FROM certificados
  WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
    AND veterinario_id = ?
  GROUP BY DATE(created_at)
  ORDER BY fecha ASC
");
$stmt->bind_param("i", $usuario_id);
$stmt->execute();
$res = $stmt->get_result();

$datosSemana = [];
$fechasSemana = [];

// Generar los últimos 7 días
for ($i = 6; $i >= 0; $i--) {
  $fecha = date("Y-m-d", strtotime("-$i days"));
  $datosSemana[$fecha] = 0;
  $fechasSemana[] = $fecha;
}

// Completar con los datos reales
while ($row = $res->fetch_assoc()) {
  $fecha = $row['fecha'];
  $total = $row['total'];
  if (isset($datosSemana[$fecha])) {
    $datosSemana[$fecha] = $total;
  }
}

// Convertir a arrays de JS-friendly
$dias_es = ['Dom', 'Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb'];
$labelsSemanaJS = json_encode(array_map(function($f) use ($dias_es) {
  $diaNum = date('w', strtotime($f)); // 0 (Domingo) a 6 (Sábado)
  return $dias_es[$diaNum];
}, $fechasSemana));

$dataSemanaJS = json_encode(array_values($datosSemana));
?>
<div class="card mb-4">
  <div class="card-body">
    <h5 class="mb-3"><i class="fas fa-chart-line me-2"></i> Informes por día (última semana)</h5>
    <canvas id="graficoSemana" style="min-height: 50px;" height="80"></canvas>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
  (function () {
    if (document.getElementById('graficoSemana')) {
      inicializarGraficoSemana();
    }
  })();

  function inicializarGraficoSemana() {
    const labelsSemana = <?= $labelsSemanaJS ?>;
    const dataSemana = <?= $dataSemanaJS ?>;

    // console.log("Labels semana:", labelsSemana);
    // console.log("Data semana:", dataSemana);

    const canvas = document.getElementById('graficoSemana');
    if (!canvas) return;

    const ctx = canvas.getContext('2d');
    new Chart(ctx, {
      type: 'line',
      data: {
        labels: labelsSemana,
        datasets: [{
          label: 'Informes',
          data: dataSemana,
          borderColor: '<?= $fila["color_primario"] ?? "#3498db" ?>',
          backgroundColor: 'rgba(52, 152, 219, 0.1)',
          fill: true,
          tension: 0.3,
          pointRadius: 4
        }]
      },
      options: {
        plugins: {
          legend: { display: false }
        },
        scales: {
          y: { beginAtZero: true, precision: 0 }
        }
      }
    });

    // Por si carga lento
    setTimeout(() => {
      window.dispatchEvent(new Event('resize'));
    }, 300);
  }


</script>