// js/auth.js 
document.addEventListener("DOMContentLoaded", function () {
    // --- Configurações e Constantes ---
    const API_BASE_URL = "http://localhost/pizzaria_express/api"; // !! VERIFIQUE SUA URL !!

    // --- Lógica de Cadastro ---
    const cadastroForm = document.getElementById("cadastroForm");
    const mensagemCadastro = document.getElementById("mensagem-cadastro"); // Espera ID 'mensagem-cadastro' no HTML

    // Só adiciona o listener se o formulário e a mensagem existirem nesta página
    if (cadastroForm && mensagemCadastro) {
        cadastroForm.addEventListener("submit", async function (e) {
            e.preventDefault(); // Impede envio padrão do formulário

            // Pega os valores dos campos
            const nome = document.getElementById("nome")?.value.trim(); // Usa optional chaining ?. por segurança
            const email = document.getElementById("email")?.value.trim();
            const senha = document.getElementById("senha")?.value.trim();
            const endereco = document.getElementById("endereco")?.value.trim() || ''; // Pega endereço (ou vazio)
            const telefone = document.getElementById("telefone")?.value.trim() || ''; // Pega telefone (ou vazio)

            // Limpa mensagens anteriores
            mensagemCadastro.textContent = "";
            mensagemCadastro.className = "mensagem"; // Reseta classes CSS

            // Validação básica no frontend (principal validação é no PHP)
            if (!nome || !email || !senha) {
                 mensagemCadastro.textContent = "Nome, Email e Senha são obrigatórios.";
                 mensagemCadastro.classList.add("error");
                 return;
            }
            // Validação simples de email (PHP fará validação mais robusta)
            if (!email.includes('@') || !email.includes('.')) {
                mensagemCadastro.textContent = "Formato de e-mail inválido.";
                mensagemCadastro.classList.add("error");
                return;
            }


            console.log("AUTH.JS (Cadastro): Enviando dados:", { nome, email, /* não logar senha */ endereco, telefone });

            try {
                // Faz a requisição POST para a API de cadastro
                const response = await fetch(`${API_BASE_URL}/cadastro.php`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    // Envia os dados (incluindo os novos campos) como JSON
                    body: JSON.stringify({ nome, email, senha, endereco, telefone })
                });

                console.log("AUTH.JS (Cadastro): Resposta recebida - Status:", response.status);

                // Tenta ler a resposta como JSON
                const result = await response.json();
                console.log("AUTH.JS (Cadastro): Resposta JSON:", result);

                // Verifica se a requisição HTTP foi bem-sucedida E se a API reportou sucesso
                 if (response.ok && result.sucesso) {
                    mensagemCadastro.textContent = result.mensagem || "Cadastro realizado com sucesso!";
                    mensagemCadastro.classList.add("success");
                    // Redireciona para a página de login após um tempo
                    setTimeout(() => {
                        console.log("AUTH.JS (Cadastro): Redirecionando para login.html");
                        window.location.href = "login.html";
                    }, 2000);
                } else {
                    // Se a requisição falhou ou a API reportou erro
                    // Usa a mensagem da API ou uma mensagem padrão
                     throw new Error(result.mensagem || `Erro ${response.status || 'desconhecido'} ao cadastrar.`);
                }

            } catch (error) {
                console.error("AUTH.JS (Cadastro): Erro na requisição ou processamento:", error);
                 if (error instanceof SyntaxError) { // Se a resposta não foi JSON válido
                     mensagemCadastro.textContent = "Erro ao processar resposta do servidor (formato inválido).";
                 } else { // Outros erros (rede, erro lançado acima)
                     mensagemCadastro.textContent = `${error.message}`; // Exibe a mensagem de erro
                 }
                mensagemCadastro.classList.add("error");
            }
        });
    } else if (document.getElementById("cadastroForm")) {
        // Se o form existe, mas a mensagem não, loga um aviso
        console.warn("AUTH.JS: Formulário de cadastro encontrado, mas elemento #mensagem-cadastro não.");
    }


    // --- Lógica de Login ---
    const loginForm = document.getElementById("loginForm");
    const mensagemLogin = document.getElementById("mensagem-login"); // Espera ID 'mensagem-login' no HTML

    // Só adiciona o listener se o formulário e a mensagem existirem nesta página
    if (loginForm && mensagemLogin) {
        loginForm.addEventListener("submit", async function(event) {
            event.preventDefault(); // Impede envio padrão

            const email = document.getElementById("email")?.value.trim();
            const senha = document.getElementById("senha")?.value; // Não faz trim na senha

            // Limpa mensagens anteriores
            mensagemLogin.textContent = "";
            mensagemLogin.className = "mensagem"; // Reseta classes CSS

            if (!email || !senha) {
                 mensagemLogin.textContent = "Email e Senha são obrigatórios.";
                 mensagemLogin.classList.add("error");
                 return;
            }

            console.log("AUTH.JS (Login): Enviando dados para login:", { email /* não logar senha */ });

            try {
                // Faz a requisição POST para a API de login
                // credentials: 'include' é VITAL para enviar/receber cookies de sessão PHP
                const response = await fetch(`${API_BASE_URL}/login.php`, {
                    method: "POST",
                    credentials: 'include', // <<< ESSENCIAL PARA SESSÃO FUNCIONAR
                    headers: { "Content-Type": "application/json" },
                    body: JSON.stringify({ email, senha })
                });

                console.log("AUTH.JS (Login): Resposta recebida - Status:", response.status);

                // Tenta ler a resposta como JSON
                const data = await response.json();
                console.log("AUTH.JS (Login): Resposta JSON:", data);

                // Verifica se a requisição HTTP foi bem-sucedida E se a API reportou sucesso
                // Adapta para verificar 'sucesso: true' ou 'status: "sucesso"'
                 if (response.ok && (data.sucesso === true || data.status === "sucesso")) {
                    mensagemLogin.textContent = data.mensagem || "Login bem-sucedido!";
                    mensagemLogin.classList.add("success");

                    // Pega status de admin da resposta da API (se existir)
                    // Usa optional chaining (?.) e nullish coalescing (??) para segurança
                    const isAdmin = data.usuario?.is_admin ?? false;
                    console.log("AUTH.JS (Login): Login sucesso. É Admin?", isAdmin);

                    // Redireciona após um curto delay
                    setTimeout(() => {
                        if (isAdmin) {
                            // Redireciona admin para a página de produtos do admin
                            console.log("AUTH.JS (Login): Redirecionando para admin_main.html");
                            window.location.href = "admin_main.html"; // Ou admin_pedidos.html se preferir
                        } else {
                            // Redireciona usuário normal para a página principal
                            console.log("AUTH.JS (Login): Redirecionando para index.html");
                            window.location.href = "index.html";
                        }
                    }, 1000); // Delay de 1 segundo

                } else {
                     // Se a requisição falhou ou a API reportou erro
                     throw new Error(data.mensagem || `Erro ${response.status || 'desconhecido'}: Credenciais inválidas ou falha no servidor.`);
                }

            } catch (error) {
                console.error("AUTH.JS (Login): Erro na requisição ou processamento:", error);
                 if (error instanceof SyntaxError) {
                     mensagemLogin.textContent = "Erro ao processar resposta do servidor.";
                 } else {
                     // Exibe a mensagem de erro capturada
                     mensagemLogin.textContent = `${error.message}`;
                 }
                mensagemLogin.classList.add("error");
            }
        });
    } else if (document.getElementById("loginForm")) {
        // Se o form existe, mas a mensagem não, loga um aviso
        console.warn("AUTH.JS: Formulário de login encontrado, mas elemento #mensagem-login não.");
    }

}); // Fim do DOMContentLoaded