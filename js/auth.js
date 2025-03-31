// js/auth.js
document.addEventListener("DOMContentLoaded", function () {
    const API_BASE_URL = "http://localhost/pizzaria_express/api";

    // --- Lógica de Cadastro ---
    const cadastroForm = document.getElementById("cadastroForm");
    const mensagemCadastro = document.getElementById("mensagem-cadastro");

    if (cadastroForm && mensagemCadastro) {
        cadastroForm.addEventListener("submit", async function (e) {
            e.preventDefault();
            const nome = document.getElementById("nome").value;
            const email = document.getElementById("email").value;
            const senha = document.getElementById("senha").value;
            // PEGAR NOVOS CAMPOS
            const endereco = document.getElementById("endereco")?.value || ''; // Usa ?. se o campo não existir ainda
            const telefone = document.getElementById("telefone")?.value || '';

            mensagemCadastro.textContent = "";
            mensagemCadastro.className = "mensagem";

            try {
                const response = await fetch(`${API_BASE_URL}/cadastro.php`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    // ENVIAR NOVOS CAMPOS
                    body: JSON.stringify({ nome, email, senha, endereco, telefone })
                });

                // Ler como JSON primeiro
                const result = await response.json();

                if (!response.ok) { // Checa status HTTP
                     throw new Error(result.mensagem || `Erro HTTP ${response.status}`);
                }

                // Usar a estrutura de resposta padrão {sucesso: true/false, mensagem: ...}
                if (result.sucesso) {
                    mensagemCadastro.textContent = result.mensagem || "Cadastro realizado com sucesso!";
                    mensagemCadastro.classList.add("success");
                    setTimeout(() => { window.location.href = "login.html"; }, 2000);
                } else {
                     // Trata especificamente erro 409 (Conflict - email existe) se a API retornar
                     if (response.status === 409) {
                         mensagemCadastro.textContent = result.mensagem || "Este e-mail já está cadastrado.";
                     } else {
                         mensagemCadastro.textContent = result.mensagem || "Ocorreu um erro no cadastro.";
                     }
                    mensagemCadastro.classList.add("error");
                }

            } catch (error) {
                console.error("Erro na requisição de cadastro:", error);
                if (error instanceof SyntaxError) {
                     mensagemCadastro.textContent = "Erro ao processar resposta do servidor.";
                 } else {
                     mensagemCadastro.textContent = `Erro: ${error.message}`;
                 }
                mensagemCadastro.classList.add("error");
            }
        });
    }

    // --- Lógica de Login ---
    const loginForm = document.getElementById("loginForm");
    const mensagemLogin = document.getElementById("mensagem-login");

    if (loginForm && mensagemLogin) {
        loginForm.addEventListener("submit", async function(event) {
            event.preventDefault();
            const email = document.getElementById("email").value;
            const senha = document.getElementById("senha").value;
            mensagemLogin.textContent = "";
            mensagemLogin.className = "mensagem";

            try {
                // credentials: 'include' é VITAL para enviar cookies de sessão
                const response = await fetch(`${API_BASE_URL}/login.php`, {
                    method: "POST",
                    credentials: 'include', // <<< IMPORTANTE
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ email, senha })
                });

                const data = await response.json(); // Lê JSON

                 if (!response.ok) { // Checa status HTTP
                     throw new Error(data.mensagem || `Erro HTTP ${response.status}`);
                 }

                // Usar a estrutura padrão {status: 'sucesso'/'erro', mensagem: ...} ou {sucesso: true/false}
                // Adaptado para a estrutura retornada pelo login.php corrigido
                 if (data.status === "sucesso" || data.sucesso === true) {
                    mensagemLogin.textContent = data.mensagem || "Login bem-sucedido!";
                    mensagemLogin.classList.add("success");

                    // Chamar função global para verificar sessão e atualizar nav (opcional aqui,
                    // pois o global.js fará isso no carregamento da próxima página)
                    // await checkLoginStatus(); // Certifique-se que essa função existe globalmente se descomentar

                    // Redireciona após um curto delay
                    setTimeout(() => {
                        // !! NÃO FAÇA A VERIFICAÇÃO DE ADMIN AQUI !!
                        // A verificação de admin deve ser feita pela API check_session.php
                        // Apenas redirecione para a página principal. O global.js cuidará dos links.
                         window.location.href = "index.html"; // Redireciona SEMPRE para a página inicial
                    }, 1000);

                } else {
                     // Se chegou aqui, status HTTP era OK, mas API reportou falha
                     mensagemLogin.textContent = data.mensagem || "Credenciais inválidas ou erro inesperado.";
                     mensagemLogin.classList.add("error");
                }

            } catch (error) {
                console.error("Erro na requisição de login:", error);
                 if (error instanceof SyntaxError) {
                     mensagemLogin.textContent = "Erro ao processar resposta do servidor.";
                 } else {
                     // Exibe a mensagem do erro capturado (pode ser da API ou de rede)
                     mensagemLogin.textContent = `Erro: ${error.message}`;
                 }
                mensagemLogin.classList.add("error");
            }
        });
    }
}); // Fim DOMContentLoaded