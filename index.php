<?php

require_once __DIR__ . '/config/sessao.php';
fascal_iniciar_sessao();
date_default_timezone_set('Africa/Luanda');
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

$baseUrl = '';
$baseUrlConfigurada = getenv('APP_BASE_URL');

if (is_string($baseUrlConfigurada) && trim($baseUrlConfigurada) !== '') {
    $baseUrl = trim($baseUrlConfigurada);
    if (!preg_match('#^https?://#i', $baseUrl)) {
        $baseUrl = '/' . trim($baseUrl, '/');
    }
} else {
    $documentRoot = realpath($_SERVER['DOCUMENT_ROOT'] ?? '');
    $caminhoAplicacao = realpath(__DIR__);

    if ($documentRoot !== false && $caminhoAplicacao !== false) {
        $documentRootNormalizado = str_replace('\\', '/', $documentRoot);
        $caminhoAplicacaoNormalizado = str_replace('\\', '/', $caminhoAplicacao);

        if (str_starts_with($caminhoAplicacaoNormalizado, $documentRootNormalizado)) {
            $relativo = substr($caminhoAplicacaoNormalizado, strlen($documentRootNormalizado));
            $relativo = '/' . trim((string) $relativo, '/');
            $baseUrl = $relativo === '/' ? '' : $relativo;
        }
    }

    if ($baseUrl === '') {
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $baseUrl = ($scriptDir === '/' || $scriptDir === '.') ? '' : rtrim($scriptDir, '/');
    }
}

if (!defined('BASE_URL')) {
    define('BASE_URL', $baseUrl);
}

if (!defined('CAMINHO_RAIZ')) {
    define('CAMINHO_RAIZ', __DIR__);
}

require_once CAMINHO_RAIZ . '/config/database.php';
require_once CAMINHO_RAIZ . '/config/funcoes.php';
require_once CAMINHO_RAIZ . '/core/Seguranca.php';
aplicar_cabecalhos_seguranca();
csrf_bootstrap();

