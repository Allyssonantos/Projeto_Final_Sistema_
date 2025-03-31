<?php
// Iniciar a sessão ANTES de qualquer output, se você pretende usá-la
// Se a API for stateless (sem sessão), remova as linhas de session_start e $_SESSION
session_start();

// Configurações de Erro
error_reporting(E_ALL);
ini_set('display_errors', 1); // 1 para debug, 0 ou log em produção

// Definição dos Headers CORS e Content-Type
header("Access-Control-Allow-Origin: *"); // Restrinja em produção
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Permitir POST e OPTIONS
// Importante: Se seu JS envia algum header customizado ou Authorization, adicione aqui
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With");
// Permitir que cookies de sessão sejam enviados/recebidos (se usar sessões)
header("Access-Control-Allow-Credentials: true");



// --- Tratamento da requisição OPTIONS (CORS Preflight) ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- Verificar se o método é POST ---
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["status" => "erro", "mensagem" => "Método HTTP não permitido. Use POST."]);
    exit;
}

// --- Conexão com o Banco de Dados ---
$mysqli = new mysqli("localhost", "root", "", "pizzaria"); // Suas credenciais

// Verificar erro de conexão
if ($mysqli->connect_error) {
    http_response_code(500);
    error_log("Erro de conexão DB: " . $mysqli->connect_error);
    echo json_encode(["status" => "erro", "mensagem" => "Erro interno do servidor: Falha ao conectar ao banco de dados."]);
    exit;
}

// Dentro do if (password_verify(...)) { ... }
session_regenerate_id(true); // Segurança!
$_SESSION['usuario_id'] = $user['id'];
$_SESSION['usuario_nome'] = $user['nome'];
// Opcional: $_SESSION['is_admin'] = ($user['email'] === 'admin@example.com'); // Defina uma flag de admin

http_response_code(200);
echo json_encode([
    "status" => "sucesso",
    "mensagem" => "Login realizado com sucesso!",
    "usuario" => ["id" => $user['id'], "nome" => $user['nome']] // Retorna dados básicos
]);

// Definir charset da conexão
$mysqli->set_charset("utf8mb4");

// --- Ler e Processar os Dados da Requisição ---
$data = json_decode(file_get_contents("php://input"), true); // Usar true para array associativo é mais comum

// Validar dados recebidos
if (
    !isset($data["email"]) || !filter_var($data["email"], FILTER_VALIDATE_EMAIL) ||
    !isset($data["senha"]) || trim($data["senha"]) === ''
   ) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "erro", "mensagem" => "Dados inválidos ou faltando. Verifique email (válido) e senha."]);
    $mysqli->close();
    exit;
}

$email = trim($data["email"]);
$senha_digitada = $data["senha"]; // Não fazer trim aqui, a senha pode ter espaços intencionais

// --- Buscar Usuário e Verificar Senha (Usando MySQLi Prepared Statements) ---
$sql = "SELECT id, nome, senha FROM usuarios WHERE email = ?"; // Seleciona o HASH da senha
$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    error_log("Erro ao preparar statement SELECT: " . $mysqli->error);
    echo json_encode(["status" => "erro", "mensagem" => "Erro interno ao preparar consulta."]);
    $mysqli->close();
    exit;
}

$stmt->bind_param("s", $email);

if (!$stmt->execute()) {
    http_response_code(500);
    error_log("Erro ao executar statement SELECT: " . $stmt->error);
    echo json_encode(["status" => "erro", "mensagem" => "Erro interno ao buscar usuário."]);
    $stmt->close();
    $mysqli->close();
    exit;
}

$result = $stmt->get_result();

if ($result->num_rows === 1) {
    // Usuário encontrado
    $user = $result->fetch_assoc();
    $senha_hash_bd = $user["senha"]; // Pega o hash armazenado no banco

    // *** VERIFICA A SENHA USANDO password_verify() ***
    if (password_verify($senha_digitada, $senha_hash_bd)) {
        // Senha correta! Login bem-sucedido.

        // Regenera o ID da sessão para segurança após login
        session_regenerate_id(true);

        // Armazena informações do usuário na sessão (se for usar sessões)
        $_SESSION["usuario_id"] = $user["id"];
        $_SESSION["usuario_nome"] = $user["nome"];
        // Você pode querer armazenar um nível de permissão também (ex: $_SESSION['is_admin'] = true/false)

        http_response_code(200); // OK
        echo json_encode([
            "status" => "sucesso",
            "mensagem" => "Login realizado com sucesso!",
            "usuario" => [ // Opcional: retornar alguns dados do usuário (NÃO a senha)
                "id" => $user["id"],
                "nome" => $user["nome"]
                // Adicione aqui se o usuário é admin, etc., se necessário no frontend
            ]
        ]);

    } else {
        // Senha incorreta
        http_response_code(401); // Unauthorized
        echo json_encode(["status" => "erro", "mensagem" => "Senha incorreta."]);
    }
} else {
    // Usuário não encontrado
    http_response_code(404); // Not Found (ou 401 para não dar pista se o email existe)
    echo json_encode(["status" => "erro", "mensagem" => "Usuário não encontrado."]);
}

// Fechar statement e conexão
$stmt->close();
$mysqli->close();

?>