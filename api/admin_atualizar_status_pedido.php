<?php
session_start();
error_reporting(E_ALL); ini_set('display_errors', 1); ini_set('log_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// !!! VERIFICAÇÃO DE ADMIN !!!
$is_admin_check = ($_SESSION['usuario_email'] === 'admin@example.com'); // Exemplo INSEGURO
if (!isset($_SESSION['usuario_id']) || !$is_admin_check ) { /* ... erro 403 ... */ exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { /* ... erro 405 ... */ exit; }

$dados = json_decode(file_get_contents("php://input"), true);
$pedido_id = $dados['pedido_id'] ?? null;
$novo_status = $dados['novo_status'] ?? null;
$status_validos = ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado'];

if (!$pedido_id || !filter_var($pedido_id, FILTER_VALIDATE_INT) || !$novo_status || !in_array($novo_status, $status_validos)) {
     http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Dados inválidos (ID do pedido ou status). Status válidos: ".implode(', ', $status_validos)]); exit;
}

$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) { /* ... tratamento erro conexão ... */ exit; }
$mysqli->set_charset("utf8mb4");

$sql = "UPDATE pedidos SET status = ? WHERE id = ?";
$stmt = $mysqli->prepare($sql);
if(!$stmt) { /* ... erro prepare ... */ exit; }

$stmt->bind_param("si", $novo_status, $pedido_id);

if ($stmt->execute()) {
    if ($stmt->affected_rows > 0) {
        http_response_code(200);
        echo json_encode(["sucesso" => true, "mensagem" => "Status do pedido ID $pedido_id atualizado para '$novo_status'."]);
    } else {
         // Pode ser 404 se o ID não existia, ou 200 se o status já era o mesmo
         http_response_code(404); // Ou 200 - depende da sua preferência
         echo json_encode(["sucesso" => false, "mensagem" => "Pedido não encontrado ou status já era '$novo_status'."]);
    }
} else {
     http_response_code(500);
     error_log("Erro ao atualizar status pedido $pedido_id: ".$stmt->error);
     echo json_encode(["sucesso" => false, "mensagem" => "Erro DB ao atualizar status."]);
}

$stmt->close();
$mysqli->close();
?>