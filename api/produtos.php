<?php
header("Content-Type: application/json");
include("../db.php"); // Arquivo de conexão com o banco

$method = $_SERVER["REQUEST_METHOD"];

if ($method === "POST") {
    // Recebe os dados do frontend
    $dados = json_decode(file_get_contents("php://input"), true);
    
    if (!isset($dados["nome"], $dados["descricao"], $dados["preco"], $dados["categoria"])) {
        echo json_encode(["success" => false, "message" => "Dados inválidos!"]);
        exit;
    }

    $nome = $dados["nome"];
    $descricao = $dados["descricao"];
    $preco = $dados["preco"];
    $categoria = $dados["categoria"];

    // Insere no banco
    $stmt = $pdo->prepare("INSERT INTO produtos (nome, descricao, preco, categoria) VALUES (?, ?, ?, ?)");
    $resultado = $stmt->execute([$nome, $descricao, $preco, $categoria]);

    echo json_encode(["success" => $resultado]);
    exit;
}

if ($method === "GET") {
    // Busca os produtos no banco
    $stmt = $pdo->query("SELECT * FROM produtos");
    $produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($produtos);
    exit;
}

echo json_encode(["success" => false, "message" => "Método não permitido"]);
exit;
?>