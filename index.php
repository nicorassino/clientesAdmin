<?php
require 'includes/header.php';
require 'includes/db.php';

// Preparar la consulta según el rol del usuario
$sql = "SELECT c.*, u.usuario as usuario_asignado FROM clientes c JOIN usuarios u ON c.id_usuario = u.id";
if ($_SESSION['rol'] !== 'admin') {
    $sql .= " WHERE c.id_usuario = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
} else {
    $stmt = $pdo->query($sql);
}
$clientes = $stmt->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Mis Clientes</h1>
    <div>
        <a href="estado_pagos.php" class="btn btn-info">Ver Estado de Pagos</a>
        <a href="cliente_form.php" class="btn btn-primary">Crear Nuevo Cliente</a>
    </div>
</div>

<?php if (isset($_GET['status']) && $_GET['status'] == 'success'): ?>
    <div class="alert alert-success">Cliente guardado correctamente.</div>
<?php endif; ?>
<?php if (isset($_GET['status']) && $_GET['status'] == 'deleted'): ?>
    <div class="alert alert-info">Cliente eliminado correctamente.</div>
<?php endif; ?>

<div class="table-responsive">
    <table class="table table-hover">
        <thead class="table">
            <tr>
                <th>Nombre</th>
                <th>Razón Social</th>
                <th>CUIT</th>
                <th>Email</th>
                <th>Usuario Asignado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($clientes as $cliente): ?>
            <tr onclick="window.location='cliente_pagos.php?id_cliente=<?php echo $cliente['id']; ?>';">
                <td><?php echo htmlspecialchars($cliente['nombre']); ?></td>
                <td><?php echo htmlspecialchars($cliente['razon_social']); ?></td>
                <td><?php echo htmlspecialchars($cliente['cuit']); ?></td>
                <td><?php echo htmlspecialchars($cliente['email']); ?></td>
                <td><?php echo htmlspecialchars($cliente['usuario_asignado']); ?></td>
                <td>
                    <a href="cliente_pagos.php?id_cliente=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-success" title="Ver Pagos"><i class="bi bi-currency-dollar"></i></a>
                    <a href="cliente_form.php?id=<?php echo $cliente['id']; ?>" class="btn btn-sm btn-warning" title="Editar"><i class="bi bi-pencil"></i></a>
                    <a href="#" onclick="event.stopPropagation(); confirmarAccion('cliente_acciones.php?accion=borrar&id=<?php echo $cliente['id']; ?>', '¿Estás seguro de que deseas eliminar este cliente? Se borrarán también todos sus pagos asociados.')" class="btn btn-sm btn-danger" title="Borrar"><i class="bi bi-trash"></i></a>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php if (empty($clientes)): ?>
            <tr>
                <td colspan="6" class="text-center">No hay clientes para mostrar.</td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require 'includes/footer.php'; ?>
