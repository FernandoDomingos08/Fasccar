<?php
$icone = imagem_url('icones');
$slider = imagem_url('slider');
$cursosImg = imagem_url('cursos');
$atividadesImg = imagem_url('atividades');
$instituicaoImg = imagem_url('instituicao');
$placeholderImagem = base_url('assets/Placeholder de imagem.jpeg');
$cursosPublicados = [];
$atividadesPublicadas = [];
$cursosConfigurados = [];

$caminhoCursos = CAMINHO_RAIZ . '/config/cursos.php';
if (is_file($caminhoCursos)) {
  $cursosConfigurados = require $caminhoCursos;
  if (!is_array($cursosConfigurados)) {
    $cursosConfigurados = [];
  }
}

if (class_exists('PainelOperacionalModel')) {
  try {
    $siteModel = new PainelOperacionalModel();
    $cursosPublicados = $siteModel->listarCursosPublicadosIndex(8);
    $atividadesPublicadas = $siteModel->listarAtividadesPublicadasIndex(8);
  } catch (Throwable $erro) {
    $cursosPublicados = [];
    $atividadesPublicadas = [];
  }
}

$iconePadraoCurso = 'fa-solid fa-graduation-cap';
$cursosFallback = [];

if (!empty($cursosConfigurados)) {
  foreach ($cursosConfigurados as $cursoId => $cursoInfo) {
    $cursoId = (string) $cursoId;
    $cursosFallback[] = [
      'id' => $cursoId,
      'nome' => (string) ($cursoInfo['nome'] ?? 'Curso'),
      'descricao' => (string) ($cursoInfo['descricao'] ?? 'Curso disponível na instituicao.'),
      'cor' => (string) ($cursoInfo['cor'] ?? '#0056a3'),
      'imagem' => imagem_url((string) ($cursoInfo['imagem'] ?? 'slider/1.jpg')),
      'icone' => (string) ($cursoInfo['icone'] ?? $iconePadraoCurso),
      'nivel' => (string) ($cursoInfo['nivel'] ?? 'Ensino Médio'),
      'duracao' => (string) ($cursoInfo['duracao'] ?? '4 anos'),
      'vagas' => (string) ($cursoInfo['vagas'] ?? '40 vagas'),
      'turnos' => (string) ($cursoInfo['turnos'] ?? 'Manhã, Tarde'),
      'coordenador' => (string) ($cursoInfo['coordenador'] ?? ''),
      'status' => (string) ($cursoInfo['status'] ?? 'Inscrições abertas'),
      'url' => base_url('matricula')
    ];
  }
} else {
  $cursosFallback = [
    [
      'id' => 'informatica',
      'nome' => 'Informática',
      'descricao' => 'Curso completo de informatica com foco em programacao, design grafico e manutencao de computadores.',
      'cor' => '#0056a3',
      'imagem' => imagem_url('cursos/20250609_130810.jpg'),
      'icone' => 'fa-solid fa-laptop-code',
      'nivel' => 'Ensino Médio',
      'duracao' => '4 anos',
      'vagas' => '40 vagas',
      'turnos' => 'Manhã, Tarde',
      'coordenador' => '',
      'status' => 'Inscrições abertas',
      'url' => base_url('matricula')
    ],
    [
      'id' => 'rh',
      'nome' => 'Recursos Humanos',
      'descricao' => 'Formacao em gestao de recursos humanos, recrutamento e desenvolvimento organizacional.',
      'cor' => '#1e78d2',
      'imagem' => imagem_url('cursos/20250609_140407.jpg'),
      'icone' => 'fa-solid fa-people-group',
      'nivel' => 'Ensino Médio',
      'duracao' => '4 anos',
      'vagas' => '35 vagas',
      'turnos' => 'Manhã, Tarde',
      'coordenador' => '',
      'status' => 'Inscrições abertas',
      'url' => base_url('matricula')
    ],
    [
      'id' => 'ciencias',
      'nome' => 'Ciências Físicas e Biológicas',
      'descricao' => 'Estudo aprofundado de fisica, quimica e biologia com laboratorios modernos.',
      'cor' => '#4a9ce8',
      'imagem' => imagem_url('cursos/Curso-ciencias Fisicas e Biologicas.jpg'),
      'icone' => 'fa-solid fa-flask-vial',
      'nivel' => 'Ensino Médio',
      'duracao' => '4 anos',
      'vagas' => '30 vagas',
      'turnos' => 'Manhã, Tarde',
      'coordenador' => '',
      'status' => 'Inscrições abertas',
      'url' => base_url('matricula')
    ],
    [
      'id' => 'contabilidade',
      'nome' => 'Contabilidade',
      'descricao' => 'Curso de contabilidade e gestao financeira para formacao de profissionais qualificados.',
      'cor' => '#ffc107',
      'imagem' => imagem_url('cursos/20250609_151819.jpg'),
      'icone' => 'fa-solid fa-scale-balanced',
      'nivel' => 'Ensino Médio',
      'duracao' => '4 anos',
      'vagas' => '40 vagas',
      'turnos' => 'Manhã, Tarde',
      'coordenador' => '',
      'status' => 'Inscrições abertas',
      'url' => base_url('matricula')
    ]
  ];
}

