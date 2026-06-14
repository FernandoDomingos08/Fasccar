<?php
$erroMatricula = obter_flash('erro_matricula');
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FASCCAR - Pre-matrícula</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>:root {
  --azul-principal: #0056a3;
  --azul-medio: #1e78d2;
  --azul-claro: #4a9ce8;
  --amarelo-destaque: #ffc107;
  --amarelo-claro: #ffe082;
  --branco: #ffffff;
  --cinza-claro: #f5f5f5;
  --cinza-medio: #e0e0e0;
  --cinza-escuro: #424242;
  --preto: #212121;
  --transicao: all 0.3s ease;
  --sombra: 0 10px 28px rgba(0, 0, 0, 0.14);
  --borda: 16px;
}

.modo-escuro {
  --branco: #212121;
  --cinza-claro: #424242;
  --cinza-medio: #616161;
  --cinza-escuro: #e0e0e0;
  --preto: #f5f5f5;
  --azul-principal: #4a9ce8;
  --azul-medio: #1e78d2;
}

* {
  margin: 0;
  padding: 0;
  box-sizing: border-box;
  font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

body {
  min-height: 100vh;
  background: linear-gradient(135deg, var(--azul-principal) 0%, var(--azul-claro) 100%);
  padding: 20px;
  transition: var(--transicao);
}

.pagina-matricula {
  min-height: calc(100vh - 40px);
  display: grid;
  place-items: center;
}

.matricula-container {
  width: 100%;
  max-width: 980px;
  background-color: var(--branco);
  border-radius: var(--borda);
  box-shadow: var(--sombra);
  padding: 34px;
  transition: var(--transicao);
}

.matricula-header {
  text-align: center;
  margin-bottom: 24px;
}

.logo {
  width: 82px;
  height: 82px;
  border-radius: 50%;
  margin: 0 auto 12px;
  background-color: var(--azul-principal);
  color: var(--branco);
  display: grid;
  place-items: center;
  font-size: 2rem;
}

.matricula-header h1 {
  color: var(--azul-principal);
  font-size: 1.8rem;
  margin-bottom: 4px;
}

.matricula-header p {
  color: var(--cinza-escuro);
  font-size: 0.94rem;
}

fieldset {
  border: 1px solid var(--cinza-medio);
  border-radius: 14px;
  padding: 18px;
  margin-bottom: 16px;
}

legend {
  color: var(--azul-principal);
  font-weight: 700;
  padding: 0 6px;
  display: inline-flex;
  gap: 8px;
  align-items: center;
}

.campos-grid {
  display: grid;
  gap: 14px;
}

.duas-colunas {
  grid-template-columns: repeat(2, minmax(0, 1fr));
}

.coluna-inteira {
  grid-column: 1 / -1;
}

.form-group label {
  display: block;
  margin-bottom: 7px;
  color: var(--preto);
  font-size: 0.92rem;
  font-weight: 600;
}

.input-wrapper {
  position: relative;
}

.input-wrapper > i:first-child {
  position: absolute;
  top: 50%;
  left: 13px;
  transform: translateY(-50%);
  color: var(--azul-medio);
}

.input-wrapper input,
.input-wrapper select,
.input-wrapper textarea {
  width: 100%;
  border: 1px solid var(--cinza-medio);
  border-radius: 12px;
  background-color: var(--branco);
  color: var(--preto);
  font-size: 0.97rem;
  transition: var(--transicao);
}

.input-wrapper input,
.input-wrapper select {
  height: 47px;
  padding: 10px 14px 10px 42px;
}

.input-wrapper textarea {
  resize: vertical;
  min-height: 98px;
  padding: 12px 14px 12px 42px;
}

.textarea-wrapper > i:first-child {
  top: 18px;
  transform: none;
}

.input-wrapper input:focus,
.input-wrapper select:focus,
.input-wrapper textarea:focus {
  outline: none;
  border-color: var(--azul-principal);
  box-shadow: 0 0 0 3px rgba(0, 86, 163, 0.14);
}

#estado-email {
  min-height: 20px;
  margin-top: 7px;
  font-size: 0.86rem;
}

