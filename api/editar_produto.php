<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");

$conn = new mysqli("localhost", "root", "", "pizzaria");

if ($conn->connect_error) {
    die(json_encode(["mensagem" => "Erro na conexão com o banco de dados"]));
}

$dados = json_decode(file_get_contents("php://input"), true);

if (isset($dados["id"], $dados["nome"], $dados["descricao"], $dados["preco"], $dados["categoria"])) {
    $id = $dados["id"];
    $nome = $conn->real_escape_string($dados["nome"]);
    $descricao = $conn->real_escape_string($dados["descricao"]);
    $preco = floatval($dados["preco"]);
    $categoria = $conn->real_escape_string($dados["categoria"]);

    $sql = "UPDATE produtos SET nome='$nome', descricao='$descricao', preco='$preco', categoria='$categoria' WHERE id='$id'";

    if ($conn->query($sql) === TRUE) {
        echo json_encode(["mensagem" => "Produto atualizado com sucesso!"]);
    } else {
        echo json_encode(["mensagem" => "Erro ao atualizar produto."]);
    }
} else {
    echo json_encode(["mensagem" => "Dados incompletos."]);
}

$conn->close();
?>
