// js/admin_main.js - COMBINED ADMIN PANEL - Completo e Atualizado

document.addEventListener("DOMContentLoaded", function () {
    // === Configurações e Constantes ===
    const API_BASE_URL = "http://localhost/pizzaria_express/api"; // !! VERIFIQUE SUA URL DA API !!
    const UPLOADS_BASE_URL = 'uploads/produtos/';         // Caminho base para exibir imagens
    const PLACEHOLDER_IMG = 'img/placeholder.png';           // Imagem padrão (certifique-se que existe em pizzaria_express/img/)
    const statusPermitidos = ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado']; // Status válidos para pedidos

    // === Referências aos Elementos do DOM ===

    // Elementos Gerais da Página (NavBar, Mensagem)
    const adminWrapper = document.querySelector(".admin-wrapper");
    const adminNav = document.querySelector(".admin-nav ul");
    const mensagemAdmin = document.getElementById("mensagem-admin");

    // Elementos da Seção de Gerenciamento de Produtos
    const adminProdutosSection = document.getElementById('admin-produtos');
    const formAdicionarProduto = document.getElementById("formAdicionarProduto");
    const btnAdicionarProduto = document.getElementById("btnAdicionarProduto");
    const listaProdutosTbody = document.getElementById("tabela-produtos")?.querySelector('tbody');
    const nomeInputAdd = document.getElementById("nomeProduto");
    const descricaoInputAdd = document.getElementById("descricaoProduto");
    const precoInputAdd = document.getElementById("precoProduto");
    const categoriaSelectAdd = document.getElementById("categoriaProduto");
    const imagemInputAdd = document.getElementById("imagemProduto");

    // Elementos da Seção de Gerenciamento de Pedidos
    const adminPedidosSection = document.getElementById('admin-pedidos');
    const corpoTabelaPedidos = document.getElementById('tabela-pedidos')?.querySelector('tbody');
    const filtroStatusSelect = document.getElementById('filtro-status');
    const btnRecarregar = document.getElementById('btn-recarregar-pedidos');

    // Elementos do Modal de Detalhes do Pedido (se existir no HTML)
    const modalDetalhes = document.getElementById('modal-detalhes-pedido');
    const modalCloseBtn = modalDetalhes?.querySelector('.modal-close-btn');
    const modalPedidoIdSpan = document.getElementById('modal-pedido-id');
    const modalPedidoCorpo = document.getElementById('modal-pedido-corpo');
    const modalListaItensUl = document.getElementById('modal-lista-itens');
    const modalObservacoesP = document.getElementById('modal-texto-observacoes');
    const modalObservacoesDiv = document.getElementById('modal-pedido-observacoes');

    // --- Verificação Inicial de Elementos ---
     const elementosEssenciais = [
         adminWrapper, adminNav, mensagemAdmin, adminProdutosSection, formAdicionarProduto,
         btnAdicionarProduto, listaProdutosTbody, nomeInputAdd, descricaoInputAdd, precoInputAdd,
         categoriaSelectAdd, imagemInputAdd, adminPedidosSection, corpoTabelaPedidos,
         filtroStatusSelect, btnRecarregar
         // Não incluir elementos do modal aqui, pois são opcionais e verificados depois
     ];
     if (elementosEssenciais.some(el => !el)) {
         console.error("ERRO FATAL: Um ou mais elementos essenciais do DOM da página admin não foram encontrados. Verifique os IDs no HTML e JS.");
         if (mensagemAdmin) exibirMensagem("Erro crítico: Falha ao carregar interface.", "error");
         return; // Interrompe
     }
     console.log("ADMIN_MAIN: Todos os elementos essenciais encontrados.");


    // --- Função Utilitária para Exibir Mensagens ---
    function exibirMensagem(texto, tipo = "info") {
         if (!mensagemAdmin) return;
         mensagemAdmin.textContent = texto;
         mensagemAdmin.className = `mensagem ${tipo}`;
         setTimeout(() => {
             if (mensagemAdmin.textContent === texto) {
                  mensagemAdmin.textContent = '';
                  mensagemAdmin.className = 'mensagem';
             }
         }, 5000);
    }

    // --- Funções de Carregamento e Renderização ---
    async function carregarProdutos() {
         console.log("ADMIN_MAIN: Carregando produtos...");
         if (!listaProdutosTbody) return;
         listaProdutosTbody.innerHTML = '<tr><td colspan="7">Carregando produtos...</td></tr>'; // Colspan 7 para produtos
         try {
             // Ajuste URL se usar all_in_one.php
             const response = await fetch(`${API_BASE_URL}/produtos.php`);
             // const response = await fetch(`${API_BASE_URL}/all_in_one.php?action=listarProdutos`, {credentials: 'include'});
             if (!response.ok) { throw new Error(`Erro HTTP: ${response.status}`); }
             const produtos = await response.json();
             listaProdutosTbody.innerHTML = "";

             if (!Array.isArray(produtos)) { throw new Error("Resposta API produtos inválida."); }
             if (produtos.length === 0) {
                 listaProdutosTbody.innerHTML = '<tr><td colspan="7">Nenhum produto cadastrado.</td></tr>'; return;
             }

             produtos.forEach(produto => {
                 const row = document.createElement("tr");
                 row.setAttribute('data-id', produto.id);
                 const imagemUrl = produto.imagem_url ? produto.imagem_url : PLACEHOLDER_IMG;
                 const nomeImagem = produto.imagem_nome || 'Nenhuma';

                 row.innerHTML = `
                     <td data-label="ID">${produto.id}</td>
                     <td data-label="Imagem" class="td-imagem">
                         <img src="${imagemUrl}" alt="${produto.nome || 'Produto'}" class="imagem-produto-preview" onerror="this.onerror=null; this.src='${PLACEHOLDER_IMG}'; this.alt='Erro Imagem';">
                         <span class="nome-imagem-atual">Atual: ${nomeImagem}</span>
                         <input type="file" id="imagemEdit-${produto.id}" class="imagem-edit-input hidden" accept="image/*">
                     </td>
                     <td data-label="Nome"><input type="text" value="${produto.nome || ''}" id="nome-${produto.id}" disabled></td>
                     <td data-label="Descrição"><input type="text" value="${produto.descricao || ''}" id="descricao-${produto.id}" disabled></td>
                     <td data-label="Preço"><input type="number" step="0.01" value="${Number(produto.preco).toFixed(2)}" id="preco-${produto.id}" disabled></td>
                     <td data-label="Categoria">
                         <select id="categoria-${produto.id}" disabled>
                             <option value="pizza" ${produto.categoria === "pizza" ? "selected" : ""}>Pizza</option>
                             <option value="bebida" ${produto.categoria === "bebida" ? "selected" : ""}>Bebida</option>
                         </select>
                     </td>
                     <td data-label="Ações" class="actions">
                         <button class="button-edit" data-id="${produto.id}">Editar</button>
                         <button class="button-save hidden" data-id="${produto.id}">Salvar</button>
                         <button class="button-delete" data-id="${produto.id}">Excluir</button>
                     </td>
                 `;
                 listaProdutosTbody.appendChild(row);
             });
         } catch (error) {
              console.error("Erro detalhado ao carregar produtos:", error);
              exibirMensagem(`Falha ao carregar produtos: ${error.message}.`, "error");
              if(listaProdutosTbody) listaProdutosTbody.innerHTML = `<tr><td colspan="7">Erro ao carregar produtos.</td></tr>`;
         }
     }

    async function carregarPedidos(status = '') {
         console.log(`ADMIN_MAIN: Carregando pedidos ${status ? `com status "${status}"` : 'todos'}...`);
         if (!corpoTabelaPedidos) return;
         corpoTabelaPedidos.innerHTML = `<tr><td colspan="8">Carregando pedidos...</td></tr>`; // Colspan 8

         // *** USA A API SEPARADA OU ALL_IN_ONE PARA LISTAR PEDIDOS ***
          let url = `${API_BASE_URL}/admin_listar_pedidos.php`; // Chama o PHP dedicado
         // let url = `${API_BASE_URL}/all_in_one.php?action=listarPedidosAdmin`; // Ou use all_in_one
         if (status) { url += `?status=${encodeURIComponent(status)}`; }

         try {
             const response = await fetch(url, { credentials: 'include' }); // ESSENCIAL
             console.log("ADMIN_MAIN: Resposta fetch pedidos - Status:", response.status);

             if (response.status === 401 || response.status === 403) {
                 exibirMensagem("Acesso negado. Faça login como administrador.", "error");
                 corpoTabelaPedidos.innerHTML = `<tr><td colspan="8">Acesso Negado.</td></tr>`; return;
             }
             if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);

             const data = await response.json();
             console.log("ADMIN_MAIN: Dados dos pedidos recebidos:", data);
             if (data.sucesso && Array.isArray(data.pedidos)) {
                 renderizarTabelaPedidos(data.pedidos);
             } else { throw new Error(data.mensagem || "Resposta da API inválida."); }
         } catch (error) {
              console.error("Erro ao carregar pedidos:", error);
              exibirMensagem(`Falha ao carregar pedidos: ${error.message}`, "error");
              if(corpoTabelaPedidos) corpoTabelaPedidos.innerHTML = `<tr><td colspan="8">Falha ao carregar pedidos.</td></tr>`;
          }
     }

     function renderizarTabelaPedidos(pedidos) {
        if (!corpoTabelaPedidos) return;
        corpoTabelaPedidos.innerHTML = '';

        if (pedidos.length === 0) {
            corpoTabelaPedidos.innerHTML = `<tr><td colspan="9">Nenhum pedido encontrado...</td></tr>`; // Colspan 9
            return;
        }

        pedidos.forEach(pedido => {
            const row = document.createElement('tr');
            row.classList.add(`status-row-${(pedido.status || '').toLowerCase().replace(/\s+/g, '-')}`);
            row.setAttribute('data-pedido-id', pedido.id);

            let dataFormatada = 'Inválida';
             try { if (pedido.data_pedido) { dataFormatada = new Date(pedido.data_pedido).toLocaleString('pt-BR'); }} catch(e){}

            let statusSelectHtml = `<select class="status-pedido-select" data-pedido-id="${pedido.id}" ...>`;
            statusPermitidos.forEach(statusOpt => { const selected = (pedido.status === statusOpt) ? ' selected' : ''; statusSelectHtml += `<option value="${statusOpt}"${selected}>${statusOpt}</option>`; });
            statusSelectHtml += `</select>`;

            const itensFormatados = (pedido.itens_pedido || 'Nenhum item').replace(/;\s*/g, '<br>');
            // ** Pega a forma de pagamento (pode ser NULL) **
            const formaPagamentoTexto = pedido.forma_pagamento || 'Não informado';

            // Monta a linha da tabela com a nova célula "Pagamento"
            row.innerHTML = `
                <td data-label="ID Pedido">${pedido.id}</td>
                <td data-label="Data">${dataFormatada}</td>
                <td data-label="Cliente">${pedido.nome_cliente || '?'} (<a href="mailto:${pedido.email_cliente || ''}">${pedido.email_cliente || 'N/A'}</a>)</td>
                <td data-label="Telefone">${pedido.telefone_cliente || 'N/A'}</td>
                <td data-label="Endereço">${pedido.endereco_entrega || '?'}</td>
                <td data-label="Itens" class="itens-cell">${itensFormatados}</td>
                <td data-label="Total">R$ ${Number(pedido.valor_total).toFixed(2)}</td>
                <td data-label="Pagamento">${formaPagamentoTexto}</td> <!-- <<< CÉLULA PAGAMENTO -->
                <td data-label="Status" class="status-cell">${statusSelectHtml}</td>
            `;
            corpoTabelaPedidos.appendChild(row);
        });
    }

    // === Funções de Ação (Produtos) ===
    async function adicionarProduto() {
         console.log("1. Botão Adicionar Produto Clicado!");
         try {
             const nome = nomeInputAdd.value.trim();
             const descricao = descricaoInputAdd.value.trim();
             const preco = precoInputAdd.value;
             const categoria = categoriaSelectAdd.value;
             const imagemFile = imagemInputAdd.files[0];

             console.log("2. Dados coletados:", { nome, preco, categoria, imagemPresente: !!imagemFile });
             if (!nome || !preco || !categoria || isNaN(parseFloat(preco)) || parseFloat(preco) < 0) { exibirMensagem("Nome, Preço e Categoria válidos são obrigatórios.", "error"); return; }
             if (imagemFile && imagemFile.size > 5 * 1024 * 1024) { exibirMensagem("Imagem muito grande (máx 5MB).", "error"); return; }
              console.log("3c. Validação passou.");

             const formData = new FormData();
             // formData.append('action', 'adicionarProduto'); // DESCOMENTE se usar all_in_one.php
             formData.append('nome', nome);
             formData.append('descricao', descricao);
             formData.append('preco', preco);
             formData.append('categoria', categoria);
             if (imagemFile) formData.append('imagemProduto', imagemFile);

             console.log("5. Enviando requisição fetch para adicionar produto...");
              const response = await fetch(`${API_BASE_URL}/adicionar_produto.php`, { method: "POST", credentials: 'include', body: formData });
             // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { method: "POST", credentials: 'include', body: formData });
             console.log("6. Resposta recebida, Status:", response.status);

             let data; try { data = await response.json(); } catch(e){ throw new SyntaxError("Resposta servidor inválida."); }
             console.log("7b. Resposta JSON:", data);

             if (!response.ok) { throw new Error(data.mensagem || `Erro HTTP ${response.status}`); }
             if (!data.sucesso) { throw new Error(data.mensagem || "Falha API."); }

             console.log("8a. Sucesso API."); exibirMensagem(data.mensagem || "Produto adicionado!", "success");
             formAdicionarProduto.reset(); await carregarProdutos();

         } catch (error) {
             console.error("9. Erro durante adicionarProduto:", error);
             if (error instanceof SyntaxError) { exibirMensagem("Erro processar resposta servidor.", "error"); }
             else { exibirMensagem(`Erro: ${error.message}`, "error"); }
         }
     }

    async function salvarEdicao(row, id) {
         console.log(`Iniciando salvar edição para ID: ${id}`);
         try {
             const nome = row.querySelector(`#nome-${id}`).value.trim();
             const descricao = row.querySelector(`#descricao-${id}`).value.trim();
             const preco = row.querySelector(`#preco-${id}`).value;
             const categoria = row.querySelector(`#categoria-${id}`).value;
             const imagemInputEdit = row.querySelector(`#imagemEdit-${id}`);
             const novaImagemFile = imagemInputEdit ? imagemInputEdit.files[0] : null;

             console.log("Dados para salvar:", { id, nome, preco, categoria, novaImagem: !!novaImagemFile });
             if (!id || !nome || !preco || !categoria || isNaN(parseFloat(preco)) || parseFloat(preco) < 0) { exibirMensagem("Dados inválidos.", "error"); return; }
             if (novaImagemFile && novaImagemFile.size > 5 * 1024 * 1024) { exibirMensagem("Nova imagem grande.", "error"); return; }

             const formData = new FormData();
             // formData.append('action', 'editarProduto'); // DESCOMENTE se usar all_in_one.php
             formData.append('id', id);
             formData.append('nome', nome);
             formData.append('descricao', descricao);
             formData.append('preco', preco);
             formData.append('categoria', categoria);
             if (novaImagemFile) formData.append('imagemProduto', novaImagemFile);

             console.log("Enviando requisição fetch para editar produto...");
             const response = await fetch(`${API_BASE_URL}/editar_produto.php`, { method: "POST", credentials: 'include', body: formData });
             // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { method: "POST", credentials: 'include', body: formData });
             console.log("Resposta salvar recebida, Status:", response.status);

             let data; try { data = await response.json(); } catch(e){ throw new SyntaxError("Resposta servidor inválida."); }
             console.log("Resposta JSON salvar:", data);

             if (!response.ok) { throw new Error(data.mensagem || `Erro HTTP ${response.status}`); }
             if (!data.sucesso) { throw new Error(data.mensagem || "Falha API."); }

             exibirMensagem(data.mensagem || "Produto atualizado!", "success");
             await carregarProdutos(); // Recarrega para garantir consistência

         } catch(error) {
             console.error("Erro durante salvarEdicao:", error);
              if (error instanceof SyntaxError) { exibirMensagem("Erro processar resposta (salvar).", "error"); }
              else { exibirMensagem(`Erro ao salvar: ${error.message}`, "error"); }
         }
     }

    async function excluirProduto(id) {
         console.log(`Tentando excluir produto ID: ${id}`);
         if (!confirm(`Confirma exclusão do produto ID ${id}?`)) return;
         try {
             console.log("Enviando requisição fetch para excluir produto...");
             const response = await fetch(`${API_BASE_URL}/excluir_produto.php`, { // Ou all_in_one.php
                 method: "POST",
                 credentials: 'include',
                 headers: { "Content-Type": "application/json" },
                  body: JSON.stringify({ id })
                 // body: JSON.stringify({ action: 'excluirProduto', id: id }) // DESCOMENTE se usar all_in_one
             });
             console.log("Resposta excluir recebida, Status:", response.status);

             let data; try { data = await response.json(); } catch(e){ throw new SyntaxError("Resposta servidor inválida."); }
             console.log("Resposta JSON excluir:", data);

             if (!response.ok && response.status !== 404) { // Trata 404 separadamente
                 throw new Error(data.mensagem || `Erro HTTP ${response.status}`);
             }
             if (!data.sucesso) {
                 // Se API retornou sucesso: false, mas status era 200 ou 404
                 exibirMensagem(data.mensagem || `Falha ao excluir (Status: ${response.status}).`, "error");
                 if (response.status === 404) await carregarProdutos(); // Recarrega se não encontrou
             } else {
                 exibirMensagem(data.mensagem || "Produto excluído!", "success");
                 await carregarProdutos(); // Recarrega lista
             }
         } catch (error) {
             console.error("Erro durante excluirProduto:", error);
              if (error instanceof SyntaxError) { exibirMensagem("Erro processar resposta (excluir).", "error"); }
              else { exibirMensagem(`Erro ao excluir: ${error.message}`, "error"); }
         }
     }

    // === Funções de Ação (Pedidos - Salvamento Automático) ===
    async function atualizarStatusPedidoAPI(pedidoId, novoStatus, selectElement) {
         console.log(`API CALL: Atualizando status pedido ID ${pedidoId} para ${novoStatus}...`);
         exibirMensagem("Atualizando status...", "info");
         const statusAnterior = selectElement.dataset.previousStatus || selectElement.querySelector(`option[selected]`)?.value || novoStatus;
         selectElement.dataset.previousStatus = novoStatus; // Guarda o novo status como 'anterior' para a próxima mudança

         try {
             // Ajuste URL e body se usar all_in_one.php
              const response = await fetch(`${API_BASE_URL}/admin_atualizar_status_pedido.php`, {
             // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { // DESCOMENTE se usar all_in_one
                 method: 'POST', credentials: 'include', headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ pedido_id: pedidoId, novo_status: novoStatus })
                 // body: JSON.stringify({ action: 'atualizarStatusPedido', pedido_id: pedidoId, novo_status: novoStatus }) // DESCOMENTE se usar all_in_one
             });
             console.log(`API CALL Resposta status ${pedidoId}:`, response.status);
             let data; try { data = await response.json(); } catch(e){ throw new SyntaxError("Resposta servidor inválida."); }
             console.log(`API CALL Resposta JSON ${pedidoId}:`, data);
             if (!response.ok) { throw new Error(data.mensagem || `Erro HTTP ${response.status}`); }
             if (!data.sucesso) { throw new Error(data.mensagem || "Falha API."); } // Lança erro se API reportar falha

             // Sucesso
             exibirMensagem(data.mensagem || "Status atualizado!", "success");
             selectElement?.classList.add('status-salvo-ok'); setTimeout(() => selectElement?.classList.remove('status-salvo-ok'), 1500);
             Array.from(selectElement.options).forEach(opt => { opt.selected = (opt.value === novoStatus); });

         } catch (error) {
             console.error("Erro API atualização status:", error); exibirMensagem(`Erro: ${error.message}`, "error");
             if (selectElement && statusAnterior !== null) { selectElement.value = statusAnterior; } // Reverte visualmente
             else { await carregarPedidos(filtroStatusSelect?.value || ''); } // Fallback recarrega lista
         }
     }


    // --- Habilitar Edição (Produtos) ---
    function habilitarEdicao(row, id) {
         row.querySelectorAll('input[type="text"], input[type="number"], select').forEach(el => el.disabled = false);
         row.querySelector(`#imagemEdit-${id}`)?.classList.remove('hidden');
         row.querySelector('.td-imagem .nome-imagem-atual')?.classList.add('hidden');
         row.querySelector('.button-edit').classList.add('hidden');
         row.querySelector('.button-save').classList.remove('hidden');
         row.querySelector(`#nome-${id}`)?.focus();
     }

    // === Gerenciamento da Interface (Abas) ===
    adminNav.addEventListener('click', (event) => {
        if (event.target.tagName === 'A' || event.target.closest('li')) {
            event.preventDefault();
            const listItem = event.target.closest('li');
            if (!listItem || !listItem.dataset.tab) return;
            const tabId = listItem.dataset.tab;
            console.log(">>> Clicado na aba:", tabId);
            document.querySelectorAll('.admin-section').forEach(section => section.classList.add('hidden'));
            document.querySelectorAll('.admin-nav ul li').forEach(li => li.classList.remove('active'));
            const sectionToShow = document.getElementById(tabId);
            if (sectionToShow) {
                sectionToShow.classList.remove('hidden');
                listItem.classList.add('active');
                if (tabId === 'admin-pedidos') { carregarPedidos(filtroStatusSelect.value); }
                // else if (tabId === 'admin-produtos') { carregarProdutos(); } // Descomente se quiser recarregar produtos sempre
            } else { console.error("Seção não encontrada para a aba:", tabId); }
        }
    });

    // === Listeners Específicos das Seções ===

    // Botão ADICIONAR PRODUTO
    btnAdicionarProduto.addEventListener("click", adicionarProduto);

    // Listener para Tabela de PRODUTOS (Editar/Salvar/Excluir)
    if(listaProdutosTbody) {
        listaProdutosTbody.addEventListener('click', async (event) => {
           const target = event.target;
           const id = target.getAttribute('data-id');
           if (!id || !target.matches('button')) return;
           const row = target.closest('tr');
           if (!row) return;

           if (target.classList.contains('button-edit')) { habilitarEdicao(row, id); }
           else if (target.classList.contains('button-save')) { await salvarEdicao(row, id); }
           else if (target.classList.contains('button-delete')) { await excluirProduto(id); }
       });
    }

    // Listeners SEÇÃO PEDIDOS
    filtroStatusSelect.addEventListener('change', () => carregarPedidos(filtroStatusSelect.value));
    btnRecarregar.addEventListener('click', () => carregarPedidos(filtroStatusSelect.value));

    // Listener de CHANGE para SALVAMENTO AUTOMÁTICO Status Pedido
    if(corpoTabelaPedidos) {
        corpoTabelaPedidos.addEventListener('change', (event) => {
            if (event.target.classList.contains('status-pedido-select')) {
                const select = event.target;
                const pedidoId = select.getAttribute('data-pedido-id');
                const novoStatus = select.value;
                if (pedidoId && novoStatus) {
                     atualizarStatusPedidoAPI(pedidoId, novoStatus, select);
                }
            }
        });
    }

    // Listener de CLICK na tabela de PEDIDOS para abrir o MODAL (Se existir)
    


    // === Inicialização ===
    console.log("Iniciando Admin Main JS...");
    // Mostra a aba de produtos por padrão e carrega os produtos
    document.querySelector('.admin-nav ul li[data-tab="admin-produtos"]')?.classList.add('active');
    document.getElementById('admin-produtos')?.classList.remove('hidden');
    document.getElementById('admin-pedidos')?.classList.add('hidden');
    carregarProdutos();

}); // Fim do DOMContentLoaded