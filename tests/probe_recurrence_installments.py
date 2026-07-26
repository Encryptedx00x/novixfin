from pathlib import Path
import re


ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def test_installment_migration_adds_nullable_columns_indexes_and_backfill():
    migration_path = ROOT / "migrations/20240101000006_add_recurrence_installments_v3.php"
    assert migration_path.exists()

    migration = migration_path.read_text(encoding="utf-8")

    assert "parent_transaction_id" in migration
    assert "installment_index" in migration
    assert "recurrence_total_occurrences" in migration
    assert "idx_transactions_parent_transaction_id" in migration
    assert "idx_transactions_installment_parent" in migration
    assert "idx_transactions_recurrence_total" in migration
    assert "idx_recurring_generated_installment" in migration
    assert "UPDATE `transactions` t" in migration
    assert "recurring_generated" in migration
    assert "child_transaction_id" in migration
    assert "FOREIGN KEY" not in migration


def test_transaction_repository_persists_and_lists_installment_fields():
    repo = read("src/Repositories/TransactionRepository.php")

    assert "parent_transaction_id" in repo
    assert "installment_index" in repo
    assert "recurrence_total_occurrences" in repo
    assert "?int $parentTransactionId = null" in repo
    assert "?int $installmentIndex = null" in repo
    assert "?int $recurrenceTotalOccurrences = null" in repo
    assert "bool $updateInstallmentFields = true" in repo
    assert "recurrence_total_occurrences = :recurrence_total_occurrences" in repo
    assert "$updateInstallmentFields && isset($columns['installment_index'])" in repo
    assert "NULL as parent_transaction_id" in repo
    assert "NULL as installment_index" in repo
    assert "NULL as recurrence_total_occurrences" in repo


def test_actions_parse_limit_modes_and_update_rejects_below_consumed():
    create = read("actions/transactions/create.php")
    update = read("actions/transactions/update.php")

    for source in (create, update):
        assert "recurrence_limit_mode" in source
        assert "recurrence_total_occurrences" in source
        assert "['never', 'date', 'count']" in source
        assert "$recurrenceLimitMode === 'count'" in source
        assert "$recurrenceTotalOccurrences < 2" in source
        assert "$recurrenceEndDate = null" in source
        assert "$recurrenceTotalOccurrences = null" in source

    assert "COUNT(*) FROM recurring_generated" in update
    assert "$consumedOccurrences" in update
    assert "$recurrenceTotalOccurrences < $consumedOccurrences" in update
    assert "parent_transaction_id FROM transactions" in update
    assert "$preserveInstallmentFields" in update
    assert "!$preserveInstallmentFields" in update


def test_recurring_service_counts_consumed_occurrences_and_sets_child_index():
    service = read("src/Services/RecurringService.php")

    assert "recurrence_total_occurrences" in service
    assert "generatedCountForParent" in service
    assert "$consumedOccurrences = 1 + $this->generatedCountForParent" in service
    assert "$consumedOccurrences >= $totalOccurrences" in service
    assert "$nextInstallmentIndex = $consumedOccurrences + 1" in service
    assert "parent_transaction_id" in service
    assert "installment_index" in service
    assert "$nextInstallmentIndex" in service
    assert "$totalOccurrences" in service


def test_dashboard_and_history_expose_limit_ui_and_json_payloads():
    dashboard = read("public/dashboard.php")
    history = read("public/history.php")
    dashboard_js = read("public/scripts/dashboard.js")
    history_js = read("public/scripts/history.js")

    for source in (dashboard, history):
        assert "Finalizar recorr" in source
        assert "recurrence_limit_mode" in source
        assert "recurrence_total_occurrences" in source
        assert "A transa" in source
        assert "'parent_transaction_id'" in source
        assert "'installment_index'" in source
        assert "'recurrence_total_occurrences'" in source

    for source in (dashboard_js, history_js):
        assert "recurrence_total_occurrences" in source
        assert "recurrence_limit_mode" in source
        assert "toggleRecurrenceLimit" in source
        assert "transaction.recurrence_total_occurrences" in source


def test_active_php_is_still_php74_safe_after_installments_patch():
    active_roots = ["public", "actions", "includes", "src", "scripts", "tests", "migrations"]
    php_files = [
        path
        for root in active_roots
        for path in (ROOT / root).rglob("*.php")
    ]

    variableless_catch = re.compile(r"catch\s*\(\s*\\?(?:Exception|Throwable|PDOException)\s*\)")
    constructor_promotion = re.compile(r"function\s+__construct\s*\([^)]*\b(?:private|protected|public)\s+")
    mixed_type = re.compile(r"(?::\s*mixed\b|\bmixed\s+\$)")

    offenders = []
    for path in php_files:
        text = path.read_text(encoding="utf-8")
        rel = path.relative_to(ROOT).as_posix()
        if variableless_catch.search(text):
            offenders.append(f"{rel}: variableless catch")
        if constructor_promotion.search(text):
            offenders.append(f"{rel}: constructor property promotion")
        if mixed_type.search(text):
            offenders.append(f"{rel}: mixed type")
        if "?->" in text:
            offenders.append(f"{rel}: nullsafe operator")
        if re.search(r"\bmatch\s*\(", text):
            offenders.append(f"{rel}: match expression")
        if "str_contains(" in text:
            offenders.append(f"{rel}: str_contains")

    assert offenders == []


def test_recurrence_overflow_chain_uses_parent_day_anchor():
    overflow = read("tests/recurrence_overflow.php")

    assert "$anchorDay = (int) $cursor->format('j');" in overflow
    assert "$method->invoke($service, $cursor, 'monthly', $anchorDay)" in overflow
    assert "$method->invoke($service, $cursor, 'monthly', 0)" not in overflow
