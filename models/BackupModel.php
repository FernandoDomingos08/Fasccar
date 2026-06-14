<?php

class BackupModel
{
    private $db;
    private $pasta;
    private $meta;

    public function __construct()
    {
        $this->db = Database::getInstancia();
        $this->pasta = CAMINHO_RAIZ . '/storage/backups';
        $this->meta = $this->pasta . '/backup-meta.json';
    }

    public function executarSeNecessario(int $intervaloHoras = 24): array
    {
        $estado = $this->obterStatus();
        $ultimo = $estado['ultimo_backup'] ?? null;

        if ($ultimo) {
            $ultimoTimestamp = strtotime($ultimo);
            if ($ultimoTimestamp !== false && (time() - $ultimoTimestamp) < ($intervaloHoras * 3600)) {
                return ['executado' => false] + $estado;
            }
        }

        return $this->executarBackup();
    }

    public function obterStatus(): array
    {
        if (!is_file($this->meta)) {
            return [
                'ultimo_backup' => null,
                'ficheiro' => null
            ];
        }

        $conteudo = file_get_contents($this->meta);
        if (!is_string($conteudo) || trim($conteudo) === '') {
            return [
                'ultimo_backup' => null,
                'ficheiro' => null
            ];
        }

        $dados = json_decode($conteudo, true);
        if (!is_array($dados)) {
            return [
                'ultimo_backup' => null,
                'ficheiro' => null
            ];
        }

        return [
            'ultimo_backup' => $dados['ultimo_backup'] ?? null,
            'ficheiro' => $dados['ficheiro'] ?? null
        ];
    }

    private function executarBackup(): array
    {
        if (!is_dir($this->pasta)) {
            mkdir($this->pasta, 0775, true);
        }

        $data = date('Y-m-d H:i:s');
        $ficheiro = 'backup-' . date('Ymd-His') . '.json';
        $caminho = $this->pasta . '/' . $ficheiro;

        $tabelas = [
            'utilizadores',
            'alunos',
            'encarregados',
            'professores',
            'funcionarios',
            'turmas',
            'disciplinas',
            'matriculas',
            'notas',
            'pagamentos',
            'avisos',
            'mensagens_internas',
            'documentos_emitidos',
            'tramitacoes_documentais'
        ];

        $snapshot = [
            'gerado_em' => $data,
            'tipo' => 'backup_simulado',
            'tabelas' => []
        ];

        foreach ($tabelas as $tabela) {
            $snapshot['tabelas'][$tabela] = $this->contarRegistos($tabela);
        }

        $json = json_encode($snapshot, JSON_PRETTY_PRINT);
        if ($json !== false) {
            @file_put_contents($caminho, $json);
        }

        $meta = [
            'ultimo_backup' => $data,
            'ficheiro' => $ficheiro
        ];
        @file_put_contents($this->meta, json_encode($meta, JSON_PRETTY_PRINT));

        return ['executado' => true] + $meta;
    }

    private function contarRegistos(string $tabela): int
    {
        try {
            $stmt = $this->db->query('SELECT COUNT(*) FROM ' . $tabela);
            return (int) $stmt->fetchColumn();
        } catch (Throwable $erro) {
            return 0;
        }
    }
}
