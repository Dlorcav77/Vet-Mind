<?php

require_once(
    $_SERVER['DOCUMENT_ROOT']
    . '/funciones/session/csrf.php'
);

$csrfPanel = tokenCsrf();

?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta
      name="csrf-token"
      content="<?= htmlspecialchars($csrfPanel, ENT_QUOTES, 'UTF-8') ?>"
  >

  <link
    rel="icon"
    type="image/svg+xml"
    href="../assets/img/branding/logo-vetmind.svg?v=1"
  >
  <!-- jQuery (si tu sistema necesita $.ajax en head) -->
  <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">

  <!-- DataTables CSS -->
  <link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
  <!-- jQuery UI CSS -->
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <!-- Select2 CSS -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/select2/4.1.0-rc.0/css/select2.min.css">
  
  <link href="https://cdn.datatables.net/buttons/2.4.1/css/buttons.bootstrap5.min.css" rel="stylesheet">
  <link href="https://cdn.datatables.net/responsive/2.5.0/css/responsive.bootstrap5.min.css" rel="stylesheet">

  <!-- FontAwesome -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

  <!-- App CSS personalizado -->
  <link href="../assets/css/app.css" rel="stylesheet">
  <link href="../assets/css/global.css?v=9" rel="stylesheet">
  <link
    href="../assets/css/branding.css?v=11"
    rel="stylesheet"
  >

  <!-- Favicon -->

  <title>VetMind</title>

