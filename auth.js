// --- START OF FILE auth.js ---

document.addEventListener("DOMContentLoaded", function () {
    const API_BASE_URL = "http://localhost/pizzaria_express/api"; // Centralizar URL base

    // --- Lógica de Cadastro ---
    const cadastroForm = document.getElementById("cadastroForm");
    const mensagemCadastro = document.getElementById("mensagem-cadastro"); // ID específico

    if (cadastroForm && mensagemCadastro) {
        cadastroForm.addEventListener("submit", async function (e) {
            e.preventDefault();

            const nome = document.getElementById("nome").value;
            const email = document.getElementById("email").value;
            const senha = document.getElementById("senha").value;

            // Limpa mensagens anteriores
            mensagemCadastro.textContent = "";
            mensagemCadastro.className = "mensagem"; // Reset classes

            try {
                const response = await fetch(`${API_BASE_URL}/cadastro.php`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ nome, email, senha })
                });

                const result = await response.json();

                if (result.sucesso) {
                    mensagemCadastro.textContent = result.sucesso;
                    mensagemCadastro.classList.add("success");
                    setTimeout(() => {
                        window.location.href = "login.html"; // Redireciona após sucesso
                    }, 2000);
                } else {
                    mensagemCadastro.textContent = result.erro || "Ocorreu um erro no cadastro.";
                    mensagemCadastro.classList.add("error");
                }

            } catch (error) {
                console.error("Erro na requisição de cadastro:", error);
                mensagemCadastro.textContent = "Erro ao conectar ao servidor.";
                mensagemCadastro.classList.add("error");
            }
        });
    }

    // --- Lógica de Login ---
    const loginForm = document.getElementById("loginForm");
    const mensagemLogin = document.getElementById("mensagem-login"); // ID específico

    if (loginForm && mensagemLogin) {
        loginForm.addEventListener("submit", async function(event) {
            event.preventDefault();

            const email = document.getElementById("email").value;
            const senha = document.getElementById("senha").value;

            // Limpa mensagens anteriores
            mensagemLogin.textContent = "";
            mensagemLogin.className = "mensagem"; // Reset classes

            try {
                const response = await fetch(`${API_BASE_URL}/login.php`, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ email, senha })
                });

                const data = await response.json();

                if (data.status === "sucesso") {
                    mensagemLogin.textContent = data.mensagem;
                    mensagemLogin.classList.add("success");
                    // Opcional: Armazenar token/session info se a API retornar
                    // localStorage.setItem('authToken', data.token);
                    setTimeout(() => {
                        // Verificar se é admin (idealmente a API deveria informar)
                        if (email === 'admin@example.com') { // Exemplo simples, NÃO SEGURO
                             window.location.href = "admin.html";
                        } else {
                             window.location.href = "index.html"; // Redireciona para a página inicial
                        }
                    }, 1500);
                } else {
                    mensagemLogin.textContent = data.mensagem || "Credenciais inválidas.";
                    mensagemLogin.classList.add("error");
                }
            } catch (error) {
                console.error("Erro na requisição de login:", error);
                mensagemLogin.textContent = "Erro ao conectar ao servidor.";
                mensagemLogin.classList.add("error");
            }
        });
    }
});
// --- END OF FILE auth.js ---