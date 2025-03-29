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

