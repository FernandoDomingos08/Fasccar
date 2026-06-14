<!doctype html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Documentos Oficiais - FASCAL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/css/estilo.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/modo-escuro.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/documentos-painel.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/documentos.css') ?>">
</head>
<body>
  <div class="layout-painel">
    <aside class="menu-lateral">
      <h1>Documentos Oficiais</h1>
      <p><?= escapar($_SESSION['usuario_nome'] ?? '') ?></p>
      <nav>
        <a class="activo" href="<?= base_url('documentos') ?>">Emitir PDF</a>
        <a href="#historico">Histórico</a>
        <a href="<?= base_url('administracao') ?>">CRUD Administrativo</a>
        <a href="<?= base_url('painel') ?>">Voltar ao painel</a>
        <a href="<?= base_url('login/sair') ?>">Terminar sessao</a>
      </nav>
    </aside>

    <main class="conteudo-painel">
      <header class="topo-painel">
        <div>
          <h2>Emissao de documentos institucionais</h2>
          <p class="texto-pequeno">Modelos oficiais de declaracao, certificado e boletim em PDF com assinatura institucional.</p>
        </div>
        <button class="botao" type="button" data-toggle-tema>Modo escuro</button>
      </header>

      <?php if (!empty($mensagem)): ?>
        <div class="alerta sucesso"><?= escapar($mensagem) ?></div>
      <?php endif; ?>

      <?php if (!empty($erro)): ?>
        <div class="alerta erro"><?= escapar($erro) ?></div>
      <?php endif; ?>

      <section class="grade col-2">
        <article class="cartao">
          <h3>Novo documento</h3>
          <form id="form-documento"
                class="form-documento"
                method="POST"
                action="<?= base_url('documentos/gerar-pdf') ?>"
                data-endpoint-dados="<?= base_url('documentos/dados-aluno') ?>">

            <div class="form-grupo">
              <label for="tipo_documento">Tipo de documento</label>
              <select id="tipo_documento" name="tipo_documento" required>
                <option value="declaracao">Declaracao de frequencia</option>
                <option value="certificado">Certificado escolar</option>
                <option value="boletim">Boletim de aproveitamento</option>
              </select>
            </div>

            <div class="form-grupo">
              <label for="aluno_id">Aluno</label>
              <select id="aluno_id" name="aluno_id" required>
                <option value="">Selecione o aluno</option>
                <?php foreach ($alunos as $aluno): ?>
                  <option value="<?= (int) $aluno['id'] ?>">
                    <?= escapar($aluno['aluno']) ?> - <?= escapar($aluno['turma']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-grupo">
              <label for="observacoes">Observacoes complementares</label>
              <textarea id="observacoes" name="observacoes" rows="3" placeholder="Informações adicionais para constar no documento"></textarea>
            </div>

            <button class="botao" type="submit">Gerar e baixar PDF</button>
          </form>
        </article>

        <article class="cartao" id="preview-documento">
          <h3>Pre-visualizacao rapida</h3>
          <p class="texto-pequeno">Selecione um aluno para visualizar os dados academicos antes de emitir o documento.</p>

          <div class="resumo-preview">
            <p><strong>Aluno:</strong> <span data-campo="nome_aluno">-</span></p>
            <p><strong>Turma:</strong> <span data-campo="turma">-</span></p>
            <p><strong>Encarregado:</strong> <span data-campo="nome_encarregado">-</span></p>
            <p><strong>Media geral:</strong> <span data-campo="media_geral">-</span></p>
            <p><strong>Faltas:</strong> <span data-campo="faltas">-</span></p>
          </div>

          <div class="tabela-responsiva">
            <table id="tabela-notas-preview">
              <thead>
                <tr>
                  <th>Disciplina</th>
                  <th>1o</th>
                  <th>2o</th>
                  <th>3o</th>
                  <th>Media</th>
                </tr>
              </thead>
              <tbody>
                <tr><td colspan="5">Sem dados para apresentar.</td></tr>
              </tbody>
            </table>
          </div>
        </article>
      </section>

      <section id="historico" class="cartao secao-painel">
        <h3>Histórico de documentos emitidos</h3>
        <div class="tabela-responsiva">
          <table>
            <thead>
              <tr>
                <th>Data</th>
                <th>Tipo</th>
                <th>Aluno</th>
                <th>Emitido por</th>
                <th>Ficheiro</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($historico)): ?>
                <tr><td colspan="5">Sem historico de emissao.</td></tr>
              <?php else: ?>
                <?php foreach ($historico as $item): ?>
                  <tr>
                    <td><?= formatar_data($item['criado_em'], 'd/m/Y H:i') ?></td>
                    <td><?= escapar(ucfirst($item['tipo_documento'])) ?></td>
                    <td><?= escapar($item['aluno']) ?></td>
                    <td><?= escapar($item['emitido_por']) ?></td>
                    <td><?= escapar($item['nome_ficheiro']) ?></td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>
      </section>
    </main>
  </div>

  <script src="<?= base_url('assets/js/main.js') ?>"></script>
  <script src="<?= base_url('assets/js/paginas/documentos.js') ?>"></script>
</body>
</html>
