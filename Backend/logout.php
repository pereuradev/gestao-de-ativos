<?php

declare(strict_types=1);

// Encerra a sessao PHP e volta o usuario para a tela de login.
session_start();

// Remove os dados guardados na sessao atual.
$_SESSION = [];

if (ini_get("session.use_cookies")) {
    // Expira o cookie da sessao no navegador.
    $parametrosCookie = session_get_cookie_params();
    setcookie(
        session_name(),
        "",
        time() - 42000,
        $parametrosCookie["path"],
        $parametrosCookie["domain"],
        (bool) $parametrosCookie["secure"],
        (bool) $parametrosCookie["httponly"]
    );
}

// Destroi a sessao no servidor.
session_destroy();

// Redireciona com um parametro para a tela mostrar a mensagem correta.
header("Location: ../pages/Pagina-login.html");
exit;
