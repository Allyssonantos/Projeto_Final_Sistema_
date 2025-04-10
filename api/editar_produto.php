<?php
// api/editar_produto.php
session_start(); // ESSENCIAL

error_reporting(E_ALL); ini_set('display_errors', 1); ini_set('log_errors', 1);

header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Credentials: true"); // ESSENCIAL
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') { exit(0); }

// --- !!! VERIFICAÇÃO DE ADMIN !!! ---
$is_admin_check = (isset($_SESSION['usuario_email']) && $_SESSION['usuario_email'] === 'allyssonsantos487@gmail.com'); // !! SUBSTITUA !!
if (!isset($_SESSION['usuario_id']) || !$is_admin_check ) {
     http_response_code(403); echo json_encode(["sucesso" => false, "mensagem" => "Acesso negado."]); exit;
}

error_log("--- [EDITAR PRODUTO] Requisição recebida ---");

if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    error_log("[EDITAR PRODUTO] Requisição OPTIONS recebida. Saindo.");
    exit(0);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    error_log("[EDITAR PRODUTO] ERRO: Método não é POST. Método: " . $_SERVER['REQUEST_METHOD']);
    http_response_code(405); echo json_encode(["sucesso" => false, "mensagem" => "Método HTTP não permitido."]); exit;
}

define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/produtos/');
error_log("[EDITAR PRODUTO] UPLOAD_DIR definido como: " . UPLOAD_DIR);
error_log("[EDITAR PRODUTO] UPLOAD_DIR é gravável? " . (is_writable(UPLOAD_DIR) ? 'SIM' : 'NÃO!'));

$mysqli = new mysqli("localhost", "root", "", "pizzaria");
if ($mysqli->connect_error) {
    http_response_code(500); error_log("[EDITAR PRODUTO] ERRO DB Connect: ".$mysqli->connect_error);
    echo json_encode(["sucesso" => false, "mensagem" => "Erro interno: Falha DB."]); exit;
}
$mysqli->set_charset("utf8mb4");
error_log("[EDITAR PRODUTO] Conexão DB OK.");

// Log dos dados recebidos
error_log("[EDITAR PRODUTO] Dados \$_POST: " . print_r($_POST, true));
error_log("[EDITAR PRODUTO] Dados \$_FILES: " . print_r($_FILES, true));


// Validação dos dados de $_POST
if (!isset($_POST["id"]) || !filter_var($_POST["id"], FILTER_VALIDATE_INT) || intval($_POST["id"]) <= 0 /*|| ... outras validações ...*/) {
    error_log("[EDITAR PRODUTO] ERRO: Dados POST inválidos ou faltando.");
    http_response_code(400); echo json_encode(["sucesso" => false,"mensagem" => "Dados inválidos ou faltando."]); $mysqli->close(); exit;
}
// Assumindo que outras validações estão OK
$id = intval($_POST["id"]);
$nome = isset($_POST["nome"]) ? trim($_POST["nome"]) : '';
$descricao = isset($_POST["descricao"]) ? trim($_POST["descricao"]) : '';
$preco = isset($_POST["preco"]) ? floatval($_POST["preco"]) : 0;
$categoria = isset($_POST["categoria"]) ? $_POST["categoria"] : '';
error_log("[EDITAR PRODUTO] Dados POST validados para ID: " . $id);

$imagem_nome_atualizar = null;
$imagem_antiga = null;
$nova_imagem_enviada = false;
$novo_nome_unico = null;

