// js/store.js - COMPLETO E ATUALIZADO (com Forma de Pagamento)

document.addEventListener("DOMContentLoaded", function () {
    // === Configurações e Constantes ===
    const API_BASE_URL = "./api"; // !! VERIFIQUE SUA URL !!
    const PLACEHOLDER_IMG = 'img/placeholder.png'; // !! Garanta que img/placeholder.png existe !!

    // === Referências aos Elementos do DOM ===
    const listaPizzasContainer = document.getElementById("lista-pizzas");
    const listaBebidasContainer = document.getElementById("lista-bebidas");
    const carrinhoUl = document.getElementById("carrinho-itens");
    const totalP = document.getElementById("total");
    const btnFinalizar = document.getElementById("btn-finalizar-pedido");
    // Seletores para Forma de Pagamento
    const opcoesPagamento = document.querySelectorAll('input[name="forma_pagamento"]');
    const mensagemPagamentoEl = document.getElementById('mensagem-pagamento');
    const mensagemCarrinhoEl = document.querySelector('.carrinho-container .mensagem-carrinho'); // Div para mensagens gerais do carrinho/pedido

    // === Estado do Carrinho (Array na memória) ===
    let carrinho = [];

    // === Funções de Carregamento e Renderização ===

    /**
     * Busca os produtos da API e chama a função para renderizá-los.
     */
    async function carregarProdutos() {
        console.log("STORE.JS: Iniciando carregamento de produtos...");
        try {
            // Ajuste a URL se estiver usando a API combinada all_in_one.php
            const response = await fetch(`${API_BASE_URL}/produtos.php`);
            // const response = await fetch(`${API_BASE_URL}/all_in_one.php?action=listarProdutos`); // DESCOMENTE se usar all_in_one

            console.log("STORE.JS: Fetch produtos - Status:", response.status);
            if (!response.ok) {
                throw new Error(`Erro HTTP ${response.status} ao buscar produtos.`);
            }
            const produtos = await response.json();
            console.log("STORE.JS: Produtos recebidos da API:", produtos);

            if (!Array.isArray(produtos)) {
                throw new Error("Formato de dados inválido recebido da API.");
            }

            // Separa produtos por categoria
            const pizzas = produtos.filter(p => p.categoria === 'pizza');
            const bebidas = produtos.filter(p => p.categoria === 'bebida');
            console.log(`STORE.JS: Filtrados - Pizzas: ${pizzas.length}, Bebidas: ${bebidas.length}`);

            // Renderiza nas seções corretas
            renderizarProdutos(pizzas, listaPizzasContainer);
            renderizarProdutos(bebidas, listaBebidasContainer);

        } catch (error) {
            console.error("STORE.JS: Erro CRÍTICO ao carregar produtos:", error);
            const errorMsg = `Falha ao carregar produtos: ${error.message}`;
            if (listaPizzasContainer) listaPizzasContainer.innerHTML = `<p class="error-message">${errorMsg}</p>`;
            if (listaBebidasContainer) listaBebidasContainer.innerHTML = `<p class="error-message">${errorMsg}</p>`;
        }
    }

    /**
     * Renderiza uma lista de produtos em um container HTML específico.
     * @param {Array} produtos Array de objetos de produto.
     * @param {HTMLElement} container Elemento HTML onde os produtos serão inseridos.
     */
    function renderizarProdutos(produtos, container) {
        if (!container) {
            console.warn("STORE.JS: Container de renderização não encontrado:", container);
            return;
        }
        container.innerHTML = ""; // Limpa conteúdo anterior

        if (!Array.isArray(produtos) || produtos.length === 0) {
            container.innerHTML = "<p>Nenhum produto disponível nesta categoria no momento.</p>";
            return;
        }
        console.log(`STORE.JS: Renderizando ${produtos.length} produtos em #${container.id}`);

        produtos.forEach(produto => {
            const divProduto = document.createElement("div");
            divProduto.classList.add("produto");
            // Usa a imagem_url da API ou o placeholder definido
            const imagemUrl = produto.imagem_url ? produto.imagem_url : PLACEHOLDER_IMG;

            // Cria o HTML do card do produto
            divProduto.innerHTML = `
                <img src="${imagemUrl}" alt="${produto.nome || 'Imagem do Produto'}">
                <div class="info">
                    <h3>${produto.nome || 'Produto Sem Nome'}</h3>
                    <p>${produto.descricao || ''}</p>
                    <p class="preco">R$ ${Number(produto.preco).toFixed(2)}</p>
                    <button class="btn-add-carrinho" data-id="${produto.id}" data-nome="${produto.nome}" data-preco="${produto.preco}">Adicionar</button>
                </div>
            `;

             // Adiciona tratamento de erro para imagens quebradas
             const imgElement = divProduto.querySelector('img');
             if (imgElement) {
                 imgElement.onerror = () => {
                    console.warn(`STORE.JS: Erro ao carregar imagem: ${imgElement.src}. Usando placeholder.`);
                    imgElement.src = PLACEHOLDER_IMG;
                    imgElement.alt = 'Erro ao carregar imagem';
                 };
             } else {
                  console.warn("STORE.JS: Tag <img> não encontrada no produto:", produto.nome);
             }

            container.appendChild(divProduto);
        });
    }

    // === Funções de Gerenciamento do Carrinho ===

    /**
     * Adiciona um item ao carrinho ou incrementa sua quantidade se já existir.
     * @param {string|number} id ID do produto.
     * @param {string} nome Nome do produto.
     * @param {string|number} preco Preço do produto.
     */
    function adicionarAoCarrinho(id, nome, preco) {
        const precoNumerico = Number(preco);
        // Validação básica dos dados recebidos do botão
        if (!id || !nome || preco === undefined || preco === null || isNaN(precoNumerico)) {
             console.error("STORE.JS: Tentativa de adicionar item inválido:", {id, nome, preco});
             exibirMensagemCarrinho("Erro ao adicionar item (dados inválidos).", "error");
             return;
        }
        console.log(">>> Função adicionarAoCarrinho chamada com:", { id, nome, preco: precoNumerico });

        const idString = String(id); // Garante comparação como string
        const itemExistente = carrinho.find(item => item.id === idString);

        if (itemExistente) {
            itemExistente.quantidade++; // Incrementa quantidade
            console.log("STORE.JS: Item existente, quantidade incrementada:", itemExistente);
        } else {
            // Adiciona novo item
            carrinho.push({ id: idString, nome, preco: precoNumerico, quantidade: 1 });
            console.log("STORE.JS: Novo item adicionado ao carrinho:", carrinho[carrinho.length - 1]);
        }
        atualizarCarrinhoDisplay(); // Atualiza a interface
    }

    /**
     * Remove um item do carrinho pelo ID.
     * @param {string|number} id ID do produto a remover.
     */
    function removerDoCarrinho(id) {
         const idString = String(id);
         console.log(`STORE.JS: Tentando remover item com ID: ${idString}`);
         carrinho = carrinho.filter(item => item.id !== idString); // Cria novo array sem o item
         console.log("STORE.JS: Carrinho após remoção:", carrinho);
         atualizarCarrinhoDisplay(); // Atualiza interface
    }

    /**
     * Atualiza a exibição HTML do carrinho (lista de itens e total).
     */
    function atualizarCarrinhoDisplay() {
        if (!carrinhoUl || !totalP) { console.error("STORE.JS: Elementos do display do carrinho não encontrados."); return; }
        console.log("STORE.JS: Atualizando display do carrinho...");

        carrinhoUl.innerHTML = ""; // Limpa lista
        let totalCalculado = 0;

        if (carrinho.length === 0) {
            carrinhoUl.innerHTML = "<li>Carrinho vazio.</li>";
        } else {
            carrinho.forEach(item => {
                const li = document.createElement("li");
                li.innerHTML = `
                    ${item.nome} (x${item.quantidade}) - R$ ${(item.preco * item.quantidade).toFixed(2)}
                    <button class="btn-remover-item" data-id="${item.id}" title="Remover item">×</button> <!-- Botão Remover -->
                `;
                carrinhoUl.appendChild(li);
                totalCalculado += item.preco * item.quantidade; // Soma ao total
            });
        }
        totalP.textContent = `Total: R$ ${totalCalculado.toFixed(2)}`; // Atualiza total
        console.log("STORE.JS: Display carrinho atualizado. Total:", totalCalculado.toFixed(2));
    }

    // === Função para Finalizar Pedido ===

    /**
     * Coleta dados do carrinho e forma de pagamento, envia para a API e trata a resposta.
     */
    async function finalizarPedido() {
        console.log("STORE.JS: Iniciando finalizarPedido...");
        exibirMensagemCarrinho("", "info"); // Limpa mensagens anteriores
        exibirMensagemPagamento("", "info");

        if (carrinho.length === 0) {
            exibirMensagemCarrinho("Seu carrinho está vazio!", "error");
            alert("Seu carrinho está vazio!");
            return;
        }

        // Pega a forma de pagamento selecionada
        let formaPagamentoSelecionada = null;
        opcoesPagamento.forEach(radio => { if (radio.checked) { formaPagamentoSelecionada = radio.value; } });

        if (!formaPagamentoSelecionada) {
             exibirMensagemPagamento("Por favor, selecione uma forma de pagamento.", "error");
             return;
        }
        console.log("STORE.JS: Forma de pagamento selecionada:", formaPagamentoSelecionada);

        // Prepara os itens para enviar (Backend deve validar preço/existência)
        const itensParaEnviar = carrinho.map(item => ({ id: item.id, quantidade: item.quantidade }));
        console.log("STORE.JS: Itens a serem enviados para API:", itensParaEnviar);

        try {
            exibirMensagemCarrinho("Processando seu pedido...", "info"); // Feedback visual

            // Faz fetch para a API (Ajuste URL e body se usar all_in_one.php)
             const response = await fetch(`${API_BASE_URL}/finalizar_pedido.php`, {
            // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { // DESCOMENTE se usar all_in_one
                 method: 'POST',
                 credentials: 'include', // <<< ESSENCIAL para enviar cookie de sessão
                 headers: { 'Content-Type': 'application/json' },
                  body: JSON.stringify({ carrinho: itensParaEnviar, formaPagamento: formaPagamentoSelecionada })
                 // body: JSON.stringify({ action: 'finalizarPedido', carrinho: itensParaEnviar, formaPagamento: formaPagamentoSelecionada }) // DESCOMENTE se usar all_in_one
             });
            console.log("STORE.JS: Resposta finalizar_pedido - Status:", response.status);

            let data; try { data = await response.json(); } catch(e){ throw new SyntaxError("Resposta inválida do servidor."); }
            console.log("STORE.JS: Resposta JSON finalizar_pedido:", data);

            // Verifica se a resposta HTTP e a lógica da API indicam sucesso
            if (!response.ok) { throw new Error(data.mensagem || `Erro ${response.status}`); }
            if (!data.sucesso) { throw new Error(data.mensagem || "Falha ao finalizar pedido."); }

            // --- SUCESSO ---
            let msgSucesso = `Pedido #${data.pedido_id || ''} realizado com sucesso!`;
            if (formaPagamentoSelecionada === 'PIX') {
                 msgSucesso += `\n\n${data.instrucoesPix || 'Instruções para pagamento PIX serão exibidas ou enviadas.'}`;
            } else { msgSucesso += `\n\nPague na entrega.`; }

            exibirMensagemCarrinho(msgSucesso, "success"); // Mostra mensagem no container do carrinho
            alert(msgSucesso); // Mostra alert também
            carrinho = []; // Limpa o array do carrinho local
            atualizarCarrinhoDisplay(); // Atualiza a interface

        } catch (error) { // --- TRATAMENTO DE ERRO ---
            console.error("STORE.JS: Erro ao finalizar pedido:", error);
             let errorMsgUser = "Não foi possível finalizar seu pedido."; // Mensagem padrão
             if (error instanceof SyntaxError) { errorMsgUser = "Erro ao processar resposta do servidor."; }
             else { errorMsgUser = `Erro: ${error.message}`; } // Usa mensagem do erro capturado
             exibirMensagemCarrinho(errorMsgUser, "error");
        }
    }

     // --- Funções Auxiliares de Mensagem ---
     function exibirMensagemCarrinho(texto, tipo) {
         if (mensagemCarrinhoEl) {
              mensagemCarrinhoEl.textContent = texto;
              mensagemCarrinhoEl.className = `mensagem mensagem-carrinho ${tipo}`; // Define classe para estilo
              mensagemCarrinhoEl.classList.toggle('hidden', !texto); // Mostra/esconde
               // Auto-limpeza após 7 segundos
               setTimeout(() => {
                  if (mensagemCarrinhoEl.textContent === texto) {
                       mensagemCarrinhoEl.textContent = '';
                       mensagemCarrinhoEl.className = 'mensagem mensagem-carrinho hidden';
                  }
              }, 7000);
         } else { // Fallback para alert
              if(tipo === 'error') alert(`Erro: ${texto}`);
              else if (texto) alert(texto);
         }
     }
     function exibirMensagemPagamento(texto, tipo) {
         if (mensagemPagamentoEl) {
              mensagemPagamentoEl.textContent = texto;
              mensagemPagamentoEl.className = `mensagem small-mensagem ${tipo}`; // Usa classe small
              mensagemPagamentoEl.classList.toggle('hidden', !texto);
         }
     }

    // --- Scroll Suave para Seções ---
    function scrollToSection(sectionId) { /* ... (código igual antes) ... */ }

    // === Adicionar Event Listeners ===

    // Listener Global no BODY para Adicionar/Remover Itens (Delegação)
    document.body.addEventListener('click', (event) => {
        // Adicionar ao Carrinho
        if (event.target.classList.contains('btn-add-carrinho')) {
            console.log(">>> Botão Adicionar Clicado!");
            const button = event.target;
            const id = button.getAttribute('data-id');
            const nome = button.getAttribute('data-nome');
            const preco = button.getAttribute('data-preco');
            console.log("Dados do botão:", { id, nome, preco });
             if (id && nome && preco !== null && preco !== undefined) {
                 adicionarAoCarrinho(id, nome, preco);
             } else { console.error("ERRO: Dados inválidos no botão 'Adicionar'!"); }
        }
        // Remover do Carrinho
        if(event.target.classList.contains('btn-remover-item')) {
            console.log(">>> Botão Remover Clicado!");
            const button = event.target;
            const id = button.getAttribute('data-id');
            console.log("ID para remover:", id);
            if (id) { removerDoCarrinho(id); }
            else { console.error("ERRO: ID não encontrado no botão 'Remover'!"); }
        }
    });

    // Listener para o botão Finalizar Pedido
    if (btnFinalizar) { btnFinalizar.addEventListener('click', finalizarPedido); }
    else { console.warn("STORE.JS: Botão #btn-finalizar-pedido não encontrado."); }

    // Listeners para os botões de Navegação Scroll (se existirem)
    const navButtons = document.querySelectorAll('.nav-buttons button[data-scroll-to]');
    if (navButtons.length > 0) {
        navButtons.forEach(button => { /* ... (código igual antes) ... */ });
    } else { console.warn("STORE.JS: Nenhum botão de navegação [data-scroll-to] encontrado."); }

    // === Inicialização ===
    carregarProdutos(); // Carrega os produtos da API
    atualizarCarrinhoDisplay(); // Garante que o carrinho comece como "vazio"

}); 


