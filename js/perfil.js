// js/perfil.js - Lógica para a página de perfil do usuário

document.addEventListener("DOMContentLoaded", function () {
    // --- Configurações e Constantes ---
    const API_BASE_URL = "./api"; // !! VERIFIQUE SUA URL !!
    const PLACEHOLDER_IMG = 'img/placeholder.png';           // Imagem padrão para itens

    // --- Seletores para Elementos da Interface ---

    // Área de Exibição de Dados
    const nomeEl = document.getElementById('perfil-nome');
    const emailEl = document.getElementById('perfil-email');
    const telefoneEl = document.getElementById('perfil-telefone');
    const enderecoEl = document.getElementById('perfil-endereco');
    const displayDadosDiv = document.getElementById('display-dados-usuario'); // Div que contém os dados para exibição
    const dadosUsuarioSection = document.getElementById('dados-usuario'); // Seção inteira dos dados

    // Formulário de Edição
    const btnEditarPerfil = document.getElementById('btn-editar-perfil');
    const formEditarPerfil = document.getElementById('form-editar-perfil');
    const inputNome = document.getElementById('edit-nome');
    const inputEmail = document.getElementById('edit-email');
    const inputTelefone = document.getElementById('edit-telefone');
    const inputEndereco = document.getElementById('edit-endereco');
    const btnSalvarPerfil = document.getElementById('btn-salvar-perfil');
    const btnCancelarEdicao = document.getElementById('btn-cancelar-edicao');

    // Histórico de Pedidos
    const listaPedidosEl = document.getElementById('lista-meus-pedidos');
    const historicoPedidosSection = document.getElementById('historico-pedidos'); // Seção do histórico

    // Mensagens
    const mensagemPerfilEl = document.getElementById('mensagem-perfil');

    // Variável para guardar os dados carregados do usuário
    let dadosAtuaisUsuario = null;

    // --- Verificação Inicial de Elementos ---
    // Garante que todos os elementos principais existem para evitar erros
    const elementosEssenciais = [
        nomeEl, emailEl, telefoneEl, enderecoEl, displayDadosDiv, dadosUsuarioSection,
        btnEditarPerfil, formEditarPerfil, inputNome, inputEmail, inputTelefone,
        inputEndereco, btnSalvarPerfil, btnCancelarEdicao, listaPedidosEl,
        historicoPedidosSection, mensagemPerfilEl
    ];
     if (elementosEssenciais.some(el => !el)) {
         console.error("PERFIL.JS: ERRO FATAL! Um ou mais elementos essenciais da página de perfil não foram encontrados. Verifique os IDs no perfil.html.");
         if(mensagemPerfilEl) exibirMensagemPerfil("Erro crítico ao carregar a interface do perfil.", "error");
         return; // Interrompe a execução se algo crucial faltar
     }
     console.log("PERFIL.JS: Todos os elementos essenciais da página encontrados.");


    // --- Função para Exibir Mensagens ---
    function exibirMensagemPerfil(texto, tipo = "info") {
         mensagemPerfilEl.textContent = texto;
         mensagemPerfilEl.className = `mensagem ${tipo}`;
         // Limpa a mensagem após 5 segundos
         setTimeout(() => {
             if (mensagemPerfilEl.textContent === texto) {
                  mensagemPerfilEl.textContent = '';
                  mensagemPerfilEl.className = 'mensagem';
             }
         }, 5000);
    }

    // --- Função para Carregar Dados do Perfil e Histórico de Pedidos ---
    async function carregarPerfilEPedidos() {
        console.log("PERFIL: Iniciando carregamento de dados do perfil e histórico...");
        dadosUsuarioSection.classList.add('loading'); // Adiciona classe para indicar carregamento
        listaPedidosEl.classList.add('loading');
        listaPedidosEl.innerHTML = '<p>Carregando histórico...</p>'; // Feedback visual

        try {
            // Faz a requisição para a API que busca dados do perfil e pedidos
            // Lembre-se de ajustar a URL se estiver usando all_in_one.php
             const response = await fetch(`${API_BASE_URL}/perfil_usuario.php`, {credentials: 'include'}); // ESSENCIAL para enviar cookie
            // const response = await fetch(`${API_BASE_URL}/all_in_one.php?action=perfilUsuario`, {credentials: 'include'}); // DESCOMENTE se usar all_in_one

             console.log("PERFIL: Resposta fetch perfil - Status:", response.status);

             // Se não autorizado (sessão inválida ou expirada), redireciona para login
             if (response.status === 401 || response.status === 403) {
                 alert("Sua sessão expirou ou é inválida. Por favor, faça login novamente.");
                 window.location.href = 'login.html';
                 return; // Interrompe a função
             }
             // Se ocorrer outro erro HTTP
             if (!response.ok) {
                 throw new Error(`Erro ao buscar dados (${response.status})`);
             }

             const data = await response.json();
             console.log("PERFIL: Dados recebidos da API:", data);

             if (data.sucesso) {
                 // Armazena os dados do perfil carregados
                 dadosAtuaisUsuario = data.perfil;
                 // Preenche a área de exibição com os dados
                 preencherDadosExibicao(data.perfil);
                 // Renderiza a lista de pedidos
                 renderizarHistorico(data.pedidos);
             } else {
                 // Se a API retornar {sucesso: false}
                 throw new Error(data.mensagem || "Falha ao carregar dados do servidor.");
             }

        } catch (error) {
             console.error("PERFIL: Erro ao carregar perfil/pedidos:", error);
             exibirMensagemPerfil(`Erro ao carregar seus dados: ${error.message}. Tente recarregar a página.`, "error");
             // Limpa áreas em caso de erro
             if (listaPedidosEl) listaPedidosEl.innerHTML = "<p>Não foi possível carregar o histórico de pedidos.</p>";
             preencherDadosExibicao(null); // Limpa dados do usuário
        } finally {
            // Remove as classes de loading independentemente do resultado
            dadosUsuarioSection.classList.remove('loading');
            listaPedidosEl.classList.remove('loading');
        }
    }

    // --- Função para Preencher a Área de EXIBIÇÃO dos Dados ---
    function preencherDadosExibicao(perfil) {
         // Atualiza o texto dos elementos <dd> com os dados do perfil
         // Usa 'Não informado' ou similar como fallback se o dado for nulo/vazio
         nomeEl.textContent = perfil?.nome || 'Não informado';
         emailEl.textContent = perfil?.email || 'Não informado';
         telefoneEl.textContent = perfil?.telefone || 'Não informado';
         enderecoEl.textContent = perfil?.endereco || 'Não informado (Complete seu cadastro!)';
    }

     // --- Função para Preencher o FORMULÁRIO de Edição com Dados Atuais ---
     function preencherFormularioEdicao() {
         if (dadosAtuaisUsuario) {
              inputNome.value = dadosAtuaisUsuario.nome || '';
              inputEmail.value = dadosAtuaisUsuario.email || '';
              inputTelefone.value = dadosAtuaisUsuario.telefone || '';
              inputEndereco.value = dadosAtuaisUsuario.endereco || '';
         } else {
             console.error("PERFIL: Dados atuais do usuário não disponíveis para preencher o formulário.");
             exibirMensagemPerfil("Erro ao carregar dados para edição.", "error");
             modoExibicao(); // Volta ao modo de exibição se houver erro
         }
     }

    // --- Funções para Alternar Visibilidade (Modo Exibição vs. Edição) ---
    function modoEdicao() {
        console.log("PERFIL: Entrando em modo de edição.");
        preencherFormularioEdicao(); // Garante que o form tem os dados mais recentes
        displayDadosDiv.classList.add('hidden');      // Esconde a área de exibição
        formEditarPerfil.classList.remove('hidden'); // Mostra o formulário de edição
        btnEditarPerfil.classList.add('hidden');       // Esconde o botão "Editar Perfil"
        mensagemPerfilEl.textContent = '';             // Limpa mensagens anteriores
        mensagemPerfilEl.className = 'mensagem';
    }

    function modoExibicao() {
        console.log("PERFIL: Voltando para modo de exibição.");
        displayDadosDiv.classList.remove('hidden');  // Mostra a área de exibição
        formEditarPerfil.classList.add('hidden');    // Esconde o formulário de edição
        btnEditarPerfil.classList.remove('hidden');    // Mostra o botão "Editar Perfil"
    }

    // --- Função para SALVAR Alterações do Perfil via API ---
    async function salvarAlteracoesPerfil(event) {
        event.preventDefault(); // Impede o envio tradicional do formulário
        console.log("PERFIL: Iniciando salvamento de alterações...");
        exibirMensagemPerfil("Salvando alterações...", "info"); // Feedback para o usuário

        // Pega os novos valores do formulário de edição
        const novosDados = {
            nome: inputNome.value.trim(),
            email: inputEmail.value.trim(),
            endereco: inputEndereco.value.trim(),
            telefone: inputTelefone.value.trim() || null // Envia null se o campo telefone estiver vazio
        };

        // Validação básica no frontend
        let errosValidacao = [];
         if (!novosDados.nome) errosValidacao.push("Nome");
         if (!novosDados.email || !novosDados.email.includes('@')) errosValidacao.push("Email (inválido)");
         if (!novosDados.endereco) errosValidacao.push("Endereço");
         if (errosValidacao.length > 0) {
             exibirMensagemPerfil(`Campos obrigatórios ou inválidos: ${errosValidacao.join(', ')}`, "error");
             return; // Interrompe se houver erros
         }
        console.log("PERFIL: Dados validados a serem enviados para atualização:", novosDados);

        try {
            // Faz a requisição POST para a API de atualização
            // Ajuste a URL e o body se usar all_in_one.php
             const response = await fetch(`${API_BASE_URL}/atualizar_perfil.php`, {
            // const response = await fetch(`${API_BASE_URL}/all_in_one.php`, { // DESCOMENTE se usar all_in_one
                 method: 'POST',
                 credentials: 'include', // ESSENCIAL para identificar o usuário pela sessão
                 headers: { 'Content-Type': 'application/json' },
                 body: JSON.stringify(novosDados) // Envia só os dados
                 // body: JSON.stringify({ action: 'atualizarPerfil', ...novosDados }) // DESCOMENTE se usar all_in_one
             });
             console.log("PERFIL: Resposta salvar perfil - Status:", response.status);

             const data = await response.json();
             console.log("PERFIL: Resposta JSON salvar:", data);

             // Verifica o status HTTP e o sucesso reportado pela API
             if (!response.ok) { throw new Error(data.mensagem || `Erro HTTP ${response.status}`); }
             if (!data.sucesso) { throw new Error(data.mensagem || "Falha ao atualizar perfil."); }

             // Se chegou aqui, deu sucesso!
             exibirMensagemPerfil(data.mensagem || "Perfil atualizado com sucesso!", "success");
             // Atualiza os dados locais com a resposta da API (se ela retornar os dados atualizados)
             dadosAtuaisUsuario = data.usuario_atualizado || novosDados;
             preencherDadosExibicao(dadosAtuaisUsuario); // Atualiza a área de exibição
             modoExibicao(); // Volta para o modo de exibição

        } catch (error) {
             console.error("PERFIL: Erro ao salvar alterações:", error);
              if (error instanceof SyntaxError) {
                  exibirMensagemPerfil("Erro ao processar resposta do servidor.", "error");
              } else {
                  // Exibe a mensagem de erro capturada (pode ser da API ou de rede)
                  exibirMensagemPerfil(`Erro: ${error.message}`, "error");
              }
        }
    }

    // --- Função para Renderizar o Histórico de Pedidos ---
    function renderizarHistorico(pedidos) {
        if (!listaPedidosEl) return;
        listaPedidosEl.innerHTML = ''; // Limpa
        listaPedidosEl.classList.remove('loading');

        if (!Array.isArray(pedidos) || pedidos.length === 0) {
            listaPedidosEl.innerHTML = '<p>Você ainda não fez nenhum pedido.</p>';
            return;
        }
        console.log(`PERFIL: Renderizando ${pedidos.length} pedidos no histórico.`);

        pedidos.forEach(pedido => {
            const pedidoDiv = document.createElement('div');
            pedidoDiv.classList.add('pedido-historico-item');

            // Formata a data
            let dataFormatada = 'Data indisponível';
            try {
                 if (pedido.data_pedido) {
                      const dataObj = new Date(pedido.data_pedido);
                      dataFormatada = dataObj.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' }) + ' ' +
                                      dataObj.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
                 }
            } catch(e){ console.warn("Erro ao formatar data:", pedido.data_pedido, e); }

            // Cria HTML para a lista de itens
             let itensHtml = '<p>Itens não disponíveis.</p>'; // Fallback
             if (Array.isArray(pedido.itens) && pedido.itens.length > 0) {
                 itensHtml = '<ul class="itens-list">'; // Adiciona classe para estilização se necessário
                 pedido.itens.forEach(item => {
                     const imgUrlItem = item.imagem_url_produto_atual || PLACEHOLDER_IMG; // Usa imagem do item
                     itensHtml += `
                        <li>
                            <img src="${imgUrlItem}" alt="Imagem ${item.nome_produto || 'item'}" onerror="this.onerror=null; this.src='${PLACEHOLDER_IMG}';">
                            <span class="item-nome">${item.quantidade}x ${item.nome_produto || '?'}</span>
                            <span class="item-preco">(R$ ${Number(item.preco_unitario).toFixed(2)} cada)</span>
                        </li>`;
                 });
                 itensHtml += '</ul>';
             }

            // Cria HTML para as observações
             let observacoesHtml = '';
             if(pedido.observacoes && pedido.observacoes.trim() !== '') {
                observacoesHtml = `
                    <div class="pedido-historico-observacoes">
                        <h4>Observações:</h4>
                        <p>${pedido.observacoes.replace(/\n/g, '<br>')}</p> <!-- Preserva quebras de linha -->
                    </div>`;
            }


            // Monta o card completo do pedido
            pedidoDiv.innerHTML = `
                <div class="pedido-historico-header">
                    <span><strong>Pedido #${pedido.id}</strong></span>
                    <span>${dataFormatada}</span>
                    <span>Status: <strong class="status-${(pedido.status || '').toLowerCase().replace(/\s+/g, '-')}">${pedido.status || '?'}</strong></span>
                    <span>Total: <strong>R$ ${Number(pedido.valor_total).toFixed(2)}</strong></span>
                </div>
                <div class="pedido-historico-itens">
                    <h4>Itens do Pedido:</h4>
                    ${itensHtml}
                </div>
                ${observacoesHtml}
            `;
            listaPedidosEl.appendChild(pedidoDiv);
        });
    }

    // --- Adicionar Event Listeners aos Botões de Edição ---
    btnEditarPerfil.addEventListener('click', modoEdicao);
    btnCancelarEdicao.addEventListener('click', modoExibicao);
    formEditarPerfil.addEventListener('submit', salvarAlteracoesPerfil);


    // --- Carregamento Inicial ---
    // Chama a função para carregar os dados do perfil e o histórico ao iniciar a página
    carregarPerfilEPedidos();

}); // Fim do DOMContentLoaded