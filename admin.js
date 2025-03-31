// js/admin.js

document.addEventListener("DOMContentLoaded", function () {
    // --- Configurações e Constantes ---
    const API_BASE_URL = "http://localhost/pizzaria_express/api"; // !! VERIFIQUE SUA URL DA API !!
    const UPLOADS_BASE_URL = 'uploads/produtos/';         // Caminho base para exibir imagens (relativo ao HTML)
    const PLACEHOLDER_IMG = 'img/placeholder.png';           // Imagem padrão (certifique-se que existe em pizzaria_express/img/)

    // --- Referências aos Elementos do DOM ---

    // Elementos Gerais
    const listaProdutosTbody = document.getElementById("listaProdutos");
    const mensagemAdmin = document.getElementById("mensagem-admin");

    // Elementos do Formulário de ADICIONAR Produto
    const formAdicionarProduto = document.getElementById("formAdicionarProduto");
    const btnAdicionarProduto = document.getElementById("btnAdicionarProduto");
    const nomeInputAdd = document.getElementById("nomeProduto");
    const descricaoInputAdd = document.getElementById("descricaoProduto");
    const precoInputAdd = document.getElementById("precoProduto");
    const categoriaSelectAdd = document.getElementById("categoriaProduto");
    const imagemInputAdd = document.getElementById("imagemProduto");

    // Verifica se todos os elementos essenciais foram encontrados
    if (!listaProdutosTbody || !mensagemAdmin || !formAdicionarProduto || !btnAdicionarProduto || !nomeInputAdd || !descricaoInputAdd || !precoInputAdd || !categoriaSelectAdd || !imagemInputAdd) {
        console.error("ERRO FATAL: Um ou mais elementos essenciais do DOM não foram encontrados. Verifique os IDs no admin.html e admin.js.");
        // Exibe mensagem para o usuário, se possível
        if (mensagemAdmin) {
             exibirMensagem("Erro crítico: Falha ao carregar interface do admin. Verifique o console.", "error");
        }
        return; // Interrompe a execução do script se elementos essenciais faltam
    }


    // --- Função Utilitária para Exibir Mensagens ---
    function exibirMensagem(texto, tipo = "info") {
         mensagemAdmin.textContent = texto;
         mensagemAdmin.className = `mensagem ${tipo}`; // Usa classes CSS para estilizar
         // Limpa a mensagem após 5 segundos
         setTimeout(() => {
             if (mensagemAdmin.textContent === texto) { // Evita limpar msg mais recente
                  mensagemAdmin.textContent = '';
                  mensagemAdmin.className = 'mensagem';
             }
         }, 5000);
    }

    // --- Carregar Produtos na Tabela ---
    async function carregarProdutos() {
        try {
            const response = await fetch(`${API_BASE_URL}/produtos.php`);
            if (!response.ok) { throw new Error(`Erro HTTP ao buscar: ${response.status}`); }

            const produtos = await response.json();
            listaProdutosTbody.innerHTML = ""; // Limpa tabela

            if (!Array.isArray(produtos)) { throw new Error("Resposta da API (produtos) inválida."); }

            if (produtos.length === 0) {
                listaProdutosTbody.innerHTML = '<tr><td colspan="7">Nenhum produto cadastrado.</td></tr>';
                return;
            }

            // Cria as linhas da tabela
            produtos.forEach(produto => {
                const row = document.createElement("tr");
                row.setAttribute('data-id', produto.id);

                // Define URL da imagem (ou placeholder) e nome do arquivo
                const imagemUrl = produto.imagem_url ? produto.imagem_url : PLACEHOLDER_IMG;
                const nomeImagem = produto.imagem_nome || 'Nenhuma';

                // Adiciona data-label para responsividade CSS
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
             exibirMensagem(`Falha ao carregar produtos: ${error.message}. Verifique API/Console.`, "error");
             listaProdutosTbody.innerHTML = `<tr><td colspan="7">Erro ao carregar produtos.</td></tr>`;
        }
    }

    // --- Adicionar Produto ---
    btnAdicionarProduto.addEventListener("click", async function () {
        console.log("1. Botão Adicionar Produto Clicado!");
        try {
            const nome = nomeInputAdd.value.trim();
            const descricao = descricaoInputAdd.value.trim();
            const preco = precoInputAdd.value;
            const categoria = categoriaSelectAdd.value;
            const imagemFile = imagemInputAdd.files[0]; // Pega o arquivo selecionado

            console.log("2. Dados coletados:", { nome, preco, categoria, imagemPresente: !!imagemFile });

            // Validação Frontend
            if (!nome || !preco || !categoria || isNaN(parseFloat(preco)) || parseFloat(preco) < 0) {
                console.error("3a. Validação falhou (dados básicos).");
                exibirMensagem("Nome, Preço e Categoria são obrigatórios e válidos.", "error"); return;
            }
            if (imagemFile && imagemFile.size > 5 * 1024 * 1024) { // 5MB limit
                 console.error("3b. Validação falhou (tamanho imagem).");
                 exibirMensagem("A imagem selecionada é muito grande (máx 5MB).", "error"); return;
             }
             console.log("3c. Validação passou.");

            // Cria FormData para enviar dados e arquivo
            const formData = new FormData();
            formData.append('nome', nome);
            formData.append('descricao', descricao);
            formData.append('preco', preco);
            formData.append('categoria', categoria);
            if (imagemFile) {
                 formData.append('imagemProduto', imagemFile); // Nome DEVE corresponder ao esperado pelo PHP
                 console.log("4a. Arquivo de imagem adicionado ao FormData.");
            } else { console.log("4b. Nenhum arquivo de imagem selecionado."); }

            console.log("5. Enviando requisição fetch para adicionar_produto.php...");
            const response = await fetch(`${API_BASE_URL}/adicionar_produto.php`, { method: "POST", body: formData });
            console.log("6. Resposta recebida, Status:", response.status);

            if (!response.ok) {
                const errorText = await response.text(); // Ler corpo como texto em caso de erro
                console.error("7a. Erro na resposta HTTP:", response.status, response.statusText, "Corpo:", errorText);
                exibirMensagem(`Erro do servidor (${response.status}). Verifique console/API.`, "error");
                throw new Error(`HTTP error ${response.status}`);
            }

            const data = await response.json(); // Tenta parsear como JSON
            console.log("7b. Resposta JSON recebida:", data);

            if (data.sucesso) {
                console.log("8a. Sucesso reportado pela API.");
                exibirMensagem(data.mensagem || "Produto adicionado!", "success");
                formAdicionarProduto.reset(); // Limpa o formulário inteiro
                // imagemInputAdd.value = null; // reset() geralmente limpa file input também
                await carregarProdutos(); // Recarrega a lista
            } else {
                console.error("8b. Falha reportada pela API:", data.mensagem);
                exibirMensagem(data.mensagem || "Falha ao adicionar.", "error");
            }

        } catch (error) {
            console.error("9. Erro durante fetch ou processamento:", error);
            if (error instanceof SyntaxError) { // Erro ao parsear JSON
                 exibirMensagem("Erro ao processar resposta do servidor (não é JSON). Verifique API/Console.", "error");
            } else if (!error.message.startsWith('HTTP error')) { // Outros erros (rede, script)
                 exibirMensagem(`Erro: ${error.message}`, "error");
            } // Erro HTTP já tratado acima
        }
    });


    // --- Delegação de Eventos na Tabela para Editar/Salvar/Excluir ---
    listaProdutosTbody.addEventListener('click', async (event) => {
        const target = event.target;
        const id = target.getAttribute('data-id');
        if (!id || !target.matches('button')) return; // Só reage a cliques em botões com data-id
        const row = target.closest('tr'); // A linha do produto

        // Determina a ação com base na classe do botão
        if (target.classList.contains('button-edit')) { habilitarEdicao(row, id); }
        else if (target.classList.contains('button-save')) { await salvarEdicao(row, id); }
        else if (target.classList.contains('button-delete')) { await excluirProduto(id); }
    });


    // --- Habilitar Modo de Edição para uma Linha ---
    function habilitarEdicao(row, id) {
        // Habilita inputs de texto/número e select
        row.querySelectorAll('input[type="text"], input[type="number"], select').forEach(el => el.disabled = false);
        // Mostra o input de arquivo específico da linha e esconde nome atual
        row.querySelector(`#imagemEdit-${id}`)?.classList.remove('hidden');
        row.querySelector('.td-imagem .nome-imagem-atual')?.classList.add('hidden');
        // Troca os botões Editar/Salvar
        row.querySelector('.button-edit').classList.add('hidden');
        row.querySelector('.button-save').classList.remove('hidden');
        // Foca no campo nome
        row.querySelector(`#nome-${id}`)?.focus();
    }


    // --- Salvar Edições de um Produto ---
    async function salvarEdicao(row, id) {
        console.log(`Iniciando salvar edição para ID: ${id}`);
        try {
            const nome = row.querySelector(`#nome-${id}`).value.trim();
            const descricao = row.querySelector(`#descricao-${id}`).value.trim();
            const preco = row.querySelector(`#preco-${id}`).value;
            const categoria = row.querySelector(`#categoria-${id}`).value;
            const imagemInputEdit = row.querySelector(`#imagemEdit-${id}`);
            const novaImagemFile = imagemInputEdit ? imagemInputEdit.files[0] : null; // Pega novo arquivo

            console.log("Dados para salvar:", { id, nome, preco, categoria, novaImagem: !!novaImagemFile });

            // Validação Frontend
            if (!nome || !preco || !categoria || isNaN(parseFloat(preco)) || parseFloat(preco) < 0) {
                 console.error("Validação salvar falhou (dados básicos)");
                 exibirMensagem("Dados inválidos para salvar.", "error"); return;
             }
             if (novaImagemFile && novaImagemFile.size > 5 * 1024 * 1024) { // 5MB limit
                  console.error("Validação salvar falhou (tamanho imagem)");
                  exibirMensagem("Nova imagem muito grande (máx 5MB).", "error"); return;
              }

            // Cria FormData para enviar dados e possível novo arquivo
            const formData = new FormData();
            formData.append('id', id); // Enviar o ID é crucial para o UPDATE
            formData.append('nome', nome);
            formData.append('descricao', descricao);
            formData.append('preco', preco);
            formData.append('categoria', categoria);
            if (novaImagemFile) {
                formData.append('imagemProduto', novaImagemFile); // Nome DEVE corresponder ao PHP
                console.log("Novo arquivo de imagem adicionado para salvar.");
            } else {
                 console.log("Nenhum novo arquivo de imagem selecionado para salvar.");
            }
             // O PHP `editar_produto.php` deve manter a imagem antiga se 'imagemProduto' não for enviado

            console.log("Enviando requisição fetch para editar_produto.php...");
            const response = await fetch(`${API_BASE_URL}/editar_produto.php`, { method: "POST", body: formData });
            console.log("Resposta salvar recebida, Status:", response.status);

            if (!response.ok) {
                const errorText = await response.text();
                console.error("Erro HTTP ao salvar:", response.status, response.statusText, "Corpo:", errorText);
                exibirMensagem(`Erro do servidor (${response.status}) ao salvar.`, "error");
                throw new Error(`HTTP error ${response.status}`);
            }

            const data = await response.json();
            console.log("Resposta JSON salvar:", data);

            if (data.sucesso) {
                exibirMensagem(data.mensagem || "Produto atualizado!", "success");
                // Bloqueia campos, troca botões, limpa/esconde file input
                row.querySelectorAll('input, select').forEach(el => el.disabled = true);
                 if(imagemInputEdit) {
                    imagemInputEdit.classList.add('hidden');
                    imagemInputEdit.value = null; // Limpa seleção
                 }
                 row.querySelector('.button-edit').classList.remove('hidden');
                 row.querySelector('.button-save').classList.add('hidden');
                 row.querySelector('.td-imagem .nome-imagem-atual')?.classList.remove('hidden');

                // Recarrega a lista para garantir que a imagem (se mudou) seja exibida corretamente
                 await carregarProdutos();
            } else {
                console.error("Falha reportada pela API ao salvar:", data.mensagem);
                exibirMensagem(data.mensagem || "Falha ao atualizar.", "error");
            }

        } catch(error) {
            console.error("Erro durante salvarEdicao:", error);
             if (error instanceof SyntaxError) {
                 exibirMensagem("Erro ao processar resposta do servidor (salvar).", "error");
             } else if (!error.message.startsWith('HTTP error')) {
                 exibirMensagem(`Erro ao salvar: ${error.message}`, "error");
             } // Erro HTTP já tratado
        }
    }


    // --- Excluir Produto ---
    async function excluirProduto(id) {
        console.log(`Tentando excluir produto ID: ${id}`);
        if (!confirm(`Confirma a exclusão do produto ID ${id}? Imagem associada também será excluída permanentemente.`)) {
            console.log("Exclusão cancelada pelo usuário.");
            return;
        }
        try {
            console.log("Enviando requisição fetch para excluir_produto.php...");
            const response = await fetch(`${API_BASE_URL}/excluir_produto.php`, {
                method: "POST",
                headers: { "Content-Type": "application/json" },
                body: JSON.stringify({ id }) // Envia ID como JSON
            });
            console.log("Resposta excluir recebida, Status:", response.status);

            if (!response.ok) {
                 const errorText = await response.text();
                 console.error("Erro HTTP ao excluir:", response.status, response.statusText, "Corpo:", errorText);
                 exibirMensagem(`Erro do servidor (${response.status}) ao excluir.`, "error");
                 throw new Error(`HTTP error ${response.status}`);
             }

            const data = await response.json();
            console.log("Resposta JSON excluir:", data);

            if (data.sucesso) {
                exibirMensagem(data.mensagem || "Produto excluído!", "success");
                await carregarProdutos(); // Recarrega a lista para remover a linha
            } else {
                 console.error("Falha reportada pela API ao excluir:", data.mensagem);
                 // Tratar caso 404 (produto não encontrado) especificamente, se a API retornar isso
                 if (response.status === 404) {
                      exibirMensagem(data.mensagem || `Produto ID ${id} não encontrado para excluir.`, "error");
                      await carregarProdutos(); // Recarrega mesmo se não achou, para atualizar a lista
                 } else {
                      exibirMensagem(data.mensagem || "Falha ao excluir.", "error");
                 }
            }
        } catch (error) {
             console.error("Erro durante excluirProduto:", error);
              if (error instanceof SyntaxError) {
                 exibirMensagem("Erro ao processar resposta do servidor (excluir).", "error");
             } else if (!error.message.startsWith('HTTP error')) {
                 exibirMensagem(`Erro ao excluir: ${error.message}`, "error");
             } // Erro HTTP já tratado
        }
    }

    // --- Carregamento Inicial ---
    // Chama a função para carregar os produtos assim que o DOM estiver pronto
    console.log("Iniciando carregamento inicial de produtos...");
    carregarProdutos();

}); // Fim do DOMContentLoaded