$coresCursos = ['#0056a3', '#1e78d2', '#4a9ce8', '#ffc107'];
$imagensCursos = [
  imagem_url('cursos/20250609_130810.jpg'),
  imagem_url('cursos/20250609_140407.jpg'),
  imagem_url('cursos/Curso-ciencias Fisicas e Biologicas.jpg'),
  imagem_url('cursos/20250609_151819.jpg')
];
$iconesCursos = ['fa-solid fa-laptop-code', 'fa-solid fa-people-group', 'fa-solid fa-flask-vial', 'fa-solid fa-scale-balanced'];

$cursosRender = [];
if (!empty($cursosPublicados)) {
  foreach ($cursosPublicados as $indice => $curso) {
    $nomeCurso = (string) ($curso['nome'] ?? ('Curso ' . ($indice + 1)));
    $idCurso = strtolower((string) preg_replace('/[^a-z0-9]+/i', '-', $nomeCurso));
    $idCurso = trim($idCurso, '-');
    if ($idCurso === '') {
      $idCurso = 'curso-' . ($indice + 1);
    }
    $configCurso = $cursosConfigurados[$idCurso] ?? null;
    $cursosRender[] = [
      'id' => $idCurso,
      'nome' => $nomeCurso,
      'descricao' => (string) ($curso['descricao'] ?? 'Curso em atualizacao.'),
      'cor' => (string) ($curso['cor'] ?? $coresCursos[$indice % count($coresCursos)]),
      'imagem' => !empty($curso['imagem_path']) ? base_url((string) $curso['imagem_path']) : $imagensCursos[$indice % count($imagensCursos)],
      'icone' => (string) ($configCurso['icone'] ?? $iconesCursos[$indice % count($iconesCursos)]),
      'nivel' => (string) ($configCurso['nivel'] ?? 'Ensino Médio'),
      'duracao' => (string) ($configCurso['duracao'] ?? '4 anos'),
      'vagas' => (string) ($configCurso['vagas'] ?? '40 vagas'),
      'turnos' => (string) ($configCurso['turnos'] ?? 'Manhã, Tarde'),
      'coordenador' => (string) ($configCurso['coordenador'] ?? ''),
      'status' => (string) ($configCurso['status'] ?? 'Inscrições abertas'),
      'url' => base_url('matricula')
    ];
  }
} else {
  $cursosRender = $cursosFallback;
}

foreach ($cursosRender as $indiceCurso => $cursoRender) {
  $nomeCurso = mb_strtolower((string) ($cursoRender['nome'] ?? ''), 'UTF-8');
  if (str_contains($nomeCurso, 'finanças') || str_contains($nomeCurso, 'financas')) {
    $cursosRender[$indiceCurso]['nome'] = 'Ciências Físicas e Biológicas';
    $nomeCurso = 'ciências físicas e biológicas';
  }

  if (str_contains($nomeCurso, 'contabilidade')) {
    $cursosRender[$indiceCurso]['imagem'] = base_url('assets/imagens/cursos/Curso-contabilidade.jpg');
    $cursosRender[$indiceCurso]['url'] = base_url('matricula');
  } elseif (str_contains($nomeCurso, 'inform')) {
    $cursosRender[$indiceCurso]['imagem'] = base_url('assets/imagens/cursos/Curso-Informática_.webp');
    $cursosRender[$indiceCurso]['url'] = base_url('matricula');
  } elseif (str_contains($nomeCurso, 'recursos humanos') || $nomeCurso === 'rh') {
    $cursosRender[$indiceCurso]['imagem'] = base_url('assets/imagens/cursos/Curso-Recursos-humanos.webp');
    $cursosRender[$indiceCurso]['url'] = base_url('matricula');
  } elseif (
    str_contains($nomeCurso, 'ciências')
    || str_contains($nomeCurso, 'ciencias')
    || str_contains($nomeCurso, 'biológicas')
    || str_contains($nomeCurso, 'biologicas')
    || str_contains($nomeCurso, 'físicas')
    || str_contains($nomeCurso, 'fisicas')
  ) {
    $cursosRender[$indiceCurso]['imagem'] = base_url('assets/imagens/cursos/Curso-ciencias Fisicas e Biologicas.jpg');
    $cursosRender[$indiceCurso]['url'] = base_url('matricula');
  } else {
    $cursosRender[$indiceCurso]['imagem'] = $placeholderImagem;
    $cursosRender[$indiceCurso]['url'] = base_url('matricula');
  }
}

