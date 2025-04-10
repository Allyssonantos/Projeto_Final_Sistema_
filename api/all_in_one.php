<?php
// api/all_in_one.php
// API combinada para gerenciamento de Produtos e Pedidos (Admin) e Sessão

// Inicia a sessão OBRIGATORIAMENTE no início de tudo
session_start();

// Configurações de relatório de erros (bom para desenvolvimento)
error_reporting(E_ALL);
ini_set('display_errors', 1); // Mude para 0 em produção
ini_set('log_errors', 1);
// ini_set('error_log', '/caminho/absoluto/para/seu/php_error.log'); // Defina se souber

// --- Headers CORS e de Resposta ---
header("Access-Control-Allow-Origin: *"); // AJUSTE EM PRODUÇÃO!
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS"); // Métodos que esta API aceita
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Access-Control-Allow-Credentials: true"); // Essencial para sessões

// --- Trata a requisição OPTIONS (preflight do CORS) ---
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    exit(0);
}

// --- Definições Globais ---
define('UPLOAD_DIR_PRODUTOS', dirname(__DIR__) . '/uploads/produtos/'); // Caminho para uploads

// --- Função Auxiliar para Respostas JSON ---
function jsonResponse($status_code, $data) {
    http_response_code($status_code);
    header('Content-Type: application/json; charset=UTF-8'); // Garante header
    echo json_encode($data);
    exit;
}

// --- Conexão com o Banco de Dados ---
$db_host = "localhost";
$db_user = "root";
$db_pass = "";
$db_name = "pizzaria"; // Verifique o nome do seu banco

$mysqli = new mysqli($db_host, $db_user, $db_pass, $db_name);
if ($mysqli->connect_error) {
    error_log("CRÍTICO - DB Connect Error: " . $mysqli->connect_error);
    jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno servidor [DB Connect]."]);
}
$mysqli->set_charset("utf8mb4");

// --- Função Auxiliar para Verificar Admin ---
function verificarAdmin() {
    error_log("[Verificar Admin] Conteúdo da Sessão: " . print_r($_SESSION, true)); // Log para ver a sessão
    $isAdmin = (isset($_SESSION['usuario_email']) && $_SESSION['usuario_email'] === 'allyssonsantos487@gmail.com');
    if (!isset($_SESSION['usuario_id']) || !$isAdmin) {
        error_log("[Verificar Admin] FALHOU! ID ou Status Admin Inválido.");
        jsonResponse(403, ["sucesso" => false, "mensagem" => "Acesso negado."]);
    }
    error_log("[Verificar Admin] SUCESSO!");
    return true;
}

// --- Roteador Principal ---
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if (empty($action)) {
    jsonResponse(400, ["sucesso" => false, "mensagem" => "Ação não especificada."]);
}

