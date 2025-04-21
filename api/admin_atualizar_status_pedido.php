<?php
// api/admin_atualizar_status_pedido.php
// Atualiza o status de um pedido específico (requer login de admin).

// Inicia a sessão OBRIGATORIAMENTE no início de tudo
session_start();

// --- Configurações Iniciais ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Mude para 1 em debug, 0 em produção
ini_set('log_errors', 1);
// ini_set('error_log', '/caminho/absoluto/para/seu/php_error.log'); // Defina se souber

// --- Headers CORS e de Resposta ---
header("Access-Control-Allow-Origin: *"); // AJUSTE EM PRODUÇÃO!
header("Content-Type: application/json; charset=UTF-8"); // A resposta SEMPRE será JSON
header("Access-Control-Allow-Credentials: true"); // ESSENCIAL para sessões
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Apenas POST e OPTIONS
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// --- Tratamento da Requisição OPTIONS (Preflight) ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- Função Auxiliar para Respostas JSON ---
function jsonResponse($status_code, $data) {
    http_response_code($status_code);
    // Garante Content-Type mesmo se chamado antes do header principal
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit; // Termina a execução após enviar resposta
}

// --- Verificar Método HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ["sucesso" => false, "mensagem" => "Método HTTP não permitido. Use POST."]);
}

// --- VERIFICAÇÃO DE ADMIN ---
// Log do conteúdo da sessão ANTES da verificação
error_log("[Atualizar Status] Verificando sessão. Sessão: " . print_r($_SESSION, true));
// !!! EXEMPLO INSEGURO - SUBSTITUA PELA SUA LÓGICA REAL !!!
$is_admin_check = (isset($_SESSION['usuario_email']) && $_SESSION['usuario_email'] === 'allyssonsantos487@gmail.com');
if (!isset($_SESSION['usuario_id']) || !$is_admin_check ) {
    error_log("[Atualizar Status] Acesso negado.");
    jsonResponse(403, ["sucesso" => false, "mensagem" => "Acesso negado. Permissões insuficientes."]);
}
error_log("[Atualizar Status] Acesso permitido para admin ID: " . $_SESSION['usuario_id']);
// --------------------------------

// --- Ler e Validar Dados JSON da Requisição ---
$dados = json_decode(file_get_contents("php://input"), true); // Ler como array associativo

$pedido_id = filter_var($dados['pedido_id'] ?? null, FILTER_VALIDATE_INT); // Valida se é inteiro
$novo_status = $dados['novo_status'] ?? null;
$status_validos = ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado']; // Status permitidos

// Verifica se os dados recebidos são válidos
if (!$pedido_id || $pedido_id <= 0 || !$novo_status || !in_array($novo_status, $status_validos)) {
     $log_msg = "[Atualizar Status] Dados inválidos recebidos. ID: " . ($pedido_id ?? 'NULO') . ", Status: " . ($novo_status ?? 'NULO');
     error_log($log_msg);
     jsonResponse(400, ["sucesso" => false, "mensagem" => "Dados inválidos (ID do pedido ou status). Status permitidos: ".implode(', ', $status_validos)]);
}
error_log("[Atualizar Status] Tentando atualizar Pedido ID: $pedido_id para Status: '$novo_status'");

// --- Conexão com o Banco de Dados ---
$db_host = "localhost"; $db_user = "root"; $db_pass = ""; $db_name = "pizzaria";
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    error_log("CRÍTICO - DB Connect Error (Atualizar Status): " . $mysqli->connect_error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno servidor [DB Connect]."]);
}
$mysqli->set_charset("utf8mb4");

// --- Preparar e Executar o UPDATE ---
$sql = "UPDATE pedidos SET status = ? WHERE id = ?";
$stmt = $mysqli->prepare($sql);

// Verificar falha na preparação
if ($stmt === false) {
    error_log("[Atualizar Status] Erro ao preparar UPDATE para ID $pedido_id: ".$mysqli->error);
    $mysqli->close(); // Fecha conexão antes de sair
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno ao preparar atualização no DB."]);
}

// Vincular parâmetros (s = string para status, i = integer para id)
$stmt->bind_param("si", $novo_status, $pedido_id);

// Executar a query
if ($stmt->execute()) {
    // Verificar quantas linhas foram realmente alteradas
    $affected_rows = $stmt->affected_rows;
    error_log("[Atualizar Status] UPDATE executado para ID $pedido_id. Linhas afetadas: " . $affected_rows);

    if ($affected_rows > 0) {
        // Sucesso, pelo menos uma linha foi atualizada
        jsonResponse(200, ["sucesso" => true, "mensagem" => "Status do pedido ID $pedido_id atualizado para '$novo_status'."]);
    } else {
        // Nenhuma linha afetada: ID não existe OU status já era o mesmo.
        // Verificar se o pedido existe para dar mensagem mais precisa.
        $check_sql = "SELECT id FROM pedidos WHERE id = ?";
        $check_stmt = $mysqli->prepare($check_sql);
        $check_stmt->bind_param("i", $pedido_id);
        $check_stmt->execute();
        $check_stmt->store_result();
        $pedido_existe = $check_stmt->num_rows > 0;
        $check_stmt->close();

        if ($pedido_existe) {
             jsonResponse(200, ["sucesso" => true, "mensagem" => "Nenhuma alteração necessária (status do pedido ID $pedido_id já era '$novo_status')."]);
        } else {
             jsonResponse(404, ["sucesso" => false, "mensagem" => "Pedido com ID $pedido_id não encontrado."]);
        }
    }
} else {
    // Erro durante a execução do UPDATE
    error_log("[Atualizar Status] ERRO ao executar UPDATE para ID $pedido_id: ".$stmt->error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro no banco de dados ao tentar atualizar o status."]);
}

// --- Fechar Recursos ---
$stmt->close();
$mysqli->close();
error_log("[Atualizar Status] Fim da execução para Pedido ID: $pedido_id");

?>