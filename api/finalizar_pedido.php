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

if (isset($dados["itens"]) && isset($dados["total"])) {
    $itens = json_encode($dados["itens"]); // Armazena os itens do pedido como JSON
    $total = floatval($dados["total"]);

    $sql = "INSERT INTO pedidos (itens, total) VALUES (?, ?)";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("sd", $itens, $total);

    if ($stmt->execute()) {
        echo json_encode(["mensagem" => "Pedido realizado com sucesso!"]);
    } else {
        echo json_encode(["mensagem" => "Erro ao finalizar pedido."]);
    }

    $stmt->close();
} else {
    echo json_encode(["mensagem" => "Dados do pedido incompletos."]);
}

$conn->close();
?>
