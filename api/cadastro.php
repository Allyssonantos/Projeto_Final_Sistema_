<?php

// Configurações de Erro
error_reporting(E_ALL);
ini_set('display_errors', 1); // Mantenha 1 para depuração, mude para 0 ou log em produção

// Definição dos Headers CORS e Content-Type
header("Access-Control-Allow-Origin: *"); // Restrinja em produção
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Permitir POST e OPTIONS
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");

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
$mysqli = new mysqli("localhost", "root", "", "pizzaria"); // Suas credenciais

// Verificar erro de conexão
if ($mysqli->connect_error) {
    http_response_code(500);
    error_log("Erro de conexão DB: " . $mysqli->connect_error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno do servidor: Falha ao conectar ao banco de dados."]);
    exit;
}

// Definir charset da conexão
$mysqli->set_charset("utf8mb4");

// --- Ler e Processar os Dados da Requisição ---
// Decodificar para objeto (acesso com ->) ou array (acesso com [''])
// O código original usava objeto, vamos manter, mas array é comum também.
$dados = json_decode(file_get_contents("php://input"));

// Validar dados recebidos (verificar se existem e não estão vazios/apenas espaços)
if (
    !isset($dados->nome) || trim($dados->nome) === '' ||
    !isset($dados->email) || !filter_var($dados->email, FILTER_VALIDATE_EMAIL) || // Valida formato do email
    !isset($dados->senha) || trim($dados->senha) === ''
   ) {
    http_response_code(400); // Bad Request
    echo json_encode(["sucesso" => false, "mensagem" => "Dados inválidos ou faltando. Verifique nome, email (válido) e senha."]);
    $mysqli->close(); // Fechar conexão
    exit;
}

// Atribuir a variáveis (trimming para remover espaços extras)
$nome = trim($dados->nome);
$email = trim($dados->email);
$senha_raw = trim($dados->senha); // Senha antes de hashear

// --- Verificar se o E-mail já Existe (Usando MySQLi Prepared Statements) ---
$sql_check = "SELECT id FROM usuarios WHERE email = ?";
$stmt_check = $mysqli->prepare($sql_check);

if ($stmt_check === false) {
    http_response_code(500);
    error_log("Erro ao preparar statement SELECT: " . $mysqli->error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao verificar email."]);
    $mysqli->close();
    exit;
}

$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$stmt_check->store_result(); // Necessário para verificar num_rows

if ($stmt_check->num_rows > 0) {
    // E-mail já existe
    http_response_code(409); // Conflict
    echo json_encode(["sucesso" => false, "mensagem" => "Este e-mail já está cadastrado."]);
    $stmt_check->close();
    $mysqli->close();
    exit;
}
// Fechar o statement de verificação
$stmt_check->close();

// --- Inserir Novo Usuário (Somente se o email não existir) ---

// Criptografar a senha ANTES de inserir
$senha_hash = password_hash($senha_raw, PASSWORD_DEFAULT);

if ($senha_hash === false) {
     http_response_code(500);
     error_log("Erro ao gerar hash da senha.");
     echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao processar senha."]);
     $mysqli->close();
     exit;
}

// Preparar a query de inserção
$sql_insert = "INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)";
$stmt_insert = $mysqli->prepare($sql_insert);

if ($stmt_insert === false) {
    http_response_code(500);
    error_log("Erro ao preparar statement INSERT: " . $mysqli->error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao preparar cadastro."]);
    $mysqli->close();
    exit;
}

// Vincular parâmetros (s = string)
$stmt_insert->bind_param("sss", $nome, $email, $senha_hash);

// Executar a inserção
if ($stmt_insert->execute()) {
    // Sucesso
    http_response_code(201); // Created
    echo json_encode(["sucesso" => true, "mensagem" => "Cadastro realizado com sucesso!"]);
} else {
    // Falha
    http_response_code(500);
    error_log("Erro ao executar statement INSERT: " . $stmt_insert->error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro ao tentar realizar o cadastro."]);
}

// Fechar o statement de inserção
$stmt_insert->close();

// --- Fechar a Conexão ---
$mysqli->close();

?>