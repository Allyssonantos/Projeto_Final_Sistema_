<?php
// api/check_session.php
session_start(); // ESSENCIAL

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true"); // ESSENCIAL
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

if (isset($_SESSION['usuario_id'])) {
    // !! LÓGICA ADMIN INSEGURA - SUBSTITUA !!
    $is_admin = (isset($_SESSION['usuario_email']) && $_SESSION['usuario_email'] === 'allyssonsantos487@gmail.com');

    echo json_encode([
        "logado" => true,
        "usuario_id" => $_SESSION['usuario_id'],
        "usuario_nome" => $_SESSION['usuario_nome'] ?? 'Usuário',
        "is_admin" => $is_admin
    ]);
} else {
    echo json_encode(["logado" => false]);
}
?>