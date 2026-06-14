<?php

class RecuperarController
{
    private $usuarioModel;

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function index(): void
    {
        require CAMINHO_RAIZ . '/views/recuperar.php';
    }

    public function solicitar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('recuperar');
        }

        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $mensagemPadrao = 'Se o email existir na base de dados, receberá instruções para redefinir a senha.';

        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $usuario = $this->usuarioModel->buscarPorEmail($email);

            if ($usuario) {
                $token = bin2hex(random_bytes(32));
                $expira = date('Y-m-d H:i:s', strtotime('+1 hour'));

                if ($this->usuarioModel->guardarTokenRecuperacao($email, $token, $expira)) {
                    $link = base_url('recuperar/redefinir?token=' . urlencode($token));
                    $assunto = 'Recuperação de senha - FASCAL';
                    $mensagem = "Olá,\n\nRecebemos um pedido de redefinição de senha.\n";
                    $mensagem .= "Use o link abaixo (válido por 1 hora):\n$link\n\n";
                    $mensagem .= "Se não fez este pedido, ignore este email.";
                    $cabecalhos = "Content-Type: text/plain; charset=UTF-8\r\n";

                    @mail($email, $assunto, $mensagem, $cabecalhos);

                    definir_flash('link_recuperacao_teste', $link);
                }
            }
        }

        definir_flash('sucesso_recuperar', $mensagemPadrao);
        redirecionar('recuperar');
    }

    public function redefinir(): void
    {
        $token = trim((string) ($_GET['token'] ?? ''));
        $tokenValido = $token !== '' && $this->usuarioModel->validarTokenRecuperacao($token);

        require CAMINHO_RAIZ . '/views/redefinir-senha.php';
    }

    public function atualizar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('recuperar');
        }

        $token = trim((string) ($_POST['token'] ?? ''));
        $novaSenha = (string) ($_POST['senha'] ?? '');
        $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');

        if ($novaSenha === '' || $confirmarSenha === '') {
            definir_flash('erro_recuperar', 'Preencha os dois campos de senha.');
            redirecionar('recuperar/redefinir?token=' . urlencode($token));
        }

        if (strlen($novaSenha) < 8) {
            definir_flash('erro_recuperar', 'A senha deve ter pelo menos 8 caracteres.');
            redirecionar('recuperar/redefinir?token=' . urlencode($token));
        }

        if ($novaSenha !== $confirmarSenha) {
            definir_flash('erro_recuperar', 'As senhas não coincidem.');
            redirecionar('recuperar/redefinir?token=' . urlencode($token));
        }

        if (!$this->usuarioModel->validarTokenRecuperacao($token)) {
            definir_flash('erro_recuperar', 'Token inválido ou expirado. Solicite novo link.');
            redirecionar('recuperar');
        }

        if ($this->usuarioModel->redefinirSenha($token, $novaSenha)) {
            definir_flash('sucesso_login', 'Senha alterada com sucesso. Faça login.');
            redirecionar('login');
        }

        definir_flash('erro_recuperar', 'Não foi possível redefinir a senha. Tente novamente.');
        redirecionar('recuperar');
    }
}
