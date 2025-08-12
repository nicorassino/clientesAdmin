<?php
session_start();

// Proteger paginas, excepto el login
$pagina_actual = basename($_SERVER['PHP_SELF']);
if (!isset($_SESSION['user_id']) && $pagina_actual != 'login.php') {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClientesAdmin</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
<link href="https://cdn.jsdelivr.net/npm/bootswatch@5.3.0/dist/litera/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <style>
        /* Pequeños ajustes para mejorar la visualización */
        .table-hover tbody tr { cursor: pointer; }
        .form-control-sm.aumento { max-width: 80px; display: inline-block; }
    </style>
</head>
<body>

<?php if (isset($_SESSION['user_id'])): ?>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">ClientesAdmin</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link active" aria-current="page" href="index.php">Clientes</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="estado_pagos.php">Estado de Pagos</a>
                </li>
            </ul>
            <ul class="navbar-nav ms-auto">
                <li class="nav-item">
                     <span class="navbar-text me-3">
                        Usuario: <strong><?php echo htmlspecialchars($_SESSION['username']); ?></strong>
                    </span>
                </li>
                <li class="nav-item">
                    <a class="btn btn-outline-light" href="logout.php">Cerrar Sesión</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
<?php endif; ?>

<main class="container mt-4">