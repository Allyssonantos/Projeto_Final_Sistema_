<?php
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

$conn = new mysqli("localhost", "root", "", "pizzaria");

if ($conn->connect_error) {
    die(json_encode(["mensagem" => "Erro ao conectar ao banco de dados"]));
}

$sql = "SELECT * FROM produtos ORDER BY categoria, nome";
$result = $conn->query($sql);

$produtos = [];

while ($row = $result->fetch_assoc()) {
    $produtos[] = $row;
}

echo json_encode($produtos);

$conn->close();
?>
