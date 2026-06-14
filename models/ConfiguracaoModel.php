<?php

class ConfiguracaoModel
{
    private string $caminho;
    private ?PDO $db;
    private static bool $estruturaGarantida = false;

    public function __construct()
    {
        $this->caminho = CAMINHO_RAIZ . '/config/escola.json';
        $this->db = null;

        try {
            $this->db = Database::getInstancia();
        } catch (Throwable $erro) {
            $this->db = null;
        }
    }

    public function obter(): array
    {
        $padrao = $this->padrao();
        $json = $this->lerJson();

        if ($this->db === null) {
            return array_merge($padrao, $json);
        }

        $this->garantirEstrutura();
        $base = $this->obterDaBase();

        if (empty($base) && !empty($json)) {
            $this->persistirNaBase(array_merge($padrao, $json));
            $base = $this->obterDaBase();
        }

        return array_merge($padrao, $json, $base);
    }

    public function atualizar(array $dados): array
    {
        $atual = $this->obter();
        $novo = array_merge($atual, [
            'nome' => trim((string) ($dados['nome'] ?? '')),
            'slogan' => trim((string) ($dados['slogan'] ?? '')),
            'email' => trim((string) ($dados['email'] ?? '')),
            'telefone' => trim((string) ($dados['telefone'] ?? '')),
            'endereco' => trim((string) ($dados['endereco'] ?? '')),
            'site' => trim((string) ($dados['site'] ?? '')),
            'logotipo' => trim((string) ($dados['logotipo'] ?? ($atual['logotipo'] ?? '')))
        ]);

        if ($novo['nome'] === '') {
            return ['sucesso' => false, 'mensagem' => 'Informe o nome da escola.'];
        }

        if ($novo['email'] !== '' && !filter_var($novo['email'], FILTER_VALIDATE_EMAIL)) {
            return ['sucesso' => false, 'mensagem' => 'Informe um email valido.'];
        }

        if ($novo['logotipo'] === '') {
            $novo['logotipo'] = 'assets/imagens/icones/logo.png';
        }

        if (!$this->persistirJson($novo)) {
            return ['sucesso' => false, 'mensagem' => 'Nao foi possivel guardar as configuracoes em ficheiro.'];
        }

        if ($this->db !== null) {
            $this->garantirEstrutura();
            if (!$this->persistirNaBase($novo)) {
                return ['sucesso' => false, 'mensagem' => 'Configuracao guardada no ficheiro, mas falhou a persistencia na base de dados.'];
            }
        }

        return [
            'sucesso' => true,
            'mensagem' => 'Configuracoes atualizadas com sucesso.',
            'dados' => $novo
        ];
    }

    private function obterDaBase(): array
    {
        if ($this->db === null) {
            return [];
        }

        try {
            $stmt = $this->db->query('SELECT chave, valor FROM configuracoes');
            $linhas = $stmt->fetchAll() ?: [];
            $dados = [];
            foreach ($linhas as $linha) {
                $chave = (string) ($linha['chave'] ?? '');
                if ($chave === '') {
                    continue;
                }
                $dados[$chave] = (string) ($linha['valor'] ?? '');
            }

            return $dados;
        } catch (Throwable $erro) {
            return [];
        }
    }

    private function persistirNaBase(array $dados): bool
    {
        if ($this->db === null) {
            return false;
        }

        try {
            $this->db->beginTransaction();
            $stmt = $this->db->prepare(
                'INSERT INTO configuracoes (chave, valor, atualizado_em)
                 VALUES (:chave, :valor, NOW())
                 ON DUPLICATE KEY UPDATE valor = VALUES(valor), atualizado_em = NOW()'
            );

            foreach ($dados as $chave => $valor) {
                $chave = trim((string) $chave);
                if ($chave === '') {
                    continue;
                }

                $stmt->execute([
                    'chave' => $chave,
                    'valor' => (string) $valor
                ]);
            }

            $this->db->commit();
            return true;
        } catch (Throwable $erro) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            return false;
        }
    }

    private function garantirEstrutura(): void
    {
        if (self::$estruturaGarantida || $this->db === null) {
            return;
        }

        try {
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS configuracoes (
                    chave VARCHAR(120) PRIMARY KEY,
                    valor TEXT NULL,
                    atualizado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );
            self::$estruturaGarantida = true;
        } catch (Throwable $erro) {
        }
    }

    private function lerJson(): array
    {
        if (!is_file($this->caminho)) {
            return [];
        }

        $conteudo = @file_get_contents($this->caminho);
        if (!is_string($conteudo) || trim($conteudo) === '') {
            return [];
        }

        $dados = json_decode($conteudo, true);
        return is_array($dados) ? $dados : [];
    }

    private function persistirJson(array $dados): bool
    {
        $json = json_encode($dados, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (!is_string($json) || $json === '') {
            return false;
        }

        return @file_put_contents($this->caminho, $json) !== false;
    }

    private function padrao(): array
    {
        return [
            'nome' => 'FASCAL',
            'slogan' => 'Ferramenta Academica de Servicos, Controlo e Aprendizagem Local',
            'email' => 'secretaria@fascal.ao',
            'telefone' => '+244 921 660 962',
            'endereco' => 'Talatona, na Rua Direita da Camama',
            'site' => '',
            'logotipo' => 'assets/imagens/icones/logo.png'
        ];
    }
}