// --- Switch para Ações ---
switch ($action) {

    case 'detalhesPedidoAdmin':
        verificarAdmin(); // Garante que só admin pode ver detalhes
        detalhesPedidoAdmin($mysqli);
        break;

    // Produtos (Admin)
    case 'listarProdutos':
        verificarAdmin(); // Descomente se apenas admin pode listar
        listarProdutos($mysqli);
        break;
    case 'adicionarProduto':
        verificarAdmin();
        adicionarProduto($mysqli);
        break;
    case 'editarProduto':
        verificarAdmin();
        editarProduto($mysqli);
        break;
    case 'excluirProduto':
        verificarAdmin();
        excluirProduto($mysqli);
        break;

    // Pedidos (Admin)
    case 'listarPedidosAdmin':
        verificarAdmin();
        listarPedidosAdmin($mysqli);
        break;
    case 'atualizarStatusPedido':
        verificarAdmin();
        atualizarStatusPedido($mysqli);
        break;

    // Sessão (Geral)
    case 'checkSession':
        checkSession($mysqli); // Não precisa de $mysqli, mas mantém padrão
        break;
    case 'logout':
        logoutUsuario($mysqli); // Não precisa de $mysqli, mas mantém padrão
        break;

    // Ações de Cliente (Login, Cadastro, Perfil, Finalizar Pedido)
    // Podem estar aqui ou em arquivos separados, dependendo da sua organização final
    case 'login':
        // Se você tiver um login.php separado, remova esta linha
        // loginUsuario($mysqli); // Implementação completa de login.php necessária aqui
        jsonResponse(501, ["sucesso" => false, "mensagem" => "Ação 'login' deve ser tratada em endpoint separado."]); // Exemplo
        break;
     case 'registrar':
        // Se você tiver um cadastro.php separado, remova esta linha
        // registrarUsuario($mysqli); // Implementação completa de cadastro.php necessária aqui
         jsonResponse(501, ["sucesso" => false, "mensagem" => "Ação 'registrar' deve ser tratada em endpoint separado."]); // Exemplo
        break;
     case 'perfilUsuario':
        // Se você tiver um perfil_usuario.php separado, remova esta linha
        // perfilUsuario($mysqli); // Implementação completa de perfil_usuario.php necessária aqui
         jsonResponse(501, ["sucesso" => false, "mensagem" => "Ação 'perfilUsuario' deve ser tratada em endpoint separado."]); // Exemplo
        break;
     case 'finalizarPedido':
        // Se você tiver um finalizar_pedido.php separado, remova esta linha
        // finalizarPedido($mysqli); // Implementação completa de finalizar_pedido.php necessária aqui
         jsonResponse(501, ["sucesso" => false, "mensagem" => "Ação 'finalizarPedido' deve ser tratada em endpoint separado."]); // Exemplo
        break;

    default:
        jsonResponse(400, ["sucesso" => false, "mensagem" => "Ação desconhecida: " . htmlspecialchars($action)]);
}

// --- Implementação das Funções de Ação ---

// ==================================
// FUNÇÕES DE PRODUTO
// ==================================

function detalhesPedidoAdmin($mysqli) {
    // Pega o ID do pedido da query string (ex: ?action=detalhesPedidoAdmin&pedido_id=123)
    $pedido_id = filter_input(INPUT_GET, 'pedido_id', FILTER_VALIDATE_INT);

    if (!$pedido_id || $pedido_id <= 0) {
        jsonResponse(400, ["sucesso" => false, "mensagem" => "ID do pedido inválido ou não fornecido."]);
    }
    error_log("[Detalhes Pedido Admin] Buscando detalhes para pedido ID: " . $pedido_id);

    $pedido = null;
    $itens = [];

    // 1. Buscar dados do pedido principal
    $sql_pedido = "SELECT id, usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, data_pedido, valor_total, status, observacoes
                   FROM pedidos WHERE id = ?";
    $stmt_pedido = $mysqli->prepare($sql_pedido);
    if ($stmt_pedido) {
        $stmt_pedido->bind_param("i", $pedido_id);
        if ($stmt_pedido->execute()) {
            $result_pedido = $stmt_pedido->get_result();
            $pedido = $result_pedido->fetch_assoc();
            if ($pedido) {
                 $pedido['valor_total'] = floatval($pedido['valor_total']);
            } else {
                 error_log("[Detalhes Pedido Admin] Pedido ID {$pedido_id} não encontrado.");
                 jsonResponse(404, ["sucesso" => false, "mensagem" => "Pedido não encontrado."]);
            }
        } else { error_log("Erro ao executar busca do pedido $pedido_id: ".$stmt_pedido->error); }
        $stmt_pedido->close();
    } else { error_log("Erro ao preparar busca do pedido $pedido_id: ".$mysqli->error); }

    // Se o pedido foi encontrado, buscar os itens
    if ($pedido) {
         $sql_itens = "SELECT pi.produto_id, pi.quantidade, pi.preco_unitario, pi.nome_produto, p.imagem_nome -- Inclui imagem do produto original
                       FROM pedido_itens pi
                       LEFT JOIN produtos p ON pi.produto_id = p.id -- JOIN para pegar imagem atual do produto
                       WHERE pi.pedido_id = ?";
         $stmt_itens = $mysqli->prepare($sql_itens);
         if($stmt_itens){
             $stmt_itens->bind_param("i", $pedido_id);
             if ($stmt_itens->execute()) {
                 $result_itens = $stmt_itens->get_result();
                 $baseUrlImagem = 'uploads/produtos/'; // Caminho base
                 while($item = $result_itens->fetch_assoc()) {
                     $item['quantidade'] = intval($item['quantidade']);
                     $item['preco_unitario'] = floatval($item['preco_unitario']);
                     // Adiciona URL da imagem do produto (pode ser diferente da do momento da compra)
                     $item['imagem_url_produto_atual'] = (!empty($item['imagem_nome'])) ? $baseUrlImagem . rawurlencode($item['imagem_nome']) : null;
                     unset($item['imagem_nome']); // Remove nome cru se não precisar
                     $itens[] = $item;
                 }
             } else { error_log("Erro ao buscar itens do pedido $pedido_id: ".$stmt_itens->error); }
             $stmt_itens->close();
         } else { error_log("Erro ao preparar busca de itens: ".$mysqli->error); }
    }

    // Se chegou aqui e $pedido é null, houve erro antes
    if (!$pedido) {
         jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro interno ao buscar detalhes do pedido."]);
    }

    // Adiciona os itens encontrados ao array do pedido
    $pedido['itens'] = $itens;

    // Retorna sucesso com os detalhes do pedido e seus itens
    jsonResponse(200, ["sucesso" => true, "pedido" => $pedido]);
}

