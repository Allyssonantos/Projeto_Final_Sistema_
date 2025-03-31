<?php
session_start(); // Sempre no topo

header("Access-Control-Allow-Origin: *"); // Substitua * pelo seu domínio frontend em produção
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true"); // Essencial para sessões/cookies
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if (isset($_SESSION['usuario_id'])) {
    // Exemplo de verificação de admin (NÃO SEGURO - IMPLEMENTE CORRETAMENTE)
    $is_admin = ($_SESSION['usuario_email'] === 'admin@example.com'); // Use um campo do DB!

    echo json_encode([
        "logado" => true,
        "usuario_id" => $_SESSION['usuario_id'],
        "usuario_nome" => $_SESSION['usuario_nome'] ?? 'Usuário',
        "is_admin" => $is_admin // Envia status de admin
    ]);
} else {
    echo json_encode(["logado" => false]);
}
?>