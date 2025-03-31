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

if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(["sucesso" => false, "mensagem" => "Usuário não autenticado."]); exit;
}
$usuario_id = $_SESSION['usuario_id'];
$perfil = null;
$pedidos = [];

// Buscar dados do usuário
$sql_user = "SELECT id, nome, email, endereco, telefone FROM usuarios WHERE id = ?";
$stmt_user = $mysqli->prepare($sql_user);
$stmt_user->bind_param("i", $usuario_id);
if ($stmt_user->execute()) {
    $result_user = $stmt_user->get_result();
    $perfil = $result_user->fetch_assoc();
} else { /* tratar erro */ }
$stmt_user->close();

// Buscar pedidos do usuário (exemplo simples, pode precisar de paginação)
$sql_pedidos = "SELECT id, data_pedido, valor_total, status FROM pedidos WHERE usuario_id = ? ORDER BY data_pedido DESC LIMIT 20";
$stmt_pedidos = $mysqli->prepare($sql_pedidos);
$stmt_pedidos->bind_param("i", $usuario_id);
if ($stmt_pedidos->execute()) {
    $result_pedidos = $stmt_pedidos->get_result();
    while ($pedido = $result_pedidos->fetch_assoc()) {
        // Buscar itens de cada pedido (poderia ser uma query só com JOIN, mas assim é mais simples de montar)
         $pedido_id = $pedido['id'];
         $itens = [];
         $sql_itens = "SELECT produto_id, quantidade, preco_unitario, nome_produto FROM pedido_itens WHERE pedido_id = ?";
         $stmt_itens = $mysqli->prepare($sql_itens);
         $stmt_itens->bind_param("i", $pedido_id);
         if ($stmt_itens->execute()) {
             $result_itens = $stmt_itens->get_result();
             while($item = $result_itens->fetch_assoc()) {
                 $itens[] = $item;
             }
         }
         $stmt_itens->close();
         $pedido['itens'] = $itens; // Adiciona os itens ao pedido
         $pedidos[] = $pedido;
    }
} else { /* tratar erro */ }
$stmt_pedidos->close();

$mysqli->close();
echo json_encode(["sucesso" => true, "perfil" => $perfil, "pedidos" => $pedidos]);
?>