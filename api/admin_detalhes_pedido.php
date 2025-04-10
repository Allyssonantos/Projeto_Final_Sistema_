<?php
// api/admin_detalhes_pedido.php
// Busca detalhes completos de um pedido específico para o admin.

// Inicia a sessão OBRIGATORIAMENTE no início de tudo
session_start();

// --- Configurações Iniciais ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // Mude para 0 em produção
ini_set('log_errors', 1);
// ini_set('error_log', '/caminho/absoluto/para/seu/php_error.log'); // Defina se souber

// --- Headers CORS e de Resposta ---
header("Access-Control-Allow-Origin: *"); // AJUSTE EM PRODUÇÃO!
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true"); // ESSENCIAL para sessões
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Apenas GET e OPTIONS
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// --- Tratamento da Requisição OPTIONS (Preflight) ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- Função Auxiliar para Respostas JSON ---
function jsonResponse($status_code, $data) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// --- Verificar Método HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ["sucesso" => false, "mensagem" => "Método HTTP não permitido. Use GET."]);
}

// --- VERIFICAÇÃO DE ADMIN ---
// Log do conteúdo da sessão ANTES da verificação
error_log("[Detalhes Pedido Admin] Verificando sessão. Conteúdo de \$_SESSION: " . print_r($_SESSION, true));

// !!! EXEMPLO INSEGURO - SUBSTITUA PELA SUA LÓGICA REAL !!!
$is_admin_check = (isset($_SESSION['usuario_email']) && $_SESSION['usuario_email'] === 'allyssonsantos487@gmail.com');

if (!isset($_SESSION['usuario_id']) || !$is_admin_check ) {
    error_log("[Detalhes Pedido Admin] Acesso negado. ID Sessão: " . ($_SESSION['usuario_id'] ?? 'NENHUM') . ", Email Sessão: " . ($_SESSION['usuario_email'] ?? 'NENHUM') . ", Check Admin: " . ($is_admin_check ? 'TRUE':'FALSE'));
    jsonResponse(403, ["sucesso" => false, "mensagem" => "Acesso negado. Permissões insuficientes."]);
}
// --------------------------------

// --- Obter e Validar ID do Pedido da URL ---
$pedido_id = filter_input(INPUT_GET, 'pedido_id', FILTER_VALIDATE_INT);

if (!$pedido_id || $pedido_id <= 0) {
    jsonResponse(400, ["sucesso" => false, "mensagem" => "ID do pedido inválido ou não fornecido na URL (ex: ?pedido_id=123)."]);
}
error_log("[Detalhes Pedido Admin] Buscando detalhes para pedido ID: " . $pedido_id);

// --- Conexão com o Banco de Dados ---
$db_host = "localhost"; $db_user = "root"; $db_pass = ""; $db_name = "pizzaria";
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    error_log("CRÍTICO - DB Connect Error (Detalhes Pedido): " . $mysqli->connect_error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno servidor [DB Connect]."]);
}
$mysqli->set_charset("utf8mb4");

// --- Variáveis para armazenar os resultados ---
$pedido = null;
$itens = [];

// --- 1. Buscar dados do pedido principal ---
$sql_pedido = "SELECT id, usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, data_pedido, valor_total, status, observacoes
               FROM pedidos WHERE id = ?";
$stmt_pedido = $mysqli->prepare($sql_pedido);

if ($stmt_pedido) {
    $stmt_pedido->bind_param("i", $pedido_id); // "i" para integer
    if ($stmt_pedido->execute()) {
        $result_pedido = $stmt_pedido->get_result();
        $pedido = $result_pedido->fetch_assoc(); // Pega a linha do pedido

        if ($pedido) {
            // Formata o valor total como float
             $pedido['valor_total'] = floatval($pedido['valor_total']);
             error_log("[Detalhes Pedido Admin] Dados do pedido principal encontrados para ID: " . $pedido_id);
        } else {
             // Pedido com o ID fornecido não foi encontrado
             error_log("[Detalhes Pedido Admin] Pedido ID {$pedido_id} não encontrado no banco.");
             jsonResponse(404, ["sucesso" => false, "mensagem" => "Pedido não encontrado."]);
        }
    } else {
        // Erro ao executar a busca do pedido
        error_log("Erro ao executar SELECT do pedido $pedido_id: ".$stmt_pedido->error);
        jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno ao buscar dados do pedido."]);
    }
    $stmt_pedido->close(); // Fecha o statement do pedido
} else {
    // Erro ao preparar a query do pedido
    error_log("Erro ao preparar SELECT do pedido $pedido_id: ".$mysqli->error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno ao preparar busca do pedido."]);
}

// --- 2. Buscar os itens do pedido (somente se o pedido foi encontrado) ---
$sql_itens = "SELECT pi.produto_id, pi.quantidade, pi.preco_unitario, pi.nome_produto, p.imagem_nome
              FROM pedido_itens pi
              LEFT JOIN produtos p ON pi.produto_id = p.id -- Pega a imagem ATUAL do produto
              WHERE pi.pedido_id = ?";
$stmt_itens = $mysqli->prepare($sql_itens);

if($stmt_itens){
    $stmt_itens->bind_param("i", $pedido_id);
    if ($stmt_itens->execute()) {
        $result_itens = $stmt_itens->get_result();
        $baseUrlImagem = 'uploads/produtos/'; // Caminho base para imagens (relativo ao HTML)

        // Itera sobre cada item encontrado
        while($item = $result_itens->fetch_assoc()) {
            $item['quantidade'] = intval($item['quantidade']);
            $item['preco_unitario'] = floatval($item['preco_unitario']);
            // Adiciona a URL da imagem atual do produto (pode ser null)
            $item['imagem_url_produto_atual'] = (!empty($item['imagem_nome']))
                                               ? $baseUrlImagem . rawurlencode($item['imagem_nome'])
                                               : null;
            unset($item['imagem_nome']); // Remove a coluna original do nome da imagem se não for mais necessária
            $itens[] = $item; // Adiciona o item formatado ao array de itens
        }
        error_log("[Detalhes Pedido Admin] Encontrados " . count($itens) . " itens para o pedido ID: " . $pedido_id);
    } else {
        // Erro ao executar a busca dos itens
        error_log("Erro ao buscar itens do pedido $pedido_id: ".$stmt_itens->error);
        // Decide se retorna erro 500 ou continua sem os itens (vamos continuar por enquanto)
         $pedido['itens_erro'] = "Erro ao carregar itens."; // Informa no JSON que houve erro
    }
    $stmt_itens->close(); // Fecha o statement dos itens
} else {
    // Erro ao preparar a query dos itens
    error_log("Erro ao preparar busca de itens para pedido $pedido_id: ".$mysqli->error);
     $pedido['itens_erro'] = "Erro interno ao buscar itens.";
}

// Adiciona o array de itens (pode estar vazio ou conter erro) ao objeto do pedido
$pedido['itens'] = $itens;

// --- Fecha a conexão e envia a resposta ---
$mysqli->close();

// Retorna sucesso (código 200) com os detalhes do pedido e seus itens
jsonResponse(200, ["sucesso" => true, "pedido" => $pedido]);
?>