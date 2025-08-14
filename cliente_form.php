<?php
require 'includes/header.php';
require 'includes/db.php';

// Inicialización de variables
$cliente = [
    'id' => '', 'nombre' => '', 'razon_social' => '', 'cuit' => '', 'email' => '', 'servicios' => '', 'datos_bancarios' => '', 'id_usuario' => ''
];
$titulo = "Crear Nuevo Cliente";

// Si se edita un cliente existente, obtener sus datos
if (isset($_GET['id'])) {
    $id = $_GET['id'];
    $stmt = $pdo->prepare("SELECT * FROM clientes WHERE id = ?");
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();
    if (!$cliente) {
        die("Cliente no encontrado.");
    }
    $titulo = "Editar Cliente: " . htmlspecialchars($cliente['nombre']);
}

// Obtener la lista de clientes que SÍ tienen datos bancarios para el selector
$stmt_clientes_con_banco = $pdo->query("SELECT id, nombre, razon_social FROM clientes WHERE datos_bancarios IS NOT NULL AND datos_bancarios != '' ORDER BY nombre");
$clientes_con_banco = $stmt_clientes_con_banco->fetchAll();

// Obtener la lista de usuarios (no admin) para el selector de asignación
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
    
    <!-- CAMPO DE DATOS BANCARIOS con la nueva funcionalidad -->
    <div class="col-12">
        <label for="datos_bancarios" class="form-label">Datos Bancarios (para depósito/transferencia)</label>
        <textarea class="form-control" id="datos_bancarios" name="datos_bancarios" rows="5" placeholder="Ej:&#10;Banco: Galicia&#10;CBU: 123...&#10;Alias: mi.alias.aqui"><?php echo htmlspecialchars($cliente['datos_bancarios']); ?></textarea>
        
        <!-- MÓDULO PARA COPIAR DATOS (se imprime solo si hay clientes de donde copiar) -->
        <?php if (!empty($clientes_con_banco)): ?>
        <div class="mt-2" id="copy-container-wrapper">
            <a href="#" id="show-copy-btn" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-clipboard-plus"></i> Copiar datos de otro cliente...
            </a>
            <div id="copy-container" class="d-none mt-2 p-3 border rounded bg-light">
                <div class="input-group">
                    <select id="client-select-source" class="form-select">
                        <option value="">Selecciona un cliente...</option>
                        <?php foreach ($clientes_con_banco as $cli): ?>
                            <?php // No mostrar el cliente actual en la lista de copia
                                if (isset($cliente['id']) && $cli['id'] == $cliente['id']) continue; 
                            ?>
                            <option value="<?php echo $cli['id']; ?>">
                                <?php echo htmlspecialchars($cli['nombre']) . ' (' . htmlspecialchars($cli['razon_social']) . ')'; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <!-- FIN MÓDULO -->

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

<!-- SCRIPT CORREGIDO PARA LA LÓGICA DE COPIADO -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Definimos las constantes de los elementos del DOM
    const showCopyBtn = document.getElementById('show-copy-btn');
    const copyContainer = document.getElementById('copy-container');
    const clientSelect = document.getElementById('client-select-source');
    const targetTextarea = document.getElementById('datos_bancarios');
    const copyContainerWrapper = document.getElementById('copy-container-wrapper');

    // *** FIX: Función robusta que primero verifica si el elemento existe ***
    const checkTextarea = () => {
        // Solo ejecuta la lógica si el contenedor de copia existe en la página
        if (copyContainerWrapper) { 
            if (targetTextarea.value.trim() === '') {
                copyContainerWrapper.classList.remove('d-none');
            } else {
                copyContainerWrapper.classList.add('d-none');
            }
        }
    };
    
    // Asignamos los eventos solo si los elementos correspondientes existen
    if (showCopyBtn) {
        showCopyBtn.addEventListener('click', function(e) {
            e.preventDefault();
            if (copyContainer) {
                copyContainer.classList.toggle('d-none');
            }
        });
    }

    if (clientSelect) {
        clientSelect.addEventListener('change', async function() {
            const clientId = this.value;
            if (!clientId) return;

            try {
                const response = await fetch(`cliente_acciones.php?accion=get_datos_bancarios&id_cliente=${clientId}`);
                if (!response.ok) {
                    throw new Error('Error en la respuesta del servidor.');
                }
                const data = await response.json();
                
                if (data.datos_bancarios && targetTextarea) {
                    targetTextarea.value = data.datos_bancarios;
                    if (copyContainer) copyContainer.classList.add('d-none');
                    this.value = ''; // Resetear el select
                    checkTextarea(); // Volver a chequear
                }

            } catch (error) {
                console.error('Error al obtener los datos:', error);
                alert('No se pudieron obtener los datos del cliente seleccionado.');
            }
        });
    }
    
    // El evento de input en el textarea principal siempre es seguro
    if (targetTextarea) {
        targetTextarea.addEventListener('input', checkTextarea);
        // Comprobar el estado inicial al cargar la página
        checkTextarea();
    }
});
</script>


<?php require 'includes/footer.php'; ?>