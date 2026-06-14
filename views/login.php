<?php
$erroLogin = obter_flash('erro_login');
$sucessoLogin = obter_flash('sucesso_login');
$sessaoTerminada = (string) ($_GET['sessao'] ?? '') === 'terminada';
$sucessoLogin = $sucessoLogin ?: ($sessaoTerminada ? 'Sessao terminada com sucesso.' : '');
$emailMemorizado = $_COOKIE['usuario_email'] ?? '';
?>
<!doctype html>
<html lang="pt">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FASCCAR - Login</title>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/login.css') ?>" />
</head>
<body>
  <button class="modo-escuro-toggle" id="toggleModoEscuro" type="button" aria-label="Alternar modo de cor">
    <i class="fas fa-moon"></i>
    <span>Modo Escuro</span>
  </button>

  <main class="pagina-login">
    <section class="login-container">
      <div class="login-header">
        <div class="logo">
          <img src="<?= base_url('assets/imagens/icones/fasccar.png') ?>" alt="Logo FASCCAR">
        </div>
        <h1>FASCCAR</h1>
        <p>Ferramenta Académica de Serviços, Controlo e Aprendizagem Local</p>
      </div>

      <?php if ($erroLogin): ?>
        <div class="mensagem erro">
          <i class="fas fa-circle-exclamation"></i>
          <span><?= escapar($erroLogin) ?></span>
        </div>
      <?php endif; ?>

      <?php if ($sucessoLogin): ?>
        <div class="mensagem sucesso">
          <i class="fas fa-circle-check"></i>
          <span><?= escapar($sucessoLogin) ?></span>
        </div>
      <?php endif; ?>

      <div class="mensagem" id="mensagemCliente" role="status" aria-live="polite"></div>

      <form id="loginForm" method="POST" action="<?= base_url('login/autenticar') ?>" novalidate>
        <?= csrf_field() ?>
        <div class="form-group">
          <label for="email">
            <i class="fas fa-envelope"></i>
            E-mail
          </label>
          <div class="input-wrapper">
            <i class="fas fa-envelope"></i>
            <input
              type="email"
              id="email"
              name="email"
              placeholder="seu@email.com"
              required
              autocomplete="email"
              value="<?= escapar($emailMemorizado) ?>"
            >
          </div>
        </div>

        <div class="form-group">
          <label for="senha">
            <i class="fas fa-lock"></i>
            Senha
          </label>
          <div class="input-wrapper">
            <i class="fas fa-lock"></i>
            <input
              type="password"
              id="senha"
              name="senha"
              placeholder="Digite a sua senha"
              required
              autocomplete="current-password"
            >
            <button type="button" class="toggle-password" id="toggleSenha" aria-label="Mostrar ou ocultar senha">
              <i class="fas fa-eye"></i>
            </button>
          </div>
        </div>

        <div class="lembrar-row">
          <label class="checkbox-label">
            <input type="checkbox" id="lembrar" name="lembrar" value="1">
            <span>Lembrar-me</span>
          </label>
          <button type="button" class="esqueceu-senha" id="esqueceuSenha">Esqueceu a senha?</button>
        </div>

        <button type="submit" class="btn-entrar" id="btnEntrar">
          <i class="fas fa-right-to-bracket"></i>
          Entrar
        </button>
      </form>

      <div class="links-rodape">
        <a href="<?= base_url('/') ?>">
          <i class="fas fa-house"></i>
          Voltar ao site
        </a>
        <a href="<?= base_url('matricula') ?>">
          <i class="fas fa-user-plus"></i>
          Nao tem conta? Matricule-se
        </a>
      </div>
    </section>
  </main>

  <script src="<?= base_url('assets/js/paginas/login.js') ?>"></script>
</body>
</html>

