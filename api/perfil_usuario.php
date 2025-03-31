<?php
session_start();
error_reporting(E_ALL); ini_set('display_errors', 1); ini_set('log_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); echo json_encode(["sucesso" => false, "mensagem" => "Use GET."]); exit;
}

// Verificar se usuário está logado
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401); // Unauthorized
    echo json_encode(["sucesso" => false, "mensagem" => "Usuário não autenticado."]); exit;
}
$usuario_id = $_SESSION['usuario_id'];

$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) { /* ... tratamento erro conexão ... */ exit; }
$mysqli->set_charset("utf8mb4");

$perfil = null;
$pedidos = [];

// Buscar dados do usuário
$sql_user = "SELECT id, nome, email, endereco, telefone FROM usuarios WHERE id = ?";
$stmt_user = $mysqli->prepare($sql_user);
if($stmt_user){
    $stmt_user->bind_param("i", $usuario_id);
    if ($stmt_user->execute()) {
        $result_user = $stmt_user->get_result();
        $perfil = $result_user->fetch_assoc(); // Pega o perfil
        if (!$perfil) {
            error_log("Usuário ID {$usuario_id} da sessão não encontrado no banco.");
             http_response_code(404); echo json_encode(["sucesso" => false, "mensagem" => "Usuário não encontrado."]);
             $stmt_user->close(); $mysqli->close(); exit;
        }
    } else { error_log("Erro ao executar busca de usuário: ".$stmt_user->error); }
    $stmt_user->close();
} else { error_log("Erro ao preparar busca de usuário: ".$mysqli->error); }


// Buscar pedidos do usuário (ordenados do mais recente)
$sql_pedidos = "SELECT id, data_pedido, valor_total, status FROM pedidos WHERE usuario_id = ? ORDER BY data_pedido DESC";
$stmt_pedidos = $mysqli->prepare($sql_pedidos);
if($stmt_pedidos){
    $stmt_pedidos->bind_param("i", $usuario_id);
    if ($stmt_pedidos->execute()) {
        $result_pedidos = $stmt_pedidos->get_result();
        while ($pedido = $result_pedidos->fetch_assoc()) {
            // Buscar itens de cada pedido
             $pedido_id = $pedido['id'];
             $itens = [];
             $sql_itens = "SELECT produto_id, quantidade, preco_unitario, nome_produto FROM pedido_itens WHERE pedido_id = ?";
             $stmt_itens = $mysqli->prepare($sql_itens);
             if($stmt_itens){
                 $stmt_itens->bind_param("i", $pedido_id);
                 if ($stmt_itens->execute()) {
                     $result_itens = $stmt_itens->get_result();
                     while($item = $result_itens->fetch_assoc()) {
                         $item['quantidade'] = intval($item['quantidade']);
                         $item['preco_unitario'] = floatval($item['preco_unitario']);
                         $itens[] = $item;
                     }
                 } else { error_log("Erro ao buscar itens do pedido $pedido_id: ".$stmt_itens->error); }
                 $stmt_itens->close();
             } else { error_log("Erro ao preparar busca de itens: ".$mysqli->error); }

             $pedido['itens'] = $itens; // Adiciona array de itens ao pedido
             $pedido['valor_total'] = floatval($pedido['valor_total']);
             $pedidos[] = $pedido; // Adiciona pedido ao array de pedidos
        }
    } else { error_log("Erro ao executar busca de pedidos: ".$stmt_pedidos->error); }
    $stmt_pedidos->close();
} else { error_log("Erro ao preparar busca de pedidos: ".$mysqli->error); }

$mysqli->close();

// Retorna sucesso com os dados encontrados
http_response_code(200);
echo json_encode(["sucesso" => true, "perfil" => $perfil, "pedidos" => $pedidos]);
?>