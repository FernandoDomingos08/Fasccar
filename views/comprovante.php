<?php
$sucessoMatricula = obter_flash('sucesso_matricula');
$codigo = (string) ($preMatricula['codigo'] ?? '');
$nomeEncarregado = (string) ($preMatricula['nome_encarregado'] ?? '');
$nomeAluno = (string) ($preMatricula['nome_aluno'] ?? '');
$classePretendida = (string) ($preMatricula['ano_pretendido'] ?? 'Não informado');
$cursoPretendido = (string) ($preMatricula['curso_pretendido'] ?? 'Não informado');
$dataPedido = formatar_data($preMatricula['criado_em'] ?? null, 'd/m/Y H:i');
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FASCCAR - Comprovante de pre-matrícula</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/comprovante.css') ?>">
</head>
<body>
  <button class="modo-escuro-toggle sem-impressão" id="toggleModoEscuro" type="button" aria-label="Alternar modo de cor">
    <i class="fas fa-moon"></i>
    <span>Modo Escuro</span>
  </button>

  <main class="pagina-comprovante">
    <section class="comprovante-container" id="comprovante-area">
      <header class="comprovante-header">
        <div class="logo">
          <i class="fas fa-file-circle-check"></i>
        </div>
        <h1>Comprovante de pre-matrícula</h1>
        <p>Guarde este comprovante e apresente na secretaria para concluir a matrícula.</p>
      </header>

      <?php if ($sucessoMatricula): ?>
        <div class="mensagem sucesso sem-impressão">
          <i class="fas fa-circle-check"></i>
          <span><?= escapar($sucessoMatricula) ?></span>
        </div>
      <?php endif; ?>

      <div class="mensagem" id="mensagemCliente" role="status" aria-live="polite"></div>

      <div class="codigo-area">
        <span>Codigo de atendimento</span>
        <strong id="codigoAtendimento"><?= escapar($codigo) ?></strong>
        <button type="button" class="btn-copiar sem-impressão" id="copiar-codigo">
          <i class="fas fa-copy"></i>
          Copiar codigo
        </button>
      </div>

      <div class="texto-introducao">
        <p>Ola, <strong><?= escapar($nomeEncarregado) ?></strong>. O pedido de pre-matrícula para o aluno <strong><?= escapar($nomeAluno) ?></strong> foi registado com sucesso.</p>
        <p>Classe: <strong><?= escapar($classePretendida) ?></strong> | Curso: <strong><?= escapar($cursoPretendido) ?></strong> | Data do pedido: <strong><?= escapar($dataPedido) ?></strong>.</p>
      </div>

      <div class="info-grid">
        <article class="info-card">
          <h2><i class="fas fa-calendar-days"></i> Data de atendimento</h2>
          <p>28 de Marco de 2026, entre 9h00 e 11h00.</p>
        </article>

        <article class="info-card">
          <h2><i class="fas fa-location-dot"></i> Local</h2>
          <p>Talatona, na Rua Direita da Camama.</p>
        </article>

        <article class="info-card">
          <h2><i class="fas fa-phone"></i> Contacto da secretaria</h2>
          <p>+244 999 999 999<br>secretaria@fascal.ao</p>
        </article>
      </div>

      <article class="documentos-card">
        <h2><i class="fas fa-folder-open"></i> Documentos obrigatorios</h2>
        <ul>
          <li>BI do aluno (ou cedula)</li>
          <li>Certidao de nascimento</li>
          <li>Boletim de vacinas actualizado</li>
          <li>Atestado medico (menos de 6 meses)</li>
          <li>Certificado de habilitacoes da escola anterior</li>
          <li>2 fotos tipo passe (3x4)</li>
          <li>BI do encarregado de educação</li>
        </ul>
      </article>

      <article class="proximo-passo">
        <h2><i class="fas fa-route"></i> Proximo passo</h2>
        <p>Depois da conferencia documental na secretaria, a matrícula sera concluida e as credenciais de acesso ao portal serao entregues.</p>
      </article>
    </section>

    <div class="acoes-comprovante sem-impressão">
      <a class="btn-acao principal" href="<?= base_url('matricula/gerar-pdf?codigo=' . urlencode($codigo)) ?>">
        <i class="fas fa-file-pdf"></i>
        Baixar PDF
      </a>
      <button class="btn-acao" type="button" id="imprimir-comprovante">
        <i class="fas fa-print"></i>
        Imprimir
      </button>
      <a class="btn-acao" href="<?= base_url('matricula') ?>">
        <i class="fas fa-file-circle-plus"></i>
        Nova pre-matrícula
      </a>
      <a class="btn-acao" href="<?= base_url('/') ?>">
        <i class="fas fa-house"></i>
        Voltar ao inicio
      </a>
    </div>
  </main>

  <script src="<?= base_url('assets/js/paginas/comprovante.js') ?>"></script>
</body>
</html>
