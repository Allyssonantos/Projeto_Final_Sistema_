// --- START OF FILE admin.js ---

document.addEventListener("DOMContentLoaded", function () {
    // URL base da sua API. Certifique-se que está correta!
    const API_BASE_URL = "http://localhost/pizzaria_express/api";

    // Referências aos elementos do DOM
    const btnAdicionarProduto = document.getElementById("btnAdicionarProduto");
    const listaProdutosTbody = document.getElementById("listaProdutos");
    const mensagemAdmin = document.getElementById("mensagem-admin"); // Container para feedback

    // Elementos do formulário de adicionar produto
    const nomeInput = document.getElementById("nomeProduto");
    const descricaoInput = document.getElementById("descricaoProduto");
    const precoInput = document.getElementById("precoProduto");
    const categoriaSelect = document.getElementById("categoriaProduto");

    // --- Função para exibir mensagens de feedback ---
    function exibirMensagem(texto, tipo = "info") { // tipo pode ser 'success', 'error', ou 'info'
        if (!mensagemAdmin) return; // Sai se o elemento de mensagem não existir
        mensagemAdmin.textContent = texto;
        // Define a classe CSS para estilizar a mensagem (usando classes de base.css ou admin.css)
        mensagemAdmin.className = `mensagem ${tipo}`;
        // Limpa a mensagem automaticamente após alguns segundos
        setTimeout(() => {
            if (mensagemAdmin.textContent === texto) { // Evita limpar uma msg mais recente
                 mensagemAdmin.textContent = '';
                 mensagemAdmin.className = 'mensagem'; // Reseta a classe
            }
        }, 5000); // Mensagem visível por 5 segundos
    }

    // --- Carregar Produtos na Tabela ---
    async function carregarProdutos() {
        // Verifica se o elemento tbody da tabela existe
        if (!listaProdutosTbody) {
            console.error("Elemento #listaProdutos (tbody) não encontrado.");
            return;
        }

        try {
            // Faz a requisição GET para a API de produtos
            const response = await fetch(`${API_BASE_URL}/produtos.php`);

            // Verifica se a requisição foi bem-sucedida (status 2xx)
            if (!response.ok) {
                throw new Error(`Erro HTTP ao buscar produtos: ${response.status} ${response.statusText}`);
            }

            // Converte a resposta para JSON
            const produtos = await response.json();

            // Limpa o conteúdo atual da tabela
            listaProdutosTbody.innerHTML = "";

            // Verifica se há produtos retornados
            if (!Array.isArray(produtos)) {
                 throw new Error("Formato de resposta da API inválido ao buscar produtos.");
            }
            if (produtos.length === 0) {
                 // Exibe uma mensagem se não houver produtos
                 listaProdutosTbody.innerHTML = '<tr><td colspan="6">Nenhum produto cadastrado.</td></tr>';
                 return;
            }

            // Itera sobre cada produto e cria uma linha na tabela
            produtos.forEach(produto => {
                const row = document.createElement("tr");
                // Define um atributo data-id na linha para fácil referência
                row.setAttribute('data-id', produto.id);

                // **MODIFICADO AQUI:** Adiciona data-label a cada TD para responsividade
                row.innerHTML = `
                    <td data-label="ID">${produto.id}</td>
                    <td data-label="Nome"><input type="text" value="${produto.nome}" id="nome-${produto.id}" disabled></td>
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
                // Adiciona a linha criada ao corpo da tabela
                listaProdutosTbody.appendChild(row);
            });

        } catch (error) {
            // Exibe erro no console e na interface
            console.error("Erro ao carregar produtos:", error);
            exibirMensagem(`Falha ao carregar produtos: ${error.message}`, "error");
            // Exibe mensagem de erro na tabela
            if (listaProdutosTbody) listaProdutosTbody.innerHTML = `<tr><td colspan="6">Erro ao carregar produtos. Verifique a conexão com a API (${API_BASE_URL}/produtos.php) e o console do navegador.</td></tr>`;
        }
    }

    // --- Adicionar Produto ---
    if (btnAdicionarProduto) {
        btnAdicionarProduto.addEventListener("click", async function () {
            // Obtém os valores dos campos do formulário
            const nome = nomeInput.value.trim();
            const descricao = descricaoInput.value.trim();
            const preco = precoInput.value; // A API deve validar se é numérico
            const categoria = categoriaSelect.value;

            // Validação simples no frontend (a validação principal deve ser na API)
            if (!nome || !preco || !categoria) {
                exibirMensagem("Nome, Preço e Categoria são obrigatórios.", "error");
                return;
            }
            if (isNaN(parseFloat(preco)) || parseFloat(preco) < 0) {
                exibirMensagem("O preço deve ser um número válido e não negativo.", "error");
                 return;
            }

            try {
                // Faz a requisição POST para adicionar o produto
                const response = await fetch(`${API_BASE_URL}/adicionar_produto.php`, {
                    method: "POST",
                    headers: { "Content-Type": "application/json" },
                    // Envia os dados como JSON no corpo da requisição
                    body: JSON.stringify({ nome, descricao, preco, categoria })
                });

                // Converte a resposta da API para JSON
                const data = await response.json();

                // Verifica se a API retornou sucesso
                if (data.sucesso) {
                    exibirMensagem(data.mensagem || "Produto adicionado com sucesso!", "success");
                    // Limpar campos do formulário após sucesso
                    nomeInput.value = "";
                    descricaoInput.value = "";
                    precoInput.value = "";
                    categoriaSelect.value = "pizza"; // Resetar para o valor padrão
                    await carregarProdutos(); // Recarrega a lista de produtos
                } else {
                    // Exibe mensagem de erro retornada pela API
                    exibirMensagem(data.mensagem || "Falha ao adicionar produto. Verifique os dados.", "error");
                }
            } catch (error) {
                console.error("Erro na requisição ao adicionar produto:", error);
                exibirMensagem(`Erro de conexão ao adicionar produto: ${error.message}`, "error");
            }
        });
    } else {
         console.warn("Botão #btnAdicionarProduto não encontrado.");
    }

    // --- Delegação de Eventos para Editar/Salvar/Excluir ---
    // Adiciona um único listener no tbody para lidar com cliques nos botões das linhas
    if (listaProdutosTbody) {
        listaProdutosTbody.addEventListener('click', async (event) => {
            const target = event.target; // O elemento que foi clicado
            const id = target.getAttribute('data-id'); // Pega o ID do produto do botão

            // Sai se o clique não foi em um botão com data-id
            if (!id || !target.matches('button')) return;

            const row = target.closest('tr'); // Encontra a linha (tr) pai do botão

            // Verifica qual botão foi clicado pela classe e chama a função correspondente
            if (target.classList.contains('button-edit')) {
                habilitarEdicao(row, id);
            } else if (target.classList.contains('button-save')) {
                await salvarEdicao(row, id); // salvarEdicao é async pois faz fetch
            } else if (target.classList.contains('button-delete')) {
                await excluirProduto(id); // excluirProduto é async pois faz fetch
            }
        });
    } else {
         console.warn("Elemento #listaProdutos (tbody) não encontrado para adicionar event listener.");
    }

    // --- Habilitar Edição de uma linha ---
    function habilitarEdicao(row, id) {
        // Habilita todos os inputs e selects dentro da linha
        row.querySelectorAll('input, select').forEach(el => el.disabled = false);
        // Esconde o botão "Editar" e mostra o botão "Salvar"
        row.querySelector('.button-edit').classList.add('hidden');
        row.querySelector('.button-save').classList.remove('hidden');
        // Foca no campo de nome para facilitar a edição
        const nomeInputEdicao = row.querySelector(`#nome-${id}`);
        if (nomeInputEdicao) nomeInputEdicao.focus();
    }

    // --- Salvar Edição ---
    async function salvarEdicao(row, id) {
        // Pega os valores atuais dos campos da linha
        const nome = row.querySelector(`#nome-${id}`).value;
        const descricao = row.querySelector(`#descricao-${id}`).value;
        const preco = row.querySelector(`#preco-${id}`).value;
        const categoria = row.querySelector(`#categoria-${id}`).value;

        // Validação básica no frontend
        if (!nome || !preco || !categoria || isNaN(parseFloat(preco)) || parseFloat(preco) < 0) {
             exibirMensagem("Dados inválidos para salvar. Verifique Nome, Preço e Categoria.", "error");
             return;
        }

        try {
            // Faz a requisição POST para a API de edição
            const response = await fetch(`${API_BASE_URL}/editar_produto.php`, {
                method: "POST", // Ou PUT, se sua API estiver configurada para isso
                headers: { "Content-Type": "application/json" },
                // Envia todos os dados, incluindo o ID
                body: JSON.stringify({ id, nome, descricao, preco, categoria })
            });

            const data = await response.json();

            if (data.sucesso) {
                exibirMensagem(data.mensagem || "Produto atualizado!", "success");
                // Desabilita os campos novamente e troca os botões
                row.querySelectorAll('input, select').forEach(el => el.disabled = true);
                row.querySelector('.button-edit').classList.remove('hidden');
                row.querySelector('.button-save').classList.add('hidden');
                // Opcional: Recarregar tudo com carregarProdutos() ou apenas atualizar a linha visualmente
                // await carregarProdutos();
            } else {
                exibirMensagem(data.mensagem || "Falha ao atualizar o produto.", "error");
            }
        } catch (error) {
            console.error("Erro na requisição ao editar produto:", error);
            exibirMensagem(`Erro de conexão ao salvar edição: ${error.message}`, "error");
        }
    }

    // --- Excluir Produto ---
    async function excluirProduto(id) {
        // Confirmação antes de excluir
        if (!confirm(`Tem certeza que deseja excluir o produto ID ${id}? Esta ação não pode ser desfeita.`)) {
            return; // Sai da função se o usuário cancelar
        }

        try {
            // Faz a requisição POST para a API de exclusão
            const response = await fetch(`${API_BASE_URL}/excluir_produto.php`, {
                method: "POST", // Ou DELETE, se a API suportar e estiver configurada
                headers: { "Content-Type": "application/json" },
                // Envia apenas o ID do produto a ser excluído
                body: JSON.stringify({ id })
            });

            const data = await response.json();

            if (data.sucesso) {
                exibirMensagem(data.mensagem || "Produto excluído com sucesso!", "success");
                await carregarProdutos(); // Recarrega a lista para remover a linha
            } else {
                exibirMensagem(data.mensagem || "Falha ao excluir o produto.", "error");
            }
        } catch (error) {
            console.error("Erro na requisição ao excluir produto:", error);
            exibirMensagem(`Erro de conexão ao excluir produto: ${error.message}`, "error");
        }
    }

    // --- Carregamento Inicial ---
    // Chama a função para carregar os produtos assim que o DOM estiver pronto
    carregarProdutos();

});
// --- END OF FILE admin.js ---