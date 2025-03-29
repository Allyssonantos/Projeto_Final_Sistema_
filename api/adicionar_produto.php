<?php

// Configurações de Erro e Headers CORS (iguais aos anteriores)
error_reporting(E_ALL);
ini_set('display_errors', 1);
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["sucesso" => false, "mensagem" => "Método HTTP não permitido."]);
    exit;
}

// --- Constante para o diretório de upload ---
// __DIR__ é o diretório do script atual (api), então voltamos um nível (..)
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/produtos/');
// Cria o diretório se não existir (adiciona verificação de permissão se necessário)
if (!is_dir(UPLOAD_DIR)) {
    mkdir(UPLOAD_DIR, 0775, true); // Permissões podem precisar de ajuste
}

// --- Conexão DB (igual) ---
$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) { /* ... (tratamento de erro igual) ... */ exit;}
$mysqli->set_charset("utf8mb4");

// --- Ler dados do formulário (agora de $_POST e $_FILES) ---

// Validação dos campos de texto (agora em $_POST)
if (
    !isset($_POST["nome"]) || trim($_POST["nome"]) === '' ||
    !isset($_POST["descricao"]) || // Descrição pode ser vazia
    !isset($_POST["preco"]) || !is_numeric($_POST["preco"]) || floatval($_POST["preco"]) < 0 ||
    !isset($_POST["categoria"]) || !in_array($_POST["categoria"], ['pizza', 'bebida'])
   ) {
    http_response_code(400);
    echo json_encode(["sucesso" => false, "mensagem" => "Dados de texto inválidos ou faltando (nome, preco, categoria)."]);
    $mysqli->close();
    exit;
}

$nome = trim($_POST["nome"]);
$descricao = trim($_POST["descricao"]);
$preco = floatval($_POST["preco"]);
$categoria = $_POST["categoria"];
$imagem_nome_final = null; // Inicializa como null

// --- Processamento do Upload da Imagem ---
if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] === UPLOAD_ERR_OK) {
    $fileTmpPath = $_FILES['imagemProduto']['tmp_name'];
    $fileName = $_FILES['imagemProduto']['name'];
    $fileSize = $_FILES['imagemProduto']['size'];
    $fileType = $_FILES['imagemProduto']['type'];
    $fileNameCmps = explode(".", $fileName);
    $fileExtension = strtolower(end($fileNameCmps));

    // Validar extensão
    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($fileExtension, $allowedfileExtensions)) {
        http_response_code(400);
        echo json_encode(["sucesso" => false, "mensagem" => "Tipo de arquivo de imagem inválido. Permitidos: " . implode(', ', $allowedfileExtensions)]);
        $mysqli->close();
        exit;
    }

    // Validar tamanho (ex: max 5MB)
    if ($fileSize > 5 * 1024 * 1024) {
         http_response_code(400);
         echo json_encode(["sucesso" => false, "mensagem" => "Arquivo de imagem muito grande (máx 5MB)."]);
         $mysqli->close();
         exit;
    }

    // Validar se é realmente uma imagem (mais seguro)
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($finfo, $fileTmpPath);
    finfo_close($finfo);
    $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
     if (!in_array($mime, $allowedMimeTypes)) {
         http_response_code(400);
         echo json_encode(["sucesso" => false, "mensagem" => "Tipo MIME do arquivo inválido."]);
         $mysqli->close();
         exit;
     }


    // Gerar nome único para evitar conflitos
    $imagem_nome_final = 'prod_' . uniqid('', true) . '.' . $fileExtension;
    $dest_path = UPLOAD_DIR . $imagem_nome_final;

    // Mover o arquivo para o diretório de uploads
    if (!move_uploaded_file($fileTmpPath, $dest_path)) {
        http_response_code(500);
        error_log("Erro ao mover arquivo para: " . $dest_path);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno ao salvar a imagem.']);
        $mysqli->close();
        exit;
    }
} elseif (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Se um arquivo foi enviado mas houve erro
    http_response_code(400);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no upload da imagem: código ' . $_FILES['imagemProduto']['error']]);
    $mysqli->close();
    exit;
}
// Se nenhum arquivo foi enviado (UPLOAD_ERR_NO_FILE), $imagem_nome_final continua null, o que é ok.

// --- Inserção no Banco de Dados (com imagem_nome) ---
$sql = "INSERT INTO produtos (nome, descricao, preco, categoria, imagem_nome) VALUES (?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($sql);

if ($stmt === false) { /* ... (tratamento de erro igual) ... */ exit; }

// Vincular parâmetros (s=string, d=double, s=string, s=string, s=string[imagem])
$stmt->bind_param("ssdss", $nome, $descricao, $preco, $categoria, $imagem_nome_final);

if ($stmt->execute()) {
    http_response_code(201);
    echo json_encode(["sucesso" => true, "mensagem" => "Produto cadastrado com sucesso!"]);
} else {
    http_response_code(500);
    error_log("Erro ao executar statement INSERT: " . $stmt->error);
    // Se deu erro ao inserir, tentar deletar a imagem que acabou de ser salva (rollback manual)
    if ($imagem_nome_final && file_exists(UPLOAD_DIR . $imagem_nome_final)) {
        unlink(UPLOAD_DIR . $imagem_nome_final);
    }
    echo json_encode(["sucesso" => false, "mensagem" => "Erro ao cadastrar o produto no banco de dados."]);
}

$stmt->close();
$mysqli->close();
?>