#estado-email.erro {
  color: #b3261e;
}

#estado-email.sucesso {
  color: #146a44;
}

.alerta-info {
  border-left: 5px solid var(--amarelo-destaque);
  background-color: var(--cinza-claro);
  border-radius: 10px;
  padding: 12px 14px;
  display: flex;
  gap: 10px;
  color: var(--preto);
  margin-bottom: 15px;
  font-size: 0.9rem;
}

.btn-enviar {
  width: 100%;
  border: none;
  border-radius: 12px;
  padding: 14px;
  background-color: var(--azul-principal);
  color: var(--branco);
  font-size: 1rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 10px;
  text-decoration: none;
  transition: var(--transicao);
}

.btn-enviar:hover {
  background-color: var(--azul-medio);
  transform: translateY(-2px);
  box-shadow: 0 7px 16px rgba(0, 86, 163, 0.32);
}

.btn-enviar:disabled {
  opacity: 0.75;
  cursor: not-allowed;
  transform: none;
}

.mensagem {
  display: none;
  align-items: center;
  gap: 10px;
  border-radius: 10px;
  padding: 11px 13px;
  margin-bottom: 14px;
  font-size: 0.92rem;
}

.mensagem.erro {
  display: flex;
  background: #f8d7da;
  color: #721c24;
  border-left: 5px solid #dc3545;
}

.mensagem.sucesso {
  display: flex;
  background: #d4edda;
  color: #155724;
  border-left: 5px solid #28a745;
}

.links-rodape {
  margin-top: 18px;
  display: flex;
  justify-content: space-between;
  gap: 12px;
  flex-wrap: wrap;
}

.links-rodape a {
  color: var(--azul-principal);
  font-size: 0.92rem;
  text-decoration: none;
  font-weight: 600;
  display: inline-flex;
  align-items: center;
  gap: 8px;
}

.links-rodape a:hover {
  color: var(--azul-medio);
}

.modo-escuro-toggle {
  position: fixed;
  top: 20px;
  right: 20px;
  border: none;
  border-radius: 999px;
  padding: 10px 14px;
  background-color: var(--branco);
  color: var(--preto);
  display: inline-flex;
  align-items: center;
  gap: 8px;
  cursor: pointer;
  box-shadow: var(--sombra);
  transition: var(--transicao);
}

.modo-escuro-toggle:hover {
  transform: translateY(-2px);
}

@media (max-width: 860px) {
  .duas-colunas {
    grid-template-columns: 1fr;
  }

  .coluna-inteira {
    grid-column: auto;
  }
}

