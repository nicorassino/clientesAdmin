<?php
require 'includes/header.php';
require 'includes/db.php';

$cliente = [
    'id' => '', 'nombre' => '', 'razon_social' => '', 'cuit' => '', 'email' => '', 'servicios' => '', 'id_usuario' => ''
];
$titulo = "Crear Nuevo Cliente";

if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();
    if (!$cliente) {
        // Manejar el caso de cliente no encontrado
        die("Cliente no encontrado.");
    }
    $titulo = "Editar Cliente: " . htmlspecialchars($cliente['nombre']);
}

// Obtener usuarios que no son admin
$stmt_usuarios = $pdo->query("SELECT id, usuario FROM usuarios WHERE rol != 'admin'");
$usuarios = $stmt_usuarios->fetchAll();
?>

<h1><?php echo $titulo; ?></h1>

<form action="cliente_acciones.php" method="post" class="row g-3">
    <input type="hidden" name="id" value="<?php echo $cliente['id']; ?>">
    <input type="hidden" name="accion" value="guardar">

    <div class="col-md-6">
        <label for="nombre" class="form-label">Nombre de Fantasía</label>
        <input type="text" class="form-control" id="nombre" name="nombre" value="<?php echo htmlspecialchars($cliente['nombre']); ?>" required>
    </div>
    <div class="col-md-6">
        <label for="razon_social" class="form-label">Razón Social</label>
        <input type="text" class="form-control" id="razon_social" name="razon_social" value="<?php echo htmlspecialchars($cliente['razon_social']); ?>">
    </div>
    <div class="col-md-6">
        <label for="cuit" class="form-label">CUIT</label>
        <input type="text" class="form-control" id="cuit" name="cuit" value="<?php echo htmlspecialchars($cliente['cuit']); ?>">
    </div>
    <div class="col-md-6">
        <label for="email" class="form-label">Email</label>
        <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($cliente['email']); ?>">
    </div>
    <div class="col-12">
        <label for="servicios" class="form-label">Servicios Contratados</label>
        <textarea class="form-control" id="servicios" name="servicios" rows="3"><?php echo htmlspecialchars($cliente['servicios']); ?></textarea>
    </div>
    <div class="col-md-6">
        <label for="id_usuario" class="form-label">Usuario Asignado</label>
        <select id="id_usuario" name="id_usuario" class="form-select" required>
            <option value="">Seleccionar...</option>
            <?php foreach ($usuarios as $usuario): ?>
            <option value="<?php echo $usuario['id']; ?>" <?php echo ($cliente['id_usuario'] == $usuario['id']) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($usuario['usuario']); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="col-12">
        <button type="submit" class="btn btn-primary">Guardar Cliente</button>
        <a href="index.php" class="btn btn-secondary">Cancelar</a>
    </div>
</form>

<?php require 'includes/footer.php'; ?>