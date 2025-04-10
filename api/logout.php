<?php
// api/logout.php
session_start(); // ESSENCIAL

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true"); // ESSENCIAL
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// Destruir a sessão
session_unset();
session_destroy();

// Limpar cookie (opcional, mas bom)
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $params["path"], $params["domain"], $params["secure"], $params["httponly"]);
}

http_response_code(200);
echo json_encode(["sucesso" => true, "mensagem" => "Logout realizado com sucesso."]);
?>