// js/admin_main.js - COMBINED ADMIN PANEL - CORRIGIDO

document.addEventListener("DOMContentLoaded", function () {
    // === Configurações e Constantes ===
    const API_BASE_URL = "http://localhost/pizzaria_express/api"; // !! VERIFIQUE SUA URL DA API !!
    const UPLOADS_BASE_URL = 'uploads/produtos/';         // Caminho base para exibir imagens
    const PLACEHOLDER_IMG = 'img/placeholder.png';           // Imagem padrão

    // === Referências aos Elementos do DOM ===
    const adminWrapper = document.querySelector(".admin-wrapper");
    const adminNav = document.querySelector(".admin-nav ul");
    const mensagemAdmin = document.getElementById("mensagem-admin");

    // Seção Produtos
    const adminProdutosSection = document.getElementById('admin-produtos');
    const formAdicionarProduto = document.getElementById("formAdicionarProduto");
    const btnAdicionarProduto = document.getElementById("btnAdicionarProduto");
    const listaProdutosTbody = document.getElementById("tabela-produtos")?.querySelector('tbody');
    const nomeInputAdd = document.getElementById("nomeProduto");
    const descricaoInputAdd = document.getElementById("descricaoProduto");
    const precoInputAdd = document.getElementById("precoProduto");
    const categoriaSelectAdd = document.getElementById("categoriaProduto");
    const imagemInputAdd = document.getElementById("imagemProduto");

    // Seção Pedidos
    const adminPedidosSection = document.getElementById('admin-pedidos');
    const corpoTabelaPedidos = document.getElementById('tabela-pedidos')?.querySelector('tbody');
    const filtroStatusSelect = document.getElementById('filtro-status');
    const btnRecarregar = document.getElementById('btn-recarregar-pedidos');

    // ---------- BLOCO REMOVIDO DAQUI ----------
    // const response = await fetch(...); // <--- ESTE BLOCO FOI REMOVIDO
    // ---------- FIM DO BLOCO REMOVIDO ----------

    // --- Validação de Elementos Essenciais ---
    // Verifica se todos os elementos principais foram encontrados para evitar erros posteriores
     const elementosEssenciais = [
         adminWrapper, adminNav, mensagemAdmin, adminProdutosSection, formAdicionarProduto,
         btnAdicionarProduto, listaProdutosTbody, nomeInputAdd, descricaoInputAdd, precoInputAdd,
         categoriaSelectAdd, imagemInputAdd, adminPedidosSection, corpoTabelaPedidos,
         filtroStatusSelect, btnRecarregar
     ];
     if (elementosEssenciais.some(el => !el)) { // some() retorna true se algum for null/undefined
         console.error("ERRO FATAL: Um ou mais elementos essenciais do DOM da página admin não foram encontrados. Verifique os IDs no HTML e JS.");
         if (mensagemAdmin) exibirMensagem("Erro crítico: Falha ao carregar interface do admin.", "error");
         return; // Interrompe a execução se algo crucial faltar
     }
     console.log("ADMIN_MAIN: Todos os elementos essenciais encontrados.");


    // --- Funções Utilitárias ---
    function exibirMensagem(texto, tipo = "info") { /* ... (código igual) ... */ }
    function validateNonEmpty(value, fieldName, errors) { /* ... (código igual) ... */ }
    function validateNumber(value, fieldName, errors) { /* ... (código igual) ... */ }


    // --- Funções de Carregamento e Renderização ---
    async function carregarProdutos() {
         console.log("ADMIN_MAIN: Carregando produtos...");
         if (!listaProdutosTbody) return; // Segurança extra
         listaProdutosTbody.innerHTML = '<tr><td colspan="7">Carregando produtos...</td></tr>';
         try {
             // Ajuste a URL se estiver usando all_in_one.php
              const response = await fetch(`${API_BASE_URL}/produtos.php`);
             // const response = await fetch(`${API_BASE_URL}/all_in_one.php?action=listarProdutos`); // DESCOMENTE SE USAR all_in_one

             if (!response.ok) throw new Error(`Erro HTTP ao buscar: ${response.status}`);
             const produtos = await response.json();
             listaProdutosTbody.innerHTML = ""; // Limpa

             if (!Array.isArray(produtos)) throw new Error("Resposta da API (produtos) inválida.");
             if (produtos.length === 0) {
                 listaProdutosTbody.innerHTML = '<tr><td colspan="7">Nenhum produto cadastrado.</td></tr>'; return;
             }

             produtos.forEach(produto => {
                 const row = document.createElement("tr");
                 row.setAttribute('data-id', produto.id);
                 const imagemUrl = produto.imagem_url ? produto.imagem_url : PLACEHOLDER_IMG;
                 const nomeImagem = produto.imagem_nome || 'Nenhuma';

                 // InnerHTML da linha (igual ao código anterior)
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
         } catch (error) { /* ... (tratamento de erro igual) ... */ }
     }

    async function carregarPedidos(status = '') {
         console.log(`ADMIN_MAIN: Carregando pedidos ${status ? `com status "${status}"` : 'todos'}...`);
         if (!corpoTabelaPedidos) return; // Segurança
         corpoTabelaPedidos.innerHTML = `<tr><td colspan="7">Carregando pedidos...</td></tr>`;

         // Ajuste a URL se estiver usando all_in_one.php
          let url = `${API_BASE_URL}/admin_listar_pedidos.php`;
         // let url = `${API_BASE_URL}/all_in_one.php?action=listarPedidosAdmin`; // DESCOMENTE SE USAR all_in_one
         if (status) { url += `?status=${encodeURIComponent(status)}`; }

         try {
             // !! ESSENCIAL !! para enviar cookie de sessão
             const response = await fetch(url, { credentials: 'include' });
             console.log("ADMIN_MAIN: Resposta fetch pedidos - Status:", response.status);

             if (response.status === 401 || response.status === 403) {
                  exibirMensagem("Acesso negado. Faça login como administrador.", "error");
                  // Idealmente, o global.js também detectaria isso e redirecionaria
                  corpoTabelaPedidos.innerHTML = `<tr><td colspan="7">Acesso Negado.</td></tr>`;
                  return;
             }
             if (!response.ok) throw new Error(`Erro HTTP: ${response.status}`);

             const data = await response.json();
             if (data.sucesso && Array.isArray(data.pedidos)) {
                 renderizarTabelaPedidos(data.pedidos);
             } else { throw new Error(data.mensagem || "Resposta da API inválida."); }
         } catch (error) { /* ... (tratamento de erro igual) ... */ }
     }

     // Status permitidos para o select de mudança
     const statusPermitidos = ['Recebido', 'Em Preparo', 'Saiu para Entrega', 'Entregue', 'Cancelado'];

    function renderizarTabelaPedidos(pedidos) {
         // Renders the order list into the table
         if (!corpoTabelaPedidos) return;
         corpoTabelaPedidos.innerHTML = '';

         if (pedidos.length === 0) { /* ... (mensagem nenhum pedido) ... */ return; }

         pedidos.forEach(pedido => {
             const row = document.createElement('tr');
             row.setAttribute('data-pedido-id', pedido.id);
             let dataFormatada = 'Inválida';
              try { if (pedido.data_pedido) { dataFormatada = new Date(pedido.data_pedido).toLocaleString('pt-BR'); }} catch(e){}

             // Cria o <select> para o status
             let statusSelectHtml = `<select class="status-pedido-select" data-pedido-id="${pedido.id}" aria-label="Status do Pedido ${pedido.id}">`;
             statusPermitidos.forEach(statusOpt => {
                  const selected = (pedido.status === statusOpt) ? ' selected' : '';
                  statusSelectHtml += `<option value="${statusOpt}"${selected}>${statusOpt}</option>`;
             });
             statusSelectHtml += `</select>`;

             row.innerHTML = `
                 <td>${pedido.id}</td>
                 <td>${dataFormatada}</td>
                 <td>${pedido.nome_cliente || '?'} (<a href="mailto:${pedido.email_cliente || ''}">${pedido.email_cliente || 'N/A'}</a>)</td>
                 <td>${pedido.telefone_cliente || 'N/A'}</td>
                 <td>${pedido.endereco_entrega || '?'}</td>
                 <td>R$ ${Number(pedido.valor_total).toFixed(2)}</td>
                 <td class="status-cell">${statusSelectHtml}</td>
             `;
             corpoTabelaPedidos.appendChild(row);
         });
     }

    // === Funções de Ação (Produtos) ===
    async function adicionarProduto() {
         console.log("1. Botão Adicionar Produto Clicado!"); // Movido para dentro do listener
         try {
             const nome = nomeInputAdd.value.trim();
             const descricao = descricaoInputAdd.value.trim();
             const preco = precoInputAdd.value;
             const categoria = categoriaSelectAdd.value;
             const imagemFile = imagemInputAdd.files[0];

             console.log("2. Dados coletados:", { nome, preco, categoria, imagemPresente: !!imagemFile });
             if (!nome || !preco || !categoria || isNaN(parseFloat(preco)) || parseFloat(preco) < 0) { /* ... validação ... */ return; }
             if (imagemFile && imagemFile.size > 5 * 1024 * 1024) { /* ... validação tamanho ... */ return; }
              console.log("3c. Validação passou.");

             const formData = new FormData();
             formData.append('action', 'adicionarProduto'); // <<== ADICIONA ACTION para all_in_one.php
             formData.append('nome', nome);
             formData.append('descricao', descricao);
             formData.append('preco', preco);
             formData.append('categoria', categoria);
             if (imagemFile) formData.append('imagemProduto', imagemFile);

             console.log("5. Enviando requisição fetch para adicionar produto...");
             // Ajuste a URL se usar all_in_one.php
              const response = await fetch(`${API_BASE_URL}/adicionar_produto.php`, { method: "POST", credentials: 'include', body: formData });
             // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { method: "POST", credentials: 'include', body: formData }); // DESCOMENTE se usar all_in_one
             console.log("6. Resposta recebida, Status:", response.status);

             if (!response.ok) { /* ... tratamento erro HTTP ... */ }
             const data = await response.json();
             console.log("7b. Resposta JSON recebida:", data);
             if (data.sucesso) { /* ... tratamento sucesso ... */ }
             else { /* ... tratamento falha API ... */ }
         } catch (error) { /* ... tratamento erro catch ... */ }
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
             if (!id || !nome || !preco || !categoria || isNaN(parseFloat(preco)) || parseFloat(preco) < 0) { /* ... validação ... */ return; }
              if (novaImagemFile && novaImagemFile.size > 5 * 1024 * 1024) { /* ... validação tamanho ... */ return; }

             const formData = new FormData();
             formData.append('action', 'editarProduto'); // <<== ADICIONA ACTION
             formData.append('id', id);
             formData.append('nome', nome);
             formData.append('descricao', descricao);
             formData.append('preco', preco);
             formData.append('categoria', categoria);
             if (novaImagemFile) formData.append('imagemProduto', novaImagemFile);

             console.log("Enviando requisição fetch para editar produto...");
             // Ajuste a URL se usar all_in_one.php
              const response = await fetch(`${API_BASE_URL}/editar_produto.php`, { method: "POST", credentials: 'include', body: formData });
             // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { method: "POST", credentials: 'include', body: formData }); // DESCOMENTE se usar all_in_one
             console.log("Resposta salvar recebida, Status:", response.status);

             if (!response.ok) { /* ... tratamento erro HTTP ... */ }
             const data = await response.json();
             console.log("Resposta JSON salvar:", data);
             if (data.sucesso) { /* ... tratamento sucesso ... */ }
             else { /* ... tratamento falha API ... */ }
         } catch(error) { /* ... tratamento erro catch ... */ }
     }

    async function excluirProduto(id) {
         console.log(`Tentando excluir produto ID: ${id}`);
         if (!confirm(`Confirma exclusão do produto ID ${id}?`)) return;
         try {
             console.log("Enviando requisição fetch para excluir produto...");
             // Ajuste a URL se usar all_in_one.php
              const response = await fetch(`${API_BASE_URL}/excluir_produto.php`, {
             // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { // DESCOMENTE se usar all_in_one
                 method: "POST",
                 credentials: 'include',
                 headers: { "Content-Type": "application/json" },
                 body: JSON.stringify({ action: 'excluirProduto', id: id }) // <<== ADICIONA ACTION
             });
             console.log("Resposta excluir recebida, Status:", response.status);

             if (!response.ok) { /* ... tratamento erro HTTP ... */ }
             const data = await response.json();
             console.log("Resposta JSON excluir:", data);
             if (data.sucesso) { /* ... tratamento sucesso ... */ }
             else { /* ... tratamento falha API (incluindo 404) ... */ }
         } catch (error) { /* ... tratamento erro catch ... */ }
     }

    // === Funções de Ação (Pedidos) ===
    async function atualizarStatusPedido(pedidoId, novoStatus) {
         console.log(`ADMIN_MAIN: Atualizando status pedido ID ${pedidoId} para ${novoStatus}...`);
         exibirMensagem("Atualizando status...", "info");
         try {
              // Ajuste a URL se usar all_in_one.php
              const response = await fetch(`${API_BASE_URL}/admin_atualizar_status_pedido.php`, {
              // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { // DESCOMENTE se usar all_in_one
                 method: 'POST',
                 credentials: 'include',
                 headers: { 'Content-Type': 'application/json' },
                 body: JSON.stringify({ action: 'atualizarStatusPedido', pedido_id: pedidoId, novo_status: novoStatus }) // <<== ADICIONA ACTION
             });
             console.log("ADMIN_MAIN: Resposta atualização status - Status:", response.status);
             const data = await response.json();
             console.log("ADMIN_MAIN: Resposta JSON atualização:", data);

             if (!response.ok) { throw new Error(data.mensagem || `Erro HTTP ${response.status}`); }
             if (data.sucesso) {
                 exibirMensagem(data.mensagem || "Status atualizado!", "success");
                 // Opcional: Não recarregar, apenas confirmar visualmente a mudança no select
                 // carregarPedidos(filtroStatusSelect.value);
             } else {
                 exibirMensagem(data.mensagem || "Falha ao atualizar status.", "error");
                 // Tenta recarregar para reverter visualmente o select
                 await carregarPedidos(filtroStatusSelect.value);
             }
         } catch (error) { /* ... tratamento erro catch ... */ }
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
         if (event.target.tagName === 'A' || event.target.closest('li')) { // Clicou no link ou no LI
             event.preventDefault();
             const listItem = event.target.closest('li');
             if (!listItem || !listItem.dataset.tab) return; // Sai se não for uma aba válida

             const tabId = listItem.dataset.tab;
             console.log(">>> Clicado na aba:", tabId);

             // Esconde todas as seções e desativa todas as abas
             document.querySelectorAll('.admin-section').forEach(section => section.classList.add('hidden'));
             document.querySelectorAll('.admin-nav ul li').forEach(li => li.classList.remove('active'));

             // Mostra a seção correta e ativa a aba
             const sectionToShow = document.getElementById(tabId);
             if (sectionToShow) {
                 sectionToShow.classList.remove('hidden');
                 listItem.classList.add('active');
                 // Carrega dados da aba recém-ativada
                 if (tabId === 'admin-pedidos') {
                     console.log("Carregando pedidos ao selecionar aba...");
                     carregarPedidos(filtroStatusSelect.value);
                 } else if (tabId === 'admin-produtos') {
                      console.log("Carregando produtos ao selecionar aba..."); // Ou já carregou inicialmente
                      // carregarProdutos(); // Descomente se quiser recarregar sempre
                 }
             } else {
                 console.error("Seção não encontrada para a aba:", tabId);
             }
         }
     });

    // === Listeners Específicos das Seções ===

    // Listener botão ADICIONAR PRODUTO
    btnAdicionarProduto.addEventListener("click", adicionarProduto); // Chama a função refatorada

    // Listeners SEÇÃO PEDIDOS
    filtroStatusSelect.addEventListener('change', () => carregarPedidos(filtroStatusSelect.value));
    btnRecarregar.addEventListener('click', () => carregarPedidos(filtroStatusSelect.value));
    corpoTabelaPedidos.addEventListener('change', (event) => { // Delegação para selects de status
         if (event.target.classList.contains('status-pedido-select')) {
             const select = event.target;
             const pedidoId = select.getAttribute('data-pedido-id');
             const novoStatus = select.value;
             if (pedidoId && novoStatus) {
                 atualizarStatusPedido(pedidoId, novoStatus);
             }
         }
     });

     // Listener SEÇÃO PRODUTOS (Delegação na tabela)
     listaProdutosTbody.addEventListener('click', async (event) => {
        const target = event.target;
        const id = target.getAttribute('data-id');
        if (!id || !target.matches('button')) return;
        const row = target.closest('tr');

        if (target.classList.contains('button-edit')) { habilitarEdicao(row, id); }
        else if (target.classList.contains('button-save')) { await salvarEdicao(row, id); }
        else if (target.classList.contains('button-delete')) { await excluirProduto(id); }
    });


    // === Inicialização ===
    console.log("Iniciando Admin Main JS...");
    // Não precisa mais da verificação de acesso admin aqui se o global.js já faz
    // e se as APIs individuais estão protegidas.

    // Mostra a aba de produtos por padrão e carrega os produtos
     document.querySelector('.admin-nav ul li[data-tab="admin-produtos"]')?.classList.add('active');
     document.getElementById('admin-produtos')?.classList.remove('hidden');
     document.getElementById('admin-pedidos')?.classList.add('hidden'); // Garante que pedidos começa escondido
     carregarProdutos(); // Carrega produtos ao iniciar

}); // Fim do DOMContentLoaded