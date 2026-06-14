<?php

class LoginController
{
    private $usuarioModel;

    private $rotasPerfil = [
        'aluno' => 'painel/aluno',
        'encarregado' => 'painel/encarregado',
        'professor' => 'painel/professor',
        'secretaria' => 'painel/secretaria',
        'direcao_pedagogica' => 'painel/direcao-pedagogica',
        'direcao_geral' => 'painel/direcao-geral',
        'rh' => 'painel/rh'
    ];

    public function __construct()
    {
        $this->usuarioModel = new UsuarioModel();
    }

    public function index(): void
    {
        if (usuario_logado()) {
            redirecionar($this->obterRotaPerfil((string) $_SESSION['perfil']));
        }

        require CAMINHO_RAIZ . '/views/login.php';
    }

    public function autenticar(): void
    {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            redirecionar('login');
        }

        $email = filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL) ?: '';
        $senha = (string) ($_POST['senha'] ?? '');
        $lembrar = isset($_POST['lembrar']);

        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $senha === '') {
            definir_flash('erro_login', 'Email ou senha invalidos. Tente novamente.');
            redirecionar('login');
        }

        $usuario = $this->usuarioModel->buscarPorEmail($email);

        if (!$usuario || !password_verify($senha, (string) ($usuario['senha'] ?? ''))) {
            definir_flash('erro_login', 'Email ou senha incorretos. Tente novamente.');
            redirecionar('login');
        }

        if ($this->usuarioModel->senhaTemporariaExpirada($usuario)) {
            definir_flash('erro_login', 'A senha temporaria expirou. Solicite redefinicao na secretaria.');
            redirecionar('login');
        }

        if ((int) $usuario['ativo'] !== 1) {
            definir_flash('erro_login', 'Conta inativa. Contacte a secretaria.');
            redirecionar('login');
        }

        $_SESSION['usuario_id'] = (int) $usuario['id'];
        $_SESSION['usuario_nome'] = $usuario['nome'];
        $_SESSION['perfil'] = $usuario['perfil'];
        $_SESSION['ultimo_acesso'] = date('Y-m-d H:i:s');

        $this->usuarioModel->atualizarUltimoAcesso((int) $usuario['id']);
        (new PerfilModel())->registarAtividade((int) $usuario['id'], 'Login efetuado', 'Acesso ao painel institucional.');

        if ($lembrar) {
            setcookie('usuario_email', $email, time() + (86400 * 30), caminho_cookie());
        } else {
            setcookie('usuario_email', '', time() - 3600, caminho_cookie());
        }

        definir_flash('sucesso_login', 'Login efetuado com sucesso! A redirecionar...');
        redirecionar($this->obterRotaPerfil($usuario['perfil']));
    }

    public function sair(): void
    {
        if (isset($_SESSION['usuario_id'])) {
            (new PerfilModel())->registarAtividade((int) $_SESSION['usuario_id'], 'Terminou sessao', 'Saida do sistema.');
        }

        fascal_encerrar_sessao();
        setcookie('usuario_email', '', time() - 3600, caminho_cookie());
        redirecionar('login?sessao=terminada');
    }

    private function obterRotaPerfil(string $perfil): string
    {
        return $this->rotasPerfil[$perfil] ?? 'painel';
    }
}
