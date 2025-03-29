<?php


// Remover require_once "db.php"; se a conexão for definida manualmente abaixo.
// Se db.php DEVE fornecer a conexão, remova a linha $mysqli = new mysqli(...)
// e use a variável de conexão de db.php (ex: $mysqli = $conn;)

// Configurações de Erro (Ideal para desenvolvimento, logar em produção)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Definição dos Headers CORS e Content-Type
header("Access-Control-Allow-Origin: *"); // Em produção, restrinja para o domínio do seu frontend
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Methods: POST, GET, OPTIONS"); // Adicionar OPTIONS
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With"); // Headers comuns

// --- Tratamento da requisição OPTIONS (CORS Preflight) ---
// Deve vir ANTES de qualquer outra saída ou processamento
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    // Apenas envia os headers e sai. O navegador entende.
    exit(0);
}

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
$method = $_SERVER["REQUEST_METHOD"];

if ($method === "POST") {
    // --- Lógica para ADICIONAR um produto ---

    // Ler e decodificar o corpo da requisição JSON
    $dados = json_decode(file_get_contents("php://input"), true);

    // Validação básica dos dados recebidos
    if (
        !$dados ||
        !isset($dados["nome"]) || empty(trim($dados["nome"])) || // Verifica se não está vazio
        !isset($dados["descricao"]) || // Descrição pode ser vazia, então não verificamos empty()
        !isset($dados["preco"]) || !is_numeric($dados["preco"]) || floatval($dados["preco"]) < 0 || // Verifica se é numérico e não negativo
        !isset($dados["categoria"]) || !in_array($dados["categoria"], ['pizza', 'bebida']) // Verifica se a categoria é válida
       ) {
        http_response_code(400); // Bad Request
        echo json_encode(["sucesso" => false, "mensagem" => "Dados inválidos ou faltando. Verifique nome, preço (numérico >= 0) e categoria ('pizza' ou 'bebida')."]);
        exit;
    }

    // Limpar e preparar dados (floatval já trata o preço)
    $nome = trim($dados["nome"]);
    $descricao = trim($dados["descricao"]);
    $preco = floatval($dados["preco"]);
    $categoria = $dados["categoria"]; // Já validada com in_array

    // --- USAR PREPARED STATEMENTS ---
    $sql = "INSERT INTO produtos (nome, descricao, preco, categoria) VALUES (?, ?, ?, ?)";
    $stmt = $mysqli->prepare($sql);

    if ($stmt === false) {
        http_response_code(500);
        error_log("Erro ao preparar statement: " . $mysqli->error); // Logar o erro real
        echo json_encode(["sucesso" => false, "mensagem" => "Erro interno ao preparar a inserção."]);
        exit;
    }

    // Vincular parâmetros (s = string, d = double/float)
    // Os tipos devem corresponder às colunas na sua tabela
    $stmt->bind_param("ssds", $nome, $descricao, $preco, $categoria);

    // Executar o statement
    if ($stmt->execute()) {
        http_response_code(201); // 201 Created - Padrão para criação bem-sucedida
        echo json_encode(["sucesso" => true, "mensagem" => "Produto cadastrado com sucesso!"]);
    } else {
        http_response_code(500);
        error_log("Erro ao executar statement: " . $stmt->error); // Logar o erro real
        echo json_encode(["sucesso" => false, "mensagem" => "Erro ao cadastrar o produto no banco de dados."]);
    }

    // Fechar o statement
    $stmt->close();

} elseif ($method === "GET") {
    // --- Lógica para BUSCAR todos os produtos ---

    // Query para buscar todos os produtos
    $result = $mysqli->query("SELECT id, nome, descricao, preco, categoria FROM produtos ORDER BY categoria, nome"); // Ordenar fica melhor

    if ($result === false) {
        http_response_code(500);
        error_log("Erro ao executar query SELECT: " . $mysqli->error);
        echo json_encode(["erro" => true, "mensagem" => "Erro ao buscar produtos."]);
        exit;
    }

    $produtos = [];
    // Buscar todos os resultados como um array associativo
    while ($row = $result->fetch_assoc()) {
        // Opcional: garantir que o preço seja float no JSON
        $row['preco'] = floatval($row['preco']);
        $produtos[] = $row;
    }

    // Fechar o result set
    $result->free();

    // Retornar o array de produtos como JSON
    // Se não houver produtos, retornará um array vazio [], o que é correto.
    http_response_code(200); // OK
    echo json_encode($produtos);

} else {
    // Método não permitido
    http_response_code(405); // Method Not Allowed
    echo json_encode(["sucesso" => false, "mensagem" => "Método HTTP não permitido."]);
}

// Fechar a conexão com o banco de dados
$mysqli->close();

?>