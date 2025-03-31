// js/global.js

document.addEventListener("DOMContentLoaded", function () {
    const API_BASE_URL = "http://localhost/pizzaria_express/api"; // !! VERIFIQUE !!

    const navLogin = document.getElementById('nav-login');
    const navLogout = document.getElementById('nav-logout');
    const navPerfil = document.getElementById('nav-perfil');
    const navAdminProd = document.getElementById('nav-admin-prod');
    const navAdminPedidos = document.getElementById('nav-admin-pedidos');

    async function checkLoginStatus() {
        console.log("GLOBAL: Verificando status do login...");
        try {
            // Incluir 'credentials: include' é VITAL para enviar/receber cookies de sessão
            const response = await fetch(`${API_BASE_URL}/check_session.php`, {credentials: 'include'});

            if (!response.ok) {
                 console.error("GLOBAL: Erro ao verificar sessão - Status HTTP:", response.status);
                 updateNav(false, false); // Assume deslogado em caso de erro
                 return;
            }
            const data = await response.json();
            console.log("GLOBAL: Resposta check_session:", data);
            updateNav(data.logado, data.is_admin);

        } catch (error) {
            console.error("GLOBAL: Erro de rede ou JSON ao verificar sessão:", error);
            updateNav(false, false); // Assume deslogado em caso de erro grave
        }
    }

    function updateNav(isLoggedIn, isAdmin) {
        console.log("GLOBAL: Atualizando Navbar - Logado:", isLoggedIn, "Admin:", isAdmin);
        if (isLoggedIn) {
            navLogin?.classList.add('hidden');
            navLogout?.classList.remove('hidden');
            navPerfil?.classList.remove('hidden');
            // Mostra links de admin SOMENTE se logado E for admin
            if (isAdmin) {
                 navAdminProd?.classList.remove('hidden');
                 navAdminPedidos?.classList.remove('hidden');
            } else {
                 navAdminProd?.classList.add('hidden');
                 navAdminPedidos?.classList.add('hidden');
            }
        } else {
            navLogin?.classList.remove('hidden');
            navLogout?.classList.add('hidden');
            navPerfil?.classList.add('hidden');
            navAdminProd?.classList.add('hidden');
            navAdminPedidos?.classList.add('hidden');
        }
    }

    async function logout() {
        console.log("GLOBAL: Tentando fazer logout...");
        try {
             // Precisa credentials: include para que o backend possa limpar o cookie de sessão corretamente
             const response = await fetch(`${API_BASE_URL}/logout.php`, {method: 'POST', credentials: 'include'});
             const data = await response.json();

             if (data.sucesso) {
                 console.log("GLOBAL: Logout realizado com sucesso.");
                 updateNav(false, false); // Atualiza a navbar
                 // Redireciona para a página inicial ou de login após logout
                 window.location.href = 'index.html';
             } else {
                 console.error("GLOBAL: Falha no logout reportada pela API:", data.mensagem);
                 alert("Erro ao fazer logout.");
             }
        } catch (error) {
             console.error("GLOBAL: Erro de rede ou JSON durante logout:", error);
             alert("Erro de conexão ao tentar fazer logout.");
        }
    }

    // Adiciona listener ao botão de logout, se ele existir
    if (navLogout) {
        navLogout.addEventListener('click', logout);
    }

    // Verifica o status do login ao carregar a página
    checkLoginStatus();

}); // Fim DOMContentLoaded