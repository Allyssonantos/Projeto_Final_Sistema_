<?php
session_start();
error_reporting(E_ALL); ini_set('display_errors', 1); ini_set('log_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { /* ... erro 405 ... */ exit; }

// Verificar login
if (!isset($_SESSION['usuario_id'])) {
    http_response_code(401); echo json_encode(["sucesso" => false, "mensagem" => "Faça login para finalizar o pedido."]); exit;
}
$usuario_id = $_SESSION['usuario_id'];

// Ler carrinho do corpo JSON
$dados = json_decode(file_get_contents("php://input"), true);
$carrinho = $dados['carrinho'] ?? null;

if (!$carrinho || !is_array($carrinho) || empty($carrinho)) {
    http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Carrinho inválido ou vazio."]); exit;
}

$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) { /* ... tratamento erro conexão ... */ exit; }
$mysqli->set_charset("utf8mb4");

// 1. Buscar dados atuais do usuário
$user_data = null;
$sql_user = "SELECT nome, email, endereco, telefone FROM usuarios WHERE id = ?";
$stmt_user = $mysqli->prepare($sql_user);
if($stmt_user){
    $stmt_user->bind_param("i", $usuario_id);
    if ($stmt_user->execute()) { $result_user = $stmt_user->get_result(); $user_data = $result_user->fetch_assoc(); }
    $stmt_user->close();
}
if (!$user_data || empty(trim($user_data['endereco'] ?? ''))) {
    http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Endereço de entrega não encontrado ou inválido no seu perfil. Atualize seu cadastro em 'Meu Perfil'."]); $mysqli->close(); exit;
}
$user_data['telefone'] = $user_data['telefone'] ?? ''; // Garante que existe

// 2. Validar itens e Recalcular Total (BUSCANDO PREÇOS DO BANCO)
$valor_total_calculado = 0;
$ids_produtos_carrinho = [];
$quantidades_carrinho = []; // Mapeia ID => quantidade vinda do carrinho
foreach ($carrinho as $item_c) {
    $id = filter_var($item_c['id'] ?? null, FILTER_VALIDATE_INT);
    $qtd = filter_var($item_c['quantidade'] ?? 0, FILTER_VALIDATE_INT);
    if ($id && $qtd > 0) {
        $ids_produtos_carrinho[] = $id;
        $quantidades_carrinho[$id] = $qtd;
    }
}

if (empty($ids_produtos_carrinho)) {
     http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Nenhum item válido no carrinho."]); $mysqli->close(); exit;
}

$placeholders = implode(',', array_fill(0, count($ids_produtos_carrinho), '?'));
$tipos = str_repeat('i', count($ids_produtos_carrinho));
$sql_produtos = "SELECT id, nome, preco FROM produtos WHERE id IN ($placeholders)";
$stmt_produtos = $mysqli->prepare($sql_produtos);
if(!$stmt_produtos) { /* ... erro prepare ... */ exit; }

$stmt_produtos->bind_param($tipos, ...$ids_produtos_carrinho);
if(!$stmt_produtos->execute()){ /* ... erro execute ... */ exit; }

$result_produtos = $stmt_produtos->get_result();
$produtos_db = []; // Mapeia ID => {id, nome, preco}
while($p = $result_produtos->fetch_assoc()){ $produtos_db[$p['id']] = $p; }
$stmt_produtos->close();

$itens_pedido_para_inserir = [];
foreach ($ids_produtos_carrinho as $produto_id) {
    if (!isset($produtos_db[$produto_id])) {
         http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Produto ID $produto_id não encontrado ou indisponível."]); $mysqli->close(); exit;
    }
    $produto_atual = $produtos_db[$produto_id];
    $quantidade = $quantidades_carrinho[$produto_id]; // Pega quantidade do carrinho
    $preco_unitario_db = floatval($produto_atual['preco']);
    $valor_total_calculado += $preco_unitario_db * $quantidade;

    $itens_pedido_para_inserir[] = [
         'produto_id' => $produto_id,
         'quantidade' => $quantidade,
         'preco_unitario' => $preco_unitario_db,
         'nome_produto' => $produto_atual['nome'] // Pega nome do banco
     ];
}

if (empty($itens_pedido_para_inserir)) { // Segurança extra
     http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Erro ao validar itens do pedido."]); $mysqli->close(); exit;
}

// 3. Iniciar Transação
$mysqli->begin_transaction();
$pedido_id_inserido = null;

try {
    // 4. Inserir na tabela `pedidos`
    $sql_pedido = "INSERT INTO pedidos (usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, valor_total, status) VALUES (?, ?, ?, ?, ?, ?, 'Recebido')";
    $stmt_pedido = $mysqli->prepare($sql_pedido);
    if(!$stmt_pedido) throw new Exception("Erro ao preparar inserção do pedido: " . $mysqli->error);

    $stmt_pedido->bind_param("issssd", // i=int, s=string, s, s, s, d=double
         $usuario_id,
         $user_data['nome'],
         $user_data['email'],
         $user_data['telefone'],
         $user_data['endereco'],
         $valor_total_calculado // USA O VALOR CALCULADO A PARTIR DO BANCO
     );
    if(!$stmt_pedido->execute()) throw new Exception("Erro ao inserir pedido: " . $stmt_pedido->error);
    $pedido_id_inserido = $mysqli->insert_id; // Pega o ID do pedido
    $stmt_pedido->close();

    // 5. Inserir na tabela `pedido_itens`
    $sql_item = "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, nome_produto) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $mysqli->prepare($sql_item);
    if(!$stmt_item) throw new Exception("Erro ao preparar inserção de itens: " . $mysqli->error);

    foreach ($itens_pedido_para_inserir as $item) {
        $stmt_item->bind_param("iiids", // i=int, i, i, d=double, s=string
            $pedido_id_inserido,
            $item['produto_id'],
            $item['quantidade'],
            $item['preco_unitario'],
            $item['nome_produto']
        );
        if(!$stmt_item->execute()) throw new Exception("Erro ao inserir item (Prod ID: {$item['produto_id']}): " . $stmt_item->error);
    }
    $stmt_item->close();

    // 6. Commit da Transação
    if (!$mysqli->commit()) {
        throw new Exception("Erro ao commitar transação: " . $mysqli->error);
    }
    http_response_code(201); // Created
    echo json_encode(["sucesso" => true, "mensagem" => "Pedido realizado com sucesso!", "pedido_id" => $pedido_id_inserido]);

} catch (Exception $e) {
    // 7. Rollback em caso de erro
    $mysqli->rollback();
    http_response_code(500);
    error_log("Erro CRÍTICO ao finalizar pedido (ID usuário: $usuario_id): " . $e->getMessage());
    echo json_encode(["sucesso" => false, "mensagem" => "Não foi possível processar seu pedido neste momento. Tente novamente mais tarde."]);
}

$mysqli->close();
?>