$descricoesCursosAtualizadas = [
  'informatica' => "Formação em programação, redes e segurança de sistemas.\nPrepara para o mercado tecnológico com ênfase em resolução de problemas e inovação.\nSaída profissional: analista de sistemas, developer, administrador de redes.",
  'rh' => "Gestão de talentos, recrutamento e legislação laboral.\nDesenvolvimento de estratégias para clima organizacional e folha de pagamento.\nHabilita para carreiras em departamento pessoal e consultoria de RH.",
  'recursos humanos' => "Gestão de talentos, recrutamento e legislação laboral.\nDesenvolvimento de estratégias para clima organizacional e folha de pagamento.\nHabilita para carreiras em departamento pessoal e consultoria de RH.",
  'contabilidade' => "Registo e análise de operações financeiras, fiscalidade e relatórios contabilísticos.\nDomínio de normas internacionais e ferramentas de gestão empresarial.\nPermite atuar como contabilista, auditor ou consultor financeiro.",
  'ciencias fisicas e biologicas' => "Estudo integrado da matéria, energia e dos seres vivos.\nExperimentação laboratorial e análise de fenómenos naturais.\nPrepara para investigação, docência ou áreas ambientais e de saúde.",
  'ciências físicas e biológicas' => "Estudo integrado da matéria, energia e dos seres vivos.\nExperimentação laboratorial e análise de fenómenos naturais.\nPrepara para investigação, docência ou áreas ambientais e de saúde.",
];

foreach ($cursosRender as $indiceCurso => $cursoRender) {
  $nomeCursoOriginal = (string) ($cursoRender['nome'] ?? '');
  $nomeCursoNormalizado = mb_strtolower($nomeCursoOriginal, 'UTF-8');
  $descricaoAtualizada = '';

  foreach ($descricoesCursosAtualizadas as $chaveCurso => $descricaoCurso) {
    if (str_contains($nomeCursoNormalizado, $chaveCurso)) {
      $descricaoAtualizada = $descricaoCurso;
      break;
    }
  }

  if ($descricaoAtualizada !== '') {
    $cursosRender[$indiceCurso]['descricao'] = $descricaoAtualizada;
  }
}

$categoriasAtividades = [
  'esportes' => ['titulo' => 'Esportes', 'icone' => 'person-running'],
  'eventos' => ['titulo' => 'Eventos', 'icone' => 'calendar-days'],
  'instalacoes' => ['titulo' => 'Instalações', 'icone' => 'building'],
  'laboratorios' => ['titulo' => 'Laboratórios', 'icone' => 'flask']
];

$atividadesFallback = [
  'esportes' => [
    ['tema' => 'Futebol', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('atividades/pexels-artempodrez-5716037.jpg')],
    ['tema' => 'Basquete', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('atividades/pexels-divinetechygirl-1181467.jpg')],
    ['tema' => 'Natacao', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('atividades/pexels-kindelmedia-8688527.jpg')],
    ['tema' => 'Atletismo', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('atividades/alferio-njau-ThwZscvTOOo-unsplash.jpg')]
  ],
  'eventos' => [
    ['tema' => 'Feira de Ciências', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('atividades/isabela-kronemberger-TW-keQcZWLw-unsplash.jpg')],
    ['tema' => 'Semana Cultural', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('atividades/kimberly-farmer-lUaaKCUANVI-unsplash.jpg')],
    ['tema' => 'Formaturas', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('atividades/vlad-tchompalov-nKNrOZ5MXZY-unsplash.jpg')],
    ['tema' => 'Palestras', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('atividades/vlad-tchompalov-nKNrOZ5M_XZY-unsplash.jpg')]
  ],
  'instalacoes' => [
    ['tema' => 'Biblioteca', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('slider/1.jpg')],
    ['tema' => 'Quadra Poliesportiva', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('slider/2.jpg')],
    ['tema' => 'Anfiteatro', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('slider/3.jpg')],
    ['tema' => 'Cantina', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('slider/4.jpg')]
  ],
  'laboratorios' => [
    ['tema' => 'Informática', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('slider/5.jpg')],
    ['tema' => 'Quimica', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('slider/6.jpg')],
    ['tema' => 'Biologia', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('cursos/20250609_130810.jpg')],
    ['tema' => 'Fisica', 'descricao' => 'O conteúdo completo será adicionado muito em breve.', 'imagem' => imagem_url('cursos/20250609_140407.jpg')]
  ]
];

$atividadesPorCategoria = [
  'esportes' => [],
  'eventos' => [],
  'instalacoes' => [],
  'laboratorios' => []
];

if (!empty($atividadesPublicadas)) {
  foreach ($atividadesPublicadas as $indice => $atividade) {
    $categoria = strtolower((string) ($atividade['categoria'] ?? 'eventos'));
    if (!isset($atividadesPorCategoria[$categoria])) {
      $categoria = 'eventos';
    }
    $atividadesPorCategoria[$categoria][] = [
      'tema' => (string) ($atividade['tema'] ?? ('Atividade ' . ($indice + 1))),
      'descricao' => (string) ($atividade['descricao'] ?? 'Atividade extracurricular publicada pela secretaria.'),
      'imagem' => $placeholderImagem,
      'preco' => (float) ($atividade['preco'] ?? 0),
      'data' => (string) ($atividade['data_atividade'] ?? '')
    ];
  }
}

