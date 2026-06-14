<?php
$icone = imagem_url('icones');
$baseUrl = base_url('');
?>
<!DOCTYPE html>
<html lang="pt-AO">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Encerramento da Defesa | Instituto Politécnico Privado Mundo Novo II</title>
  <link rel="icon" href="<?= $icone ?>/logo.png" />
  <link rel="stylesheet" href="<?= base_url('assets/css/estilo.css') ?>" />
  <link rel="stylesheet" href="<?= base_url('assets/css/paginas/encerramento.css') ?>" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Manrope:wght@300;400;600;700&display=swap" rel="stylesheet" />
</head>
<body class="encerramento" data-base-url="<?= $baseUrl ?>/">
  <canvas id="particles" aria-hidden="true"></canvas>
  <div class="light-sweep" aria-hidden="true"></div>

  <audio id="bg-music" autoplay loop preload="auto">
    <!-- Substituir pela musica institucional em /audio/ -->
    <source src="<?= audio_url('entrada.mp3') ?>" type="audio/mpeg" />
  </audio>

  <div class="presentation" id="presentation">
    <div class="progress">
      <span id="progress-bar"></span>
    </div>

    <section class="scene scene-logo" data-duration="4500">
      <div class="spotlight"></div>
      <img class="logo" src="<?= $icone ?>/logo.png" alt="Logo Instituto Politécnico Privado Mundo Novo II" />
      <p class="logo-sub">Instituto Politécnico Privado Mundo Novo II</p>
    </section>

    <section class="scene scene-title" data-duration="3500">
      <h1>INSTITUTO POLITÉCNICO PRIVADO MUNDO NOVO II</h1>
      <div class="divider"></div>
    </section>

    <section class="scene scene-subtitle" data-duration="3200">
      <h2>CURSO TECNICO DE INFORMATICA</h2>
    </section>

    <section class="scene scene-subtitle" data-duration="3200">
      <h2>PROVA DE APTIDAO PROFISSIONAL (P.A.P)</h2>
    </section>

    <section class="scene scene-subtitle" data-duration="2600">
      <h2>13a CLASSE</h2>
    </section>

    <section class="scene scene-theme" data-duration="9000">
      <h2>TEMA DO PROJECTO</h2>
      <p class="typed" data-text="DESENVOLVIMENTO E IMPLEMENTACAO DE UM SISTEMA WEB DE GESTAO ESCOLAR PARA O INSTITUTO POLITÉCNICO PRIVADO MUNDO NOVO II"></p>
    </section>

    <section class="scene scene-group" data-duration="12000">
      <h2>GRUPO No 20</h2>
      <ul class="members">
        <li style="--delay: 0s;">ALEXANDRA ARMANDA TITO LOPES - No 03</li>
        <li style="--delay: 0.4s;">ARMINDO XINGANECA DIOGO JOAO - No 07</li>
        <li style="--delay: 0.8s;">CARLOS FERNANDES CORREIA - No 13</li>
        <li style="--delay: 1.2s;">CLAUDIO ELANGA MIGUEL JOAQUIM - No 15</li>
        <li style="--delay: 1.6s;">FERNANDO MATEUS DOMINGOS - No 28</li>
        <li style="--delay: 2s;">SIMAO LUVANGANO SUAMINO - No 00</li>
        <li style="--delay: 2.4s;">RICARDO LISANDRO DA COSTA DA SILVA - No 00</li>
      </ul>
      <div class="group-meta">
        <p style="--delay: 3s;">TURNO: TARDE</p>
        <p style="--delay: 3.4s;">TURMA: MTI/1</p>
        <p style="--delay: 3.8s;">ORIENTADOR: SIVI LANDU SEBASTIAO</p>
      </div>
    </section>

    <section class="scene scene-dedicatory" data-duration="15000">
      <h2>DEDICATORIA</h2>
      <div class="dedicatory-text">
        <p style="--delay: 0.6s;">Dedicamos este trabalho, em primeiro lugar, a Deus, pela vida, pela sabedoria e pela forca que nos permitiram superar os desafios encontrados ao longo da nossa formacao academica.</p>
        <p style="--delay: 1.2s;">Dedicamos igualmente aos nossos pais e familiares, pelo apoio incondicional, pelos valores transmitidos e por acreditarem sempre na importancia da educacao como instrumento fundamental para o desenvolvimento pessoal e profissional.</p>
        <p style="--delay: 1.8s;">Aos nossos colegas e amigos, que partilharam connosco momentos de aprendizagem, cooperacao e superacao durante este percurso academico.</p>
        <p style="--delay: 2.4s;">Por fim, dedicamos este projecto a todos os estudantes e profissionais da area da educacao que acreditam que a tecnologia pode contribuir significativamente para a melhoria da qualidade do ensino e para a modernizacao das instituicoes educativas.</p>
      </div>
    </section>

    <section class="scene scene-thanks" data-duration="12000">
      <h2>AGRADECIMENTOS</h2>
      <div class="thanks-text">
        <p style="--delay: 0.6s;">A realizacao deste trabalho representa a conclusao de uma importante etapa do nosso percurso academico, e a sua concretizacao nao teria sido possivel sem o apoio e a colaboracao de diversas pessoas e instituicoes.</p>
        <p style="--delay: 1.2s;">Expressamos tambem o nosso sincero agradecimento aos nossos professores e orientadores, que com dedicacao, profissionalismo e partilha de conhecimentos contribuiram de forma significativa para a realizacao deste trabalho.</p>
        <p style="--delay: 1.8s;">Ao Instituto Politécnico Privado Mundo Novo II, manifestamos o nosso reconhecimento pela disponibilidade e colaboracao prestadas durante o processo de recolha de informacoes e analise das necessidades da instituicao, elementos fundamentais para o desenvolvimento deste projecto.</p>
      </div>
    </section>

    <section class="scene scene-final" data-duration="10000">
      <div class="final-messages">
        <p style="--delay: 0.6s;">Muito obrigado pela vossa atencao.</p>
        <p style="--delay: 1.4s;">Foi uma honra apresentar este trabalho.</p>
        <p style="--delay: 2.2s;">Agradecemos a mesa de jurados pela avaliacao e pela oportunidade de demonstrar o nosso projecto.</p>
        <p style="--delay: 3s;">Estamos muito felizes por partilhar este momento convosco.</p>
      </div>
      <div class="final-group" style="--delay: 4.2s;">GRUPO No 20</div>
    </section>

    <section class="scene scene-end" data-duration="5000">
      <img class="logo" src="<?= $icone ?>/logo.png" alt="Logo Instituto Politécnico Privado Mundo Novo II" />
      <p class="logo-sub">Obrigado pela vossa atencao</p>
    </section>
  </div>

  <div class="audio-hint" id="audio-hint" hidden>
    Toque para ativar o som
  </div>

  <script src="<?= base_url('assets/js/paginas/encerramento.js') ?>"></script>
</body>
</html>