@media (max-width: 520px) {
  body {
    padding: 12px;
  }

  .pagina-matricula {
    min-height: calc(100vh - 24px);
  }

  .matricula-container {
    padding: 24px 16px;
  }

  .matricula-header h1 {
    font-size: 1.45rem;
  }

  .links-rodape {
    flex-direction: column;
  }

  .modo-escuro-toggle {
    top: 10px;
    right: 10px;
    padding: 8px 12px;
  }
}</style>
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/matricula.css') ?>">
</head>
<body>
  <button class="modo-escuro-toggle" id="toggleModoEscuro" type="button" aria-label="Alternar modo de cor">
    <i class="fas fa-moon"></i>
    <span>Modo Escuro</span>
  </button>

  <main class="pagina-matricula">
    <section class="matricula-container">
      <div class="matricula-header">
        <div class="logo">
          <i class="fas fa-user-graduate"></i>
        </div>
        <h1>Pre-matrícula FASCCAR</h1>
        <p>Preencha os dados do encarregado e do aluno para gerar o codigo unico de atendimento.</p>
      </div>

      <?php if ($erroMatricula): ?>
        <div class="mensagem erro">
          <i class="fas fa-circle-exclamation"></i>
          <span><?= escapar($erroMatricula) ?></span>
        </div>
      <?php endif; ?>

      <div class="mensagem" id="mensagemCliente" role="status" aria-live="polite"></div>

      <form
        id="form-pre-matricula"
        method="POST"
        action="<?= base_url('matricula/processar') ?>"
        data-validar-email-url="<?= base_url('matricula/validar-email') ?>"
        novalidate
      >
        <?= csrf_field() ?>
        <fieldset>
          <legend><i class="fas fa-user-tie"></i> Dados do encarregado</legend>

          <div class="campos-grid duas-colunas">
            <div class="form-group">
              <label for="nome_encarregado">Nome do encarregado</label>
              <div class="input-wrapper">
                <i class="fas fa-user"></i>
                <input id="nome_encarregado" name="nome_encarregado" type="text" required>
              </div>
            </div>

            <div class="form-group">
              <label for="telefone_encarregado">Telefone</label>
              <div class="input-wrapper">
                <i class="fas fa-phone"></i>
                <input id="telefone_encarregado" name="telefone_encarregado" type="tel" placeholder="9XXXXXXXX" required>
              </div>
            </div>

            <div class="form-group coluna-inteira">
              <label for="email_encarregado">E-mail</label>
              <div class="input-wrapper">
                <i class="fas fa-envelope"></i>
                <input id="email_encarregado" name="email_encarregado" type="email" placeholder="encarregado@email.com" required>
              </div>
              <p id="estado-email" aria-live="polite"></p>
            </div>
          </div>
        </fieldset>

        <fieldset>
          <legend><i class="fas fa-child"></i> Dados do aluno</legend>

          <div class="campos-grid duas-colunas">
            <div class="form-group">
              <label for="nome_aluno">Nome completo do aluno</label>
              <div class="input-wrapper">
                <i class="fas fa-id-card"></i>
                <input id="nome_aluno" name="nome_aluno" type="text" required>
              </div>
            </div>

            <div class="form-group">
              <label for="data_nascimento_aluno">Data de nascimento</label>
              <div class="input-wrapper">
                <i class="fas fa-calendar-days"></i>
                <input id="data_nascimento_aluno" name="data_nascimento_aluno" type="date" required>
              </div>
            </div>

            <div class="form-group">
              <label for="ano_pretendido">Classe/ano pretendido</label>
              <div class="input-wrapper">
                <i class="fas fa-school"></i>
                <select id="ano_pretendido" name="ano_pretendido" required>
                  <option value="">Selecione</option>
                  <option>10ª Classe</option>
                  <option>11ª Classe</option>
                  <option>12ª Classe</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label for="curso_pretendido">Curso pretendido</label>
              <div class="input-wrapper">
                <i class="fas fa-graduation-cap"></i>
                <select id="curso_pretendido" name="curso_pretendido" required>
                  <option value="">Selecione</option>
                  <option>Informática</option>
                  <option>Recursos Humanos (RH)</option>
                  <option>Contabilidade</option>
                  <option>Ciências Físicas e Biológicas</option>
                </select>
              </div>
            </div>

            <div class="form-group">
              <label for="observacoes">Observacoes (opcional)</label>
              <div class="input-wrapper textarea-wrapper">
                <i class="fas fa-note-sticky"></i>
                <textarea id="observacoes" name="observacoes" rows="3" placeholder="Necessidades especificas, transferencia, etc."></textarea>
              </div>
            </div>
          </div>
        </fieldset>

        <div class="alerta-info">
          <i class="fas fa-triangle-exclamation"></i>
          <span>Importante: os dados submetidos são enviados ao painel da secretaria para confirmacao presencial da matrícula.</span>
        </div>

        <button class="btn-enviar" id="btnEnviarMatricula" type="submit">
          <i class="fas fa-file-signature"></i>
          Gerar codigo de pre-matrícula
        </button>
      </form>

      <div class="links-rodape">
        <a href="<?= base_url('login') ?>">
          <i class="fas fa-right-to-bracket"></i>
          Ja tenho conta
        </a>
        <a href="<?= base_url('/') ?>">
          <i class="fas fa-house"></i>
          Voltar ao site
        </a>
      </div>
    </section>
  </main>

  <script src="<?= base_url('assets/js/paginas/matricula.js') ?>"></script>
</body>
</html>
