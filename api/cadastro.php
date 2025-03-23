<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type, Access-Control-Allow-Headers, Authorization");

// Inclui a conexão com o banco de dados
require_once "db.php";

// Recebe os dados do JavaScript
$data = json_decode(file_get_contents("php://input"));

if (!isset($data->nome) || !isset($data->email) || !isset($data->senha)) {
    echo json_encode(["erro" => "Preencha todos os campos!"]);
    exit;
}

$nome = $data->nome;
$email = $data->email;
$senha = password_hash($data->senha, PASSWORD_DEFAULT); // Criptografa a senha

// Verifica se o email já existe
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
$stmt->execute([$email]);

if ($stmt->fetch()) {
    echo json_encode(["erro" => "E-mail já cadastrado!"]);
    exit;
}

// Insere no banco de dados
$stmt = $pdo->prepare("INSERT INTO usuarios (nome, email, senha) VALUES (?, ?, ?)");
if ($stmt->execute([$nome, $email, $senha])) {
    echo json_encode(["sucesso" => "Cadastro realizado com sucesso!"]);
} else {
    echo json_encode(["erro" => "Erro ao cadastrar usuário."]);
}
?>