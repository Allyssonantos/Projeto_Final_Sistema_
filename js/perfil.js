// js/perfil.js

document.addEventListener("DOMContentLoaded", function () {
    const API_BASE_URL = "http://localhost/pizzaria_express/api"; // !! VERIFIQUE !!

    const nomeEl = document.getElementById('perfil-nome');
    const emailEl = document.getElementById('perfil-email');
    const telefoneEl = document.getElementById('perfil-telefone');
    const enderecoEl = document.getElementById('perfil-endereco');
    const listaPedidosEl = document.getElementById('lista-meus-pedidos');
    const mensagemPerfilEl = document.getElementById('mensagem-perfil');
    const dadosUsuarioSection = document.getElementById('dados-usuario');

    function exibirMensagemPerfil(texto, tipo = "info") {
         if (!mensagemPerfilEl) return;
         mensagemPerfilEl.textContent = texto;
         mensagemPerfilEl.className = `mensagem ${tipo}`;
         setTimeout(() => { /* ... (lógica para limpar msg) ... */ }, 5000);
    }

    async function carregarPerfilEPedidos() {
        console.log("PERFIL: Carregando dados...");
        try {
             // credentials: include é necessário para enviar o cookie de sessão
             const response = await fetch(`${API_BASE_URL}/perfil_usuario.php`, {credentials: 'include'});
             console.log("PERFIL: Resposta fetch perfil - Status:", response.status);

             if (response.status === 401 || response.status === 403) {
                 // Se não autorizado, redireciona para login
                 alert("Sessão expirada ou inválida. Faça login novamente.");
                 window.location.href = 'login.html';
                 return;
             }
             if (!response.ok) {
                 throw new Error(`Erro HTTP: ${response.status}`);
             }

             const data = await response.json();
             console.log("PERFIL: Dados recebidos:", data);

             if (data.sucesso) {
                 // Preenche dados do perfil
                 if (data.perfil && nomeEl && emailEl && telefoneEl && enderecoEl) {
                     nomeEl.textContent = data.perfil.nome || 'Não informado';
                     emailEl.textContent = data.perfil.email || 'Não informado';
                     telefoneEl.textContent = data.perfil.telefone || 'Não informado';
                     enderecoEl.textContent = data.perfil.endereco || 'Não informado (Complete seu cadastro!)';
                     dadosUsuarioSection?.classList.remove('loading');
                 } else {
                      console.error("PERFIL: Dados do perfil faltando na resposta ou elementos HTML não encontrados.");
                      exibirMensagemPerfil("Erro ao carregar dados do perfil.", "error");
                 }

                 // Renderiza histórico de pedidos
                 renderizarHistorico(data.pedidos);

             } else {
                 throw new Error(data.mensagem || "Falha ao carregar dados.");
             }

        } catch (error) {
             console.error("PERFIL: Erro ao carregar perfil/pedidos:", error);
             exibirMensagemPerfil(`Erro ao carregar dados: ${error.message}`, "error");
             if (listaPedidosEl) listaPedidosEl.innerHTML = "<p>Erro ao carregar histórico.</p>";
             dadosUsuarioSection?.classList.remove('loading'); // Remove loading mesmo com erro
             dadosUsuarioSection?.classList.add('error-loading');
        }
    }

    function renderizarHistorico(pedidos) {
        if (!listaPedidosEl) return;
        listaPedidosEl.innerHTML = ''; // Limpa
        listaPedidosEl.classList.remove('loading');

        if (!Array.isArray(pedidos) || pedidos.length === 0) {
            listaPedidosEl.innerHTML = '<p>Você ainda não fez nenhum pedido.</p>';
            return;
        }

        pedidos.forEach(pedido => {
            const pedidoDiv = document.createElement('div');
            pedidoDiv.classList.add('pedido-historico-item');

            // Formata a data (ex: DD/MM/AAAA HH:MM)
            let dataFormatada = 'Data indisponível';
            try {
                 if (pedido.data_pedido) {
                      const dataObj = new Date(pedido.data_pedido);
                      dataFormatada = dataObj.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                                      dataObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                 }
            } catch(e){ console.warn("Erro ao formatar data:", pedido.data_pedido, e); }


            // Cria HTML para o cabeçalho do pedido
             let itensHtml = '<p>Nenhum item encontrado para este pedido.</p>';
             if (Array.isArray(pedido.itens) && pedido.itens.length > 0) {
                 itensHtml = '<ul>';
                 pedido.itens.forEach(item => {
                     itensHtml += `<li>${item.quantidade}x ${item.nome_produto} (R$ ${Number(item.preco_unitario).toFixed(2)} cada)</li>`;
                 });
                 itensHtml += '</ul>';
             }

            pedidoDiv.innerHTML = `
                <div class="pedido-historico-header">
                    <span><strong>Pedido #${pedido.id}</strong></span>
                    <span>${dataFormatada}</span>
                    <span>Status: <strong class="status-${(pedido.status || '').toLowerCase().replace(' ', '-')}">${pedido.status || '?'}</strong></span>
                    <span>Total: <strong>R$ ${Number(pedido.valor_total).toFixed(2)}</strong></span>
                </div>
                <div class="pedido-historico-itens">
                    <h4>Itens:</h4>
                    ${itensHtml}
                </div>
            `;
            listaPedidosEl.appendChild(pedidoDiv);
        });
    }

    // Carrega os dados ao iniciar a página
    carregarPerfilEPedidos();

}); // Fim DOMContentLoaded