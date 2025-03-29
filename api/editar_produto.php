<?php

// Configurações de Erro
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definição dos Headers CORS e Content-Type
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
// PUT é semanticamente mais correto para update, mas POST é mais comum/simples de implementar no frontend
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Permitir POST e OPTIONS
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// --- Tratamento da requisição OPTIONS (CORS Preflight) ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- Verificar se o método é POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["sucesso" => false, "mensagem" => "Método HTTP não permitido. Use POST."]);
    exit;
}

// --- Conexão com o Banco de Dados ---
$mysqli = new mysqli("localhost", "root", "", "pizzaria"); // Use suas credenciais

// Verificar erro de conexão
if ($mysqli->connect_error) {
    http_response_code(500);
    error_log("Erro de conexão DB: " . $mysqli->connect_error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno do servidor ao conectar ao banco de dados."]);
    exit;
}

// Definir charset da conexão
$mysqli->set_charset("utf8mb4");

// --- Ler e Processar os Dados da Requisição ---
$dados = json_decode(file_get_contents("php://input"), true);

// Validação robusta dos dados recebidos (incluindo ID)
if (
    !$dados ||
    !isset($dados["id"]) || !filter_var($dados["id"], FILTER_VALIDATE_INT) || intval($dados["id"]) <= 0 || // ID é obrigatório, inteiro e positivo
    !isset($dados["nome"]) || trim($dados["nome"]) === '' ||
    !isset($dados["descricao"]) ||
    !isset($dados["preco"]) || !is_numeric($dados["preco"]) || floatval($dados["preco"]) < 0 ||
    !isset($dados["categoria"]) || !in_array($dados["categoria"], ['pizza', 'bebida'])
   ) {
    http_response_code(400); // Bad Request
    echo json_encode([
        "sucesso" => false,
        "mensagem" => "Dados inválidos ou faltando. Verifique 'id' (inteiro positivo), 'nome' (não vazio), 'descricao', 'preco' (numérico >= 0) e 'categoria' ('pizza' ou 'bebida')."
    ]);
    exit;
}

// Atribuir dados validados a variáveis
$id = intval($dados["id"]); // Garante que é inteiro
$nome = trim($dados["nome"]);
$descricao = trim($dados["descricao"]);
$preco = floatval($dados["preco"]);
$categoria = $dados["categoria"];

// --- Atualização Segura no Banco de Dados usando Prepared Statements ---

// 1. Preparar a SQL query com placeholders (?)
// A ordem dos SETs não importa, mas a ordem dos tipos em bind_param DEVE corresponder
$sql = "UPDATE produtos SET nome=?, descricao=?, preco=?, categoria=? WHERE id=?";
$stmt = $mysqli->prepare($sql);

// Verificar se a preparação falhou
if ($stmt === false) {
    http_response_code(500);
    error_log("Erro ao preparar statement UPDATE: " . $mysqli->error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao preparar a operação de banco de dados."]);
    $mysqli->close();
    exit;
}

// 2. Vincular os valores das variáveis aos placeholders
// Tipos: s=string, d=double(float), i=integer
// A ordem DEVE corresponder aos placeholders: nome(s), descricao(s), preco(d), categoria(s), id(i)
$stmt->bind_param("ssdsi", $nome, $descricao, $preco, $categoria, $id);

// 3. Executar o statement preparado
if ($stmt->execute()) {
    // Verificar se alguma linha foi realmente afetada (o produto com o ID existia?)
    if ($stmt->affected_rows > 0) {
        http_response_code(200); // OK
        echo json_encode(["sucesso" => true, "mensagem" => "Produto (ID: $id) atualizado com sucesso!"]);
    } else {
        // Nenhuma linha afetada - Provavelmente o ID não foi encontrado
        http_response_code(404); // Not Found
        echo json_encode(["sucesso" => false, "mensagem" => "Nenhum produto encontrado com o ID $id para atualizar."]);
    }
} else {
    // Falha na execução
    http_response_code(500); // Internal Server Error
    error_log("Erro ao executar statement UPDATE: " . $stmt->error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro ao tentar atualizar o produto no banco de dados."]);
}

// 4. Fechar o statement
$stmt->close();

// --- Fechar a Conexão com o Banco de Dados ---
$mysqli->close();

?>