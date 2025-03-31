<?php
// api/adicionar_produto.php

// --- Configurações Iniciais ---
error_reporting(E_ALL); // Mostrar todos os erros
ini_set('display_errors', 1); // Exibir erros (bom para debug, desative em produção)
ini_set('log_errors', 1); // Habilitar log de erros
// ini_set('error_log', '/caminho/completo/para/php_error.log'); // Defina um caminho se souber

// --- Headers CORS e de Resposta ---
header("Access-Control-Allow-Origin: *"); // Permite acesso de qualquer origem (restrinja em produção!)
header("Content-Type: application/json; charset=UTF-8"); // A resposta SEMPRE será JSON
header("Access-Control-Allow-Methods: POST, OPTIONS"); // Métodos permitidos
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Headers permitidos

// --- Tratamento da Requisição OPTIONS (Preflight) ---
// Necessário para requisições POST com certos headers ou Content-Type (como multipart/form-data implícito)
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Simplesmente envia os headers acima e termina
    exit(0);
}

// --- Verificar Método HTTP ---
// Este endpoint só aceita POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405); // Method Not Allowed
    echo json_encode(["sucesso" => false, "mensagem" => "Método HTTP não permitido. Use POST."]);
    exit;
}

// --- Definições e Verificações do Diretório de Upload ---
// Define o caminho para a pasta de uploads, subindo um nível a partir de /api/
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/produtos/');

// Verifica se o diretório de upload existe, tenta criar se não existir
if (!is_dir(UPLOAD_DIR)) {
    // Tenta criar recursivamente com permissões razoáveis (ajuste se necessário)
    if (!mkdir(UPLOAD_DIR, 0775, true)) {
        http_response_code(500); // Internal Server Error
        error_log("CRÍTICO: Falha ao criar diretório de uploads: " . UPLOAD_DIR);
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno do servidor (Upload Dir Creation).']);
        exit;
    }
}

// Verifica se o diretório tem permissão de escrita (ESSENCIAL!)
if (!is_writable(UPLOAD_DIR)) {
     http_response_code(500); // Internal Server Error
     error_log("CRÍTICO: Diretório de uploads não tem permissão de escrita: " . UPLOAD_DIR);
     echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno do servidor (Upload Dir Permission).']);
     exit;
}

// --- Conexão com o Banco de Dados ---
$mysqli = new mysqli("localhost", "root", "", "pizzaria"); // Substitua se necessário