function fascal_injetar_head_global(string $conteudo): string
{
    if (stripos($conteudo, '<html') === false || stripos($conteudo, '</head>') === false) {
        return $conteudo;
    }

    $favicon = base_url('assets/imagens/icones/logo.png');
    $chartLocal = base_url('assets/vendor/chart/chart.umd.min.js');
    $blocos = [];

    if (stripos($conteudo, 'rel="icon"') === false && stripos($conteudo, "rel='icon'") === false) {
        $blocos[] = '<link rel="icon" type="image/png" href="' . escapar($favicon) . '">';
    }

    if (
        stripos($conteudo, 'chart.js') !== false
        && stripos($conteudo, '__fascalChartFallback') === false
    ) {
        $chartLocalJson = json_encode($chartLocal, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $blocos[] = '<script id="__fascalChartFallback">if(typeof window!=="undefined"&&typeof window.Chart==="undefined"){document.write(\'<script src="\'+'
            . $chartLocalJson
            . '+\'"><\/script>\');}</script>';
    }

    if (empty($blocos)) {
        return $conteudo;
    }

    $injecao = implode("\n", $blocos) . "\n";
    return preg_replace('#</head>#i', $injecao . '</head>', $conteudo, 1) ?? $conteudo;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $tokenCsrf = csrf_token_requisicao();
    if (!csrf_token_valido($tokenCsrf)) {
        if (requisicao_assincrona()) {
            http_response_code(403);
            header('Content-Type: application/json; charset=UTF-8');
            echo json_encode([
                'sucesso' => false,
                'mensagem' => 'Operacao bloqueada por token CSRF invalido.'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }

        http_response_code(403);
        echo 'Operacao bloqueada por token CSRF invalido.';
        exit;
    }
}

spl_autoload_register(static function (string $classe): void {
    $pastas = ['controllers', 'models'];
    foreach ($pastas as $pasta) {
        $ficheiro = CAMINHO_RAIZ . '/' . $pasta . '/' . $classe . '.php';
        if (is_file($ficheiro)) {
            require_once $ficheiro;
            return;
        }
    }
});

$rota = trim((string) ($_GET['url'] ?? ''), '/');
if ($rota === '') {
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $caminho = (string) (parse_url($uri, PHP_URL_PATH) ?? '/');

    if ($caminho !== '' && $caminho !== '/') {
        $basePath = '';
        if (BASE_URL !== '' && preg_match('#^https?://#i', BASE_URL) !== 1) {
            $basePath = '/' . trim((string) BASE_URL, '/');
            if ($basePath === '//') {
                $basePath = '/';
            }
        } else {
            $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? '/index.php'));
            $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');
            if ($scriptDir !== '' && $scriptDir !== '.') {
                $basePath = $scriptDir;
            }
        }

        if ($basePath !== '' && $basePath !== '/' && str_starts_with($caminho, $basePath . '/')) {
            $caminho = substr($caminho, strlen($basePath));
        } elseif ($basePath !== '' && $basePath !== '/' && $caminho === $basePath) {
            $caminho = '/';
        }

        $caminho = trim($caminho, '/');
        if ($caminho !== '' && $caminho !== 'index.php') {
            $rota = $caminho;
        }
    }
}
$segmentos = $rota === '' ? [] : explode('/', $rota);
$modulo = strtolower($segmentos[0] ?? '');
$rotasRecuperacao = ['recuperar', 'redefinir-senha', 'reset', 'password-reset', 'password_resets'];

if (in_array($modulo, $rotasRecuperacao, true)) {
    definir_flash(
        'erro_login',
        'Dirija-se à direcção da escola com o seu cartão de estudante para redefinir a senha.'
    );
    redirecionar('login');
}

if ($modulo === 'curso') {
    $slugCurso = strtolower(trim((string) ($_GET['curso'] ?? '')));
    $bloqueados = ['contabilidade', 'informatica', 'recursos-humanos'];
    if (in_array($slugCurso, $bloqueados, true)) {
        http_response_code(404);
        require CAMINHO_RAIZ . '/views/404.php';
        exit;
    }
}

if (!in_array($modulo, ['api', 'download'], true)) {
    ob_start('fascal_injetar_head_global');
}

// Registe novos controladores aqui para expor rotas dinamicas.
$mapaControladores = [
    'login' => 'LoginController',
    'matricula' => 'MatriculaController',
    'painel' => 'DashboardController',
    'aluno-painel' => 'AlunoController',
    'professor-painel' => 'ProfessorController',
    'secretaria-painel' => 'SecretariaController',
    'pagamento' => 'PagamentoController',
    'documento' => 'DocumentoController',
    'notificacao' => 'NotificacaoController',
    'documentos' => 'DocumentosController',
    'download' => 'DownloadController',
    'administracao' => 'AdministracaoController',
    'contacto' => 'ContactoController',
    'recrutamento' => 'RecrutamentoController',
    'configuracoes' => 'ConfiguracaoController',
    'api' => 'ApiController'
];

if ($rota === '') {
    require CAMINHO_RAIZ . '/views/index.php';
    exit;
}

if ($modulo === 'comprovante') {
    $controlador = 'MatriculaController';
    $metodo = 'comprovante';
    $parametros = [];
} elseif (isset($mapaControladores[$modulo])) {
    $controlador = $mapaControladores[$modulo];
    $metodoBruto = $segmentos[1] ?? 'index';
    $metodo = str_replace('-', '_', $metodoBruto);
    $parametros = array_slice($segmentos, 2);
} else {
    $viewEstatica = CAMINHO_RAIZ . '/views/' . $modulo . '.php';
    if ($modulo !== '' && is_file($viewEstatica)) {
        // Qualquer ficheiro dentro de views/ pode ser servido diretamente por rota.
        require $viewEstatica;
        exit;
    }

    http_response_code(404);
    require CAMINHO_RAIZ . '/views/404.php';
    exit;
}

if (!class_exists($controlador)) {
    http_response_code(500);
    echo 'Controlador nao encontrado.';
    exit;
}

$instancia = new $controlador();

if (!method_exists($instancia, $metodo)) {
    http_response_code(404);
    require CAMINHO_RAIZ . '/views/404.php';
    exit;
}

call_user_func_array([$instancia, $metodo], $parametros);
