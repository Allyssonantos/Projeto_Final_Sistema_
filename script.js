// cadastro de usuario
document.addEventListener("DOMContentLoaded", function () {
    const cadastroForm = document.getElementById("cadastroForm");

    if (cadastroForm) {
        cadastroForm.addEventListener("submit", async function (e) {
            e.preventDefault();
            
            const nome = document.getElementById("nome").value;
            const email = document.getElementById("email").value;
            const senha = document.getElementById("senha").value;
            const mensagemContainer = document.getElementById("mensagem-container");

            try {
                const response = await fetch("http://localhost/pizzaria_express/api/cadastro.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json"
                    },
                    body: JSON.stringify({ nome, email, senha })
                });

                const result = await response.json();

                // Exibe a mensagem
                mensagemContainer.textContent = result.sucesso || result.erro;
                mensagemContainer.style.color = result.sucesso ? "green" : "red";
                mensagemContainer.style.fontWeight = "bold";
                mensagemContainer.style.padding = "10px";
                mensagemContainer.style.textAlign = "center";
                mensagemContainer.style.border = result.sucesso ? "2px solid green" : "2px solid red";
                mensagemContainer.style.backgroundColor = result.sucesso ? "#d4edda" : "#f8d7da";

                // Redireciona após sucesso
                if (result.sucesso) {
                    setTimeout(() => {
                        window.location.href = "login.html";
                    }, 2000);
                } else {
                    setTimeout(() => {
                        mensagemContainer.textContent = "";
                    }, 3000);
                }
            } catch (error) {
                console.error("Erro na requisição:", error);
                mensagemContainer.textContent = "Erro ao conectar ao servidor.";
                mensagemContainer.style.color = "red";
            }
        });
    }
});

// login
document.addEventListener("DOMContentLoaded", function () {
    let loginForm = document.getElementById("loginForm");

    if (loginForm) {
        loginForm.addEventListener("submit", function(event) {
            event.preventDefault();

            let email = document.getElementById("email").value;
            let senha = document.getElementById("senha").value;

            fetch("http://localhost/pizzaria_express/api/login.php", {
                method: "POST",
                headers: {
                    "Content-Type": "application/json"
                },
                body: JSON.stringify({ email, senha })
            })
            .then(response => response.json())
            .then(data => {
                let mensagem = document.getElementById("mensagem");
                if (data.status === "sucesso") {
                    mensagem.style.color = "green";
                    mensagem.innerText = data.mensagem;
                    setTimeout(() => {
                        window.location.href = "index.html"; // Redireciona para a página inicial
                    }, 2000);
                } else {
                    mensagem.style.color = "red";
                    mensagem.innerText = data.mensagem;
                }
            })
            .catch(error => console.error("Erro ao conectar:", error));
        });
    } else {
        console.error("❌ ERRO: Formulário de login não encontrado.");
    }
});
// função de verificação administrator



// função de castrado de produtos pagina administrator

document.addEventListener("DOMContentLoaded", function () {
    carregarProdutos();
});

document.getElementById("btnAdicionarProduto").addEventListener("click", function () {
    let nome = document.getElementById("nomeProduto").value;
    let descricao = document.getElementById("descricaoProduto").value;
    let preco = document.getElementById("precoProduto").value;
    let categoria = document.getElementById("categoriaProduto").value;

    fetch("http://localhost/pizzaria_express/api/adicionar_produto.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ nome, descricao, preco, categoria })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.mensagem);
        carregarProdutos();
    })
    .catch(error => console.error("Erro ao adicionar produto:", error));
});

function carregarProdutos() {
    fetch("http://localhost/pizzaria_express/api/produtos.php")
        .then(response => response.json())
        .then(produtos => {
            let listaProdutos = document.getElementById("listaProdutos");
            listaProdutos.innerHTML = "";

            produtos.forEach(produto => {
                let row = document.createElement("tr");

                row.innerHTML = `
                    <td>${produto.id}</td>
                    <td><input type="text" value="${produto.nome}" id="nome-${produto.id}" disabled></td>
                    <td><input type="text" value="${produto.descricao}" id="descricao-${produto.id}" disabled></td>
                    <td><input type="number" step="0.01" value="${produto.preco}" id="preco-${produto.id}" disabled></td>
                    <td>
                        <select id="categoria-${produto.id}" disabled>
                            <option value="pizza" ${produto.categoria === "pizza" ? "selected" : ""}>Pizza</option>
                            <option value="bebida" ${produto.categoria === "bebida" ? "selected" : ""}>Bebida</option>
                        </select>
                    </td>
                    <td>
                        <button onclick="habilitarEdicao(${produto.id})">Editar</button>
                        <button onclick="editarProduto(${produto.id})" id="salvar-${produto.id}" style="display:none;">Salvar</button>
                        <button onclick="excluirProduto(${produto.id})" style="background-color: red; color: white;">Excluir</button>
                    </td>
                `;

                listaProdutos.appendChild(row);
            });
        })
        .catch(error => console.error("Erro ao carregar produtos:", error));
}

function habilitarEdicao(id) {
    document.getElementById(`nome-${id}`).disabled = false;
    document.getElementById(`descricao-${id}`).disabled = false;
    document.getElementById(`preco-${id}`).disabled = false;
    document.getElementById(`categoria-${id}`).disabled = false;
    document.getElementById(`salvar-${id}`).style.display = "inline"; // Mostra o botão "Salvar"
}

