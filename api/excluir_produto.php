<?php
$method = $_SERVER["REQUEST_METHOD"];
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");
header("Access-Control-Allow-Methods: POST");
header("Access-Control-Allow-Headers: Content-Type");
// --- Conexão com o Banco de Dados ---
// Use as credenciais corretas e o nome do seu banco
$mysqli = new mysqli("localhost", "root", "", "pizzaria");

// Verificar erro de conexão
if ($mysqli->connect_error) {
    // Enviar resposta de erro JSON e sair
    http_response_code(500); // Internal Server Error
    echo json_encode(["erro" => true, "mensagem" => "Erro interno do servidor: Falha ao conectar ao banco de dados."]);
    exit;
}

// Definir charset da conexão (importante para evitar problemas com caracteres especiais)
$mysqli->set_charset("utf8mb4");

// --- Roteamento baseado no Método HTTP ---


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
