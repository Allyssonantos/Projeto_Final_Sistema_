<?php
// --- Configurações Iniciais ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // 0 ou log em produção
ini_set('log_errors', 1);
// ini_set('error_log', '/caminho/completo/para/php_error.log');

// --- Headers CORS e de Resposta ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Permitir APENAS GET e OPTIONS neste endpoint
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// --- Tratamento da Requisição OPTIONS (Preflight) ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- Verificar Método HTTP ---
// Este endpoint só aceita GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["sucesso" => false, "mensagem" => "Método HTTP não permitido. Use GET."]);
    exit;
}

// --- Conexão com o Banco de Dados ---
$mysqli = new mysqli("localhost", "root", "", "pizzaria"); // Substitua se necessário

// Verificar erro de conexão
if ($mysqli->connect_error) {
    http_response_code(500);
    error_log("Erro de Conexão DB (produtos.php): " . $mysqli->connect_error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao conectar ao banco de dados."]);
    exit;
}
$mysqli->set_charset("utf8mb4");

session_start();
session_unset(); // Remove todas as variáveis de sessão
session_destroy(); // Destrói a sessão
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *"); // Ajuste em produção
header("Access-Control-Allow-Credentials: true");
echo json_encode(["sucesso" => true, "mensagem" => "Logout realizado com sucesso."]);
?>