function editarProduto(id) {
    let nome = document.getElementById(`nome-${id}`).value;
    let descricao = document.getElementById(`descricao-${id}`).value;
    let preco = document.getElementById(`preco-${id}`).value;
    let categoria = document.getElementById(`categoria-${id}`).value;

    fetch("http://localhost/pizzaria_express/api/editar_produto.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ id, nome, descricao, preco, categoria })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.mensagem);
        carregarProdutos();
    })
    .catch(error => console.error("Erro ao editar produto:", error));
}

function excluirProduto(id) {
    if (!confirm("Tem certeza que deseja excluir este produto?")) {
        return;
    }

    fetch("http://localhost/pizzaria_express/api/excluir_produto.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json"
        },
        body: JSON.stringify({ id })
    })
    .then(response => response.json())
    .then(data => {
        alert(data.mensagem);
        carregarProdutos();
    })
    .catch(error => console.error("Erro ao excluir produto:", error));
}

// função lista de produtos

function carregarProdutos() {
    fetch("http://localhost/pizzaria_express/api/listar_produtos.php")
    .then(response => response.json())
    .then(produtos => {
        let listaPizzas = document.getElementById("lista-pizzas");
        let listaBebidas = document.getElementById("lista-bebidas");

        listaPizzas.innerHTML = "";
        listaBebidas.innerHTML = "";

        produtos.forEach(produto => {
            let divProduto = document.createElement("div");
            divProduto.classList.add("produto");

            divProduto.innerHTML = `
                <h3>${produto.nome}</h3>
                <p>${produto.descricao}</p>
                <p><strong>Preço:</strong> R$ ${produto.preco}</p>
                <button onclick="adicionarAoCarrinho(${produto.id}, '${produto.nome}', ${produto.preco})">Adicionar ao Carrinho</button>
            `;

            if (produto.categoria === "pizza") {
                listaPizzas.appendChild(divProduto);
            } else if (produto.categoria === "bebida") {
                listaBebidas.appendChild(divProduto);
            }
        });
    })
    .catch(error => console.error("Erro ao carregar produtos:", error));
}


// função carrinho

document.addEventListener("DOMContentLoaded", function() {
    carregarProdutos();
});

// Variável global do carrinho
let carrinho = [];

// Função para carregar produtos do banco de dados
function carregarProdutos() {
    fetch("api/produtos.php")
        .then(response => response.json())
        .then(produtos => {
            let listaPizzas = document.getElementById("lista-pizzas");
            let listaBebidas = document.getElementById("lista-bebidas");

            if (!listaPizzas || !listaBebidas) {
                console.error("Elementos da lista de produtos não encontrados!");
                return;
            }

            listaPizzas.innerHTML = "";
            listaBebidas.innerHTML = "";

            produtos.forEach(produto => {
                let div = document.createElement("div");
                div.classList.add("produto");
                div.innerHTML = `
                    <h3>${produto.nome}</h3>
                    <p>${produto.descricao}</p>
                    <p><strong>R$ ${produto.preco}</strong></p>
                    <button onclick="adicionarAoCarrinho(${produto.id}, '${produto.nome}', ${produto.preco})">
                        Adicionar ao Carrinho
                    </button>
                `;

                if (produto.categoria === "pizza") {
                    listaPizzas.appendChild(div);
                } else if (produto.categoria === "bebida") {
                    listaBebidas.appendChild(div);
                }
            });
        })
        .catch(error => console.error("Erro ao carregar produtos:", error));
}

// Função para adicionar um produto ao carrinho
function adicionarAoCarrinho(id, nome, preco) {
    let itemExistente = carrinho.find(item => item.id === id);

    if (itemExistente) {
        itemExistente.quantidade += 1;
    } else {
        carrinho.push({ id, nome, preco, quantidade: 1 });
    }

    atualizarCarrinho();
}

// Função para remover um item do carrinho
function removerDoCarrinho(id) {
    carrinho = carrinho.filter(item => item.id !== id);
    atualizarCarrinho();
}

// Função para atualizar a exibição do carrinho
function atualizarCarrinho() {
    let listaCarrinho = document.getElementById("lista-carrinho");
    let totalCarrinho = document.getElementById("total");

    if (!listaCarrinho || !totalCarrinho) {
        console.error("Elementos do carrinho não encontrados!");
        return;
    }

    listaCarrinho.innerHTML = "";
    let total = 0;

    carrinho.forEach(item => {
        total += item.preco * item.quantidade;

        let li = document.createElement("li");
        li.innerHTML = `
            ${item.nome} - R$ ${item.preco} x ${item.quantidade}
            <button onclick="removerDoCarrinho(${item.id})">Remover</button>
        `;
        listaCarrinho.appendChild(li);
    });

    totalCarrinho.innerText = `Total: R$ ${total.toFixed(2)}`;
}

// Função para finalizar o pedido
function finalizarPedido() {
    if (carrinho.length === 0) {
        alert("Seu carrinho está vazio!");
        return;
    }

    let pedido = {
        produtos: carrinho,
        total: carrinho.reduce((acc, item) => acc + item.preco * item.quantidade, 0)
    };

    fetch("api/finalizar_pedido.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify(pedido)
    })
    .then(response => response.json())
    .then(data => {
        alert("Pedido realizado com sucesso!");
        carrinho = [];
        atualizarCarrinho();
    })
    .catch(error => console.error("Erro ao finalizar pedido:", error));
}




