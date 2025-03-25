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

if (isset($dados["id"])) {
    $id = intval($dados["id"]);

    $sql = "DELETE FROM produtos WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id);

    if ($stmt->execute()) {
        echo json_encode(["mensagem" => "Produto excluído com sucesso!"]);
    } else {
        echo json_encode(["mensagem" => "Erro ao excluir produto."]);
    }

    $stmt->close();
} else {
    echo json_encode(["mensagem" => "ID do produto não informado."]);
}

$conn->close();
?>
