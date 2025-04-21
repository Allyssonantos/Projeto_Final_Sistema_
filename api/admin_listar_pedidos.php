<?php
// api/admin_listar_pedidos.php
// Lista pedidos para o admin, incluindo uma string concatenada dos itens.

session_start(); // ESSENCIAL: No topo absoluto!

// --- Configurações Iniciais ---
error_reporting(E_ALL);
ini_set('display_errors', 0); // Mude para 1 em debug, 0 em produção
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
    header('Content-Type: application/json; charset=UTF-8'); // Garante header
    echo json_encode($data);
    exit; // Termina a execução
}

// --- Verificar Método HTTP ---
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    jsonResponse(405, ["sucesso" => false, "mensagem" => "Método HTTP não permitido. Use GET."]);
}

// --- VERIFICAÇÃO DE ADMIN ---
error_log("[Listar Pedidos Admin] Verificando sessão. Sessão: " . print_r($_SESSION, true));
// !!! EXEMPLO INSEGURO - SUBSTITUA PELA SUA LÓGICA REAL !!!
$is_admin_check = (isset($_SESSION['usuario_email']) && $_SESSION['usuario_email'] === 'allyssonsantos487@gmail.com');
if (!isset($_SESSION['usuario_id']) || !$is_admin_check ) {
    error_log("[Listar Pedidos Admin] Acesso negado.");
    jsonResponse(403, ["sucesso" => false, "mensagem" => "Acesso negado. Permissões insuficientes."]);
}
error_log("[Listar Pedidos Admin] Acesso permitido para admin ID: " . $_SESSION['usuario_id']);
// --------------------------------

// --- Conexão com o Banco de Dados ---
$db_host = "localhost"; $db_user = "root"; $db_pass = ""; $db_name = "pizzaria";
$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    error_log("CRÍTICO - DB Connect Error (Admin Pedidos): " . $mysqli->connect_error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno servidor [DB Connect]."]);
}
$mysqli->set_charset("utf8mb4");

// --- Lógica para Buscar Pedidos COM ITENS CONCATENADOS ---

$status_filtro = $_GET['status'] ?? null; // Pega filtro da URL
$status_validos = ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado'];
$pedidos = [];
$params = []; // Parâmetros para bind
$types = ""; // Tipos para bind

// SQL Modificada com LEFT JOIN e GROUP_CONCAT
// CONCAT(pi.quantidade, 'x ', pi.nome_produto): Formata cada item como "Qtdx Nome"
// GROUP_CONCAT(... SEPARATOR '; '): Junta todos os itens de um mesmo pedido em uma única string, separados por "; ".
// Usamos um alias 'itens_pedido' para a string concatenada.
$sql = "SELECT
            p.id, p.usuario_id, p.nome_cliente, p.email_cliente, p.telefone_cliente,
            p.endereco_entrega, p.data_pedido, p.valor_total, p.status, p.observacoes,
            GROUP_CONCAT( CONCAT(pi.quantidade, 'x ', pi.nome_produto) ORDER BY pi.id SEPARATOR '; ') AS itens_pedido
        FROM pedidos p
        LEFT JOIN pedido_itens pi ON p.id = pi.pedido_id"; // LEFT JOIN inclui pedidos mesmo sem itens

// Adiciona filtro WHERE se necessário
if ($status_filtro && in_array($status_filtro, $status_validos)) {
     $sql .= " WHERE p.status = ?"; // Filtra pelo status do pedido principal
     $params[] = $status_filtro;
     $types .= "s";
     error_log("[Listar Pedidos Admin] Filtrando por status: " . $status_filtro);
} else {
    error_log("[Listar Pedidos Admin] Listando todos os status.");
}

// Agrupa por pedido para o GROUP_CONCAT funcionar corretamente para cada pedido
$sql .= " GROUP BY p.id ";

// Adiciona ordenação
$sql .= " ORDER BY CASE p.status
             WHEN 'Recebido' THEN 1
             WHEN 'Em Preparo' THEN 2
             WHEN 'Saiu para Entrega' THEN 3
             WHEN 'Entregue' THEN 4
             WHEN 'Cancelado' THEN 5
             ELSE 6
          END, p.data_pedido DESC";

// Prepara e executa a query
$stmt = $mysqli->prepare($sql);
if($stmt){
    // Faz bind do parâmetro de status, se existir
    if(!empty($params)){
        $stmt->bind_param($types, ...$params);
    }

    if($stmt->execute()){
        $result = $stmt->get_result();
        // Processa os resultados
        while($p = $result->fetch_assoc()){
            $p['valor_total'] = floatval($p['valor_total']);
            // A coluna 'itens_pedido' já vem como string concatenada ou NULL se não houver itens
            $pedidos[] = $p;
        }
        $result->free(); // Libera memória
        error_log("[Listar Pedidos Admin] Encontrados " . count($pedidos) . " pedidos.");
        jsonResponse(200, ["sucesso" => true, "pedidos" => $pedidos]); // Envia resposta

    } else {
        // Erro na execução da query
        error_log("Erro ao executar busca de pedidos/itens admin: ".$stmt->error);
        jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno ao buscar pedidos [DB Execute]."]);
    }
    $stmt->close(); // Fecha statement
} else {
    // Erro na preparação da query
    error_log("Erro ao preparar busca de pedidos/itens admin: ".$mysqli->error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno ao buscar pedidos [DB Prepare]."]);
}

// Fecha a conexão
$mysqli->close();
?>