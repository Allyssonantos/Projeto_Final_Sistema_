<?php
// api/login.php

// Iniciar a sessão ANTES de qualquer output. Essencial para $_SESSION funcionar.
session_start();

// --- Configurações Iniciais ---
error_reporting(E_ALL); // Mostrar todos os erros durante o desenvolvimento
ini_set('display_errors', 1); // Exibir erros na tela (mude para 0 ou Off em produção)
ini_set('log_errors', 1); // Habilitar log de erros em arquivo
// ini_set('error_log', '/caminho/absoluto/para/seu/php_error.log'); // Defina um caminho se souber

// --- Headers CORS e de Resposta ---
// Permite que seu frontend (rodando em outra porta ou domínio, talvez) acesse a API
header("Access-Control-Allow-Origin: *"); // !! Restrinja para seu domínio frontend em produção !! Ex: http://localhost:xxxx ou https://seusite.com
header("Content-Type: application/json; charset=UTF-8"); // Informa que a resposta será JSON
header("Access-Control-Allow-Credentials: true"); // NECESSÁRIO para permitir o envio/recebimento de cookies de sessão
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Métodos HTTP permitidos para este endpoint
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization, X-Requested-With"); // Headers que o frontend pode enviar

// --- Tratamento da Requisição OPTIONS (Preflight) ---
// O navegador envia OPTIONS antes de POST com 'credentials' ou certos headers
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Apenas retorna os headers acima e sai
    exit(0);
}

// --- Verificar Método HTTP ---
// Garante que apenas requisições POST sejam processadas
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["status" => "erro", "mensagem" => "Método HTTP não permitido. Use POST."]);
    exit;
}

// --- Conexão com o Banco de Dados ---
// Substitua pelas suas credenciais reais se forem diferentes
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "pizzaria";

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);

// Verificar erro de conexão
if ($mysqli->connect_error) {
    http_response_code(500); // Internal Server Error
    // Logar o erro detalhado para o desenvolvedor/administrador
    error_log("Erro CRÍTICO de Conexão DB (login.php): " . $mysqli->connect_error);
    // Enviar mensagem genérica para o usuário final
    echo json_encode(["status" => "erro", "mensagem" => "Erro interno do servidor [DB Connect]."]);
    exit;
}
// Definir o charset da conexão para evitar problemas com caracteres especiais
$mysqli->set_charset("utf8mb4");
error_log("[Login API] Conexão DB estabelecida."); // Log de sucesso (opcional)

// --- Ler e Processar os Dados da Requisição JSON ---
// Ler o corpo da requisição
$json_payload = file_get_contents("php://input");
// Decodificar o JSON para um array associativo
$data = json_decode($json_payload, true);

// Validar dados recebidos (email e senha existem e não estão vazios)
if (
    !isset($data["email"]) || !filter_var(trim($data["email"]), FILTER_VALIDATE_EMAIL) || // Valida formato do email
    !isset($data["senha"]) || trim($data["senha"]) === ''
   ) {
    http_response_code(400); // Bad Request
    echo json_encode(["status" => "erro", "mensagem" => "Dados inválidos ou faltando. Verifique e-mail (válido) e senha."]);
    $mysqli->close(); // Fecha conexão
    exit;
}

$email = trim($data["email"]);
$senha_digitada = $data["senha"]; // Pega a senha como foi digitada (NÃO faça trim aqui)
error_log("[Login API] Tentativa de login para email: " . $email);

// --- Buscar Usuário e Verificar Senha (Usando MySQLi Prepared Statements) ---
$sql = "SELECT id, nome, email, senha FROM usuarios WHERE email = ?"; // Busca o HASH da senha

$stmt = $mysqli->prepare($sql);

// Verificar se a preparação da query falhou
if ($stmt === false) {
    http_response_code(500); // Internal Server Error
    error_log("Erro ao preparar statement SELECT (login.php): " . $mysqli->error);
    echo json_encode(["status" => "erro", "mensagem" => "Erro interno do servidor [DB Prepare]."]);
    $mysqli->close();
    exit;
}