// Verificar erro de conexão
if ($mysqli->connect_error) {
    http_response_code(500);
    error_log("Erro de Conexão DB: " . $mysqli->connect_error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao conectar ao banco de dados."]);
    exit;
}
$mysqli->set_charset("utf8mb4"); // Definir charset da conexão

// --- Leitura e Validação dos Dados do Formulário ---

// Campos de texto (vem de $_POST pois usamos FormData/multipart)
if (
    !isset($_POST["nome"]) || trim($_POST["nome"]) === '' ||
    !isset($_POST["preco"]) || !is_numeric($_POST["preco"]) || floatval($_POST["preco"]) < 0 ||
    !isset($_POST["categoria"]) || !in_array($_POST["categoria"], ['pizza', 'bebida'])
   ) {
    http_response_code(400); // Bad Request
    echo json_encode(["sucesso" => false, "mensagem" => "Dados inválidos ou faltando (Nome, Preço, Categoria)."]);
    $mysqli->close();
    exit;
}

// Atribui valores validados a variáveis
$nome = trim($_POST["nome"]);
$descricao = isset($_POST["descricao"]) ? trim($_POST["descricao"]) : ''; // Descrição é opcional
$preco = floatval($_POST["preco"]);
$categoria = $_POST["categoria"];
$imagem_nome_final = null; // Variável para armazenar o nome do arquivo salvo no servidor

// --- Processamento do Upload da Imagem (se existir) ---

// Verifica se o campo 'imagemProduto' foi enviado e se não houve erro de upload
if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] === UPLOAD_ERR_OK) {

    $fileTmpPath = $_FILES['imagemProduto']['tmp_name']; // Caminho temporário do arquivo
    $fileName = basename($_FILES['imagemProduto']['name']); // Nome original (com basename para segurança)
    $fileSize = $_FILES['imagemProduto']['size']; // Tamanho em bytes
    $fileType = $_FILES['imagemProduto']['type']; // Tipo MIME enviado pelo navegador (não confiar 100%)
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION)); // Pega a extensão

    // 1. Validar Extensão do Arquivo
    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($fileExtension, $allowedfileExtensions)) {
        http_response_code(400);
        echo json_encode(["sucesso" => false, "mensagem" => "Tipo de arquivo de imagem inválido. Permitidos: " . implode(', ', $allowedfileExtensions)]);
        $mysqli->close();
        exit;
    }

    // 2. Validar Tamanho do Arquivo (ex: máximo 5MB)
    $maxFileSize = 5 * 1024 * 1024; // 5 MB
    if ($fileSize > $maxFileSize) {
         http_response_code(400);
         echo json_encode(["sucesso" => false, "mensagem" => "Arquivo de imagem muito grande (máx 5MB)."]);
         $mysqli->close();
         exit;
     }

    // 3. Validar Tipo MIME Real (mais seguro que apenas extensão)
     if (function_exists('finfo_open')) { // Verifica se a função finfo existe
         $finfo = finfo_open(FILEINFO_MIME_TYPE);
         $mime = finfo_file($finfo, $fileTmpPath);
         finfo_close($finfo);
         $allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
          if (!in_array($mime, $allowedMimeTypes)) {
              http_response_code(400);
              echo json_encode(["sucesso" => false, "mensagem" => "Tipo MIME do arquivo inválido detectado."]);
              $mysqli->close();
              exit;
          }
     } else {
         // Fallback se finfo não estiver disponível (menos seguro, mas evita erro fatal)
         error_log("AVISO: Extensão finfo não está habilitada no PHP. Não foi possível validar o tipo MIME real do arquivo.");
     }


    // 4. Gerar Nome Único para o Arquivo
    // Isso evita sobreescrever arquivos e problemas com nomes estranhos
    $imagem_nome_final = 'prod_' . md5(uniqid(rand(), true)) . '_' . time() . '.' . $fileExtension;
    $dest_path = UPLOAD_DIR . $imagem_nome_final; // Caminho completo de destino

    // 5. Mover o Arquivo Temporário para o Destino Final
    if (!move_uploaded_file($fileTmpPath, $dest_path)) {
        // Falha ao mover o arquivo - geralmente permissão ou caminho inválido
        http_response_code(500);
        error_log("ERRO CRÍTICO: Falha ao executar move_uploaded_file de '$fileTmpPath' para '$dest_path'. Verifique permissões e caminho.");
        echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno do servidor ao salvar a imagem.']);
        $mysqli->close();
        exit;
    }
    // Se chegou aqui, o arquivo foi salvo com sucesso no servidor
     error_log("SUCESSO Upload: Arquivo salvo como '$imagem_nome_final' em " . UPLOAD_DIR);

} elseif (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] !== UPLOAD_ERR_NO_FILE) {
    // Se um arquivo foi selecionado mas houve um erro diferente de "nenhum arquivo"
    $upload_error_code = $_FILES['imagemProduto']['error'];
    http_response_code(400);
    error_log("Erro no Upload (código PHP): " . $upload_error_code);
    echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no upload da imagem (código: ' . $upload_error_code . '). Verifique o arquivo ou tente novamente.']);
    $mysqli->close();
    exit;
}
// Se nenhum arquivo foi enviado (error === UPLOAD_ERR_NO_FILE), $imagem_nome_final continua null, o que é permitido

// --- Inserção no Banco de Dados ---
// Inclui a coluna imagem_nome na query
$sql = "INSERT INTO produtos (nome, descricao, preco, categoria, imagem_nome) VALUES (?, ?, ?, ?, ?)";
$stmt = $mysqli->prepare($sql);

if ($stmt === false) {
    http_response_code(500);
    error_log("Erro ao preparar statement INSERT: " . $mysqli->error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao preparar inserção no DB."]);
    $mysqli->close();
    exit;
}

// Vincular parâmetros: s=string, s=string, d=double, s=string, s=string (imagem_nome pode ser null)
$stmt->bind_param("ssdss", $nome, $descricao, $preco, $categoria, $imagem_nome_final);

// Executar a inserção
if ($stmt->execute()) {
    // Sucesso!
    http_response_code(201); // Código 201 Created para sucesso na criação
    echo json_encode(["sucesso" => true, "mensagem" => "Produto cadastrado com sucesso!"]);
} else {
    // Falha na execução do INSERT
    http_response_code(500);
    error_log("Erro ao executar statement INSERT: " . $stmt->error . " | Dados: nome=$nome, preco=$preco, cat=$categoria, img=$imagem_nome_final");

    // IMPORTANTE: Tentar reverter o upload da imagem se a inserção no banco falhar
    if ($imagem_nome_final && file_exists(UPLOAD_DIR . $imagem_nome_final)) {
        error_log("Tentando deletar imagem '$imagem_nome_final' devido a falha no INSERT.");
        unlink(UPLOAD_DIR . $imagem_nome_final);
    }
    echo json_encode(["sucesso" => false, "mensagem" => "Erro ao salvar informações do produto no banco de dados."]);
}

// Fechar o statement e a conexão
$stmt->close();
$mysqli->close();

?>