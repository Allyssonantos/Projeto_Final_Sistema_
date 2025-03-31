// js/admin_pedidos.js

document.addEventListener("DOMContentLoaded", function () {
    const API_BASE_URL = "http://localhost/pizzaria_express/api"; // !! VERIFIQUE !!

    const corpoTabelaPedidos = document.getElementById('corpo-tabela-pedidos');
    const filtroStatusSelect = document.getElementById('filtro-status');
    const btnRecarregar = document.getElementById('btn-recarregar-pedidos');
    const mensagemAdminPedidos = document.getElementById('mensagem-admin-pedidos');

    // Verifica se elementos essenciais existem
    if (!corpoTabelaPedidos || !filtroStatusSelect || !btnRecarregar || !mensagemAdminPedidos) {
        console.error("ADMIN_PEDIDOS: Elementos essenciais não encontrados!");
        if(mensagemAdminPedidos) mensagemAdminPedidos.textContent = "Erro ao carregar interface.";
        return;
    }

    const statusPermitidos = ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado'];

    function exibirMensagem(texto, tipo = "info") {
         mensagemAdminPedidos.textContent = texto;
         mensagemAdminPedidos.className = `mensagem ${tipo}`;
         setTimeout(() => { /* ... (limpar msg) ... */ }, 5000);
    }

    async function carregarPedidos(status = '') {
        console.log(`ADMIN_PEDIDOS: Carregando pedidos ${status ? `com status "${status}"` : 'todos'}...`);
        corpoTabelaPedidos.innerHTML = `<tr><td colspan="7">Carregando...</td></tr>`; // Feedback visual

        let url = `${API_BASE_URL}/admin_listar_pedidos.php`;
        if (status) {
            url += `?status=${encodeURIComponent(status)}`; // Adiciona filtro na URL se houver
        }

        try {
            // credentials: include necessário por causa da verificação de sessão/admin no backend
            const response = await fetch(url, {credentials: 'include'});
            console.log("ADMIN_PEDIDOS: Resposta fetch pedidos - Status:", response.status);

            if (response.status === 401 || response.status === 403) {
                 alert("Acesso negado ou sessão expirada. Faça login como administrador.");
                 window.location.href = 'login.html'; // Redireciona
                 return;
            }
            if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);

            const data = await response.json();
            console.log("ADMIN_PEDIDOS: Dados recebidos:", data);

            if (data.sucesso && Array.isArray(data.pedidos)) {
                renderizarTabelaPedidos(data.pedidos);
            } else {
                throw new Error(data.mensagem || "Resposta da API inválida.");
            }

        } catch (error) {
            console.error("ADMIN_PEDIDOS: Erro ao carregar pedidos:", error);
            exibirMensagem(`Erro ao carregar pedidos: ${error.message}`, "error");
            corpoTabelaPedidos.innerHTML = `<tr><td colspan="7">Falha ao carregar pedidos.</td></tr>`;
        }
    }

    function renderizarTabelaPedidos(pedidos) {
        corpoTabelaPedidos.innerHTML = ''; // Limpa tabela

        if (pedidos.length === 0) {
            corpoTabelaPedidos.innerHTML = `<tr><td colspan="7">Nenhum pedido encontrado${filtroStatusSelect.value ? ` com o status "${filtroStatusSelect.value}"` : ''}.</td></tr>`;
            return;
        }

        pedidos.forEach(pedido => {
            const row = document.createElement('tr');
            row.setAttribute('data-pedido-id', pedido.id);

            let dataFormatada = 'Inválida';
             try { if (pedido.data_pedido) { dataFormatada = new Date(pedido.data_pedido).toLocaleString('pt-BR'); }} catch(e){}

            // Cria o <select> para o status
            let statusSelectHtml = `<select class="status-pedido-select" data-pedido-id="${pedido.id}">`;
            statusPermitidos.forEach(statusOpt => {
                 const selected = (pedido.status === statusOpt) ? ' selected' : '';
                 statusSelectHtml += `<option value="${statusOpt}"${selected}>${statusOpt}</option>`;
            });
            statusSelectHtml += `</select>`;

            row.innerHTML = `
                <td>${pedido.id}</td>
                <td>${dataFormatada}</td>
                <td>${pedido.nome_cliente || '?'} (${pedido.email_cliente || '?'})</td>
                <td>${pedido.telefone_cliente || 'N/A'}</td>
                <td>${pedido.endereco_entrega || '?'}</td>
                <td>R$ ${Number(pedido.valor_total).toFixed(2)}</td>
                <td class="status-cell">${statusSelectHtml}</td>
                <!-- Adicionar coluna/botão "Ver Itens" futuramente -->
            `;
            corpoTabelaPedidos.appendChild(row);
        });
    }

    async function atualizarStatusPedido(pedidoId, novoStatus) {
         console.log(`ADMIN_PEDIDOS: Atualizando status pedido ID ${pedidoId} para ${novoStatus}...`);
         exibirMensagem("Atualizando status...", "info"); // Feedback visual

         try {
             const response = await fetch(`${API_BASE_URL}/admin_atualizar_status_pedido.php`, {
                 method: 'POST',
                 credentials: 'include', // Enviar cookie de sessão
                 headers: { 'Content-Type': 'application/json' },
                 body: JSON.stringify({ pedido_id: pedidoId, novo_status: novoStatus })
             });
             console.log("ADMIN_PEDIDOS: Resposta atualização status - Status:", response.status);

             const data = await response.json(); // Sempre tentar ler JSON
             console.log("ADMIN_PEDIDOS: Resposta JSON atualização:", data);

             if (!response.ok) { // Verifica status HTTP primeiro
                  throw new Error(data.mensagem || `Erro HTTP ${response.status}`);
             }

             if (data.sucesso) {
                 exibirMensagem(data.mensagem || "Status atualizado!", "success");
                 // Opcional: apenas destacar a linha ou mudar a cor em vez de recarregar tudo
                 // carregarPedidos(filtroStatusSelect.value); // Recarrega com o filtro atual
             } else {
                 // A API retornou sucesso=false, mas status HTTP pode ter sido 200 ou 404
                 exibirMensagem(data.mensagem || "Falha ao atualizar status.", "error");
                 // Reverter o select para o valor anterior visualmente? (Mais complexo)
                 await carregarPedidos(filtroStatusSelect.value); // Recarrega para corrigir visualmente
             }

         } catch (error) {
              console.error("ADMIN_PEDIDOS: Erro ao atualizar status:", error);
              exibirMensagem(`Erro ao atualizar status: ${error.message}`, "error");
              // Tentar recarregar para talvez reverter visualmente
               await carregarPedidos(filtroStatusSelect.value);
         }
    }

    // --- Event Listeners ---

    // Filtro de status
    filtroStatusSelect.addEventListener('change', () => {
        carregarPedidos(filtroStatusSelect.value); // Recarrega com o status selecionado
    });

    // Botão Recarregar
    btnRecarregar.addEventListener('click', () => {
         carregarPedidos(filtroStatusSelect.value);
    });

    // Delegação de evento para os selects de status na tabela
    corpoTabelaPedidos.addEventListener('change', (event) => {
        if (event.target.classList.contains('status-pedido-select')) {
            const select = event.target;
            const pedidoId = select.getAttribute('data-pedido-id');
            const novoStatus = select.value;
            if (pedidoId && novoStatus) {
                 atualizarStatusPedido(pedidoId, novoStatus);
            }
        }
    });


    // --- Carregamento Inicial ---
    carregarPedidos(); // Carrega todos os pedidos inicialmente

}); // Fim DOMContentLoaded