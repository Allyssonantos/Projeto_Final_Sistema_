<?php
$host = "localhost";  // Altere se necessário
$dbname = "pizzaria"; // Nome do banco de dados
$username = "root";   // Usuário do banco
$password = "";       // Senha do banco (deixe vazio se estiver no XAMPP)

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Erro na conexão com o banco de dados: " . $e->getMessage());
}
?>