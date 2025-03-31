<?php
session_start();
// ... (headers CORS, conexão DB $mysqli) ...

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

// !!! VERIFICAÇÃO DE ADMIN !!! (MUITO IMPORTANTE)
 if (!isset($_SESSION['usuario_id']) /* || !($_SESSION['is_admin'] ?? false) */ ) { /* ... erro 403 ... */ exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { /* ... erro 405 ... */ exit; }

$dados = json_decode(file_get_contents("php://input"), true);
$pedido_id = $dados['pedido_id'] ?? null;
$novo_status = $dados['novo_status'] ?? null;
$status_validos = ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado']; // Status permitidos

if (!$pedido_id || !filter_var($pedido_id, FILTER_VALIDATE_INT) || !$novo_status || !in_array($novo_status, $status_validos)) {
     http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Dados inválidos (ID do pedido ou status)."]); exit;
}

$sql = "UPDATE pedidos SET status = ? WHERE id = ?";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param("si", $novo_status, $pedido_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        echo json_encode(["sucesso" => true, "mensagem" => "Status do pedido atualizado para '$novo_status'."]);
    } else {
         echo json_encode(["sucesso" => false, "mensagem" => "Pedido não encontrado ou status já era '$novo_status'."]);
    }
} else { /* tratar erro DB */ }

$stmt->close();
$mysqli->close();
?>