<?php
// api/finalizar_pedido.php - VERSÃO COMPLETA COM TESTE INSERT+UPDATE

session_start(); // ESSENCIAL: No topo absoluto!

// --- Configurações Iniciais e Headers ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // 0 para produção, 1 para debug SE precisar e não achar log
ini_set('log_errors', 1); // Habilitar log
// ini_set('error_log', '/caminho/absoluto/para/seu/php_error.log'); // Defina o caminho do log

header("Access-Control-Allow-Origin: *"); // !! AJUSTE EM PRODUÇÃO !!
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true"); // ESSENCIAL para sessão
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// --- Função Auxiliar JSON ---
function jsonResponse($status_code, $data) {
    http_response_code($status_code);
    if (!headers_sent()) { header('Content-Type: application/json; charset=UTF-8'); }
    echo json_encode($data);
    exit; // Termina a execução
}

// --- Verificar Método e Login ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { jsonResponse(405, ["sucesso" => false, "mensagem" => "Use POST."]); }
if (!isset($_SESSION['usuario_id'])) { jsonResponse(401, ["sucesso" => false, "mensagem" => "Faça login para finalizar o pedido."]); }
$usuario_id = $_SESSION['usuario_id'];

// --- Ler e Validar Dados da Requisição ---
$dados = json_decode(file_get_contents("php://input"), true);
error_log("[Finalizar Pedido TESTE] Dados JSON recebidos: " . print_r($dados, true));

$carrinho = $dados['carrinho'] ?? null;
$formaPagamento = $dados['formaPagamento'] ?? null;
$observacoes = $dados['observacoes'] ?? null; // Pega observações

// Valida carrinho
if (!$carrinho || !is_array($carrinho) || empty($carrinho)) { jsonResponse(400, ["sucesso" => false, "mensagem" => "Carrinho inválido ou vazio."]); }

// Validação Forma de Pagamento (Revisada)
error_log("[Finalizar Pedido TESTE] Validando Forma Pgto. Valor bruto: " . var_export($formaPagamento, true));
$formaPagamentoProcessada = null; if ($formaPagamento !== null) { $formaPagamentoProcessada = trim($formaPagamento); }
$formaPagamentoNormalizada = null; $valido = false; $formasPagamentoValidas = ['PIX', 'Na Entrega'];
if ($formaPagamentoProcessada !== null) {
    if (strcasecmp($formaPagamentoProcessada, 'PIX') === 0) { $formaPagamentoNormalizada = 'PIX'; $valido = true; }
    elseif (strcasecmp($formaPagamentoProcessada, 'Na Entrega') === 0) { $formaPagamentoNormalizada = 'Na Entrega'; $valido = true; }
}
if (!$valido) { error_log("[Finalizar Pedido TESTE] FALHA VALIDAÇÃO FORMA PGTO! Processado: '$formaPagamentoProcessada'"); jsonResponse(400, ["sucesso" => false, "mensagem" => "Forma de pagamento inválida ('" . htmlspecialchars($formaPagamento ?? 'N/A') . "')."]); }
error_log("[Finalizar Pedido TESTE] User ID: $usuario_id, Forma Pgto Validada: '$formaPagamentoNormalizada'");


