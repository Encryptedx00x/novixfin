            </div>
    </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="/public/scripts/date-format.js"></script>
    <script src="/public/scripts/main.js" defer></script>
    <!-- Shell (sidebar recolhível). main.js segue carregado só para os bits
         genéricos (menu/ai-cards); seu código de sidebar antigo fica inerte
         porque os ids #sidebar/#sidebarToggle não existem mais. -->
    <script src="/public/scripts/shell.js" defer></script>
    <!-- v3.1 Task 5: balance-visibility loaded globally so pages that render
         monetary values can share the same DB-backed privacy preference and
         the same show/hide toggle. -->
    <script src="/public/scripts/balance-visibility.js" defer></script>
    <?php if (isset($pageScripts) && is_array($pageScripts)) : ?>
        <?php foreach ($pageScripts as $script) : ?>
            <?php // ?v=filemtime pelo mesmo motivo do CSS: JS novo precisa chegar
                  // ao navegador sem depender de o usuário limpar o cache. ?>
            <script src="/public/<?php echo sanitize($script); ?>?v=<?php echo (int) @filemtime(__DIR__ . '/../../public/' . $script); ?>" defer></script>
        <?php endforeach; ?>
    <?php endif; ?>
</body>
</html>