foreach ($atividadesPorCategoria as $categoria => $lista) {
  if (empty($lista)) {
    $atividadesPorCategoria[$categoria] = $atividadesFallback[$categoria];
  }
}

foreach ($atividadesPorCategoria as $categoria => $lista) {
  foreach ($lista as $indice => $atividade) {
    $atividade['imagem'] = $placeholderImagem;
    if (!array_key_exists('preco', $atividade)) {
      $atividade['preco'] = 15000 + ($indice * 500);
    }
    if (empty($atividade['data'])) {
      $atividade['data'] = date('Y-m-d', strtotime('+' . $indice . ' days'));
    }
    $atividadesPorCategoria[$categoria][$indice] = $atividade;
  }
}
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>INSTITUTO POLITÉCNICO PRIVADO MUNDO NOVO II</title>
    <link rel="stylesheet" href="<?= base_url('assets/css/paginas/index.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/modo-escuro.css') ?>" />
  <link rel="icon" href="<?= $icone ?>/logo.png">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" />
</head>
<body>
  <header class="cabecalho">
    <div class="cabecalho-superior">
      <div class="lado-esquerdo">
        <button class="botao botao-ligar icone-acao" id="botao-ligar" title="Ligar Agora" aria-label="Ligar Agora">
          <i class="fa-solid fa-phone" aria-hidden="true"></i>
          <span class="texto-acao">Ligar</span>
        </button>
        <button class="botao botao-matricular icone-acao" id="botao-matricular" data-url-matricula="<?= base_url('matricula') ?>" title="Matrícula Online" aria-label="Matrícula Online">
          <i class="fa-solid fa-user-plus" aria-hidden="true"></i>
          <span class="texto-acao">Matrícula</span>
        </button>
        <button class="botao botao-entrar icone-acao" id="botao-entrar" data-url-login="<?= base_url('login') ?>" title="Entrar" aria-label="Entrar">
          <i class="fa-solid fa-right-to-bracket" aria-hidden="true"></i>
          <span class="texto-acao">Entrar</span>
        </button>
      </div>

      <div class="centro">
        <div class="logo-titulo">
          <div class="logo-placeholder">
            <img id="logo-encerramento" data-url-encerramento="<?= base_url('encerramento') ?>" src="<?= $icone ?>/logo.png" alt="Logo do Instituto Politécnico Privado Mundo Novo II" style="width: 55px; height: 55px;"/>
          </div>
          <div class="titulo-colegio" >
            <h1 style="font-size: 14pt;">INSTITUTO POLITÉCNICO PRIVADO MUNDO NOVO II</h1>
            <p class="slogan">Educar para um mundo melhor</p>
          </div>
        </div>
      </div>

      <div class="lado-direito">
        <button class="icone botao-email" id="botao-email" title="Enviar email">
          <i class="fa-regular fa-envelope" aria-hidden="true" ></i>
        </button>
        <button class="icone botao-whatsapp" id="botao-whatsapp" title="WhatsApp">
          <i class="fa-brands fa-whatsapp" aria-hidden="true"></i>
        </button>
        <button class="icone botao-modo-escuro" id="botao-modo-escuro" title="Modo Escuro">
          <i class="fa-solid fa-moon" aria-hidden="true"></i>
        </button>
      </div>
    </div>

    <nav class="cabecalho-inferior">
      <button class="menu-hamburger" id="menu-hamburger" type="button" aria-label="Abrir menu">
        <span></span><span></span><span></span>
      </button>
      <ul class="menu" id="menu-principal">
        <li><a href="#inicio" class="ativo">Página Inicial</a></li>
        <li><a href="#cursos">Cursos</a></li>
        <li><a href="<?= base_url('login') ?>">Área Estudantil</a></li>
        <li class="item-cursos">
          <a href="#cursos" class="link-cursos">
            Cursos <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </a>
          <div class="submenu">
            <div class="cursos-lista" id="lista-cursos-menu">
              <?php foreach ($cursosRender as $cursoMenu): ?>
                <a href="#<?= escapar((string) ($cursoMenu['id'] ?? 'curso')) ?>" class="curso-item-menu">
                  <i class="<?= escapar((string) ($cursoMenu['icone'] ?? $iconePadraoCurso)) ?>" aria-hidden="true"></i>
                  <h3 class="curso-titulo"><?= escapar((string) ($cursoMenu['nome'] ?? 'Curso')) ?></h3>
                </a>
              <?php endforeach; ?>
            </div>
            <a href="#cursos" class="botao-ver-mais">
              Ver Mais Cursos <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
            </a>
          </div>
        </li>
        <li><a href="#vida">Vida Estudantil</a></li>
        <li><a href="#servicos">Serviços</a></li>
        <li><a href="<?= base_url('recrutamento') ?>">Recrutamento</a></li>
      </ul>
    </nav>
  </header>

  <section class="slider" id="inicio">
    <div class="slides-container">
      <div class="slide ativo" data-indice="0">
        <div class="imagem-slide" style="background-image:linear-gradient(rgba(0,87,163,0.093),rgba(0,87,163,0.126)),url('<?= $slider ?>/1.jpg');">
          <div class="overlay"></div>
        </div>
        <div class="conteudo-slide">
          <h2>Infraestrutura de Primeira</h2>
          <p>Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
          <div class="botoes-slide">
            <a class="botao-slide-primario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Conheça Nossas Instalações</a>
            <a class="botao-slide-secundario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-vr-cardboard" aria-hidden="true"></i> Faça um Tour Virtual</a>
          </div>
        </div>
      </div>

      <div class="slide" data-indice="1">
        <div class="imagem-slide" style="background-image:linear-gradient(rgba(0,87,163,0.093),rgba(0,87,163,0.126)),url('<?= $slider ?>/2.jpg');">
          <div class="overlay"></div>
        </div>
        <div class="conteudo-slide">
          <h2>Laboratórios Modernos</h2>
          <p>Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
          <div class="botoes-slide">
            <a class="botao-slide-primario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Conheça Nossas Instalações</a>
            <a class="botao-slide-secundario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-vr-cardboard" aria-hidden="true"></i> Faça um Tour Virtual</a>
          </div>
        </div>
      </div>

      <div class="slide" data-indice="2">
        <div class="imagem-slide" style="background-image:linear-gradient(rgba(0,87,163,0.093),rgba(0,87,163,0.126)),url('<?= $slider ?>/3.jpg');">
          <div class="overlay"></div>
        </div>
        <div class="conteudo-slide">
          <h2>Quadras Esportivas</h2>
          <p>Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
          <div class="botoes-slide">
            <a class="botao-slide-primario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Conheça Nossas Instalações</a>
            <a class="botao-slide-secundario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-vr-cardboard" aria-hidden="true"></i> Faça um Tour Virtual</a>
          </div>
        </div>
      </div>

      <div class="slide" data-indice="3">
        <div class="imagem-slide" style="background-image:linear-gradient(rgba(0,87,163,0.093),rgba(0,87,163,0.126)),url('<?= $slider ?>/4.jpg');">
          <div class="overlay"></div>
        </div>
        <div class="conteudo-slide">
          <h2>Biblioteca Completa</h2>
          <p>Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
          <div class="botoes-slide">
            <a class="botao-slide-primario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Conheça Nossas Instalações</a>
            <a class="botao-slide-secundario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-vr-cardboard" aria-hidden="true"></i> Faça um Tour Virtual</a>
          </div>
        </div>
      </div>

      <div class="slide" data-indice="4">
        <div class="imagem-slide" style="background-image:linear-gradient(rgba(0,87,163,0.093),rgba(0,87,163,0.126)),url('<?= escapar($placeholderImagem) ?>');">
          <div class="overlay"></div>
        </div>
        <div class="conteudo-slide">
          <h2>Atividades Extracurriculares</h2>
          <p>Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
          <div class="botoes-slide">
            <a class="botao-slide-primario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Conheça Nossas Instalações</a>
            <a class="botao-slide-secundario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-vr-cardboard" aria-hidden="true"></i> Faça um Tour Virtual</a>
          </div>
        </div>
      </div>

      <div class="slide" data-indice="5">
        <div class="imagem-slide" style="background-image:linear-gradient(rgba(0,87,163,0.093),rgba(0,87,163,0.126)),url('<?= $slider ?>/6.jpg');">
          <div class="overlay"></div>
        </div>
        <div class="conteudo-slide">
          <h2>Suporte Integral</h2>
          <p>Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
          <div class="botoes-slide">
            <a class="botao-slide-primario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-chalkboard-user" aria-hidden="true"></i> Conheça Nossas Instalações</a>
            <a class="botao-slide-secundario" href="<?= base_url('tour-virtual') ?>"><i class="fa-solid fa-vr-cardboard" aria-hidden="true"></i> Faça um Tour Virtual</a>
          </div>
        </div>
      </div>
    </div>

    <div class="indicadores">
      <button class="indicador ativo" data-indice="0"></button>
      <button class="indicador" data-indice="1"></button>
      <button class="indicador" data-indice="2"></button>
      <button class="indicador" data-indice="3"></button>
      <button class="indicador" data-indice="4"></button>
      <button class="indicador" data-indice="5"></button>
    </div>
  </section>

  <section class="seccao-cursos" id="cursos">
    <div class="container">
      <h2 class="titulo-seccao">Nossos Cursos</h2>
      <div class="linha-decorativa"></div>
      <p class="descricao">Formação técnica estruturada por classes e foco prático para inserção no mercado de trabalho.</p>
      <div class="cursos-grid" id="cursos-grid">
        <?php foreach ($cursosRender as $cursoRender): ?>
          <article class="curso-card" id="<?= escapar((string) ($cursoRender['id'] ?? 'curso')) ?>" style="--cor-curso: <?= escapar((string) ($cursoRender['cor'] ?? '#0056a3')) ?>;">
            <div class="imagem-curso" style="background-image:linear-gradient(rgba(0,0,0,0.3),rgba(0,0,0,0.3)),url('<?= escapar((string) ($cursoRender['imagem'] ?? imagem_url('slider/1.jpg'))) ?>');background-color:<?= escapar((string) ($cursoRender['cor'] ?? '#0056a3')) ?>;">
              <div class="icone-curso" style="background-color:<?= escapar((string) ($cursoRender['cor'] ?? '#0056a3')) ?>">
                <i class="<?= escapar((string) ($cursoRender['icone'] ?? $iconePadraoCurso)) ?>" aria-hidden="true"></i>
              </div>
              <span class="status-curso"><?= escapar((string) ($cursoRender['status'] ?? 'Inscrições abertas')) ?></span>
            </div>
            <div class="conteudo-curso">
              <h3 style="color:<?= escapar((string) ($cursoRender['cor'] ?? '#0056a3')) ?>"><?= escapar((string) ($cursoRender['nome'] ?? 'Curso')) ?></h3>
              <p><?= nl2br(escapar((string) ($cursoRender['descricao'] ?? 'Curso disponível na instituicao.'))) ?></p>
              <ul class="curso-info">
                <li><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i><?= escapar((string) ($cursoRender['nivel'] ?? 'Ensino Médio')) ?></li>
                <li><i class="fa-regular fa-clock" aria-hidden="true"></i>Duração: <?= escapar((string) ($cursoRender['duracao'] ?? '4 anos')) ?></li>
                <li><i class="fa-solid fa-users" aria-hidden="true"></i><?= escapar((string) ($cursoRender['vagas'] ?? '40 vagas')) ?></li>
              </ul>
              <div class="acoes-curso">
                <a class="botao-curso botao-curso-primario" href="<?= base_url('matricula') ?>">Inscrever-se</a>
                <a class="botao-curso botao-curso-secundario" href="<?= escapar((string) ($cursoRender['url'] ?? base_url('matricula'))) ?>">
                  Saiba mais <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
                </a>
              </div>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="seccao vida-estudantil" id="vida">
    <div class="container">
      <h2 class="titulo-seccao">Vida Estudantil</h2>
      <div class="linha-decorativa"></div>
      <p class="descricao">A vida estudantil combina aprendizagem, convivência e oportunidades. Veja algumas experiências e serviços que fazem parte do dia a dia no colégio.</p>
      <div class="vida-grid">
        <div class="vida-card">
          <i class="fa-solid fa-people-group" aria-hidden="true"></i>
          <h3>Clubes e Conexoes</h3>
          <p>Espacos de convivência, clubes tematicos e projetos colaborativos para fortalecer a comunidade escolar.</p>
        </div>
        <div class="vida-card">
          <i class="fa-solid fa-futbol" aria-hidden="true"></i>
          <h3>Desporto e Bem-estar</h3>
          <p>Programas esportivos, torneios internos e acompanhamento para promover equilibrio e saude.</p>
        </div>
        <div class="vida-card">
          <i class="fa-solid fa-laptop" aria-hidden="true"></i>
          <h3>Laboratórios e Tecnologia</h3>
          <p>Acesso a laboratorios modernos, recursos digitais e apoio para projetos academicos.</p>
        </div>
        <div class="vida-card">
          <i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i>
          <h3>Apoio Pedagogico</h3>
          <p>Mentorias, orientacao e acompanhamento individual para garantir progresso consistente.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="seccao servicos" id="servicos">
    <div class="container">
      <h2 class="titulo-seccao">Nossos Serviços</h2>
      <div class="linha-decorativa"></div>
      <div class="servicos-container">
        <div class="servico-item">
          <div class="servico-icone-container"><div class="servico-icone" style="background-color:#0056a3"><i class="fa-solid fa-bus" aria-hidden="true"></i></div></div>
          <h3 class="servico-titulo">Transporte Escolar</h3>
          <p class="servico-descricao">Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
        </div>
        <div class="servico-item">
          <div class="servico-icone-container"><div class="servico-icone" style="background-color:#1e78d2"><i class="fa-solid fa-utensils" aria-hidden="true"></i></div></div>
          <h3 class="servico-titulo">Cantina</h3>
          <p class="servico-descricao">Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
        </div>
        <div class="servico-item">
          <div class="servico-icone-container"><div class="servico-icone" style="background-color:#4a9ce8"><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i></div></div>
          <h3 class="servico-titulo">Atendimento Médico</h3>
          <p class="servico-descricao">Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
        </div>
      </div>
    </div>
  </section>

  <section class="seccao atividades" id="atividades">
    <div class="container">
      <h2 class="titulo-seccao">Atividades Extracurriculares</h2>
      <div class="linha-decorativa"></div>
      <p class="descricao-atividades">Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p>
      <div class="filtro-atividades">
        <?php $categoriaIndice = 0; ?>
        <?php foreach ($categoriasAtividades as $categoriaId => $categoriaInfo): ?>
          <button class="botao-filtro <?= $categoriaIndice === 0 ? 'ativo' : '' ?>" data-categoria="<?= escapar($categoriaId) ?>">
            <i class="fa-solid fa-<?= escapar((string) $categoriaInfo['icone']) ?>" aria-hidden="true"></i>
            <?= escapar((string) $categoriaInfo['titulo']) ?>
            <i class="fa-solid fa-chevron-down" aria-hidden="true"></i>
          </button>
          <?php $categoriaIndice++; ?>
        <?php endforeach; ?>
      </div>

      <div class="galeria-atividades">
        <?php $categoriaIndice = 0; ?>
        <?php foreach ($atividadesPorCategoria as $categoriaId => $listaAtividades): ?>
          <div class="categoria-atividade <?= $categoriaIndice === 0 ? 'ativa' : '' ?>" id="<?= escapar($categoriaId) ?>">
            <div class="grid-atividades">
              <?php foreach ($listaAtividades as $atividadeItem): ?>
                <div class="atividade-item">
                  <img src="<?= escapar((string) ($atividadeItem['imagem'] ?? imagem_url('slider/1.jpg'))) ?>" alt="<?= escapar((string) ($atividadeItem['tema'] ?? 'Atividade')) ?>" class="atividade-img" />
                  <div class="atividade-info">
                    <h3><?= escapar((string) ($atividadeItem['tema'] ?? 'Atividade')) ?></h3>
                    <p><?= escapar((string) ($atividadeItem['descricao'] ?? '')) ?></p>
                    <?php
                      $precoAtividade = (float) ($atividadeItem['preco'] ?? 0);
                      $dataAtividade = trim((string) ($atividadeItem['data'] ?? ''));
                    ?>
                    <div class="atividade-meta">
                      <span><i class="fa-solid fa-money-bill-wave" aria-hidden="true"></i> <?= number_format($precoAtividade, 2, ',', '.') ?> Kz</span>
                      <span><i class="fa-regular fa-calendar" aria-hidden="true"></i> <?= $dataAtividade !== '' ? escapar(formatar_data($dataAtividade, 'd/m/Y')) : 'A definir' ?></span>
                    </div>
                    <div class="atividade-acoes">
                      <a class="botao-detalhes-atividade" href="<?= escapar(base_url('login')) ?>">Ver mais detalhes</a>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
          <?php $categoriaIndice++; ?>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <section class="seccao perguntas-frequentes" id="perguntas-frequentes">
    <div class="container">
      <div class="cabecalho-perguntas">
        <h2 class="titulo-seccao">Perguntas Frequentes</h2>
        <div class="linha-decorativa"></div>
        <p class="subtitulo-perguntas">Tire as suas dúvidas sobre o Instituto Politécnico Privado Mundo Novo II</p>
      </div>

      <div class="grid-perguntas">
        <div class="coluna-perguntas">
          <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="pergunta-item">
              <div class="pergunta-cabecalho">
                <h3>Como posso matricular meu filho no colégio?</h3>
                <span class="icone-pergunta"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
              </div>
              <div class="pergunta-conteudo"><p>Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p></div>
            </div>
          <?php endfor; ?>
        </div>

        <div class="coluna-perguntas">
          <?php for ($i = 0; $i < 4; $i++): ?>
            <div class="pergunta-item">
              <div class="pergunta-cabecalho">
                <h3>Quais documentos são exigidos para matrícula?</h3>
                <span class="icone-pergunta"><i class="fa-solid fa-plus" aria-hidden="true"></i></span>
              </div>
              <div class="pergunta-conteudo"><p>Conteúdo atualizado com foco pedagógico, atividades práticas e acompanhamento contínuo dos alunos.</p></div>
            </div>
          <?php endfor; ?>
        </div>
      </div>

      <div class="contacto-adicional">
        <p>Ainda tem dúvidas? Entre em contacto connosco!</p>
        <div class="botoes-contacto">
          <button class="botao botao-whatsapp-contato" id="botao-whatsapp-perguntas"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> WhatsApp</button>
          <button class="botao botao-email-contato" id="botao-email-perguntas"><i class="fa-regular fa-envelope" aria-hidden="true"></i> Email</button>
        </div>
      </div>
    </div>
  </section>

  <section class="seccao contacto-secretaria" id="contacto-secretaria">
    <div class="container">
      <div class="contacto-secretaria-cartao">
        <h2 class="titulo-seccao">Enviar Mensagem para a Secretária</h2>
        <div class="linha-decorativa"></div>
        <p class="descricao">
          Preencha o formulário e a mensagem será enviada diretamente para o painel da secretária.
        </p>

        <form id="formulario-secretaria" class="formulario-secretaria" action="<?= base_url('contacto/enviar') ?>" method="post" novalidate>
          <?= csrf_field() ?>
          <div class="linha-formulario">
            <div class="grupo-campo">
              <label for="nome_contacto">Nome</label>
              <input type="text" id="nome_contacto" name="nome" maxlength="120" required />
            </div>
            <div class="grupo-campo">
              <label for="email_contacto">Email</label>
              <input type="email" id="email_contacto" name="email" maxlength="120" required />
            </div>
          </div>

          <div class="grupo-campo">
            <label for="assunto_contacto">Assunto</label>
            <input type="text" id="assunto_contacto" name="assunto" maxlength="150" required />
          </div>

          <div class="grupo-campo">
            <label for="mensagem_contacto">Mensagem</label>
            <textarea id="mensagem_contacto" name="mensagem" rows="6" maxlength="3000" required></textarea>
          </div>

          <div class="acoes-formulario">
            <button type="submit" class="botao botao-enviar-secretaria">
              <i class="fa-solid fa-paper-plane" aria-hidden="true"></i>
              Enviar Mensagem
            </button>
          </div>

          <p id="mensagem-formulario-secretaria" class="mensagem-formulario" aria-live="polite"></p>
        </form>
      </div>
    </div>
  </section>

  <footer class="footer" id="mapa">
    <div class="container">
      <div class="footer-grid">
        <div class="footer-col">
          <div class="footer-logo">
            <img src="<?= $icone ?>/logo.png" alt="Logo Instituto Politécnico Privado Mundo Novo II" class="logo-footer" />
            <h2 class="titulo-footer">Instituto Politécnico Privado Mundo Novo II</h2>
          </div>
          <p class="footer-descricao">Uma instituição de ensino comprometida com a excelência educacional e formação integral dos alunos.</p>
          <div class="redes-sociais">
            <a href="#" class="rede-social" title="Facebook"><i class="fa-brands fa-facebook-f" aria-hidden="true"></i></a>
            <a href="#" class="rede-social" title="Instagram"><i class="fa-brands fa-instagram" aria-hidden="true"></i></a>
            <a href="#" class="rede-social" title="YouTube"><i class="fa-brands fa-youtube" aria-hidden="true"></i></a>
            <a href="#" class="rede-social" title="WhatsApp"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i></a>
          </div>
        </div>

        <div class="footer-col">
          <h3 class="titulo-coluna">Links Rápidos</h3>
          <ul class="footer-links">
            <li><a href="<?= base_url('') ?>#inicio">Página Inicial</a></li>
            <li><a href="<?= base_url('') ?>#cursos">Nossos Cursos</a></li>
            <li><a href="<?= base_url('') ?>#atividades">Atividades Extracurriculares</a></li>
            <li><a href="<?= base_url('') ?>#vida">Vida Estudantil</a></li>
            <li><a href="<?= base_url('') ?>#servicos">Nossos Serviços</a></li>
            <li><a href="<?= base_url('') ?>#perguntas-frequentes">Perguntas Frequentes</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h3 class="titulo-coluna">Serviços</h3>
          <ul class="footer-links">
            <li><a href="<?= base_url('tour-virtual') ?>">Transporte Escolar</a></li>
            <li><a href="<?= base_url('login') ?>">Cantina Saudável</a></li>
            <li><a href="<?= base_url('login') ?>">Atendimento Médico</a></li>
            <li><a href="<?= base_url('tour-virtual') ?>?area=biblioteca#tour-interativo">Biblioteca</a></li>
            <li><a href="<?= base_url('tour-virtual') ?>?area=laboratorio#tour-interativo">Laboratórios</a></li>
            <li><a href="<?= base_url('matricula') ?>">Matrículas Online</a></li>
          </ul>
        </div>

        <div class="footer-col">
          <h3 class="titulo-coluna">Contacto</h3>
          <ul class="footer-contatos">
            <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span>Talatona, na Rua Direita da Camama</span></li>
            <li><i class="fa-solid fa-phone" aria-hidden="true"></i><span>+244 921 660 962</span></li>
            <li><i class="fa-regular fa-envelope" aria-hidden="true"></i><span>fernandomateusdomingos08@gmail.com</span></li>
            <li><i class="fa-regular fa-clock" aria-hidden="true"></i><span>Segunda a Sexta: 7h00 - 18h00<br />Sábado: 8h00 - 13h00</span></li>
          </ul>
        </div>
      </div>

      <div class="footer-inferior">
        <div class="copyright">
          <p>&copy; 2025 <strong>Instituto Politécnico Privado Mundo Novo II</strong>. Todos os direitos reservados.</p>
        </div>
        <div class="footer-links-inferior">
          <a href="<?= base_url('tour-virtual') ?>" class="link-inferior">Tour Virtual</a>
          <span class="separador">|</span>
          <a href="<?= base_url('') ?>#perguntas-frequentes" class="link-inferior">Perguntas Frequentes</a>
        </div>
      </div>
    </div>
  </footer>

  <script src="<?= base_url('assets/js/paginas/index.js') ?>"></script>





