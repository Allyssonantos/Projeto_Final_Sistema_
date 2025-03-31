<?php
// api/produtos.php

// --- Configurações Iniciais ---
error_reporting(E_ALL);
ini_set('display_errors', 1); // 0 ou log em produção
ini_set('log_errors', 1);
// ini_set('error_log', '/caminho/completo/para/php_error.log');

// --- Headers CORS e de Resposta ---
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: GET, OPTIONS"); // Permitir APENAS GET e OPTIONS neste endpoint
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

// --- Tratamento da Requisição OPTIONS (Preflight) ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- Verificar Método HTTP ---
// Este endpoint só aceita GET
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["sucesso" => false, "mensagem" => "Método HTTP não permitido. Use GET."]);
    exit;
}

// --- Conexão com o Banco de Dados ---
$mysqli = new mysqli("localhost", "root", "", "pizzaria"); // Substitua se necessário

// Verificar erro de conexão
if ($mysqli->connect_error) {
    http_response_code(500);
    error_log("Erro de Conexão DB (produtos.php): " . $mysqli->connect_error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao conectar ao banco de dados."]);
    exit;
}
$mysqli->set_charset("utf8mb4");

// --- Lógica para Buscar Todos os Produtos ---

// Query SQL para buscar todos os produtos, incluindo a coluna imagem_nome
// Ordenar por categoria e depois por nome melhora a exibição
$sql = "SELECT id, nome, descricao, preco, categoria, imagem_nome FROM produtos ORDER BY categoria, nome";

$result = $mysqli->query($sql);

// Verificar se a query falhou
if ($result === false) {
    http_response_code(500);
    error_log("Erro ao executar query SELECT produtos: " . $mysqli->error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao buscar produtos."]);
    $mysqli->close();
    exit;
}

// Array para armazenar os produtos formatados
$produtos = [];

// Define o caminho base para as imagens.
// IMPORTANTE: Este caminho deve ser relativo à RAIZ DO SEU SITE vista pelo navegador,
// ou seja, o caminho que o HTML (index.html/admin.html) usará para encontrar as imagens.
// Se as pastas 'uploads' e 'index.html' estão ambas dentro de 'pizzaria_express', este caminho está OK.
$baseUrlImagem = 'uploads/produtos/';

// Itera sobre cada linha (produto) retornada do banco
while ($row = $result->fetch_assoc()) {
    // Garante que o preço seja um número float no JSON
    $row['preco'] = floatval($row['preco']);

    // Cria a chave 'imagem_url' no array $row
    // Se 'imagem_nome' existe e não está vazio, constrói a URL completa
    // Caso contrário, define como null
    $row['imagem_url'] = (!empty($row['imagem_nome']))
                        ? $baseUrlImagem . rawurlencode($row['imagem_nome']) // rawurlencode para nomes com espaços/caracteres especiais
                        : null;

    // Adiciona o produto formatado (com imagem_url) ao array final
    $produtos[] = $row;
}

// Liberar a memória do resultado da query
$result->free();

// Fechar a conexão com o banco
$mysqli->close();

// --- Enviar Resposta JSON ---
http_response_code(200); // OK
// Codifica o array $produtos (que pode estar vazio se não houver produtos) em JSON e envia
echo json_encode($produtos);

?>