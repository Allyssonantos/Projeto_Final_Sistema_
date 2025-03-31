<?php
session_start(); // Precisa iniciar para poder destruir

header("Access-Control-Allow-Origin: *"); // Ajuste em produção
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Usaremos POST para logout
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// Destruir a sessão
session_unset();    // Remove todas as variáveis da sessão
session_destroy();  // Destrói a sessão em si

// Limpar cookie de sessão (opcional, mas bom)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

http_response_code(200);
echo json_encode(["sucesso" => true, "mensagem" => "Logout realizado com sucesso."]);
?>