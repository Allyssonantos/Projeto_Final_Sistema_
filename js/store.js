// js/store.js - CORRIGIDO

document.addEventListener("DOMContentLoaded", function () {
    // --- Configurações e Constantes ---
    const API_BASE_URL = "http://localhost/pizzaria_express/api"; // !! VERIFIQUE SUA URL DA API !!
    // const UPLOADS_BASE_URL = 'uploads/produtos/'; // Não precisamos mais disto se a API retorna a URL completa
    const PLACEHOLDER_IMG = 'img/placeholder.png'; // !! Certifique-se que este arquivo existe em pizzaria_express/img/ !!

    // --- Referências aos Elementos do DOM ---
    const listaPizzasContainer = document.getElementById("lista-pizzas");
    const listaBebidasContainer = document.getElementById("lista-bebidas");
    const carrinhoUl = document.getElementById("carrinho-itens"); // ID atualizado no HTML
    const totalP = document.getElementById("total");
    const btnFinalizar = document.getElementById("btn-finalizar-pedido"); // ID atualizado no HTML

    // ---------- BLOCO REMOVIDO DAQUI ----------
    // const response = await fetch(...); // <--- ESTE BLOCO FOI REMOVIDO
    // ---------- FIM DO BLOCO REMOVIDO ----------

    // --- Estado do Carrinho (Armazenado na memória do navegador) ---
    let carrinho = [];

    // --- Buscar e Renderizar Produtos ---
    async function carregarProdutos() {
        console.log("STORE.JS: Iniciando carregamento de produtos..."); // Log inicial
        try {
            // Ajuste para usar a API correta se mudou para all_in_one.php
             const response = await fetch(`${API_BASE_URL}/produtos.php`); // Ou all_in_one.php?action=listarProdutos
            // const response = await fetch(`${API_BASE_URL}/all_in_one.php?action=listarProdutos`); // DESCOMENTE esta linha se usar all_in_one

            console.log("STORE.JS: Fetch produtos - Status:", response.status); // Log status
            if (!response.ok) {
                throw new Error(`Erro HTTP ao buscar produtos: ${response.status}`);
            }
            const produtos = await response.json();
            console.log("STORE.JS: Produtos recebidos da API:", produtos); // Log dados recebidos

            // Verifica se 'produtos' é um array antes de filtrar
             if (!Array.isArray(produtos)) {
                 console.error("STORE.JS: API não retornou um array de produtos:", produtos);
                 throw new Error("Formato de dados inválido da API.");
             }

            // Separa produtos por categoria
            const pizzas = produtos.filter(p => p.categoria === 'pizza');
            const bebidas = produtos.filter(p => p.categoria === 'bebida');
            console.log("STORE.JS: Pizzas filtradas:", pizzas.length);
            console.log("STORE.JS: Bebidas filtradas:", bebidas.length);

            // Renderiza nas seções corretas
            renderizarProdutos(pizzas, listaPizzasContainer);
            renderizarProdutos(bebidas, listaBebidasContainer);

        } catch (error) {
            console.error("STORE.JS: Erro ao carregar produtos:", error);
            // Exibe mensagens de erro nos containers, se existirem
            const errorMsg = `Erro ao carregar: ${error.message}`;
            if (listaPizzasContainer) listaPizzasContainer.innerHTML = `<p>${errorMsg}</p>`;
            if (listaBebidasContainer) listaBebidasContainer.innerHTML = `<p>${errorMsg}</p>`;
        }
    }

    function renderizarProdutos(produtos, container) {
        if (!container) {
            console.warn("STORE.JS: Container para renderizar produtos não encontrado.");
            return;
        }
        container.innerHTML = ""; // Limpa container

        if (!Array.isArray(produtos) || produtos.length === 0) {
            container.innerHTML = "<p>Nenhum produto disponível nesta categoria.</p>";
            return;
        }
        console.log(`STORE.JS: Renderizando ${produtos.length} produtos no container`, container.id);

        produtos.forEach(produto => {
            const divProduto = document.createElement("div");
            divProduto.classList.add("produto");
            // Usa a imagem_url retornada pela API ou o placeholder
            const imagemUrl = produto.imagem_url ? produto.imagem_url : PLACEHOLDER_IMG;

            // Cria o HTML interno do card do produto
            divProduto.innerHTML = `
                <img src="${imagemUrl}" alt="${produto.nome || 'Produto'}">
                <div class="info">
                    <h3>${produto.nome || 'Nome Indisponível'}</h3>
                    <p>${produto.descricao || ''}</p>
                    <p class="preco">R$ ${Number(produto.preco).toFixed(2)}</p>
                    <button class="btn-add-carrinho" data-id="${produto.id}" data-nome="${produto.nome}" data-preco="${produto.preco}">Adicionar</button>
                </div>
            `;

             // Adiciona tratamento de erro para imagem quebrada (importante!)
             const imgElement = divProduto.querySelector('img');
             if (imgElement) { // Verifica se a imagem existe antes de adicionar o handler
                 imgElement.onerror = () => {
                    console.warn(`STORE.JS: Erro ao carregar imagem: ${imgElement.src}. Usando placeholder.`);
                    imgElement.src = PLACEHOLDER_IMG;
                    imgElement.alt = 'Erro ao carregar imagem';
                 };
             } else {
                  console.warn("STORE.JS: Tag <img> não encontrada no produto:", produto.nome);
             }


            // Adiciona o card do produto ao container correto
            container.appendChild(divProduto);
        });
    }

    // --- Gerenciamento do Carrinho ---
    function adicionarAoCarrinho(id, nome, preco) {
        // Validação básica dos dados recebidos
        if (!id || !nome || preco === undefined || preco === null || isNaN(Number(preco))) {
             console.error("STORE.JS: Tentativa de adicionar item inválido ao carrinho:", {id, nome, preco});
             alert("Erro ao adicionar item ao carrinho. Dados inválidos.");
             return;
        }
        console.log(">>> Função adicionarAoCarrinho chamada com:", { id, nome, preco });
        // Procura se o item já existe no carrinho pelo ID
        const itemExistente = carrinho.find(item => String(item.id) === String(id)); // Compara como string para segurança

        if (itemExistente) {
            // Se existe, incrementa a quantidade
            itemExistente.quantidade++;
            console.log("STORE.JS: Item existente, quantidade incrementada:", itemExistente);
        } else {
            // Se não existe, adiciona novo item ao array do carrinho
            carrinho.push({ id: String(id), nome, preco: Number(preco), quantidade: 1 });
            console.log("STORE.JS: Novo item adicionado ao carrinho:", carrinho[carrinho.length - 1]);
        }
        // Atualiza a exibição do carrinho na interface
        atualizarCarrinhoDisplay();
    }

    function removerDoCarrinho(id) {
         console.log(`STORE.JS: Tentando remover item com ID: ${id}`);
         // Filtra o array, mantendo apenas os itens cujo ID NÃO é o que queremos remover
         carrinho = carrinho.filter(item => String(item.id) !== String(id));
         console.log("STORE.JS: Carrinho após remoção:", carrinho);
         // Atualiza a exibição
         atualizarCarrinhoDisplay();
    }

    function atualizarCarrinhoDisplay() {
        // Verifica se os elementos do carrinho existem no HTML
        if (!carrinhoUl || !totalP) {
            console.error("STORE.JS: Elementos do display do carrinho (#carrinho-itens ou #total) não encontrados.");
            return;
        }
        console.log("STORE.JS: Atualizando display do carrinho...");

        carrinhoUl.innerHTML = ""; // Limpa lista atual
        let totalCalculado = 0;

        if (carrinho.length === 0) {
            carrinhoUl.innerHTML = "<li>Carrinho vazio.</li>";
        } else {
            carrinho.forEach(item => {
                const li = document.createElement("li");
                // Cria o HTML para cada item no carrinho, incluindo botão de remover
                li.innerHTML = `
                    ${item.nome} (x${item.quantidade}) - R$ ${(item.preco * item.quantidade).toFixed(2)}
                    <button class="btn-remover-item" data-id="${item.id}">Remover</button>
                `;
                carrinhoUl.appendChild(li);
                // Soma o valor do item ao total
                totalCalculado += item.preco * item.quantidade;
            });
        }
        // Atualiza o texto do total
        totalP.textContent = `Total: R$ ${totalCalculado.toFixed(2)}`;
        console.log("STORE.JS: Display do carrinho atualizado. Total:", totalCalculado.toFixed(2));
    }

    // --- Finalizar Pedido (Função CORRETA) ---
    async function finalizarPedido() { // Já estava async, correto
        console.log("STORE.JS: Iniciando finalizarPedido...");
        if (carrinho.length === 0) {
            alert("Seu carrinho está vazio!");
            return;
        }

        const itensParaEnviar = carrinho.map(item => ({
            id: item.id,
            quantidade: item.quantidade
        }));
        console.log("STORE.JS: Itens a serem enviados para API:", itensParaEnviar);

        try {
            exibirMensagemCarrinho("Processando seu pedido...", "info");

            // Faz fetch para a API de finalizar pedido (AJUSTE a URL se usar all_in_one.php)
             const response = await fetch(`${API_BASE_URL}/finalizar_pedido.php`, { // Ou all_in_one.php
            // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { // DESCOMENTE esta linha se usar all_in_one
                 method: 'POST',
                 credentials: 'include', // <<< IMPORTANTE para sessão
                 headers: { 'Content-Type': 'application/json' },
                 // Envia action E carrinho se usar all_in_one
                 // body: JSON.stringify({ action: 'finalizarPedido', carrinho: itensParaEnviar }) // DESCOMENTE se usar all_in_one
                 body: JSON.stringify({ carrinho: itensParaEnviar }) // MANTENHA esta se usar finalizar_pedido.php separado
             });
            console.log("STORE.JS: Resposta finalizar_pedido - Status:", response.status);

            const data = await response.json();
            console.log("STORE.JS: Resposta JSON finalizar_pedido:", data);

            if (!response.ok) {
                 throw new Error(data.mensagem || `Erro ${response.status} ao finalizar pedido.`);
             }

            if (data.sucesso) {
                exibirMensagemCarrinho(`Pedido #${data.pedido_id || ''} realizado com sucesso!`, "success");
                carrinho = [];
                atualizarCarrinhoDisplay();
            } else {
                throw new Error(data.mensagem || "Falha ao processar o pedido.");
            }

        } catch (error) {
            console.error("STORE.JS: Erro ao finalizar pedido:", error);
             if (error instanceof SyntaxError) {
                 exibirMensagemCarrinho("Erro ao processar resposta do servidor.", "error");
             } else {
                 exibirMensagemCarrinho(`Erro: ${error.message}`, "error");
             }
        }
    }

     // Função auxiliar para mostrar mensagens perto do carrinho
     function exibirMensagemCarrinho(texto, tipo) {
         const msgContainer = document.querySelector('.carrinho-container .mensagem-carrinho');
         if (msgContainer) {
              msgContainer.textContent = texto;
              msgContainer.className = `mensagem mensagem-carrinho ${tipo}`;
              // Adicionar um timeout para limpar a mensagem
              setTimeout(() => {
                 if (msgContainer.textContent === texto) { msgContainer.textContent = ''; msgContainer.className = 'mensagem mensagem-carrinho'; }
             }, 5000);
         } else {
              if(tipo === 'error') alert(`Erro: ${texto}`);
              else alert(texto); // Alert para sucesso também
         }
     }

    // --- Scroll Suave para Seções ---
    function scrollToSection(sectionId) {
        console.log(`STORE.JS: Tentando scroll para #${sectionId}`);
        const section = document.getElementById(sectionId);
        if (section) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            console.warn(`STORE.JS: Seção com id "${sectionId}" não encontrada para scroll.`);
        }
    }

    // --- Adicionar Event Listeners Globais (Delegação) ---
    document.body.addEventListener('click', (event) => {
        if (event.target.classList.contains('btn-add-carrinho')) {
            console.log(">>> Botão Adicionar Clicado!");
            const button = event.target;
            const id = button.getAttribute('data-id');
            const nome = button.getAttribute('data-nome');
            const preco = button.getAttribute('data-preco');
            console.log("Dados do botão:", { id, nome, preco });
             if (id && nome && preco !== null && preco !== undefined) {
                 adicionarAoCarrinho(id, nome, preco);
             } else {
                 console.error("ERRO: Não foi possível obter dados (id, nome, preco) do botão 'Adicionar' clicado!");
                 alert("Erro ao obter informações do produto.");
             }
        }
        if(event.target.classList.contains('btn-remover-item')) {
            console.log(">>> Botão Remover Clicado!");
            const button = event.target;
            const id = button.getAttribute('data-id');
            console.log("ID para remover:", id);
            if (id) {
                removerDoCarrinho(id);
            } else {
                 console.error("ERRO: Não foi possível obter ID do botão 'Remover' clicado!");
            }
        }
    });

    // Listener para o botão Finalizar Pedido
    if (btnFinalizar) {
        btnFinalizar.addEventListener('click', finalizarPedido);
    } else {
        console.warn("STORE.JS: Botão #btn-finalizar-pedido não encontrado.");
    }

    // Listeners para os botões de Navegação (Scroll)
    const navButtons = document.querySelectorAll('.nav-buttons button[data-scroll-to]');
    if (navButtons.length > 0) {
        navButtons.forEach(button => {
            button.addEventListener('click', () => {
                const sectionId = button.getAttribute('data-scroll-to');
                if (sectionId) {
                    scrollToSection(sectionId);
                } else {
                    console.warn("STORE.JS: Botão de navegação não tem atributo data-scroll-to.");
                }
            });
        });
    } else {
        console.warn("STORE.JS: Nenhum botão de navegação com [data-scroll-to] encontrado.");
    }

    // --- Inicialização ---
    carregarProdutos();
    atualizarCarrinhoDisplay(); // Mostra "Carrinho vazio." inicialmente

}); // Fim do DOMContentLoaded