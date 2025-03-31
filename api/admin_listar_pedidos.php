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


// !!! VERIFICAÇÃO DE ADMIN !!! (Exemplo MUITO SIMPLES - NÃO USE EM PRODUÇÃO)
// Idealmente, verificar um campo 'is_admin' ou 'role' no banco ou na sessão
if (!isset($_SESSION['usuario_id']) /* || !($_SESSION['is_admin'] ?? false) */ ) {
     http_response_code(403); // Forbidden
     echo json_encode(["sucesso" => false, "mensagem" => "Acesso negado."]); exit;
}

// Busca pedidos (todos ou filtrados por status, etc.)
// Adicionar JOIN com pedido_itens se quiser mostrar itens na lista principal
$status_filtro = $_GET['status'] ?? null; // Ex: /admin_listar_pedidos.php?status=Recebido
$pedidos = [];
$sql = "SELECT id, usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, data_pedido, valor_total, status
        FROM pedidos";
if ($status_filtro) {
     $sql .= " WHERE status = ?";
}
$sql .= " ORDER BY data_pedido DESC";

$stmt = $mysqli->prepare($sql);
if ($status_filtro) {
    $stmt->bind_param("s", $status_filtro);
}

if($stmt->execute()){
    $result = $stmt->get_result();
    while($p = $result->fetch_assoc()){
        // Poderia buscar os itens aqui também se necessário
        $pedidos[] = $p;
    }
} else { /* tratar erro */ }
$stmt->close();
$mysqli->close();

echo json_encode(["sucesso" => true, "pedidos" => $pedidos]);
?>