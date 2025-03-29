<?php
// --- Conexão com o Banco de Dados ---
// Use as credenciais corretas e o nome do seu banco
$mysqli = new mysqli("localhost", "root", "", "pizzaria");

// Verificar erro de conexão
if ($mysqli->connect_error) {
    // Enviar resposta de erro JSON e sair
    http_response_code(500); // Internal Server Error
    echo json_encode(["erro" => true, "mensagem" => "Erro interno do servidor: Falha ao conectar ao banco de dados."]);
    exit;
}

// Definir charset da conexão (importante para evitar problemas com caracteres especiais)
$mysqli->set_charset("utf8mb4");

// --- Roteamento baseado no Método HTTP ---
$method = $_SERVER["REQUEST_METHOD"];

session_start();
if (!isset($_SESSION["usuario_id"])) {
    echo json_encode(["status" => "erro", "mensagem" => "Acesso negado"]);
    exit;
}
?>
