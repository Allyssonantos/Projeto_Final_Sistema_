<?php
// api/excluir_produto.php

error_reporting(E_ALL);
ini_set('display_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Usando POST por simplicidade
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "mensagem" => "Método HTTP não permitido."]);
    exit;
}

define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/produtos/');

$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) { /* ... (tratamento erro conexão) ... */ exit; }
$mysqli->set_charset("utf8mb4");

$dados = json_decode(file_get_contents("php://input"), true);

// Validar ID
if (!isset($dados["id"]) || !filter_var($dados["id"], FILTER_VALIDATE_INT) || intval($dados["id"]) <= 0) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "mensagem" => "ID inválido ou faltando."]);
    $mysqli->close(); exit;
}
$id = intval($dados["id"]);
$imagem_a_deletar = null;

// 1. Buscar nome da imagem antes de deletar o registro
$sql_get = "SELECT imagem_nome FROM produtos WHERE id = ?";
$stmt_get = $mysqli->prepare($sql_get);
if ($stmt_get) {
    $stmt_get->bind_param("i", $id);
    $stmt_get->execute();
    $result = $stmt_get->get_result();
    if ($row = $result->fetch_assoc()) {
        $imagem_a_deletar = $row['imagem_nome'];
    }
    $stmt_get->close();
} else {
    error_log("Erro ao preparar SELECT imagem para exclusão: ".$mysqli->error);
    // Continua mesmo assim para tentar deletar o registro
}

// 2. Deletar registro do banco
$sql_delete = "DELETE FROM produtos WHERE id = ?";
$stmt_delete = $mysqli->prepare($sql_delete);
if ($stmt_delete === false) { /* ... (erro prepare) ... */ exit; }

$stmt_delete->bind_param("i", $id);

if ($stmt_delete->execute()) {
    // Verificar se alguma linha foi afetada
    if ($stmt_delete->affected_rows > 0) {
        // 3. Deletar arquivo de imagem SE o registro foi deletado E havia imagem
        if ($imagem_a_deletar) {
            $caminho_imagem = UPLOAD_DIR . $imagem_a_deletar;
            if (file_exists($caminho_imagem)) {
                if (!unlink($caminho_imagem)) {
                    error_log("Falha ao deletar arquivo de imagem: " . $caminho_imagem);
                    // Não retorna erro ao usuário por isso, mas loga
                }
            }
        }
        http_response_code(200);
        echo json_encode(["sucesso" => true, "mensagem" => "Produto excluído com sucesso!"]);
    } else {
        // Nenhuma linha afetada - ID não encontrado
        http_response_code(404);
        echo json_encode(["sucesso" => false, "mensagem" => "Produto não encontrado com o ID fornecido."]);
    }
} else {
    http_response_code(500);
    error_log("Erro DELETE DB: " . $stmt_delete->error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro DB ao excluir."]);
}

$stmt_delete->close();
$mysqli->close();
?>