function listarProdutos($mysqli) {
    // (Cole aqui o código da função listarProdutos do all_in_one.php anterior)
    $sql = "SELECT id, nome, descricao, preco, categoria, imagem_nome FROM produtos ORDER BY categoria, nome";
    $result = $mysqli->query($sql);
    if ($result === false) { error_log("Erro listarProdutos: ".$mysqli->error); jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro ao buscar produtos."]); }
    $produtos = [];
    $baseUrl = 'uploads/produtos/';
    while ($row = $result->fetch_assoc()) {
        $row['preco'] = floatval($row['preco']);
        $row['imagem_url'] = (!empty($row['imagem_nome'])) ? $baseUrl . rawurlencode($row['imagem_nome']) : null;
        $produtos[] = $row;
    }
    $result->free();
    jsonResponse(200, $produtos);
}

function adicionarProduto($mysqli) {
    // (Cole aqui o código da função adicionarProduto do all_in_one.php anterior)
    // Garanta que UPLOAD_DIR_PRODUTOS esteja definido corretamente
     if (!is_dir(UPLOAD_DIR_PRODUTOS)) { if (!mkdir(UPLOAD_DIR_PRODUTOS, 0775, true)) { jsonResponse(500, ['sucesso' => false, 'mensagem' => 'Erro criar dir upload.']); }}
     if (!is_writable(UPLOAD_DIR_PRODUTOS)) { jsonResponse(500, ['sucesso' => false, 'mensagem' => 'Sem permissão escrita upload.']); }

     // Validação $_POST
     if (!isset($_POST["nome"]) || trim($_POST["nome"]) === '' /*|| etc...*/) { jsonResponse(400, ["sucesso" => false, "mensagem" => "Dados inválidos."]); }
     $nome = trim($_POST["nome"]);
     $descricao = trim($_POST["descricao"] ?? '');
     $preco = floatval($_POST["preco"] ?? 0);
     $categoria = $_POST["categoria"] ?? '';
     $imagem_nome_final = null;

     // Processamento $_FILES['imagemProduto'] (com validações, nome único, move_uploaded_file)
     if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] === UPLOAD_ERR_OK) {
         // ... (Lógica completa de validação e move_uploaded_file) ...
         $fileTmpPath = $_FILES['imagemProduto']['tmp_name'];
         $fileName = basename($_FILES['imagemProduto']['name']);
         $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
         // ... (valida extensão, tamanho, MIME) ...
         $imagem_nome_final = 'prod_' . md5(uniqid(rand(), true)) . '.' . $fileExtension;
         $dest_path = UPLOAD_DIR_PRODUTOS . $imagem_nome_final;
         if (!move_uploaded_file($fileTmpPath, $dest_path)) {
              jsonResponse(500, ['sucesso' => false, 'mensagem' => 'Erro ao salvar imagem.']);
         }
     } // ... (tratar outros erros de upload) ...

     // INSERT no DB
     $sql = "INSERT INTO produtos (nome, descricao, preco, categoria, imagem_nome) VALUES (?, ?, ?, ?, ?)";
     $stmt = $mysqli->prepare($sql);
     if (!$stmt) { jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB Prepare."]); }
     $stmt->bind_param("ssdss", $nome, $descricao, $preco, $categoria, $imagem_nome_final);
     if ($stmt->execute()) {
         jsonResponse(201, ["sucesso" => true, "mensagem" => "Produto cadastrado!"]);
     } else {
         // Tenta deletar imagem se INSERT falhou
         if ($imagem_nome_final && file_exists(UPLOAD_DIR_PRODUTOS . $imagem_nome_final)) { unlink(UPLOAD_DIR_PRODUTOS . $imagem_nome_final); }
         error_log("Erro INSERT produto: ".$stmt->error);
         jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB ao cadastrar."]);
     }
     $stmt->close();
}

