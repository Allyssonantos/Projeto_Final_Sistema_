<?php
// api/finalizar_pedido.php
// Processa o carrinho, valida itens/preços, salva o pedido e retorna confirmação.

session_start(); // ESSENCIAL

// --- Configurações Iniciais e Headers ---
error_reporting(E_ALL); ini_set('display_errors', 0); ini_set('log_errors', 1);
header("Access-Control-Allow-Origin: *"); // AJUSTE
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true"); // ESSENCIAL
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// --- Função Auxiliar JSON ---
function jsonResponse($status_code, $data) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// --- Verificar Método e Login ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(405, ["sucesso" => false, "mensagem" => "Use POST."]); }
if (!isset($_SESSION['usuario_id'])) { jsonResponse(401, ["sucesso" => false, "mensagem" => "Faça login para finalizar o pedido."]); }
$usuario_id = $_SESSION['usuario_id'];

// --- Ler e Validar Dados da Requisição ---
$dados = json_decode(file_get_contents("php://input"), true);
$carrinho = $dados['carrinho'] ?? null;
$formaPagamento = $dados['formaPagamento'] ?? null;

// Valida carrinho
if (!$carrinho || !is_array($carrinho) || empty($carrinho)) { jsonResponse(400, ["sucesso" => false, "mensagem" => "Carrinho inválido ou vazio."]); }
// Valida forma de pagamento
if (!$formaPagamento || !in_array($formaPagamento, ['PIX', 'Na Entrega'])) { jsonResponse(400, ["sucesso" => false, "mensagem" => "Forma de pagamento inválida."]); }

error_log("[Finalizar Pedido] User ID: $usuario_id, Forma Pgto: $formaPagamento, Itens Recebidos: " . count($carrinho));

