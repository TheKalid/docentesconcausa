<?php
// guardar_contexto.php
session_start();
require_once 'conexion.php';
header('Content-Type: application/json');

// Verificar que sea un usuario válido
if (!isset($_SESSION['usuario_id'])) {
    echo json_encode(['success' => false, 'error' => 'No autorizado']);
    exit;
}

// Recibir el texto desde JavaScript
$data = json_decode(file_get_contents('php://input'), true);
$contexto = $data['contexto'] ?? '';
$userId = $_SESSION['usuario_id'];

// Actualizar la base de datos
$stmt = $conexion->prepare("UPDATE usuarios SET contexto_guardado = ? WHERE id = ?");
$stmt->bind_param("si", $contexto, $userId);

if ($stmt->execute()) {
    echo json_encode(['success' => true]);
} else {
    echo json_encode(['success' => false, 'error' => 'Error al guardar en la BD']);
}

$stmt->close();
?>