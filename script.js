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
                const response = await fetch("http://localhost/pizzaria/api/cadastro.php", {
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

// Funções para achar os dodos clicando nos botoes no carberçario

function scrollToSection(id) {
    document.getElementById(id).scrollIntoView({ behavior: 'smooth' }); 
}