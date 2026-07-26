from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def read(path: str) -> str:
    return (ROOT / path).read_text(encoding="utf-8")


def test_migration_runner_strips_line_comments_before_semicolon_split():
    runner = read("scripts/migrate.php")

    assert "function stripSqlLineComments(string $sql): string" in runner
    assert "$sql = stripSqlLineComments($sql);" in runner
    assert "ctype_space($sql[$i + 2])" in runner
    assert "$ch === ';' && !$inString" in runner


def test_sql_migration_line_comments_do_not_contain_semicolons():
    offenders = []
    for path in sorted((ROOT / "migrations").glob("*.sql")):
        for line_number, line in enumerate(path.read_text(encoding="utf-8").splitlines(), start=1):
            if line.lstrip().startswith("--") and ";" in line:
                offenders.append(f"{path.relative_to(ROOT).as_posix()}:{line_number}:{line.strip()}")

    assert offenders == []
