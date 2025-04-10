<?php
// api/admin_listar_pedidos.php
// Lista pedidos para visualização no painel de administração.

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
error_log("[Listar Pedidos Admin] Verificando sessão. Conteúdo de \$_SESSION: " . print_r($_SESSION, true));

// !!! EXEMPLO INSEGURO - SUBSTITUA PELA SUA LÓGICA REAL !!!
// Verifica se 'usuario_email' existe na sessão e se é igual ao email do admin
$is_admin_check = (isset($_SESSION['usuario_email']) && $_SESSION['usuario_email'] === 'allyssonsantos487@gmail.com');

// Verifica se o usuário está logado (usuario_id existe) E se passou na verificação de admin
if (!isset($_SESSION['usuario_id']) || !$is_admin_check ) {
    error_log("[Listar Pedidos Admin] Acesso negado. ID Sessão: " . ($_SESSION['usuario_id'] ?? 'NENHUM') . ", Email Sessão: " . ($_SESSION['usuario_email'] ?? 'NENHUM') . ", Check Admin: " . ($is_admin_check ? 'TRUE':'FALSE'));
    jsonResponse(403, ["sucesso" => false, "mensagem" => "Acesso negado. Permissões insuficientes."]);
}
error_log("[Listar Pedidos Admin] Acesso permitido para usuário ID: " . $_SESSION['usuario_id']);
// --------------------------------

// --- Conexão com o Banco de Dados ---
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "pizzaria";

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    error_log("CRÍTICO - DB Connect Error (Admin Pedidos): " . $mysqli->connect_error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno servidor [DB Connect]."]);
}
$mysqli->set_charset("utf8mb4");

// --- Lógica para Buscar Pedidos ---

// Filtro opcional por status vindo da URL (ex: ?status=Recebido)
$status_filtro = $_GET['status'] ?? null;
$status_validos = ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado'];
$pedidos = [];
$params = [];
$types = "";

// Monta a query SQL base
$sql = "SELECT id, usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, data_pedido, valor_total, status
        FROM pedidos";

// Adiciona cláusula WHERE se um status válido foi fornecido
if ($status_filtro && in_array($status_filtro, $status_validos)) {
     $sql .= " WHERE status = ?";
     $params[] = $status_filtro; // Adiciona o status aos parâmetros para bind
     $types .= "s"; // Adiciona 's' (string) aos tipos para bind
     error_log("[Listar Pedidos Admin] Filtrando por status: " . $status_filtro);
} else {
    error_log("[Listar Pedidos Admin] Listando todos os status.");
}

// Adiciona ordenação: primeiro pelos status na ordem lógica, depois pela data mais recente
$sql .= " ORDER BY CASE status
             WHEN 'Recebido' THEN 1
             WHEN 'Em Preparo' THEN 2
             WHEN 'Saiu para Entrega' THEN 3
             WHEN 'Entregue' THEN 4
             WHEN 'Cancelado' THEN 5
             ELSE 6
          END, data_pedido DESC";

// Prepara a query SQL
$stmt = $mysqli->prepare($sql);

if($stmt){
    // Faz o bind dos parâmetros SE houver filtro
    if(!empty($params)){
        // Usa o operador splat (...) para passar os parâmetros do array
        $stmt->bind_param($types, ...$params);
    }

    // Executa a query
    if($stmt->execute()){
        $result = $stmt->get_result();
        // Itera sobre os resultados e adiciona ao array $pedidos
        while($p = $result->fetch_assoc()){
            $p['valor_total'] = floatval($p['valor_total']); // Garante que total seja float
            // Você poderia buscar os itens de cada pedido aqui se quisesse incluí-los na resposta
            // mas isso pode deixar a resposta grande e lenta. É melhor buscar os itens
            // separadamente quando o admin clicar em "Ver Detalhes" de um pedido específico.
            $pedidos[] = $p;
        }
        $result->free(); // Libera resultado
        error_log("[Listar Pedidos Admin] Encontrados " . count($pedidos) . " pedidos.");
        jsonResponse(200, ["sucesso" => true, "pedidos" => $pedidos]); // Retorna sucesso e a lista

    } else {
        // Erro na execução da query
        error_log("Erro ao executar busca de pedidos admin: ".$stmt->error);
        jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno ao buscar pedidos [DB Execute]."]);
    }
    $stmt->close(); // Fecha o statement
} else {
    // Erro na preparação da query
    error_log("Erro ao preparar busca de pedidos admin: ".$mysqli->error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno ao buscar pedidos [DB Prepare]."]);
}

// Fecha a conexão
$mysqli->close();
?>