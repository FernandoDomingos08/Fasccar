<!doctype html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Configurações da Escola - FASCAL</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/css/estilo.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/modo-escuro.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/configuracoes-painel.css') ?>">
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/administracao.css') ?>">
</head>
<body>
  <div class="layout-painel">
    <aside class="menu-lateral">
      <h1>Configurações</h1>
      <p><?= escapar($_SESSION['usuario_nome'] ?? '') ?></p>
      <nav>
        <a class="activo" href="<?= base_url('configuracoes') ?>">Dados da escola</a>
        <a href="<?= base_url('painel') ?>">Voltar ao painel</a>
        <a href="<?= base_url('login/sair') ?>">Terminar sessao</a>
      </nav>
    </aside>

    <main class="conteudo-painel">
      <header class="topo-painel">
        <div>
          <h2>Configurações institucionais</h2>
          <p class="texto-pequeno">Atualize dados oficiais e contactos do colégio.</p>
        </div>
        <button class="botao" type="button" data-toggle-tema>Modo escuro</button>
      </header>

      <?php if (!empty($mensagem)): ?>
        <div class="alerta sucesso"><?= escapar($mensagem) ?></div>
      <?php endif; ?>

      <?php if (!empty($erro)): ?>
        <div class="alerta erro"><?= escapar($erro) ?></div>
      <?php endif; ?>

      <section class="cartao secao-painel">
        <h3>Dados oficiais da escola</h3>
        <form class="form-admin" method="POST" action="<?= base_url('configuracoes/atualizar') ?>">
          <?= csrf_field() ?>
          <div class="grade col-3">
            <div class="form-grupo">
              <label>Nome da escola</label>
              <input type="text" name="nome" value="<?= escapar((string) ($config['nome'] ?? '')) ?>" required>
            </div>
            <div class="form-grupo">
              <label>Slogan</label>
              <input type="text" name="slogan" value="<?= escapar((string) ($config['slogan'] ?? '')) ?>">
            </div>
            <div class="form-grupo">
              <label>Email institucional</label>
              <input type="email" name="email" value="<?= escapar((string) ($config['email'] ?? '')) ?>">
            </div>
            <div class="form-grupo">
              <label>Telefone principal</label>
              <input type="text" name="telefone" value="<?= escapar((string) ($config['telefone'] ?? '')) ?>">
            </div>
            <div class="form-grupo coluna-inteira">
              <label>Endereço</label>
              <input type="text" name="endereco" value="<?= escapar((string) ($config['endereco'] ?? '')) ?>">
            </div>
            <div class="form-grupo coluna-inteira">
              <label>Website (opcional)</label>
              <input type="text" name="site" value="<?= escapar((string) ($config['site'] ?? '')) ?>">
            </div>
          </div>
          <button class="botao" type="submit">Guardar configuracoes</button>
        </form>
      </section>
    </main>
  </div>

  <script src="<?= base_url('assets/js/main.js') ?>"></script>
</body>
</html>
