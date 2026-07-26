from pathlib import Path
import re


ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def test_header_renders_hide_balances_class_server_side():
    header = read("includes/layout/header.php")

    assert "new \\Novix\\Repositories\\UserRepository" in header
    assert "isset($user['hide_balances'])" in header
    assert "$_SESSION['user']['hide_balances'] = $hideBalances" in header
    assert "$bodyClasses" in header
    assert "hide-balances" in header
    assert "implode(' ', $bodyClasses)" in header
    assert "data-hide-balances-default" in header


def test_balance_visibility_js_uses_server_preference_as_source_of_truth():
    script = read("public/scripts/balance-visibility.js")

    assert "body.dataset.hideBalancesDefault === 'true'" in script
    assert "localStorage.getItem" not in script
    assert "localStorage.setItem" not in script


def test_dashboard_has_inline_category_modal_and_js_handler():
    dashboard = read("public/dashboard.php")
    dashboard_js = read("public/scripts/dashboard.js")

    assert 'id="inline-category-modal"' in dashboard
    assert 'id="inline-category-name"' in dashboard
    assert "initInlineCategoryCreation" in dashboard_js
    assert "/actions/categories/create-inline.php" in dashboard_js


def test_dashboard_category_filter_uses_master_options_registry():
    dashboard = read("public/dashboard.php")
    dashboard_js = read("public/scripts/dashboard.js")

    assert "window.NOVIX_CATEGORY_OPTIONS" in dashboard
    assert "NOVIX_CATEGORY_OPTIONS" in dashboard_js
    assert "appendCategoryOption" in dashboard_js


def test_transaction_category_scope_allows_global_defaults():
    dashboard = read("public/dashboard.php")
    create_action = read("actions/transactions/create.php")
    update_action = read("actions/transactions/update.php")

    expected = "(user_id = :user_id OR user_id IS NULL)"
    assert expected in dashboard
    assert expected in create_action
    assert expected in update_action


def test_active_php_is_php74_safe_for_hotfix_scope():
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


def test_balance_visibility_uses_text_replacement_not_permanent_overlay():
    script = read("public/scripts/balance-visibility.js")
    css = read("public/styles/main.css")

    assert "data-balance-value" in script
    assert "textContent = BALANCE_PLACEHOLDER" in script
    assert "restoreBalanceValues" in script
    assert "balance-visibility-ready" in css
    assert "body.hide-balances [data-balance]::after" not in css


def test_smoke_loads_throttle_and_avoids_php74_reflection_deprecations():
    smoke = read("tests/smoke.php")

    assert "require_once $root . '/includes/throttle.php';" in smoke
    assert "ReflectionClass::exists" not in smoke
    assert "class_exists(" in smoke
    assert "ReflectionNamedType" in smoke
    assert "(string) $method->getReturnType()" not in smoke
    assert "if ($hasThrottleFunctions)" in smoke


def test_balance_visibility_has_single_state_setter_without_ajax_rollback():
    script = read("public/scripts/balance-visibility.js")

    assert "var currentHidden" in script
    assert "function setHidden(hidden" in script
    assert "preventDefault()" in script
    assert "stopPropagation()" in script
    assert "body.dataset.hideBalancesDefault" in script
    assert "currentHidden = body.dataset.hideBalancesDefault === 'true'" in script
    assert "isHidden = previous" not in script
    assert "body.classList.toggle('hide-balances', previous)" not in script


def test_balance_visibility_toggle_is_available_outside_dashboard():
    header = read("includes/layout/header.php")
    footer = read("includes/layout/footer.php")
    dashboard = read("public/dashboard.php")
    profile = read("public/profile.php")
    action = read("actions/profile/update-balance-visibility.php")

    assert 'id="balance-visibility-btn"' in header
    assert "basename($_SERVER['PHP_SELF'] ?? '') !== 'dashboard.php'" in header
    assert "only rendered on dashboard.php" not in footer
    assert dashboard.count('id="balance-visibility-btn"') == 1
    assert '<input type="hidden" name="hide_balances" value="0">' in profile
    assert '<input type="hidden" name="redirect_to" value="profile.php">' in profile
    assert "redirect('profile.php')" in action
    assert "redirect('/public/profile.php')" not in action


def test_balance_visibility_click_handler_is_delegated_and_compat_safe():
    script = read("public/scripts/balance-visibility.js")

    assert "function findToggleButton" in script
    assert "document.addEventListener('click'" in script
    assert "Promise" not in script or ".finally(" not in script
    assert "setButtonsDisabled(true)" in script
    assert "setButtonsDisabled(false)" in script


def test_bulk_delete_transactions_is_transactional_and_redirects_to_history():
    repo = read("src/Repositories/TransactionRepository.php")
    action = read("actions/transactions/delete_all.php")
    history = read("public/history.php")

    assert "public function deleteAll" in repo
    assert "$this->connection->beginTransaction()" in repo
    assert "$this->connection->commit()" in repo
    assert "$this->connection->rollBack()" in repo
    assert "recurring_generated" in repo
    assert "DELETE rg" in repo
    assert "balance = balance + :delta" in repo
    assert "is_effective = 1" in repo
    assert "updateBillTotal" in repo
    assert "$redirectTo = $_POST['redirect_to'] ?? 'history.php'" in action
    assert "redirect($redirectTo)" in action
    assert '<input type="hidden" name="redirect_to" value="history.php">' in history


def test_sidebar_footer_alignment_has_scoped_hooks():
    header = read("includes/layout/header.php")
    css = read("public/styles/main.css")

    assert "sidebar-footer" in header
    assert "sidebar-user-row" in header
    assert "sidebar-logout-btn" in header
    assert "#sidebar .sidebar-footer" in css
    assert "#sidebar .sidebar-user-row" in css
    assert "#sidebar .sidebar-logout-btn" in css
    assert "#sidebar.sidebar-collapsed .sidebar-logout-btn" in css