// --- Conexão DB ---
$db_host = "localhost"; $db_user = "root"; $db_pass = ""; $db_name = "pizzaria";
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) { error_log("CRITICO DB Connect Error: ".$mysqli->connect_error); jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB Connect."]); }
$mysqli->set_charset("utf8mb4");
error_log("[Finalizar Pedido TESTE] Conexão DB OK.");

// --- 1. Buscar dados ATUAIS do usuário ---
// ... (Código igual à versão anterior para buscar $user_data) ...
$user_data = null; $sql_user = "SELECT nome, email, endereco, telefone FROM usuarios WHERE id = ?"; $stmt_user = $mysqli->prepare($sql_user); if(!$stmt_user) { /*...*/ jsonResponse(500, ["s"=>false, "m"=>"Erro P1"]); } $stmt_user->bind_param("i", $usuario_id); if (!$stmt_user->execute()) { /*...*/ jsonResponse(500, ["s"=>false, "m"=>"Erro E1"]); } $result_user = $stmt_user->get_result(); $user_data = $result_user->fetch_assoc(); $stmt_user->close(); if (!$user_data) { /*...*/ jsonResponse(404, ["s"=>false, "m"=>"Usuário não encontrado."]); } if (empty(trim($user_data['endereco'] ?? ''))) { /*...*/ jsonResponse(400, ["s"=>false, "m"=>"Endereço não cadastrado."]); } $user_data['telefone'] = $user_data['telefone'] ?? ''; error_log("[Finalizar Pedido TESTE] Dados do usuário OK.");

// --- 2. Validar itens e Recalcular Total ---
// ... (Código igual à versão anterior para validar itens e calcular $valor_total_calculado) ...
$valor_total_calculado = 0.00; $ids_produtos_carrinho = []; $quantidades_carrinho = []; foreach ($carrinho as $item_c) { $id = filter_var($item_c['id'] ?? null, FILTER_VALIDATE_INT); $qtd = filter_var($item_c['quantidade'] ?? 0, FILTER_VALIDATE_INT); if ($id && $qtd > 0) { $ids_produtos_carrinho[] = $id; $quantidades_carrinho[$id] = $qtd; }} if (empty($ids_produtos_carrinho)) { $mysqli->close(); jsonResponse(400, ["s"=>false, "m"=>"Carrinho vazio."]); } $placeholders = implode(',', array_fill(0, count($ids_produtos_carrinho), '?')); $tipos = str_repeat('i', count($ids_produtos_carrinho)); $sql_produtos = "SELECT id, nome, preco FROM produtos WHERE id IN ($placeholders)"; $stmt_produtos = $mysqli->prepare($sql_produtos); if(!$stmt_produtos) { /*...*/ $mysqli->close(); jsonResponse(500, ["s"=>false, "m"=>"Erro P2"]); } $stmt_produtos->bind_param($tipos, ...$ids_produtos_carrinho); if(!$stmt_produtos->execute()){ /*...*/ $stmt_produtos->close(); $mysqli->close(); jsonResponse(500, ["s"=>false, "m"=>"Erro E2"]); } $result_produtos = $stmt_produtos->get_result(); $produtos_db = []; while($p = $result_produtos->fetch_assoc()){ $produtos_db[$p['id']] = $p; } $stmt_produtos->close(); $itens_pedido_para_inserir = []; foreach ($ids_produtos_carrinho as $produto_id) { if (!isset($produtos_db[$produto_id])) { $mysqli->close(); jsonResponse(400, ["s"=>false, "m"=>"Item indisponível (ID $produto_id)."]); } $p_atual = $produtos_db[$produto_id]; $q = $quantidades_carrinho[$produto_id]; $preco_db = floatval($p_atual['preco']); $valor_total_calculado += $preco_db * $q; $itens_pedido_para_inserir[] = ['produto_id' => $produto_id, 'quantidade' => $q, 'preco_unitario' => $preco_db, 'nome_produto' => $p_atual['nome']]; } if (empty($itens_pedido_para_inserir)) { $mysqli->close(); jsonResponse(400, ["s"=>false, "m"=>"Erro validar itens."]); } error_log("[Finalizar Pedido TESTE] Total: $valor_total_calculado. Itens: ".count($itens_pedido_para_inserir));

// --- 3. INSERIR PEDIDO SIMPLIFICADO (SEM transação por enquanto) ---
$pedido_id_teste = null;
error_log("[Finalizar Pedido TESTE] Preparando INSERT SIMPLES para 'pedidos'...");
// Inserindo SEM forma_pagamento e observacoes inicialmente
$sql_pedido_simples = "INSERT INTO pedidos (usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, valor_total, status) VALUES (?, ?, ?, ?, ?, ?, 'Recebido')";
$stmt_pedido_simples = $mysqli->prepare($sql_pedido_simples);

if (!$stmt_pedido_simples) { error_log("Erro PREPARE Pedido Simples: ".$mysqli->error); $mysqli->close(); jsonResponse(500, ["s"=>false, "m"=>"Erro DB PS"]); }

// Tipos: i, s, s, s, s, d -> 6 tipos
$stmt_pedido_simples->bind_param("issssd", $usuario_id, $user_data['nome'], $user_data['email'], $user_data['telefone'], $user_data['endereco'], $valor_total_calculado);

error_log("[Finalizar Pedido TESTE] Executando INSERT SIMPLES pedido...");
if (!$stmt_pedido_simples->execute()) {
    error_log("Erro EXECUTE Pedido Simples: ".$stmt_pedido_simples->error);
    $stmt_pedido_simples->close(); $mysqli->close();
    jsonResponse(500, ["s"=>false, "m"=>"Erro DB ES"]);
}
$pedido_id_teste = $mysqli->insert_id; // Pega o ID do pedido inserido
$stmt_pedido_simples->close();
error_log("[Finalizar Pedido TESTE] Pedido Simples ID $pedido_id_teste inserido.");

// --- 4. ATUALIZAR Pedido com Forma de Pagamento e Observações (SE insert deu certo) ---
if ($pedido_id_teste) {
    error_log("[Finalizar Pedido TESTE] Tentando UPDATE para ID $pedido_id_teste com FormaPgto: '$formaPagamentoNormalizada' e Obs: '" . ($observacoes ?? 'NULL') . "'");
    $sql_update_extra = "UPDATE pedidos SET forma_pagamento = ?, observacoes = ? WHERE id = ?";
    $stmt_update_extra = $mysqli->prepare($sql_update_extra);

    if (!$stmt_update_extra) {
        error_log("[Finalizar Pedido TESTE] Erro PREPARE Update Extra: ".$mysqli->error);
        // Não retorna erro fatal aqui, mas loga. Continua para inserir itens.
    } else {
        // Tipos: s (forma), s (obs), i (id)
        $stmt_update_extra->bind_param("ssi", $formaPagamentoNormalizada, $observacoes, $pedido_id_teste);
        if (!$stmt_update_extra->execute()) {
            error_log("[Finalizar Pedido TESTE] Erro EXECUTE Update Extra: ".$stmt_update_extra->error); // Loga o erro mas continua
        } else {
            error_log("[Finalizar Pedido TESTE] UPDATE Extra executado. Affected: " . $stmt_update_extra->affected_rows); // Loga quantas linhas foram afetadas
        }
        $stmt_update_extra->close();
    }

    // --- 5. Inserir Itens do Pedido (SE insert inicial deu certo) ---
    error_log("[Finalizar Pedido TESTE] Preparando INSERT para 'pedido_itens'...");
    $sql_item = "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, nome_produto) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $mysqli->prepare($sql_item);
    if(!$stmt_item) { error_log("Erro prepare itens: ".$mysqli->error); $mysqli->close(); jsonResponse(500, ["s"=>false, "m"=>"Erro DB PI"]); } // Erro aqui é mais crítico
    error_log("[Finalizar Pedido TESTE] Inserindo " . count($itens_pedido_para_inserir) . " itens...");
    $erros_itens = 0;
    foreach ($itens_pedido_para_inserir as $item) {
        $stmt_item->bind_param("iiids", $pedido_id_teste, $item['produto_id'], $item['quantidade'], $item['preco_unitario'], $item['nome_produto']);
        if(!$stmt_item->execute()) {
            error_log("Erro insert item (Prod ID: {$item['produto_id']}): ".$stmt_item->error);
            $erros_itens++; // Conta erros de item mas não para imediatamente
        }
    }
    $stmt_item->close();

    // Se houve erro ao inserir QUALQUER item, retorna erro geral (sem transação, não podemos reverter facilmente)
    if ($erros_itens > 0) {
         error_log("[Finalizar Pedido TESTE] Erro(s) ao inserir itens para pedido ID $pedido_id_teste.");
         $mysqli->close();
         jsonResponse(500, ["s"=>false, "m"=>"Erro ao salvar alguns itens do pedido."]);
    }
    error_log("[Finalizar Pedido TESTE] Itens inseridos para pedido ID $pedido_id_teste.");

    // --- 6. Preparar Resposta de Sucesso (Mesmo se UPDATE falhou, o pedido base foi criado) ---
    $resposta = [ "sucesso" => true, "mensagem" => "Pedido TESTE nº $pedido_id_teste recebido!", "pedido_id" => $pedido_id_teste ];
    if ($formaPagamentoNormalizada === 'PIX') { $resposta['instrucoesPix'] = "Pagamento PIX selecionado."; /* Lógica Real */ }
    jsonResponse(201, $resposta); // 201 Created

} else {
    // Se o INSERT inicial falhou (improvável se chegou aqui sem erro antes)
    error_log("[Finalizar Pedido TESTE] Falha crítica, pedido ID não foi gerado.");
    $mysqli->close();
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Falha crítica ao criar o pedido."]);
}

// --- Fecha a conexão ---
// A execução termina dentro de jsonResponse(), então esta linha pode não ser alcançada.
$mysqli->close();
?>