/**
 * Rola suavemente a página qunado clica em bebidas ou pizzas no navBar
 * @param {string} sectionId - O ID do elemento para o qual rolar.
 */
function scrollToSection(sectionId) {
    console.log(`Tentando scroll para #${sectionId}`);
    const section = document.getElementById(sectionId);
    if (section) {
        section.scrollIntoView({ behavior: 'smooth', block: 'start' });
        console.log(`Scroll para #${sectionId} iniciado.`);
    } else {
        console.warn(`Seção com id "${sectionId}" não encontrada para scroll.`);
    }
}

// Listener para os botões de Navegação Scroll na navbar
// Certifique-se que o seletor está correto para sua estrutura HTML da navbar
const navScrollButtons = document.querySelectorAll('.navbar-right button[data-scroll-to], .nav-buttons button[data-scroll-to]'); // Pega de ambas as estruturas possíveis
if (navScrollButtons.length > 0) {
    navScrollButtons.forEach(button => {
        button.addEventListener('click', () => {
            const sectionId = button.getAttribute('data-scroll-to');
            if (sectionId) {
                scrollToSection(sectionId);
            } else {
                console.warn("Botão de navegação scroll sem atributo data-scroll-to válido.");
            }
        });
    });
} else {
    console.warn("Nenhum botão de navegação scroll com [data-scroll-to] encontrado.");
}