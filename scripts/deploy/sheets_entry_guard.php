<?php
/** Temporary entry fence. Install only through the reviewed two-stage runbook. */
http_response_code(503);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
echo '{"ok":false,"error":"Planilha em manutenção. Preserve seus dados pendentes e tente novamente após a liberação.","deploy_guard":true}';
exit;