function editarProduto($mysqli) {
    // (Cole aqui o código da função editarProduto do all_in_one.php anterior)
     // Garanta que UPLOAD_DIR_PRODUTOS esteja definido
     // Valide $_POST['id'] e outros campos
     $id = intval($_POST['id'] ?? 0);
     if ($id <= 0 /*|| outras validações $_POST */) { jsonResponse(400, ["sucesso" => false, "mensagem" => "Dados inválidos."]); }

     $nome = trim($_POST["nome"]);
     // ... (outras variáveis) ...
     $imagem_nome_atualizar = null;
     $imagem_antiga = null;
     $nova_imagem_enviada = false;
     $novo_nome_unico = null;

     // 1. Busca imagem antiga
     $sql_get_old = "SELECT imagem_nome FROM produtos WHERE id = ?";
     $stmt_get_old = $mysqli->prepare($sql_get_old);
     // ... (bind, execute, fetch $imagem_antiga) ...
     $stmt_get_old->close();
     $imagem_nome_atualizar = $imagem_antiga; // Assume manter antiga

     // 2. Processa novo upload $_FILES['imagemProduto'] (se houver)
     if (isset($_FILES['imagemProduto']) && $_FILES['imagemProduto']['error'] === UPLOAD_ERR_OK) {
        // ... (Lógica completa de validação e move_uploaded_file) ...
         $fileTmpPath = $_FILES['imagemProduto']['tmp_name'];
         // ... (valida extensão, tamanho, MIME) ...
         $novo_nome_unico = 'prod_' . md5(uniqid(rand(), true)) . '.' . $fileExtension;
         $dest_path = UPLOAD_DIR_PRODUTOS . $novo_nome_unico;
         if (move_uploaded_file($fileTmpPath, $dest_path)) {
             $imagem_nome_atualizar = $novo_nome_unico; // ATUALIZA O NOME A SALVAR
             $nova_imagem_enviada = true;
         } else {
              jsonResponse(500, ['sucesso' => false, 'mensagem' => 'Erro ao salvar nova imagem.']);
         }
     } // ... (tratar outros erros de upload) ...

     // 3. UPDATE no DB
     $sql_update = "UPDATE produtos SET nome=?, descricao=?, preco=?, categoria=?, imagem_nome=? WHERE id=?";
     $stmt_update = $mysqli->prepare($sql_update);
     if (!$stmt_update) { jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB Prepare."]); }
     // bind_param usa $imagem_nome_atualizar
     $stmt_update->bind_param("ssdssi", $nome, $descricao, $preco, $categoria, $imagem_nome_atualizar, $id);
     if ($stmt_update->execute()) {
         // 4. Deleta imagem antiga se necessário
         if ($nova_imagem_enviada && $imagem_antiga && file_exists(UPLOAD_DIR_PRODUTOS . $imagem_antiga)) {
             unlink(UPLOAD_DIR_PRODUTOS . $imagem_antiga);
         }
         jsonResponse(200, ["sucesso" => true, "mensagem" => "Produto atualizado!"]);
     } else {
         // Tenta deletar nova imagem se UPDATE falhou
         if ($nova_imagem_enviada && $novo_nome_unico && file_exists(UPLOAD_DIR_PRODUTOS . $novo_nome_unico)) {
             unlink(UPLOAD_DIR_PRODUTOS . $novo_nome_unico);
         }
         error_log("Erro UPDATE produto ID $id: ".$stmt_update->error);
         jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB ao atualizar."]);
     }
     $stmt_update->close();
}

function excluirProduto($mysqli) {
    // (Cole aqui o código da função excluirProduto do all_in_one.php anterior)
    $dados = json_decode(file_get_contents("php://input"), true); // Espera JSON para delete
     $id = filter_var($dados['id'] ?? null, FILTER_VALIDATE_INT);
     if (!$id) { jsonResponse(400, ["sucesso" => false, "mensagem" => "ID inválido."]); }

     $imagem_a_deletar = null;

     // 1. Busca imagem
     $sql_get = "SELECT imagem_nome FROM produtos WHERE id = ?";
     $stmt_get = $mysqli->prepare($sql_get);
     // ... (bind, execute, fetch $imagem_a_deletar) ...
     $stmt_get->close();

     // 2. Deleta do DB
     $sql_delete = "DELETE FROM produtos WHERE id = ?";
     $stmt_delete = $mysqli->prepare($sql_delete);
     if (!$stmt_delete) { jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB Prepare."]); }
     $stmt_delete->bind_param("i", $id);
     if ($stmt_delete->execute()) {
         if ($stmt_delete->affected_rows > 0) {
             // 3. Deleta arquivo se existia
             if ($imagem_a_deletar && file_exists(UPLOAD_DIR_PRODUTOS . $imagem_a_deletar)) {
                 unlink(UPLOAD_DIR_PRODUTOS . $imagem_a_deletar);
             }
             jsonResponse(200, ["sucesso" => true, "mensagem" => "Produto excluído!"]);
         } else {
             jsonResponse(404, ["sucesso" => false, "mensagem" => "Produto não encontrado."]);
         }
     } else {
         error_log("Erro DELETE produto ID $id: ".$stmt_delete->error);
         jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB ao excluir."]);
     }
     $stmt_delete->close();
}

// ==================================
// FUNÇÕES DE SESSÃO
// ==================================
function checkSession($mysqli) { // $mysqli não é usado aqui, mas mantém assinatura
    if (isset($_SESSION['usuario_id'])) {
        // !! LÓGICA ADMIN INSEGURA !!
        $is_admin = (isset($_SESSION['usuario_email']) && $_SESSION['usuario_email'] === 'allyssonsantos487@gmail.com');
        jsonResponse(200, [
            "logado" => true,
            "usuario_id" => $_SESSION['usuario_id'],
            "usuario_nome" => $_SESSION['usuario_nome'] ?? 'Usuário',
            "is_admin" => $is_admin
        ]);
    } else {
        jsonResponse(200, ["logado" => false]); // Retorna 200 OK mas indica não logado
    }
}

function logoutUsuario($mysqli) { // $mysqli não é usado aqui
    session_unset();
    session_destroy();
    if (ini_get("session.use_cookies")) { /* ... (limpa cookie) ... */ }
    jsonResponse(200, ["sucesso" => true, "mensagem" => "Logout realizado."]);
}

// ==================================
// FUNÇÕES DE PEDIDOS (Admin)
// ==================================
function listarPedidosAdmin($mysqli) {
    // (Cole aqui o código da função listarPedidosAdmin do all_in_one.php anterior)
     // !! Inclua a função verificarAdmin() no início !!  verificarAdmin();
    

     $status_filtro = $_GET['status'] ?? null;
     $pedidos = [];
     $params = [];
     $types = "";
     $sql = "SELECT id, usuario_id, nome_cliente, email_cliente, telefone_cliente, endereco_entrega, data_pedido, valor_total, status FROM pedidos";
     if ($status_filtro && in_array($status_filtro, ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado'])) {
          $sql .= " WHERE status = ?"; $params[] = $status_filtro; $types .= "s";
     }
     // Ordenação por status e data
     $sql .= " ORDER BY CASE status WHEN 'Recebido' THEN 1 WHEN 'Em Preparo' THEN 2 WHEN 'Saiu para Entrega' THEN 3 WHEN 'Entregue' THEN 4 WHEN 'Cancelado' THEN 5 ELSE 6 END, data_pedido DESC";

     $stmt = $mysqli->prepare($sql);
     if($stmt){
         if(!empty($params)){ $stmt->bind_param($types, ...$params); }
         if($stmt->execute()){
             $result = $stmt->get_result();
             while($p = $result->fetch_assoc()){
                 $p['valor_total'] = floatval($p['valor_total']);
                 $pedidos[] = $p;
             }
         } else { error_log("Erro listarPedidosAdmin: ".$stmt->error); }
         $stmt->close();
     } else { error_log("Erro prepare listarPedidosAdmin: ".$mysqli->error); }
     jsonResponse(200, ["sucesso" => true, "pedidos" => $pedidos]);
}

function atualizarStatusPedido($mysqli) {
    // (Cole aqui o código da função atualizarStatusPedido do all_in_one.php anterior)
     // !! Inclua a função verificarAdmin() no início !!

     $dados = json_decode(file_get_contents("php://input"), true); // Espera JSON
     $pedido_id = filter_var($dados['pedido_id'] ?? null, FILTER_VALIDATE_INT);
     $novo_status = $dados['novo_status'] ?? null;
     $status_validos = ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado'];

     if (!$pedido_id || !$novo_status || !in_array($novo_status, $status_validos)) {
          jsonResponse(400, ["sucesso" => false, "mensagem" => "Dados inválidos."]);
     }

     $sql = "UPDATE pedidos SET status = ? WHERE id = ?";
     $stmt = $mysqli->prepare($sql);
     if(!$stmt) { jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB Prepare."]); }
     $stmt->bind_param("si", $novo_status, $pedido_id);
     if ($stmt->execute()) {
         if ($stmt->affected_rows > 0) {
             jsonResponse(200, ["sucesso" => true, "mensagem" => "Status atualizado."]);
         } else {
             jsonResponse(404, ["sucesso" => false, "mensagem" => "Pedido não encontrado ou status inalterado."]);
         }
     } else {
         error_log("Erro UPDATE status pedido $pedido_id: ".$stmt->error);
         jsonResponse(500, ["sucesso" => false, "mensagem" => "Erro DB ao atualizar status."]);
     }
     $stmt->close();
}

// ==================================
// ADICIONE AQUI AS IMPLEMENTAÇÕES COMPLETAS DAS FUNÇÕES FALTANTES
// como registrarUsuario, loginUsuario, perfilUsuario, finalizarPedido
// Copie e cole o código dos arquivos PHP separados que forneci anteriormente.
// Lembre-se de usar $mysqli->prepare e bind_param em todas as queries.
// ==================================

// --- Fecha a conexão DB no final, se não foi fechada antes ---
// Isso pode não ser alcançado se jsonResponse() for chamado antes.
$mysqli->close();
?>