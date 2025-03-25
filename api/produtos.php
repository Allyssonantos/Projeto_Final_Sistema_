<?php

require_once "db.php";

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

$mysqli = new mysqli("localhost", "root", "", "pizzaria");

if ($mysqli->connect_error) {
    echo json_encode(["status" => "erro", "mensagem" => "Erro ao conectar ao banco"]);
    exit;
}

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "POST") {
    $dados = json_decode(file_get_contents("php://input"), true);

    if (!$dados || !isset($dados["nome"]) || !isset($dados["descricao"]) || !isset($dados["preco"]) || !isset($dados["categoria"])) {
        echo json_encode(["status" => "erro", "mensagem" => "Dados inválidos"]);
        exit;
    }

    $nome = $mysqli->real_escape_string($dados["nome"]);
    $descricao = $mysqli->real_escape_string($dados["descricao"]);
    $preco = floatval($dados["preco"]);
    $categoria = $mysqli->real_escape_string($dados["categoria"]);

    $sql = "INSERT INTO produtos (nome, descricao, preco, categoria) VALUES ('$nome', '$descricao', '$preco', '$categoria')";

    if ($mysqli->query($sql)) {
        echo json_encode(["status" => "sucesso", "mensagem" => "Produto cadastrado"]);
    } else {
        echo json_encode(["status" => "erro", "mensagem" => "Erro ao cadastrar"]);
    }
} elseif ($method === "GET") {
    $result = $mysqli->query("SELECT * FROM produtos");
    $produtos = [];

    while ($row = $result->fetch_assoc()) {
        $produtos[] = $row;
    }

    echo json_encode($produtos);
} else {
    echo json_encode(["status" => "erro", "mensagem" => "Método não permitido"]);
}

$mysqli->close();

?>








