<?php
session_start();
require 'includes/db.php';

// Verificar que el usuario está logueado
if (!isset($_SESSION['user_id'])) {
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
        $id_usuario = $_POST['id_usuario'];

        if (empty($id)) { // Crear nuevo
            $sql = "INSERT INTO clientes (nombre, razon_social, cuit, email, servicios, id_usuario) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $razon_social, $cuit, $email, $servicios, $id_usuario]);
        } else { // Actualizar
            $sql = "UPDATE clientes SET nombre = ?, razon_social = ?, cuit = ?, email = ?, servicios = ?, id_usuario = ? WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$nombre, $razon_social, $cuit, $email, $servicios, $id_usuario, $id]);
        }
        header('Location: index.php?status=success');
        break;

    case 'borrar':
        $id = $_GET['id'];
        // Opcional: Verificar si el usuario tiene permiso para borrar este cliente
        $sql = "DELETE FROM clientes WHERE id = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        header('Location: index.php?status=deleted');
        break;

    default:
        header('Location: index.php');
        break;
}
exit();
?>