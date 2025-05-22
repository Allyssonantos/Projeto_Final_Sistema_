// js/global.js - CORRIGIDO

document.addEventListener("DOMContentLoaded", function () {
    // --- Configurações e Constantes ---
    const API_BASE_URL = "./api"; // !! VERIFIQUE !!

    // --- Referências aos Elementos do DOM (Navbar) ---
    const navLogin = document.getElementById('nav-login');
    const navLogout = document.getElementById('nav-logout');
    const navPerfil = document.getElementById('nav-perfil');
    const navAdminProd = document.getElementById('nav-admin-prod');
    const navAdminPedidos = document.getElementById('nav-admin-pedidos');

    // ---------- BLOCO REMOVIDO DAQUI ----------
    // const response = await fetch(...); // <--- ESTE BLOCO FOI REMOVIDO
    // ---------- FIM DO BLOCO REMOVIDO ----------


    // --- Função para Verificar Status de Login via API ---
    async function checkLoginStatus() {
        console.log("GLOBAL: Verificando status do login...");
        try {
            // Incluir 'credentials: include' é VITAL para enviar/receber cookies de sessão
            // Ajuste a URL se estiver usando all_in_one.php
             const response = await fetch(`${API_BASE_URL}/check_session.php`, {credentials: 'include'});
            // const response = await fetch(`${API_BASE_URL}/all_in_one.php?action=checkSession`, {credentials: 'include'}); // DESCOMENTE se usar all_in_one

            console.log("GLOBAL: Fetch check_session - Status:", response.status);

            if (!response.ok) {
                 // Loga o erro, mas não impede a atualização da navbar (assume deslogado)
                 console.error("GLOBAL: Erro ao verificar sessão - Status HTTP:", response.status);
                 updateNav(false, false);
                 return;
            }

            // Tenta ler a resposta como JSON
             try {
                const data = await response.json();
                console.log("GLOBAL: Resposta check_session:", data);
                // Atualiza a navbar com base nos dados recebidos
                updateNav(data.logado ?? false, data.is_admin ?? false); // Usa ?? para default seguro
             } catch(jsonError) {
                 // Se a resposta não for JSON válido (ex: erro PHP não capturado)
                 console.error("GLOBAL: Erro ao processar JSON da resposta check_session:", jsonError);
                 const errorText = await response.text(); // Tenta ler como texto
                 console.error("GLOBAL: Corpo da resposta não-JSON:", errorText);
                 updateNav(false, false); // Assume deslogado
             }

        } catch (error) {
            // Erro de rede ou outro erro no fetch
            console.error("GLOBAL: Erro de rede ou script ao verificar sessão:", error);
            updateNav(false, false); // Assume deslogado em caso de erro grave
        }
    }

    // --- Função para Atualizar a Aparência da Navbar ---
    function updateNav(isLoggedIn, isAdmin) {
        console.log("GLOBAL: Atualizando Navbar - Logado:", isLoggedIn, "Admin:", isAdmin);
        // Garante que os elementos existem antes de tentar modificar classes
        navLogin?.classList.toggle('hidden', isLoggedIn); // Esconde se logado
        navLogout?.classList.toggle('hidden', !isLoggedIn); // Mostra se logado
        navPerfil?.classList.toggle('hidden', !isLoggedIn); // Mostra se logado

        // Mostra links de admin SOMENTE se logado E for admin
        const showAdminLinks = isLoggedIn && isAdmin;
        navAdminProd?.classList.toggle('hidden', !showAdminLinks);
        navAdminPedidos?.classList.toggle('hidden', !showAdminLinks);
    }

    // --- Função para Fazer Logout via API ---
    async function logout() {
        console.log("GLOBAL: Tentando fazer logout...");
        try {
             // credentials: 'include' para limpar o cookie de sessão corretamente no backend
             // Ajuste a URL se usar all_in_one.php
              const response = await fetch(`${API_BASE_URL}/logout.php`, {method: 'POST', credentials: 'include'});
             // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, {method: 'POST', credentials: 'include', body: JSON.stringify({action: 'logout'})}); // Se usar all_in_one

             console.log("GLOBAL: Fetch logout - Status:", response.status);

             const data = await response.json();
             console.log("GLOBAL: Resposta logout:", data);

             if (data.sucesso) {
                 console.log("GLOBAL: Logout realizado com sucesso.");
                 updateNav(false, false); // Atualiza a navbar imediatamente
                 alert("Logout realizado com sucesso!"); // Feedback para o usuário
                 // Redireciona para a página inicial após logout
                 window.location.href = 'index.html';
             } else {
                 // Falha reportada pela API
                 console.error("GLOBAL: Falha no logout reportada pela API:", data.mensagem);
                 alert(`Erro ao fazer logout: ${data.mensagem || 'Erro desconhecido'}`);
             }
        } catch (error) {
             // Erro de rede ou JSON
             console.error("GLOBAL: Erro de rede ou JSON durante logout:", error);
              if (error instanceof SyntaxError) {
                 alert("Erro ao processar resposta do servidor durante logout.");
             } else {
                 alert(`Erro de conexão ao tentar fazer logout: ${error.message}`);
             }
        }
    }

    // --- Adicionar Listener ao Botão de Logout ---
    if (navLogout) {
        navLogout.addEventListener('click', logout);
    } else {
         console.warn("GLOBAL: Botão #nav-logout não encontrado.");
    }

    // --- Execução Inicial ---
    // Verifica o status do login assim que a página carregar
    checkLoginStatus();

}); // Fim DOMContentLoaded