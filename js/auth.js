// js/auth.js - ATUALIZADO E CORRIGIDO (Inclui Endereço/Telefone no Cadastro)

document.addEventListener("DOMContentLoaded", function () {
    // --- Configurações e Constantes ---
    const API_BASE_URL = "./api"; // !! VERIFIQUE SUA URL !!
    // Defina a URL da API aqui. Se usar all_in_one.php, ajuste as URLs dos fetch abaixo.

    // --- Lógica de Cadastro ---
    const cadastroForm = document.getElementById("cadastroForm");
    const mensagemCadastro = document.getElementById("mensagem-cadastro"); // Espera ID 'mensagem-cadastro' no HTML

    // Só adiciona o listener se o formulário e a mensagem existirem nesta página
    if (cadastroForm && mensagemCadastro) {
        cadastroForm.addEventListener("submit", async function (e) {
            e.preventDefault(); // Impede envio padrão do formulário

            // Pega os valores dos campos (usa ?. para evitar erro se elemento não existir)
            const nome = document.getElementById("nome")?.value.trim();
            const email = document.getElementById("email")?.value.trim();
            const senha = document.getElementById("senha")?.value.trim();
            const endereco = document.getElementById("endereco")?.value.trim(); // Pega endereço
            const telefone = document.getElementById("telefone")?.value.trim() || null; // Pega telefone (null se vazio)

            // Limpa mensagens anteriores
            mensagemCadastro.textContent = "";
            mensagemCadastro.className = "mensagem"; // Reseta classes CSS

            // Validação básica no frontend (principal validação é no PHP)
            let errosValidacao = [];
            if (!nome) { errosValidacao.push("Nome"); }
            if (!email) { errosValidacao.push("Email"); }
            if (!senha) { errosValidacao.push("Senha"); }
            if (!endereco) { errosValidacao.push("Endereço"); } // Endereço obrigatório
             // Validação simples de email
             if (email && (!email.includes('@') || !email.includes('.'))) {
                errosValidacao.push("Email (formato inválido)");
            }
             // Validação simples de senha (ex: mínimo 6 caracteres)
             if (senha && senha.length < 6) {
                errosValidacao.push("Senha (mínimo 6 caracteres)");
            }

            if (errosValidacao.length > 0) {
                 mensagemCadastro.textContent = `Campos obrigatórios ou inválidos: ${errosValidacao.join(', ')}.`;
                 mensagemCadastro.classList.add("error");
                 return;
            }


            console.log("AUTH.JS (Cadastro): Enviando dados:", { nome, email, /* não logar senha */ endereco, telefone });

            try {
                // Faz a requisição POST para a API de cadastro
                // Ajuste a URL se usar all_in_one.php (adicionar action=registrar)
                const response = await fetch(`${API_BASE_URL}/cadastro.php`, {
                // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { // DESCOMENTE se usar all_in_one
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    // Envia os dados (incluindo os novos campos) como JSON
                    body: JSON.stringify({ nome, email, senha, endereco, telefone }) // Inclui endereco e telefone
                    // body: JSON.stringify({ action: 'registrar', nome, email, senha, endereco, telefone }) // DESCOMENTE se usar all_in_one
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
                // Ajuste a URL se usar all_in_one.php (adicionar action=login)
                 const response = await fetch(`${API_BASE_URL}/login.php`, {
                // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { // DESCOMENTE se usar all_in_one
                    method: "POST",
                    credentials: 'include', // <<< ESSENCIAL PARA SESSÃO FUNCIONAR
                    headers: { "Content-Type": "application/json" },
                     body: JSON.stringify({ email, senha })
                    // body: JSON.stringify({ action: 'login', email, senha }) // DESCOMENTE se usar all_in_one
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
                            console.log("AUTH.JS (Login): Redirecionando para admin_main.html"); // Mudado para admin_main
                            window.location.href = "admin_main.html"; // Usa a nova página combinada
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

    // === NOVA LÓGICA PARA FORMATAÇÃO DE TELEFONE ===
    const telefoneInput = document.getElementById('telefone');

    if (telefoneInput) {
        telefoneInput.addEventListener('input', function (e) {
            let valor = e.target.value.replace(/\D/g, ''); // Remove tudo que não é dígito
            let formatado = '';

            if (valor.length > 0) {
                formatado = '(' + valor.substring(0, 2);
            }
            if (valor.length > 2) {
                formatado += ') ' + valor.substring(2, valor.length <= 10 ? 6 : 7);
            }
            if (valor.length > (valor.length <= 10 ? 6 : 7)) {
                formatado += '-' + valor.substring(valor.length <= 10 ? 6 : 7, 11);
            }

            // Limita o número de caracteres (considerando máscara)
            if (formatado.length > 15) { // (XX) XXXXX-XXXX (15 chars) ou (XX) XXXX-XXXX (14 chars)
                 e.target.value = formatado.substring(0, 15);
            } else {
                 e.target.value = formatado;
            }
        });

        // Opcional: Limpar formatação se o campo ficar vazio (melhora UX)
        telefoneInput.addEventListener('blur', function(e) {
            if (e.target.value === '() ' || e.target.value === '()') {
                e.target.value = '';
            }
        });
    }
    // === FIM DA NOVA LÓGICA ===

}); // Fim do DOMContentLoaded