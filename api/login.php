<?php
require_once "db.php";

header("Content-Type: application/json");

$data = json_decode(file_get_contents("php://input"), true);

if (!isset($data["email"]) || !isset($data["senha"])) {
    echo json_encode(["status" => "erro", "mensagem" => "Dados incompletos"]);
    exit;
}

$email = $data["email"];
$senha = $data["senha"];

// Busca o usuário no banco de dados
$stmt = $conn->prepare("SELECT id, nome, senha FROM usuarios WHERE email = ?");
$stmt->bind_param("s", $email);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    $user = $result->fetch_assoc();
    
    // Verifica a senha
    if (password_verify($data["senha"], $user["senha"])) {
        session_start();
        $_SESSION["usuario_id"] = $user["id"];
        $_SESSION["usuario_nome"] = $user["nome"];
        
        echo json_encode(["status" => "sucesso", "mensagem" => "Login realizado"]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => "Senha incorreta"]);
    }
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Usuário não encontrado"]);
}

$stmt->close();
$conn->close();
?>