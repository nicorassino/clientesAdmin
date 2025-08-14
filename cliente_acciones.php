<?php
session_start();
require 'includes/db.php';

if (!isset($_SESSION['user_id'])) {
    if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
        http_response_code(401);
        echo json_encode(['error' => 'No autorizado']);
        exit();
    }
    header('Location: login.php');
    exit();
}

$accion = $_REQUEST['accion'] ?? '';

switch ($accion) {
    case 'guardar':
        $id = $_POST['id'];
        $nombre = $_POST['nombre'];
        $razon_social = $_POST['razon_social'];
        $cuit = $_POST['cuit'];
        $email = $_POST['email'];
        $servicios = $_POST['servicios'];
        $datos_bancarios = $_POST['datos_bancarios']; // <-- Obtenemos el nuevo campo
        $id_usuario = $_POST['id_usuario'];

        if (empty($id)) { // Crear nuevo
            // *** FIX: Añadida la columna 'datos_bancarios' y su placeholder '?' ***
            $sql = "INSERT INTO clientes (nombre, razon_social, cuit, email, servicios, datos_bancarios, id_usuario) VALUES (?, ?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            // *** FIX: Añadida la variable $datos_bancarios al array de execute() ***
            $stmt->execute([$nombre, $razon_social, $cuit, $email, $servicios, $datos_bancarios, $id_usuario]);
        } else { // Actualizar
            // *** FIX: Añadido 'datos_bancarios = ?' a la sentencia UPDATE ***
            $sql = "UPDATE clientes SET nombre = ?, razon_social = ?, cuit = ?, email = ?, servicios = ?, datos_bancarios = ?, id_usuario = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            // *** FIX: Añadida la variable $datos_bancarios al array de execute() ***
            $stmt->execute([$nombre, $razon_social, $cuit, $email, $servicios, $datos_bancarios, $id_usuario, $id]);
        }
        header('Location: index.php?status=success');
        break;

    case 'borrar':
        $id = $_GET['id'];
        $sql = "DELETE FROM clientes WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        header('Location: index.php?status=deleted');
        break;

    // --- NUEVA ACCIÓN PARA EL AUTOCOMPLETADO ---
    case 'get_datos_bancarios':
        header('Content-Type: application/json');
        if (!isset($_GET['id_cliente']) || !is_numeric($_GET['id_cliente'])) {
            echo json_encode(['error' => 'ID de cliente inválido']);
            exit();
        }

        $id_cliente = $_GET['id_cliente'];
        $stmt = $pdo->prepare("SELECT datos_bancarios FROM clientes WHERE id = ?");
        $stmt->execute([$id_cliente]);
        $result = $stmt->fetch();

        echo json_encode(['datos_bancarios' => $result['datos_bancarios'] ?? '']);
        break;


    default:
        header('Location: index.php');
        break;
}
exit();
?>