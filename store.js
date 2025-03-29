// --- START OF FILE store.js ---

document.addEventListener("DOMContentLoaded", function () {
    const API_BASE_URL = "http://localhost/pizzaria_express/api";
    const listaPizzasContainer = document.getElementById("lista-pizzas");
    const listaBebidasContainer = document.getElementById("lista-bebidas");
    const carrinhoUl = document.getElementById("carrinho-itens"); // ID atualizado para clareza
    const totalP = document.getElementById("total");
    const btnFinalizar = document.getElementById("btn-finalizar-pedido"); // ID adicionado ao botão

    // --- Estado do Carrinho ---
    let carrinho = [];

    // --- Buscar e Renderizar Produtos ---
    async function carregarProdutos() {
        try {
            const response = await fetch(`${API_BASE_URL}/produtos.php`);
            if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
            const produtos = await response.json();

            const pizzas = produtos.filter(p => p.categoria === 'pizza');
            const bebidas = produtos.filter(p => p.categoria === 'bebida');

            renderizarProdutos(pizzas, listaPizzasContainer);
            renderizarProdutos(bebidas, listaBebidasContainer);

        } catch (error) {
            console.error("Erro ao carregar produtos:", error);
            if (listaPizzasContainer) listaPizzasContainer.innerHTML = "<p>Erro ao carregar pizzas.</p>";
            if (listaBebidasContainer) listaBebidasContainer.innerHTML = "<p>Erro ao carregar bebidas.</p>";
        }
    }

    function renderizarProdutos(produtos, container) {
        if (!container) return;
        container.innerHTML = ""; // Limpar container

        if (produtos.length === 0) {
            container.innerHTML = "<p>Nenhum produto disponível nesta categoria.</p>";
            return;
        }

        produtos.forEach(produto => {
            const divProduto = document.createElement("div");
            divProduto.classList.add("produto");
            // Adiciona atributos data-* para fácil acesso no JS
            divProduto.innerHTML = `
                <img src="img/placeholder.png" alt="${produto.nome}"> <!-- Usar um placeholder ou buscar img do produto -->
                <div class="info">
                    <h3>${produto.nome}</h3>
                    <p>${produto.descricao || ''}</p>
                    <p class="preco">R$ ${Number(produto.preco).toFixed(2)}</p>
                    <button class="btn-add-carrinho" data-id="${produto.id}" data-nome="${produto.nome}" data-preco="${produto.preco}">Adicionar</button>
                </div>
            `;
            container.appendChild(divProduto);
        });
    }

    // --- Gerenciamento do Carrinho ---
    function adicionarAoCarrinho(id, nome, preco) {
        const itemExistente = carrinho.find(item => item.id === id);

        if (itemExistente) {
            itemExistente.quantidade++;
        } else {
            carrinho.push({ id, nome, preco: Number(preco), quantidade: 1 });
        }
        atualizarCarrinhoDisplay();
    }

    function removerDoCarrinho(id) {
         carrinho = carrinho.filter(item => item.id !== id);
         atualizarCarrinhoDisplay();
    }

    function atualizarCarrinhoDisplay() {
        if (!carrinhoUl || !totalP) return;

        carrinhoUl.innerHTML = ""; // Limpa lista
        let totalCalculado = 0;

        if (carrinho.length === 0) {
            carrinhoUl.innerHTML = "<li>Carrinho vazio.</li>";
        } else {
            carrinho.forEach(item => {
                const li = document.createElement("li");
                li.innerHTML = `
                    ${item.nome} (x${item.quantidade}) - R$ ${(item.preco * item.quantidade).toFixed(2)}
                    <button class="btn-remover-item" data-id="${item.id}">Remover</button>
                `;
                carrinhoUl.appendChild(li);
                totalCalculado += item.preco * item.quantidade;
            });
        }
        totalP.textContent = `Total: R$ ${totalCalculado.toFixed(2)}`;
    }

    // --- Finalizar Pedido (Placeholder) ---
    function finalizarPedido() {
        if (carrinho.length === 0) {
            alert("Seu carrinho está vazio!");
            return;
        }
        console.log("Pedido finalizado (simulação):", carrinho);
        alert(`Pedido finalizado (simulação)! Total: R$ ${totalP.textContent.split('R$ ')[1]}`);
        // Aqui você enviaria os dados do carrinho para o backend
        carrinho = []; // Limpa o carrinho após finalizar
        atualizarCarrinhoDisplay();
    }

    // --- Scroll Suave ---
    function scrollToSection(sectionId) {
        const section = document.getElementById(sectionId);
        if (section) {
            section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        } else {
            console.warn(`Seção com id "${sectionId}" não encontrada.`);
        }
    }

    // --- Adicionar Event Listeners ---

    // Delegação de evento para botões "Adicionar"
    document.body.addEventListener('click', (event) => {
        if (event.target.classList.contains('btn-add-carrinho')) {
            const id = event.target.getAttribute('data-id');
            const nome = event.target.getAttribute('data-nome');
            const preco = event.target.getAttribute('data-preco');
            adicionarAoCarrinho(id, nome, preco);
        }
        // Delegação para botões "Remover" do carrinho
        if(event.target.classList.contains('btn-remover-item')) {
            const id = event.target.getAttribute('data-id');
            removerDoCarrinho(id);
        }
    });

    // Botão Finalizar Pedido
    if (btnFinalizar) {
        btnFinalizar.addEventListener('click', finalizarPedido);
    }

    // Botões de Navegação (Scroll)
    const navButtons = document.querySelectorAll('.nav-buttons button[data-scroll-to]');
    navButtons.forEach(button => {
        button.addEventListener('click', () => {
            const sectionId = button.getAttribute('data-scroll-to');
            scrollToSection(sectionId);
        });
    });


    // --- Inicialização ---
    carregarProdutos();
    atualizarCarrinhoDisplay(); // Para mostrar "Carrinho vazio" inicialmente
});
// --- END OF FILE store.js ---