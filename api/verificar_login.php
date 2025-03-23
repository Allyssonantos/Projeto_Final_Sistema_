<?php
session_start();
if (!isset($_SESSION["usuario_id"])) {
    echo json_encode(["status" => "erro", "mensagem" => "Acesso negado"]);
    exit;
}
?>