// --- Conexão DB ---
$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) { /* ... erro conexão ... */ jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB."]); }
$mysqli->set_charset("utf8mb4");

// --- 1. Buscar dados ATUAIS do usuário (incluindo endereço) ---
$user_data = null;
$sql_user = "SELECT nome, email, endereco, telefone FROM usuarios WHERE id = ?";
$stmt_user = $mysqli->prepare($sql_user);
if($stmt_user){
    $stmt_user->bind_param("i", $usuario_id);
    if ($stmt_user->execute()) { $result_user = $stmt_user->get_result(); $user_data = $result_user->fetch_assoc(); }
    $stmt_user->close();
}
if (!$user_data) { jsonResponse(404, ["sucesso" => false, "mensagem" => "Erro: Usuário não encontrado."]); }
if (empty(trim($user_data['endereco'] ?? ''))) { jsonResponse(400, ["sucesso" => false, "mensagem" => "Seu endereço de entrega não está cadastrado. Atualize seu perfil."]); }
$user_data['telefone'] = $user_data['telefone'] ?? ''; // Garante que existe
error_log("[Finalizar Pedido] Dados do usuário carregados. Endereço: " . $user_data['endereco']);


// --- 2. Validar itens e Recalcular Total (BUSCANDO PREÇOS DO BANCO!) ---
$valor_total_calculado = 0;
$ids_produtos_carrinho = [];
$quantidades_carrinho = []; // Mapeia ID => quantidade vinda do carrinho
foreach ($carrinho as $item_c) {
    $id = filter_var($item_c['id'] ?? null, FILTER_VALIDATE_INT);
    $qtd = filter_var($item_c['quantidade'] ?? 0, FILTER_VALIDATE_INT);
    if ($id && $qtd > 0) { $ids_produtos_carrinho[] = $id; $quantidades_carrinho[$id] = $qtd; }
}

if (empty($ids_produtos_carrinho)) { jsonResponse(400, ["sucesso" => false, "mensagem" => "Nenhum item válido encontrado no carrinho."]); }

$placeholders = implode(',', array_fill(0, count($ids_produtos_carrinho), '?'));
$tipos = str_repeat('i', count($ids_produtos_carrinho));
$sql_produtos = "SELECT id, nome, preco FROM produtos WHERE id IN ($placeholders)"; // Busca apenas produtos existentes
$stmt_produtos = $mysqli->prepare($sql_produtos);
if(!$stmt_produtos) { error_log("Erro prepare busca produtos: ".$mysqli->error); jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro ao verificar produtos."]); }

$stmt_produtos->bind_param($tipos, ...$ids_produtos_carrinho);
if(!$stmt_produtos->execute()){ error_log("Erro execute busca produtos: ".$stmt_produtos->error); jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro ao verificar produtos."]); }

$result_produtos = $stmt_produtos->get_result();
$produtos_db = []; // Mapeia ID => {id, nome, preco}
while($p = $result_produtos->fetch_assoc()){ $produtos_db[$p['id']] = $p; }
$stmt_produtos->close();

$itens_pedido_para_inserir = [];
// Recalcula o total e valida se todos os produtos do carrinho existem no DB
foreach ($ids_produtos_carrinho as $produto_id) {
    if (!isset($produtos_db[$produto_id])) {
         error_log("[Finalizar Pedido] ERRO: Produto ID $produto_id do carrinho não existe mais no DB.");
         jsonResponse(400, ["sucesso" => false, "mensagem" => "Desculpe, um dos itens do seu carrinho (ID $produto_id) não está mais disponível."]);
    }
    $produto_atual = $produtos_db[$produto_id];
    $quantidade = $quantidades_carrinho[$produto_id];
    $preco_unitario_db = floatval($produto_atual['preco']);
    $valor_total_calculado += $preco_unitario_db * $quantidade;

    $itens_pedido_para_inserir[] = [
         'produto_id' => $produto_id,
         'quantidade' => $quantidade,
         'preco_unitario' => $preco_unitario_db,
         'nome_produto' => $produto_atual['nome'] // Pega nome atual do banco
     ];
}

if (empty($itens_pedido_para_inserir)) { jsonResponse(400, ["sucesso" => false, "mensagem" => "Erro ao validar os itens do pedido."]); }
error_log("[Finalizar Pedido] Total recalculado: " . $valor_total_calculado . ". Itens validados: " . count($itens_pedido_para_inserir));

// --- 3. Iniciar Transação ---
$mysqli->begin_transaction();
$pedido_id_inserido = null;

try {
    // 4. Inserir na tabela `pedidos`
    // Se você adicionou a coluna 'forma_pagamento' ao DB:
    // $sql_pedido = "INSERT INTO pedidos (usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, valor_total, status, forma_pagamento) VALUES (?, ?, ?, ?, ?, ?, 'Recebido', ?)";
    $sql_pedido = "INSERT INTO pedidos (usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, valor_total, status) VALUES (?, ?, ?, ?, ?, ?, 'Recebido')";
    $stmt_pedido = $mysqli->prepare($sql_pedido);
    if(!$stmt_pedido) throw new Exception("Erro prepare pedido: " . $mysqli->error);

    // Ajuste bind_param se adicionou forma_pagamento (ex: "issssdsS")
    $stmt_pedido->bind_param("issssd",
         $usuario_id, $user_data['nome'], $user_data['email'], $user_data['telefone'], $user_data['endereco'], $valor_total_calculado
         // , $formaPagamento // << Adicione se a coluna existir
     );
    if(!$stmt_pedido->execute()) throw new Exception("Erro insert pedido: " . $stmt_pedido->error);
    $pedido_id_inserido = $mysqli->insert_id;
    $stmt_pedido->close();
    error_log("[Finalizar Pedido] Pedido principal ID $pedido_id_inserido inserido.");


    // 5. Inserir na tabela `pedido_itens`
    $sql_item = "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, nome_produto) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $mysqli->prepare($sql_item);
    if(!$stmt_item) throw new Exception("Erro prepare itens: " . $mysqli->error);

    foreach ($itens_pedido_para_inserir as $item) {
        $stmt_item->bind_param("iiids", // i=int, i, i, d=double, s=string
            $pedido_id_inserido, $item['produto_id'], $item['quantidade'], $item['preco_unitario'], $item['nome_produto']
        );
        if(!$stmt_item->execute()) throw new Exception("Erro insert item (Prod ID: {$item['produto_id']}): " . $stmt_item->error);
    }
    $stmt_item->close();
    error_log("[Finalizar Pedido] Itens inseridos para pedido ID $pedido_id_inserido.");

    // 6. Commit da Transação
    if (!$mysqli->commit()) {
        throw new Exception("Erro commit transação: " . $mysqli->error);
    }
    error_log("[Finalizar Pedido] Transação commitada para pedido ID $pedido_id_inserido.");

    // 7. Preparar Resposta de Sucesso
    $resposta = [
        "sucesso" => true,
        "mensagem" => "Pedido nº $pedido_id_inserido recebido com sucesso!",
        "pedido_id" => $pedido_id_inserido
    ];
    if ($formaPagamento === 'PIX') {
         // LÓGICA PARA OBTER/GERAR DADOS PIX
         // Exemplo estático:
         $resposta['instrucoesPix'] = "Pagamento via PIX selecionado. Use a chave aleatória: xyz-abc-123-def ou escaneie o QR Code que será gerado.";
         // Em um caso real, você chamaria outra API de pagamento ou buscaria a chave do seu sistema.
         error_log("[Finalizar Pedido] Enviando instruções PIX para pedido ID $pedido_id_inserido.");
    }

    jsonResponse(201, $resposta); // 201 Created

} catch (Exception $e) {
    // 8. Rollback em caso de erro em qualquer etapa
    $mysqli->rollback(); // Desfaz as alterações da transação
    error_log("Erro CRÍTICO ao finalizar pedido User $usuario_id (ROLLBACK): " . $e->getMessage());
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Não foi possível processar seu pedido neste momento. Tente novamente."]);
}

// Fecha a conexão se não foi fechada antes
$mysqli->close();
?>