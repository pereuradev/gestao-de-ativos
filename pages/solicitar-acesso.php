<?php

declare(strict_types=1);

session_start();

if (empty($_SESSION['csrf_solicitacao_acesso']) || !is_string($_SESSION['csrf_solicitacao_acesso'])) {
  $_SESSION['csrf_solicitacao_acesso'] = bin2hex(random_bytes(32));
}

function escaparSolicitacao(string $valor): string
{
  return htmlspecialchars($valor, ENT_QUOTES, 'UTF-8');
}

$csrfToken = escaparSolicitacao($_SESSION['csrf_solicitacao_acesso']);
$departamentos = [
  'Comercial' => 'Comercial',
  'TI' => 'TI',
  'Administrativo' => 'Administração',
];
?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Solicitar acesso | TI TECH Solutions</title>
  <meta name="description" content="Solicite acesso ao portal de gestao de ativos da TI TECH Solutions." />
  <link rel="icon" type="image/png" href="../assets/favicon.png" />
  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet" />
  <link rel="stylesheet" href="../css/solicitar-acesso.css?v=20260806-foto-quadrada-v2" />
  <script src="../js/pages/solicitar-acesso.js?v=20260806-foto-quadrada-v2" defer></script>
</head>

<body>
  <header class="request-header">
    <a class="request-brand" href="https://www.titechsolutions.com.br/" aria-label="Site da TI TECH Solutions">
      <img src="../assets/logo-branca.png" alt="TI TECH Solutions" />
    </a>
    <a class="back-login-link" href="Pagina-login.html">
      <i class="bi bi-arrow-left" aria-hidden="true"></i>
      Voltar ao login
    </a>
  </header>

  <main class="request-page">
    <section class="request-intro" aria-labelledby="requestTitle">
      <span class="request-kicker">Novo acesso</span>
      <h1 id="requestTitle">Solicite seu acesso ao portal</h1>
      <p>Preencha seus dados. A equipe responsavel analisara a solicitacao antes de liberar a conta.</p>

      <div class="request-steps" aria-label="Etapas da solicitacao">
        <span class="active"><strong>1</strong> Seus dados</span>
        <span><strong>2</strong> Analise interna</span>
        <span><strong>3</strong> Acesso liberado</span>
      </div>

    </section>

    <section class="request-card" aria-label="Formulario de solicitacao de acesso">
      <form id="accessRequestForm" enctype="multipart/form-data" novalidate>
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>" />
        <input id="requestRole" type="hidden" name="tipo_usuario" value="Colaborador" />

        <div class="request-section-heading">
          <span><i class="bi bi-person-vcard" aria-hidden="true"></i></span>
          <div>
            <h2>Identificacao</h2>
            <p>Use os mesmos dados dos seus documentos.</p>
          </div>
        </div>

        <div class="request-grid">
          <label class="request-field field-wide">
            <span>Nome completo <b>*</b></span>
            <div class="request-input">
              <i class="bi bi-person" aria-hidden="true"></i>
              <input name="nome_completo" type="text" autocomplete="name" placeholder="Nome e sobrenome" required />
            </div>
          </label>

          <label class="request-field">
            <span>E-mail corporativo <b>*</b></span>
            <div class="request-input">
              <i class="bi bi-envelope" aria-hidden="true"></i>
              <input name="email" type="email" autocomplete="email" placeholder="nome@titechsolutions.com.br"
                required />
            </div>
          </label>

          <label class="request-field">
            <span>Empresa <b>*</b></span>
            <div class="request-input">
              <i class="bi bi-buildings" aria-hidden="true"></i>
              <input name="empresa" type="text" autocomplete="organization" placeholder="Nome da empresa" required />
            </div>
          </label>

          <label class="request-field">
            <span>RG <b>*</b></span>
            <div class="request-input">
              <i class="bi bi-card-text" aria-hidden="true"></i>
              <input name="rg" type="text" inputmode="numeric" placeholder="00.000.000-0" required />
            </div>
          </label>

          <label class="request-field">
            <span>CPF <b>*</b></span>
            <div class="request-input">
              <i class="bi bi-person-vcard" aria-hidden="true"></i>
              <input name="cpf" type="text" inputmode="numeric" placeholder="000.000.000-00" required />
            </div>
          </label>

          <label class="request-field">
            <span>Celular <b>*</b></span>
            <div class="request-input">
              <i class="bi bi-phone" aria-hidden="true"></i>
              <input name="celular" type="tel" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000"
                required />
            </div>
          </label>

          <label class="request-field">
            <span>Data de nascimento <b>*</b></span>
            <div class="request-input">
              <i class="bi bi-calendar3" aria-hidden="true"></i>
              <input name="data_nascimento" type="date" autocomplete="bday" required />
            </div>
          </label>

          <label class="request-field">
            <span>Departamento <b>*</b></span>
            <div class="request-input">
              <i class="bi bi-diagram-3" aria-hidden="true"></i>
              <select name="departamento" required>
                <option value="">Selecione</option>
                <?php foreach ($departamentos as $valor => $rotulo): ?>
                  <option value="<?php echo escaparSolicitacao($valor); ?>"><?php echo escaparSolicitacao($rotulo); ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
          </label>

          <div class="request-field">
            <span>Tipo de usuario <b>*</b></span>
            <div class="request-role-control" id="requestRoleControl" data-active="Colaborador">
              <button class="active" type="button" data-role="Colaborador">Colaborador</button>
              <button type="button" data-role="Administrador">Administrador</button>
            </div>
          </div>
        </div>

        <div class="request-section-heading section-spacing">
          <span><i class="bi bi-camera" aria-hidden="true"></i></span>
          <div>
            <h2>Foto e seguranca</h2>
            <p>A foto sera usada no seu cracha digital.</p>
          </div>
        </div>

        <div class="photo-password-grid">
          <div class="photo-field">
            <div class="photo-preview" id="requestPhotoPreview" aria-label="Previa da foto selecionada"
              aria-live="polite">
              <i class="bi bi-person-bounding-box" aria-hidden="true"></i>
              <img id="requestPhotoImage" alt="Previa da foto selecionada" />
            </div>
            <div class="photo-actions">
              <input id="requestPhotoFile" class="photo-input-native" type="file" accept="image/*"
                aria-label="Selecionar foto" />
              <input id="requestPhotoCamera" class="photo-input-native" type="file" accept="image/*"
                capture="user" aria-label="Tirar uma foto" />
              <label class="photo-button" for="requestPhotoFile" role="button" tabindex="0"
                data-photo-label="file">
                <i class="bi bi-image" aria-hidden="true"></i>
                Selecionar foto
              </label>
              <label class="photo-button capture-photo-button" for="requestPhotoCamera" role="button" tabindex="0"
                data-photo-label="camera">
                <i class="bi bi-camera-fill" aria-hidden="true"></i>
                Tirar uma foto
              </label>
              <small id="requestPhotoName">JPG, PNG ou WebP de ate 2 MB.</small>
            </div>
          </div>

          <label class="request-field password-field">
            <span>Senha <b>*</b></span>
            <div class="request-input">
              <i class="bi bi-key" aria-hidden="true"></i>
              <input id="requestPassword" name="senha" type="password" autocomplete="new-password"
                placeholder="Minimo de 6 caracteres" required />
              <button class="password-visibility" type="button" aria-label="Mostrar senha">
                <i class="bi bi-eye" aria-hidden="true"></i>
              </button>
            </div>
            <div class="request-password-meter" aria-live="polite">
              <span><i id="requestPasswordBar"></i></span>
              <small id="requestPasswordText">Forca da senha: aguardando</small>
            </div>
          </label>
        </div>

        <div id="accessRequestMessage" class="request-message" role="status" aria-live="polite"></div>

        <div class="request-actions">
          <a href="Pagina-login.html">Ja tenho acesso</a>
          <button id="accessRequestSubmit" type="submit">
            <i class="bi bi-send-check" aria-hidden="true"></i>
            <span>Solicitar acesso</span>
          </button>
        </div>
      </form>

      <div id="accessRequestSuccess" class="request-success" hidden>
        <span><i class="bi bi-check2-circle" aria-hidden="true"></i></span>
        <p>Solicitacao recebida</p>
        <h2>Agora e so aguardar a analise</h2>
        <p>Quando o acesso for aprovado, voce podera entrar com o e-mail e a senha informados.</p>
        <a href="Pagina-login.html">Voltar ao login</a>
      </div>
    </section>
  </main>
</body>

</html>
