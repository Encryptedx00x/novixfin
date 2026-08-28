<?php

declare(strict_types=1);

/**
 * NovixFin — cliente HTTP mínimo para testes de integração.
 *
 * POR QUE ISTO EXISTE: vários handlers leem a requisição com
 * `filter_input(INPUT_POST, ...)` — que lê o corpo REAL da requisição, e não a
 * superglobal `$_POST`. Um teste que apenas preenche `$_POST` em CLI **nunca**
 * exercita esses handlers: `filter_input` devolve null e o handler rejeita.
 * É o caso de `actions/sheets/restore.php` e de `actions/credit-cards/pay-bill.php`.
 * Para testá-los de verdade é preciso fazer requisições HTTP de verdade.
 *
 * Usa só streams (sem depender da extensão cURL), mantém cookie jar de sessão
 * e reaproveita o token CSRF — que em `includes/csrf.php` é por sessão, então
 * o token pego no login vale para os POSTs seguintes.
 *
 * Habilita-se com HTTP_BASE (ex.: http://novixfin-web:8080). Sem ela, os
 * testes de integração se marcam como SKIP.
 */

/** Base do servidor de teste, ou null se não configurada. */
function http_base(): ?string
{
    $base = getenv('HTTP_BASE');
    if (!is_string($base) || trim($base) === '') {
        return null;
    }
    return rtrim(trim($base), '/');
}

/** Estado da "aba do navegador": cookies de sessão + token CSRF. */
function http_new_session(): array
{
    return ['cookies' => [], 'csrf' => null];
}

/**
 * Requisição HTTP. $session é passado por referência para acumular cookies.
 *
 * @param array<string,string> $params
 * @return array{status:int, body:string, headers:array<int,string>}
 */
function http_request(string $method, string $path, array $params, array &$session): array
{
    $base = http_base();
    if ($base === null) {
        throw new RuntimeException('HTTP_BASE não configurada.');
    }

    $url = $base . '/' . ltrim($path, '/');
    $method = strtoupper($method);

    $headers = ["User-Agent: NovixFin-Test"];
    if ($session['cookies'] !== []) {
        $pairs = [];
        foreach ($session['cookies'] as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        $headers[] = 'Cookie: ' . implode('; ', $pairs);
    }

    $body = '';
    if ($method === 'POST') {
        $body = http_build_query($params);
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        $headers[] = 'Content-Length: ' . strlen($body);
    } elseif ($params !== []) {
        $url .= (strpos($url, '?') === false ? '?' : '&') . http_build_query($params);
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'ignore_errors' => true,   // queremos ler 4xx/5xx, não virar warning
            'follow_location' => 0,    // redirect é informação: não seguir
            'timeout' => 20,
        ],
    ]);

    $responseBody = @file_get_contents($url, false, $context);
    $responseHeaders = $http_response_header ?? [];

    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $m)) {
            $status = (int) $m[1];
        }
        // Acumula cookies de sessão.
        if (stripos($header, 'Set-Cookie:') === 0) {
            $cookie = trim(substr($header, strlen('Set-Cookie:')));
            $firstPair = explode(';', $cookie)[0];
            $eq = strpos($firstPair, '=');
            if ($eq !== false) {
                $name = trim(substr($firstPair, 0, $eq));
                $value = trim(substr($firstPair, $eq + 1));
                $session['cookies'][$name] = $value;
            }
        }
    }

    return [
        'status' => $status,
        'body' => $responseBody === false ? '' : $responseBody,
        'headers' => $responseHeaders,
    ];
}

/** Extrai o token CSRF de uma página (input hidden ou window.NOVIX_CSRF_TOKEN). */
function http_extract_csrf(string $html): ?string
{
    if (preg_match('/name="_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    if (preg_match('/NOVIX_CSRF_TOKEN\s*=\s*[\'"]([^\'"]+)[\'"]/', $html, $m)) {
        return $m[1];
    }
    return null;
}

/**
 * Faz login de verdade (POST em /public/login.php) e guarda cookie + CSRF.
 * Devolve true se a sessão ficou autenticada.
 */
function http_login(string $email, string $password, array &$session): bool
{
    $page = http_request('GET', '/public/login.php', [], $session);
    $token = http_extract_csrf($page['body']);
    if ($token === null) {
        return false;
    }

    $res = http_request('POST', '/public/login.php', [
        '_token' => $token,
        'email' => $email,
        'password' => $password,
    ], $session);

    // Login bem-sucedido redireciona (302) para o dashboard.
    if ($res['status'] !== 302 && $res['status'] !== 301) {
        return false;
    }

    // Após o login a sessão é regenerada: pega o token válido da sessão nova.
    // Tenta várias páginas de propósito — `dashboard.php` FATALA num schema v3
    // recém-migrado (usa COALESCE(title, name) em `goals`, e o baseline só cria
    // `title`), então o harness não pode depender dela.
    foreach (['/public/categories.php', '/public/accounts.php', '/public/profile.php'] as $page) {
        $res = http_request('GET', $page, [], $session);
        $token = http_extract_csrf($res['body']);
        if ($token !== null) {
            $session['csrf'] = $token;
            return true;
        }
    }

    return false;
}

/** POST autenticado, injetando o _token da sessão. */
function http_post(string $path, array $params, array &$session): array
{
    if (!isset($params['_token']) && is_string($session['csrf'])) {
        $params['_token'] = $session['csrf'];
    }
    return http_request('POST', $path, $params, $session);
}

/** POST que espera JSON de volta. Devolve null se não vier JSON. */
function http_post_json(string $path, array $params, array &$session): ?array
{
    $res = http_post($path, $params, $session);
    $decoded = json_decode($res['body'], true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Resposta não-JSON de {$path} (HTTP {$res['status']}):\n" . substr($res['body'], 0, 400) . "\n");
        return null;
    }
    return $decoded;
}

/** GET que espera JSON de volta. */
function http_get_json(string $path, array &$session): ?array
{
    $res = http_request('GET', $path, [], $session);
    $decoded = json_decode($res['body'], true);
    if (!is_array($decoded)) {
        fwrite(STDERR, "Resposta não-JSON de {$path} (HTTP {$res['status']}):\n" . substr($res['body'], 0, 400) . "\n");
        return null;
    }
    return $decoded;
}
