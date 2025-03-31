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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { /* ... erro 405 ... */ exit; }
if (!isset($_SESSION['usuario_id'])) { /* ... erro 401 ... */ exit; }

$usuario_id = $_SESSION['usuario_id'];
$dados = json_decode(file_get_contents("php://input"), true);
$carrinho = $dados['carrinho'] ?? null; // Espera um array 'carrinho' no JSON

if (!$carrinho || !is_array($carrinho) || empty($carrinho)) {
    http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Carrinho inválido ou vazio."]); exit;
}

// 1. Buscar dados atuais do usuário (nome, email, endereço, telefone)
$sql_user = "SELECT nome, email, endereco, telefone FROM usuarios WHERE id = ?";
// ... (preparar, bind, execute, get_result) ...
$user_data = $result_user->fetch_assoc();
if (!$user_data || empty($user_data['endereco'])) { // Exige endereço cadastrado
     http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Endereço de entrega não encontrado no seu perfil. Atualize seu cadastro."]); exit;
}
$stmt_user->close();

// 2. Calcular Total e Validar Itens (IMPORTANTE: Buscar preços do DB, não confiar nos preços do carrinho vindo do JS!)
$valor_total_calculado = 0;
$ids_produtos_carrinho = array_map(fn($item) => $item['id'], $carrinho); // Pega só os IDs
$placeholders = implode(',', array_fill(0, count($ids_produtos_carrinho), '?')); // Cria ?,?,?
$tipos = str_repeat('i', count($ids_produtos_carrinho)); // Cria iiii...

$sql_produtos = "SELECT id, nome, preco FROM produtos WHERE id IN ($placeholders)";
$stmt_produtos = $mysqli->prepare($sql_produtos);
$stmt_produtos->bind_param($tipos, ...$ids_produtos_carrinho); // Desempacota os IDs
$stmt_produtos->execute();
$result_produtos = $stmt_produtos->get_result();
$produtos_db = [];
while($p = $result_produtos->fetch_assoc()){ $produtos_db[$p['id']] = $p; } // Mapeia ID => produto
$stmt_produtos->close();

$itens_pedido_para_inserir = [];
foreach ($carrinho as $item_carrinho) {
    $produto_id = $item_carrinho['id'];
    $quantidade = intval($item_carrinho['quantidade']);
    if ($quantidade <= 0) continue; // Ignora quantidade inválida

    if (!isset($produtos_db[$produto_id])) {
         http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Produto ID $produto_id não encontrado ou indisponível."]); exit;
    }
    $produto_atual = $produtos_db[$produto_id];
    $preco_unitario_db = floatval($produto_atual['preco']);
    $valor_total_calculado += $preco_unitario_db * $quantidade;
    $itens_pedido_para_inserir[] = [
         'produto_id' => $produto_id,
         'quantidade' => $quantidade,
         'preco_unitario' => $preco_unitario_db,
         'nome_produto' => $produto_atual['nome']
     ];
}

if (empty($itens_pedido_para_inserir)) {
     http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Nenhum item válido no pedido."]); exit;
}

// 3. Iniciar Transação (Opcional, mas recomendado)
$mysqli->begin_transaction();

try {
    // 4. Inserir na tabela `pedidos`
    $sql_pedido = "INSERT INTO pedidos (usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, valor_total, status) VALUES (?, ?, ?, ?, ?, ?, 'Recebido')";
    $stmt_pedido = $mysqli->prepare($sql_pedido);
    // Assumindo que pegamos os dados do usuário em $user_data
    $stmt_pedido->bind_param("issssds",
         $usuario_id,
         $user_data['nome'],
         $user_data['email'],
         $user_data['telefone'],
         $user_data['endereco'], // Endereço do perfil no momento
         $valor_total_calculado
     );
    if(!$stmt_pedido->execute()) throw new Exception("Erro ao inserir pedido: " . $stmt_pedido->error);
    $pedido_id_inserido = $mysqli->insert_id; // Pega o ID do pedido recém-criado
    $stmt_pedido->close();

    // 5. Inserir na tabela `pedido_itens`
    $sql_item = "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario, nome_produto) VALUES (?, ?, ?, ?, ?)";
    $stmt_item = $mysqli->prepare($sql_item);
    foreach ($itens_pedido_para_inserir as $item) {
        $stmt_item->bind_param("iiids",
            $pedido_id_inserido,
            $item['produto_id'],
            $item['quantidade'],
            $item['preco_unitario'],
            $item['nome_produto']
        );
        if(!$stmt_item->execute()) throw new Exception("Erro ao inserir item do pedido: " . $stmt_item->error);
    }
    $stmt_item->close();

    // 6. Commit da Transação
    $mysqli->commit();
    http_response_code(201); // Created
    echo json_encode(["sucesso" => true, "mensagem" => "Pedido realizado com sucesso!", "pedido_id" => $pedido_id_inserido]);

} catch (Exception $e) {
    // 7. Rollback em caso de erro
    $mysqli->rollback();
    http_response_code(500);
    error_log("Erro ao finalizar pedido: " . $e->getMessage());
    echo json_encode(["sucesso" => false, "mensagem" => "Erro ao processar o pedido. Tente novamente."]);
}

$mysqli->close();
?>