// 1. Buscar nome da imagem antiga
error_log("[EDITAR PRODUTO ID $id] Buscando imagem antiga...");
$sql_get_old = "SELECT imagem_nome FROM produtos WHERE id = ?";
$stmt_get_old = $mysqli->prepare($sql_get_old);
if ($stmt_get_old) {
    $stmt_get_old->bind_param("i", $id);
    if($stmt_get_old->execute()){
        $result_old = $stmt_get_old->get_result();
        if ($row_old = $result_old->fetch_assoc()) {
            $imagem_antiga = $row_old['imagem_nome'];
            error_log("[EDITAR PRODUTO ID $id] Imagem antiga encontrada: " . ($imagem_antiga ?? 'Nenhuma'));
        } else {
            error_log("[EDITAR PRODUTO ID $id] AVISO: Produto não encontrado ao buscar imagem antiga.");
            // Pode ser um problema se o ID for inválido, mas a validação inicial deve pegar
        }
    } else {
        error_log("[EDITAR PRODUTO ID $id] ERRO ao executar SELECT da imagem antiga: ".$stmt_get_old->error);
    }
    $stmt_get_old->close();
} else {
     error_log("[EDITAR PRODUTO ID $id] ERRO ao preparar SELECT da imagem antiga: ".$mysqli->error);
}
$imagem_nome_atualizar = $imagem_antiga; // Assume que manterá a antiga inicialmente
error_log("[EDITAR PRODUTO ID $id] Valor inicial de \$imagem_nome_atualizar: " . ($imagem_nome_atualizar ?? 'NULL'));


// 2. Processar novo upload (SE houver)
error_log("[EDITAR PRODUTO ID $id] Verificando \$_FILES['imagemProduto']...");
if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] === UPLOAD_ERR_OK) {
    error_log("[EDITAR PRODUTO ID $id] Novo arquivo detectado! Erro: 0 (UPLOAD_ERR_OK)");
    $fileTmpPath = $_FILES['imagemProduto']['tmp_name'];
    $fileName = basename($_FILES['imagemProduto']['name']);
    $fileSize = $_FILES['imagemProduto']['size'];
    $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    error_log("[EDITAR PRODUTO ID $id] Detalhes do arquivo: Tmp={$fileTmpPath}, Nome={$fileName}, Tamanho={$fileSize}, Ext={$fileExtension}");

    // Validar extensão, tamanho, MIME (adicione as validações completas aqui)
    $allowedfileExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
     if (!in_array($fileExtension, $allowedfileExtensions)) {
          error_log("[EDITAR PRODUTO ID $id] ERRO: Extensão inválida.");
          http_response_code(400); echo json_encode(["sucesso" => false, "mensagem" => "Tipo de arquivo inválido."]); $mysqli->close(); exit;
      }
     // Adicione validação de tamanho e MIME aqui também...

    // Gerar nome único
    $novo_nome_unico = 'prod_' . md5(uniqid(rand(), true)) . '.' . $fileExtension;
    $dest_path = UPLOAD_DIR . $novo_nome_unico;
    error_log("[EDITAR PRODUTO ID $id] Nome único gerado: {$novo_nome_unico}. Destino: {$dest_path}");

    // Mover o arquivo
    $move_result = move_uploaded_file($fileTmpPath, $dest_path);
    if ($move_result) {
        error_log("[EDITAR PRODUTO ID $id] SUCESSO: move_uploaded_file para {$dest_path}");
        $imagem_nome_atualizar = $novo_nome_unico; // <<< PONTO CRÍTICO: Atualiza o nome a ser salvo no DB
        $nova_imagem_enviada = true;
        error_log("[EDITAR PRODUTO ID $id] \$imagem_nome_atualizar DEFINIDO PARA NOVO NOME: " . ($imagem_nome_atualizar ?? 'ERRO'));
    } else {
        error_log("[EDITAR PRODUTO ID $id] ERRO: move_uploaded_file FALHOU!");
        // Considerar se deve parar ou continuar sem a imagem nova
        http_response_code(500); echo json_encode(['sucesso' => false, 'mensagem' => 'Erro interno ao salvar nova imagem. Verifique permissões/caminho.']); $mysqli->close(); exit;
    }

} elseif (isset($_FILES['imagemProduto'])) {
     // Se existe a chave mas deu erro no upload
     $upload_error_code = $_FILES['imagemProduto']['error'];
     error_log("[EDITAR PRODUTO ID $id] AVISO: \$_FILES['imagemProduto'] existe, mas com erro: " . $upload_error_code);
     if ($upload_error_code !== UPLOAD_ERR_NO_FILE) { // Ignora "nenhum arquivo enviado"
         http_response_code(400); echo json_encode(['sucesso' => false, 'mensagem' => 'Erro no upload da nova imagem: código ' . $upload_error_code]); $mysqli->close(); exit;
     } else {
          error_log("[EDITAR PRODUTO ID $id] Nenhum arquivo novo enviado (UPLOAD_ERR_NO_FILE). Manterá imagem antiga (se houver).");
     }
} else {
     error_log("[EDITAR PRODUTO ID $id] Nenhuma informação de arquivo em \$_FILES['imagemProduto']. Manterá imagem antiga (se houver).");
}
error_log("[EDITAR PRODUTO ID $id] VALOR FINAL de \$imagem_nome_atualizar ANTES do UPDATE: " . ($imagem_nome_atualizar ?? 'NULL'));


