<!doctype html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=yes">
  <title>Recrutamento Docente | Instituto Mundo Novo II</title>
  <!-- Font Awesome para ícones -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <style>
    /* ========== RESET & VARIÁVEIS ========== */
    * {
      margin: 0;
      padding: 0;
      box-sizing: border-box;
    }

    :root {
      --azul-escuro: #0a2b4e;
      --azul-principal: #1e4a76;
      --azul-claro: #eef2ff;
      --destaque: #f59e0b;
      --destaque-hover: #d97706;
      --cinza-claro: #f8fafc;
      --cinza-borda: #e2e8f0;
      --cinza-texto: #334155;
      --verde-sucesso: #10b981;
      --vermelho-erro: #ef4444;
      --branco: #ffffff;
      --sombra-padrao: 0 10px 25px -5px rgba(0, 0, 0, 0.05), 0 8px 10px -6px rgba(0, 0, 0, 0.02);
      --sombra-media: 0 20px 25px -12px rgba(0, 0, 0, 0.1);
      --transicao: all 0.2s ease;
    }

    body {
      font-family: system-ui, 'Segoe UI', 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
      background: linear-gradient(135deg, #e0e7ff 0%, #f1f5f9 100%);
      color: var(--cinza-texto);
      line-height: 1.5;
      min-height: 100vh;
      padding: 1.5rem;
    }

    /* ========== CONTAINER PRINCIPAL ========== */
    .pagina-recrutamento {
      max-width: 1000px;
      margin: 0 auto;
      animation: fadeInUp 0.5s ease;
    }

    @keyframes fadeInUp {
      from {
        opacity: 0;
        transform: translateY(20px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    /* ========== CARTÃO PRINCIPAL ========== */
    .recrutamento-cartao {
      background: var(--branco);
      border-radius: 2rem;
      box-shadow: var(--sombra-media);
      overflow: hidden;
      transition: var(--transicao);
    }

    /* Cabeçalho com gradiente */
    .cartao-header {
      background: linear-gradient(135deg, var(--azul-principal), var(--azul-escuro));
      padding: 2rem 2rem 1.8rem;
      color: white;
      text-align: center;
    }

    .cartao-header h1 {
      font-size: 1.9rem;
      margin-bottom: 0.5rem;
      font-weight: 700;
    }

    .cartao-header h1 i {
      margin-right: 0.5rem;
      color: var(--destaque);
    }

    .cartao-header p {
      opacity: 0.9;
      font-size: 1rem;
      max-width: 80%;
      margin: 0 auto;
    }

    /* Conteúdo do formulário */
    .cartao-conteudo {
      padding: 2rem;
    }

    /* ========== ALERTAS ========== */
    .alerta {
      padding: 1rem 1.2rem;
      border-radius: 1rem;
      margin-bottom: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.75rem;
      font-weight: 500;
      animation: slideIn 0.3s ease;
    }

    .alerta.sucesso {
      background: #d1fae5;
      color: #065f46;
      border-left: 5px solid var(--verde-sucesso);
    }

    .alerta.erro {
      background: #fee2e2;
      color: #991b1b;
      border-left: 5px solid var(--vermelho-erro);
    }

    .alerta i {
      font-size: 1.3rem;
    }

    @keyframes slideIn {
      from {
        opacity: 0;
        transform: translateX(-10px);
      }
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    /* ========== FORMULÁRIO ========== */
    .form-recrutamento {
      display: flex;
      flex-direction: column;
      gap: 1.5rem;
    }

    .grade-colunas {
      display: grid;
      grid-template-columns: repeat(2, 1fr);
      gap: 1.2rem;
    }

    /* Grupo de campo */
    .form-grupo {
      display: flex;
      flex-direction: column;
      gap: 0.4rem;
    }

    .form-grupo label {
      font-weight: 600;
      font-size: 0.9rem;
      color: var(--azul-escuro);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }

    .form-grupo label i {
      color: var(--destaque);
      width: 1.2rem;
    }

    .form-grupo input,
    .form-grupo textarea {
      padding: 0.8rem 1rem;
      border: 1.5px solid var(--cinza-borda);
      border-radius: 1rem;
      font-family: inherit;
      font-size: 0.95rem;
      transition: var(--transicao);
      background: var(--cinza-claro);
      resize: vertical;
    }

    .form-grupo input:focus,
    .form-grupo textarea:focus {
      outline: none;
      border-color: var(--azul-principal);
      box-shadow: 0 0 0 3px rgba(30, 74, 118, 0.2);
      background: white;
    }

    /* Campos de arquivo personalizados */
    .arquivo-label {
      position: relative;
      cursor: pointer;
    }

    .arquivo-input {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .arquivo-fake {
      display: flex;
      align-items: center;
      gap: 0.8rem;
      padding: 0.8rem 1rem;
      background: var(--cinza-claro);
      border: 1.5px dashed var(--cinza-borda);
      border-radius: 1rem;
      cursor: pointer;
      transition: var(--transicao);
    }

    .arquivo-fake i {
      font-size: 1.3rem;
      color: var(--azul-principal);
    }

    .arquivo-fake span {
      color: #64748b;
    }

    .arquivo-fake:hover {
      background: var(--azul-claro);
      border-color: var(--azul-principal);
    }

    .nome-arquivo {
      font-size: 0.75rem;
      color: #64748b;
      margin-top: 0.3rem;
    }

    /* Botões */
    .acoes-formulario {
      display: flex;
      flex-wrap: wrap;
      gap: 1rem;
      margin-top: 0.5rem;
    }

    .botao {
      display: inline-flex;
      align-items: center;
      gap: 0.6rem;
      padding: 0.8rem 1.8rem;
      background: var(--azul-principal);
      color: white;
      border: none;
      border-radius: 3rem;
      font-weight: 600;
      font-size: 0.95rem;
      cursor: pointer;
      transition: var(--transicao);
      text-decoration: none;
    }

    .botao i {
      font-size: 1rem;
    }

    .botao:hover {
      background: var(--azul-escuro);
      transform: translateY(-2px);
      box-shadow: var(--sombra-padrao);
    }

    .botao.secundario {
      background: #f1f5f9;
      color: var(--azul-principal);
      border: 1px solid var(--cinza-borda);
    }

    .botao.secundario:hover {
      background: #e2e8f0;
      transform: translateY(-2px);
    }

    /* Card de informações */
    .info-recrutamento {
      margin-top: 2rem;
      background: var(--azul-claro);
      border-radius: 1.5rem;
      padding: 1.5rem;
    }

    .info-recrutamento h2 {
      display: flex;
      align-items: center;
      gap: 0.6rem;
      font-size: 1.3rem;
      color: var(--azul-escuro);
      margin-bottom: 1rem;
    }

    .info-recrutamento h2 i {
      color: var(--destaque);
    }

    .info-recrutamento ol {
      padding-left: 1.5rem;
      display: flex;
      flex-direction: column;
      gap: 0.6rem;
    }

    .info-recrutamento li {
      line-height: 1.4;
    }

    /* Responsividade */
    @media (max-width: 700px) {
      body {
        padding: 1rem;
      }
      .grade-colunas {
        grid-template-columns: 1fr;
        gap: 1rem;
      }
      .cartao-header {
        padding: 1.5rem;
      }
      .cartao-header h1 {
        font-size: 1.5rem;
      }
      .cartao-header p {
        max-width: 100%;
      }
      .cartao-conteudo {
        padding: 1.5rem;
      }
      .acoes-formulario {
        justify-content: center;
      }
      .botao {
        flex: 1;
        justify-content: center;
      }
    }

    @media (max-width: 480px) {
      .recrutamento-cartao {
        border-radius: 1.5rem;
      }
      .info-recrutamento ol {
        padding-left: 1rem;
      }
    }
  </style>
</head>
<body>
  <main class="pagina-recrutamento">
    <div class="recrutamento-cartao">
      <div class="cartao-header">
        <h1><i class="fas fa-chalkboard-user"></i> Recrutamento de Professores</h1>
        <p>Submeta a sua candidatura para integrar a equipa pedagógica do Instituto Politécnico Privado Mundo Novo II.</p>
      </div>

      <div class="cartao-conteudo">
        <!-- Mensagens de sucesso/erro -->
        <?php if (!empty($mensagemSucesso)): ?>
          <div class="alerta sucesso">
            <i class="fas fa-check-circle"></i>
            <span><?= escapar((string) $mensagemSucesso) ?></span>
          </div>
          <div class="acoes-formulario" style="margin-bottom: 1rem;">
            <button class="botao secundario" type="button" onclick="window.print()">
              <i class="fas fa-print"></i> Imprimir comprovativo
            </button>
          </div>
        <?php endif; ?>

        <?php if (!empty($mensagemErro)): ?>
          <div class="alerta erro">
            <i class="fas fa-exclamation-triangle"></i>
            <span><?= escapar((string) $mensagemErro) ?></span>
          </div>
        <?php endif; ?>

        <!-- Formulário -->
        <form class="form-recrutamento" method="POST" action="<?= base_url('recrutamento/candidatar') ?>" enctype="multipart/form-data" id="formCandidatura">
          <?= csrf_field() ?>
          <div class="grade-colunas">
            <div class="form-grupo">
              <label><i class="fas fa-user"></i> Nome completo *</label>
              <input type="text" name="nome" id="nome" required placeholder="Ex: Maria João dos Santos">
            </div>
            <div class="form-grupo">
              <label><i class="fas fa-envelope"></i> E-mail *</label>
              <input type="email" name="email" id="email" required placeholder="exemplo@email.com">
            </div>
            <div class="form-grupo">
              <label><i class="fas fa-phone-alt"></i> Telefone</label>
              <input type="tel" name="telefone" placeholder="+244 923 456 789">
            </div>
            <div class="form-grupo">
              <label><i class="fas fa-graduation-cap"></i> Área de candidatura *</label>
              <input type="text" name="area_candidatura" required placeholder="Matemática, Português, Física...">
            </div>
            <div class="form-grupo coluna-inteira" style="grid-column: 1/-1;">
              <label><i class="fas fa-briefcase"></i> Experiência profissional</label>
              <textarea name="experiencia" rows="3" placeholder="Descreva os anos de experiência, níveis de ensino, escolas onde trabalhou e certificações relevantes."></textarea>
            </div>
            <div class="form-grupo coluna-inteira" style="grid-column: 1/-1;">
              <label><i class="fas fa-comment-dots"></i> Mensagem adicional</label>
              <textarea name="mensagem" rows="2" placeholder="Disponibilidade de horário, pretensões, observações..."></textarea>
            </div>
            <div class="form-grupo">
              <label><i class="fas fa-file-pdf"></i> Currículo (PDF) *</label>
              <div class="arquivo-label">
                <input type="file" name="cv_pdf" id="cv_pdf" accept=".pdf" required class="arquivo-input">
                <div class="arquivo-fake" onclick="document.getElementById('cv_pdf').click()">
                  <i class="fas fa-cloud-upload-alt"></i>
                  <span>Clique para selecionar o CV</span>
                </div>
                <div id="cv_nome" class="nome-arquivo"></div>
              </div>
            </div>
            <div class="form-grupo">
              <label><i class="fas fa-certificate"></i> Certificados (opcional)</label>
              <div class="arquivo-label">
                <input type="file" name="certificados_pdf" id="certificados_pdf" accept=".pdf" class="arquivo-input">
                <div class="arquivo-fake" onclick="document.getElementById('certificados_pdf').click()">
                  <i class="fas fa-paperclip"></i>
                  <span>Anexar certificados</span>
                </div>
                <div id="cert_nome" class="nome-arquivo"></div>
              </div>
            </div>
          </div>

          <div class="acoes-formulario">
            <button class="botao" type="submit" id="btnEnviar">
              <i class="fas fa-paper-plane"></i> Enviar candidatura
            </button>
            <a class="botao secundario" href="<?= base_url('/') ?>">
              <i class="fas fa-arrow-left"></i> Voltar ao início
            </a>
          </div>
        </form>

        <!-- Informações adicionais -->
        <section class="info-recrutamento">
          <h2><i class="fas fa-clipboard-list"></i> Como funciona a avaliação</h2>
          <ol>
            <li><strong>1.</strong> Recepção e triagem dos documentos pelo RH.</li>
            <li><strong>2.</strong> Avaliação técnica e validação académica da equipa pedagógica.</li>
            <li><strong>3.</strong> Contacto para entrevista (quando aplicável) dentro de 10 dias úteis.</li>
          </ol>
          <p style="margin-top: 1rem; font-size: 0.85rem;"><i class="fas fa-info-circle"></i> Após o envio, receberá um e-mail de confirmação automática.</p>
        </section>
      </div>
    </div>
  </main>

  <!-- JavaScript para melhorar a UX: validação simples, feedback de arquivos, loading no botão -->
  <script>
    (function() {
      // Exibir nome dos arquivos selecionados
      const cvInput = document.getElementById('cv_pdf');
      const certInput = document.getElementById('certificados_pdf');
      const cvNomeSpan = document.getElementById('cv_nome');
      const certNomeSpan = document.getElementById('cert_nome');

      if (cvInput) {
        cvInput.addEventListener('change', function(e) {
          if (cvInput.files.length > 0) {
            cvNomeSpan.textContent = `📄 ${cvInput.files[0].name}`;
          } else {
            cvNomeSpan.textContent = '';
          }
        });
      }

      if (certInput) {
        certInput.addEventListener('change', function(e) {
          if (certInput.files.length > 0) {
            certNomeSpan.textContent = `📎 ${certInput.files[0].name}`;
          } else {
            certNomeSpan.textContent = '';
          }
        });
      }

      // Validação em tempo real no formulário (antes do envio)
      const form = document.getElementById('formCandidatura');
      const btnEnviar = document.getElementById('btnEnviar');

      if (form) {
        form.addEventListener('submit', function(event) {
          // Validação simples de campos obrigatórios
          const nome = document.getElementById('nome');
          const email = document.getElementById('email');
          const area = document.querySelector('input[name="area_candidatura"]');
          const cv = cvInput;

          let erro = false;
          let msgErro = '';

          if (!nome.value.trim()) {
            erro = true;
            msgErro = 'Por favor, preencha o nome completo.';
            nome.focus();
          } else if (!email.value.trim() || !email.value.includes('@')) {
            erro = true;
            msgErro = 'Informe um e-mail válido.';
            email.focus();
          } else if (!area.value.trim()) {
            erro = true;
            msgErro = 'Digite a área de candidatura.';
            area.focus();
          } else if (!cv.files || cv.files.length === 0) {
            erro = true;
            msgErro = 'Selecione o arquivo do Currículo (PDF).';
          } else if (cv.files[0].type !== 'application/pdf') {
            erro = true;
            msgErro = 'O currículo deve ser um arquivo PDF.';
          } else if (certInput.files.length > 0 && certInput.files[0].type !== 'application/pdf') {
            erro = true;
            msgErro = 'Os certificados anexados devem ser PDF.';
          }

          if (erro) {
            event.preventDefault();
            // Criar ou exibir alerta de erro personalizado
            let alertaExistente = document.querySelector('.alerta.erro:not(.sistema)');
            if (!alertaExistente) {
              const divErro = document.createElement('div');
              divErro.className = 'alerta erro sistema';
              divErro.innerHTML = `<i class="fas fa-exclamation-circle"></i><span>${msgErro}</span>`;
              const cartaoConteudo = document.querySelector('.cartao-conteudo');
              const primeiroElemento = cartaoConteudo.firstChild;
              cartaoConteudo.insertBefore(divErro, primeiroElemento);
              setTimeout(() => divErro.remove(), 5000);
            } else {
              alertaExistente.querySelector('span').innerText = msgErro;
            }
            return false;
          }

          // Mostrar loading no botão
          if (btnEnviar) {
            btnEnviar.disabled = true;
            btnEnviar.innerHTML = '<i class="fas fa-spinner fa-pulse"></i> Enviando...';
          }
          // O formulário será submetido normalmente
        });
      }

      // Opcional: Remover mensagens automáticas após alguns segundos
      const alertas = document.querySelectorAll('.alerta');
      alertas.forEach(alerta => {
        setTimeout(() => {
          alerta.style.opacity = '0';
          setTimeout(() => alerta.remove(), 300);
        }, 6000);
      });
    })();
  </script>
</body>
</html>