<?php
// api/cadastro.php
session_start(); // Inicia sessão (embora não seja usada diretamente aqui, boa prática ter em todas APIs)

// Configurações de Erro e Headers (como nos outros arquivos PHP)
error_reporting(E_ALL); ini_set('display_errors', 1); ini_set('log_errors', 1);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true"); // Necessário se o JS enviar

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); echo json_encode(["sucesso" => false, "mensagem" => "Use POST."]); exit; }

// Função Auxiliar JSON Response
function jsonResponse($status_code, $data) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// Conexão DB
$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) { http_response_code(500); error_log("DB Connect Error: ".$mysqli->connect_error); jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB Connect."]); }
$mysqli->set_charset("utf8mb4");

// Ler dados JSON
$dados = json_decode(file_get_contents("php://input")); // Recebe como objeto

// Validação robusta dos dados recebidos
if (
    !isset($dados->nome) || trim($dados->nome) === '' ||
    !isset($dados->email) || !filter_var(trim($dados->email), FILTER_VALIDATE_EMAIL) ||
    !isset($dados->senha) || strlen(trim($dados->senha)) < 6 || // Verifica comprimento mínimo da senha
    !isset($dados->endereco) || trim($dados->endereco) === '' // Endereço agora é obrigatório
    // Telefone é opcional, não valida aqui obrigatoriedade
   ) {
    jsonResponse(400, ["sucesso" => false, "mensagem" => "Dados inválidos ou faltando. Verifique nome, email (válido), senha (mín. 6 chars) e endereço."]);
}

// Atribuição e Limpeza
$nome = trim($dados->nome);
$email = trim($dados->email);
$senha_raw = trim($dados->senha);
$endereco = trim($dados->endereco);
$telefone = isset($dados->telefone) ? trim($dados->telefone) : null; // Pega telefone se existir, senão null

error_log("[Cadastro API] Tentativa de cadastro para: " . $email);

// Verificar se o E-mail já Existe
$sql_check = "SELECT id FROM usuarios WHERE email = ?";
$stmt_check = $mysqli->prepare($sql_check);
if (!$stmt_check) { error_log("DB Prepare Check Error: ".$mysqli->error); jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB Check Prepare."]); }

$stmt_check->bind_param("s", $email);
$stmt_check->execute();
$stmt_check->store_result(); // Armazena resultado para verificar num_rows

if ($stmt_check->num_rows > 0) {
    // E-mail já existe
    error_log("[Cadastro API] Email já existe: " . $email);
    jsonResponse(409, ["sucesso" => false, "mensagem" => "Este e-mail já está cadastrado."]); // 409 Conflict
}
$stmt_check->close(); // Fecha statement de verificação

// Hash da Senha
$senha_hash = password_hash($senha_raw, PASSWORD_DEFAULT);
if ($senha_hash === false) {
    error_log("[Cadastro API] Erro ao gerar hash da senha para: " . $email);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno ao processar senha."]);
}
error_log("[Cadastro API] Hash da senha gerado para: " . $email);

// Inserir Novo Usuário no Banco
$sql_insert = "INSERT INTO usuarios (nome, email, senha, endereco, telefone) VALUES (?, ?, ?, ?, ?)";
$stmt_insert = $mysqli->prepare($sql_insert);
if (!$stmt_insert) { error_log("DB Prepare Insert Error: ".$mysqli->error); jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB Insert Prepare."]); }

// Bind dos 5 parâmetros (s = string)
$stmt_insert->bind_param("sssss", $nome, $email, $senha_hash, $endereco, $telefone);

if ($stmt_insert->execute()) {
    // Sucesso
    error_log("[Cadastro API] Usuário cadastrado com sucesso: " . $email);
    jsonResponse(201, ["sucesso" => true, "mensagem" => "Cadastro realizado com sucesso!"]); // 201 Created
} else {
    // Falha
    error_log("[Cadastro API] Erro ao executar INSERT para " . $email . ": " . $stmt_insert->error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro ao tentar realizar o cadastro no banco de dados."]);
}

// Fechar statement e conexão
$stmt_insert->close();
$mysqli->close();
?>