// 3. Atualizar o banco de dados
error_log("[EDITAR PRODUTO ID $id] Preparando UPDATE no banco...");
$sql_update = "UPDATE produtos SET nome=?, descricao=?, preco=?, categoria=?, imagem_nome=? WHERE id=?";
$stmt_update = $mysqli->prepare($sql_update);
if ($stmt_update === false) {
    error_log("[EDITAR PRODUTO ID $id] ERRO ao preparar UPDATE: ".$mysqli->error);
    http_response_code(500); echo json_encode(["sucesso" => false, "mensagem" => "Erro DB (prepare)."]); $mysqli->close(); exit;
}

error_log("[EDITAR PRODUTO ID $id] Fazendo bind_param com imagem_nome: " . ($imagem_nome_atualizar ?? 'NULL'));
$stmt_update->bind_param("ssdssi", $nome, $descricao, $preco, $categoria, $imagem_nome_atualizar, $id);

if ($stmt_update->execute()) {
    $affected_rows = $stmt_update->affected_rows; // Pega quantas linhas foram afetadas
    error_log("[EDITAR PRODUTO ID $id] SUCESSO: UPDATE executado. Linhas afetadas: " . $affected_rows);

    // 4. Deletar imagem antiga SE uma nova foi enviada com sucesso E a antiga existia
    if ($nova_imagem_enviada && $imagem_antiga && $imagem_antiga !== $imagem_nome_atualizar) {
        $caminho_img_antiga = UPLOAD_DIR . $imagem_antiga;
        error_log("[EDITAR PRODUTO ID $id] Tentando deletar imagem antiga: " . $caminho_img_antiga);
        if (file_exists($caminho_img_antiga)) {
            if (unlink($caminho_img_antiga)) {
                error_log("[EDITAR PRODUTO ID $id] SUCESSO: Imagem antiga deletada.");
            } else {
                error_log("[EDITAR PRODUTO ID $id] AVISO: Falha ao deletar imagem antiga (unlink falhou).");
            }
        } else {
             error_log("[EDITAR PRODUTO ID $id] AVISO: Imagem antiga não encontrada para deletar.");
        }
    }

    if ($affected_rows > 0) {
         http_response_code(200);
         echo json_encode(["sucesso" => true, "mensagem" => "Produto atualizado com sucesso!"]);
    } else {
         // UPDATE executou mas não alterou nada (talvez ID não exista ou dados eram iguais)
         http_response_code(200); // Ainda OK, mas informa que nada mudou
         echo json_encode(["sucesso" => true, "mensagem" => "Produto atualizado (nenhuma alteração detectada ou ID não encontrado)."]);
    }

} else {
    error_log("[EDITAR PRODUTO ID $id] ERRO ao executar UPDATE: " . $stmt_update->error);
    // Tentar deletar a NOVA imagem se o UPDATE falhou
    if ($nova_imagem_enviada && $novo_nome_unico && file_exists(UPLOAD_DIR . $novo_nome_unico)) {
        error_log("[EDITAR PRODUTO ID $id] Tentando reverter upload da nova imagem devido a erro no DB...");
        unlink(UPLOAD_DIR . $novo_nome_unico);
    }
    http_response_code(500); echo json_encode(["sucesso" => false, "mensagem" => "Erro DB ao atualizar."]);
}

$stmt_update->close();
$mysqli->close();
error_log("--- [EDITAR PRODUTO ID $id] Fim da execução ---");
?>