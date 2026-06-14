<?php

class ContactoController
{
    private $mensagemSecretariaModel;

    public function __construct()
    {
        $this->mensagemSecretariaModel = new MensagemSecretariaModel();
    }

    public function index(): void
    {
        redirecionar('/');
    }

    public function enviar(): void
    {
        header('Content-Type: application/json; charset=UTF-8');

        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Metodo nao permitido.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        $dadosEntrada = $this->obterDadosEntrada();

        $nome = trim((string) ($dadosEntrada['nome'] ?? ''));
        $email = filter_var($dadosEntrada['email'] ?? '', FILTER_SANITIZE_EMAIL) ?: '';
        $assunto = trim((string) ($dadosEntrada['assunto'] ?? ''));
        $mensagem = trim((string) ($dadosEntrada['mensagem'] ?? ''));

        if (mb_strlen($nome) < 3) {
            $this->responderErro('Informe um nome valido com pelo menos 3 caracteres.', 422);
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->responderErro('Informe um email valido.', 422);
        }

        if (mb_strlen($assunto) < 3) {
            $this->responderErro('Informe um assunto valido.', 422);
        }

        if (mb_strlen($mensagem) < 10) {
            $this->responderErro('A mensagem deve ter pelo menos 10 caracteres.', 422);
        }

        $registado = $this->mensagemSecretariaModel->inserir([
            'nome' => $nome,
            'email' => $email,
            'assunto' => $assunto,
            'mensagem' => $mensagem
        ]);

        if (!$registado) {
            $this->responderErro('Nao foi possivel registar a mensagem. Tente novamente.', 500);
        }

        echo json_encode([
            'sucesso' => true,
            'mensagem' => 'Mensagem enviada com sucesso para a secretaria.'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    private function obterDadosEntrada(): array
    {
        $tipoConteudo = (string) ($_SERVER['CONTENT_TYPE'] ?? '');
        $usaJson = stripos($tipoConteudo, 'application/json') !== false;

        if ($usaJson) {
            $bruto = file_get_contents('php://input');
            $dadosJson = json_decode((string) $bruto, true);
            if (is_array($dadosJson)) {
                return $dadosJson;
            }
        }

        return $_POST;
    }

    private function responderErro(string $mensagem, int $codigoHttp): void
    {
        http_response_code($codigoHttp);
        echo json_encode([
            'sucesso' => false,
            'mensagem' => $mensagem
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
}
