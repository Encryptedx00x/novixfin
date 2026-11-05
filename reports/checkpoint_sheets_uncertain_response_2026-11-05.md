# Checkpoint — resposta perdida e proteção de saída (2026-11-05)

- `sheet.js`: falha de comunicação informa que a gravação não foi confirmada e oferece cópia manual em JSON de todas as abas, incluindo a célula em edição; não grava nem recarrega automaticamente os dados.
- Chrome e MariaDB descartável: resposta perdida após o servidor confirmar a escrita, outra página salva uma alteração e a tentativa anterior recebe conflito; conteúdo pendente permanece copiável. Erro antes da gravação e HTTP 500 continuam testados.
- Chrome real testou confirmação ao recarregar, navegar e fechar; alteração aberta no editor foi enviada ao simular perda de visibilidade.
- Riscos: encerramento forçado pode impedir aviso e envio; recuperação manual não mescla conteúdo; a resposta perdida pode ter sido salva ou não.
- Pendente: suíte completa e preparação da publicação coordenada. Sem deploy.
- Confiança: 90% nos cenários testados.