// Vincular o parâmetro (email) à query preparada
$stmt->bind_param("s", $email); // "s" indica que $email é uma string

// Executar a query preparada
if (!$stmt->execute()) {
    http_response_code(500); // Internal Server Error
    error_log("Erro ao executar statement SELECT (login.php): " . $stmt->error);
    echo json_encode(["status" => "erro", "mensagem" => "Erro interno do servidor [DB Execute]."]);
    $stmt->close();
    $mysqli->close();
    exit;
}

// Obter o resultado da query
$result = $stmt->get_result();

// Verificar se o usuário foi encontrado (deve haver exatamente 1 resultado)
if ($result->num_rows === 1) {
    // Usuário encontrado, pegar os dados
    $user = $result->fetch_assoc();
    $senha_hash_bd = $user["senha"]; // Pega o HASH da senha armazenado no banco

    error_log("[Login API] Usuário encontrado para email: " . $email . ". Verificando senha...");

    // *** VERIFICA A SENHA USANDO password_verify() ***
    // Compara a senha digitada pelo usuário com o hash armazenado no banco
    if (password_verify($senha_digitada, $senha_hash_bd)) {
        // Senha CORRETA! Login bem-sucedido.

        // Regenera o ID da sessão - medida de segurança importante após login bem-sucedido
        session_regenerate_id(true);

        // Armazena informações do usuário na sessão PHP
        $_SESSION['usuario_id'] = $user['id'];
        $_SESSION['usuario_nome'] = $user['nome'];
        $_SESSION['usuario_email'] = $user['email']; // Pode ser útil guardar o email

        // !! Exemplo de verificação de Admin - NÃO SEGURO !!
        // Você DEVE ter uma forma melhor de identificar admins (ex: coluna 'role' no DB)
        $_SESSION['is_admin'] = ($user['email'] === 'allyssonsantos487@gmail.com');
        error_log("[Login API] Verificando se {$user['email']} é admin... Resultado: " . ($_SESSION['is_admin'] ? 'SIM' : 'NÃO')); // Log para debug

        error_log("[Login API] Login BEM-SUCEDIDO para usuário ID: " . $user['id'] . ", Email: " . $email);

        http_response_code(200); // OK
        echo json_encode([
            "status" => "sucesso", // Mantido "status" para compatibilidade com seu JS atual
            "sucesso" => true,     // Adicionado "sucesso" para consistência
            "mensagem" => "Login realizado com sucesso!",
            "usuario" => [ // Enviar dados básicos do usuário é útil para o frontend
                "id" => $user['id'],
                "nome" => $user['nome'],
                "is_admin" => $_SESSION['is_admin'] // Informa se é admin
            ]
        ]);

    } else {
        // Senha INCORRETA
        error_log("[Login API] Senha INCORRETA para email: " . $email);
        http_response_code(401); // Unauthorized - Código apropriado para credenciais inválidas
        echo json_encode(["status" => "erro", "sucesso" => false, "mensagem" => "Senha incorreta."]);
    }
} else {
    // Usuário NÃO encontrado com o e-mail fornecido
    error_log("[Login API] Usuário NÃO ENCONTRADO para email: " . $email);
    http_response_code(404); // Not Found (ou 401 para não dar pista se o email existe ou não)
    echo json_encode(["status" => "erro", "sucesso" => false, "mensagem" => "Usuário não encontrado."]);
}

$telefoneRecebido = $dados->telefone; // Ex: "(62) 91234-5678"
$telefoneLimpo = preg_replace('/\D/', '', $telefoneRecebido); // Resultado: "62912345678"
// Salve $telefoneLimpo no banco

// Fechar o statement e a conexão com o banco de dados
$stmt->close();
$mysqli->close();
error_log("[Login API] Conexão DB fechada.");

?>