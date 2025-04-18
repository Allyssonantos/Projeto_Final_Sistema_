<?php
// api/atualizar_perfil.php
session_start(); // Essencial

error_reporting(E_ALL); ini_set('display_errors', 1); ini_set('log_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// Função Auxiliar JSON Response
function jsonResponse($status_code, $data) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($data);
    exit;
}

// 1. Verificar se está logado
if (!isset($_SESSION['usuario_id'])) {
    jsonResponse(401, ["sucesso" => false, "mensagem" => "Usuário não autenticado."]);
}
$usuario_id = $_SESSION['usuario_id']; // Pega ID da SESSÃO, não do input!

// 2. Verificar Método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(405, ["sucesso" => false, "mensagem" => "Use POST."]);
}

// 3. Ler e Validar Dados JSON
$dados = json_decode(file_get_contents("php://input"), true); // Ler como array

if (
    !isset($dados["nome"]) || trim($dados["nome"]) === '' ||
    !isset($dados["email"]) || !filter_var(trim($dados["email"]), FILTER_VALIDATE_EMAIL) ||
    !isset($dados["endereco"]) || trim($dados["endereco"]) === ''
    // Telefone é opcional
   ) {
    jsonResponse(400, ["sucesso" => false, "mensagem" => "Dados inválidos ou faltando (Nome, Email, Endereço)."]);
}

$nome = trim($dados["nome"]);
$email = trim($dados["email"]);
$endereco = trim($dados["endereco"]);
$telefone = isset($dados["telefone"]) ? trim($dados["telefone"]) : null;

error_log("[Atualizar Perfil] Tentativa para usuário ID: $usuario_id com email: $email");

// 4. Conectar ao DB
$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) { /* ... erro conexão ... */ jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB."]); }
$mysqli->set_charset("utf8mb4");

// 5. VERIFICAR SE O NOVO EMAIL JÁ EXISTE PARA OUTRO USUÁRIO
$sql_check_email = "SELECT id FROM usuarios WHERE email = ? AND id != ?"; // Verifica email em OUTROS usuários
$stmt_check_email = $mysqli->prepare($sql_check_email);
if(!$stmt_check_email) { /* ... erro prepare ... */ $mysqli->close(); exit; }

$stmt_check_email->bind_param("si", $email, $usuario_id);
$stmt_check_email->execute();
$stmt_check_email->store_result();

if ($stmt_check_email->num_rows > 0) {
    // Email já pertence a outro usuário
    error_log("[Atualizar Perfil] Email $email já existe para outro usuário.");
    jsonResponse(409, ["sucesso" => false, "mensagem" => "Este e-mail já está em uso por outra conta."]); // 409 Conflict
}
$stmt_check_email->close();
error_log("[Atualizar Perfil] Verificação de email duplicado passou para: " . $email);

// 6. Preparar e Executar UPDATE
$sql_update = "UPDATE usuarios SET nome = ?, email = ?, endereco = ?, telefone = ? WHERE id = ?";
$stmt_update = $mysqli->prepare($sql_update);
if (!$stmt_update) { error_log("Erro prepare UPDATE perfil: ".$mysqli->error); jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB Prepare Update."]); }

// bind_param: s=string, s, s, s, i=integer
$stmt_update->bind_param("ssssi", $nome, $email, $endereco, $telefone, $usuario_id);

if ($stmt_update->execute()) {
    // Verificar se alguma linha foi realmente alterada
    if ($stmt_update->affected_rows > 0) {
        error_log("[Atualizar Perfil] Perfil atualizado com sucesso para ID: $usuario_id");
        // ATUALIZAR SESSÃO com novos dados se mudaram!
        $_SESSION['usuario_nome'] = $nome;
        $_SESSION['usuario_email'] = $email;
        // Opcional: retornar os dados atualizados
        jsonResponse(200, [
            "sucesso" => true,
            "mensagem" => "Perfil atualizado com sucesso!",
            "usuario_atualizado" => ['nome' => $nome, 'email' => $email, 'endereco' => $endereco, 'telefone' => $telefone]
        ]);
    } else {
        error_log("[Atualizar Perfil] Nenhuma linha afetada para ID: $usuario_id (dados iguais ou ID inválido?)");
        jsonResponse(200, ["sucesso" => true, "mensagem" => "Nenhuma alteração detectada."]); // Ou pode ser 304 Not Modified
    }
} else {
    // Erro ao executar
    error_log("[Atualizar Perfil] Erro execute UPDATE para ID $usuario_id: ".$stmt_update->error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro ao salvar alterações no banco de dados."]);
}

$stmt_update->close();
$mysqli->close();
?>