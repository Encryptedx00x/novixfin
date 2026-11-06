# Checkpoint — revisão obrigatória (2026-11-04)

- `actions/sheets/_bootstrap.php`: exige revisão após autenticação/CSRF e antes de qualquer criação ou atualização. Ausente: HTTP 428 com orientação; malformada: 422; desatualizada: 409 após bloqueio de linha.
- Testes de planilha passaram a obter revisão do load e enviar a última revisão confirmada; regressão HTTP verifica os três endpoints e ausência de criação para cliente antigo.
- Validação: testes dirigidos HTTP, histórico e múltiplas abas em MariaDB local. Restam resposta perdida, saída, suíte final e preparação coordenada.
- Risco: somente backend novo rejeita cliente antigo; deploy precisa coordenar frontend e backend, sem escrita com backend antigo durante a troca.
- Confiança: 90% nesta etapa; sem deploy.
