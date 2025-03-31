<?php
session_start();
error_reporting(E_ALL); ini_set('display_errors', 1); ini_set('log_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') { /* ... erro 405 ... */ exit; }

// !!! VERIFICAÇÃO DE ADMIN - IMPLEMENTE DE FORMA SEGURA !!!
$is_admin_check = ($_SESSION['usuario_email'] === 'admin@example.com'); // Exemplo INSEGURO
if (!isset($_SESSION['usuario_id']) || !$is_admin_check ) {
     http_response_code(403); // Forbidden
     echo json_encode(["sucesso" => false, "mensagem" => "Acesso negado."]); exit;
}

$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) { /* ... tratamento erro conexão ... */ exit; }
$mysqli->set_charset("utf8mb4");

// Filtro opcional por status
$status_filtro = $_GET['status'] ?? null;
$pedidos = [];
$params = [];
$types = "";

$sql = "SELECT id, usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, data_pedido, valor_total, status
        FROM pedidos";

if ($status_filtro && in_array($status_filtro, ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado'])) {
     $sql .= " WHERE status = ?";
     $params[] = $status_filtro;
     $types .= "s";
}
$sql .= " ORDER BY CASE status
             WHEN 'Recebido' THEN 1
             WHEN 'Em Preparo' THEN 2
             WHEN 'Saiu para Entrega' THEN 3
             WHEN 'Entregue' THEN 4
             WHEN 'Cancelado' THEN 5
             ELSE 6
          END, data_pedido DESC"; // Ordena por status e depois por data

$stmt = $mysqli->prepare($sql);
if($stmt){
    if(!empty($params)){
        $stmt->bind_param($types, ...$params);
    }
    if($stmt->execute()){
        $result = $stmt->get_result();
        while($p = $result->fetch_assoc()){
            $p['valor_total'] = floatval($p['valor_total']);
            // Buscar itens aqui se necessário para exibição na lista principal
            $pedidos[] = $p;
        }
    } else { error_log("Erro ao buscar pedidos admin: ".$stmt->error); }
    $stmt->close();
} else { error_log("Erro ao preparar busca pedidos admin: ".$mysqli->error); }

$mysqli->close();

echo json_encode(["sucesso" => true, "pedidos" => $pedidos]);
?>