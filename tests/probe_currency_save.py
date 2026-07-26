#!/usr/bin/env python3
"""One-shot probe: reproduce CurrencySettingsRepository UPSERT against live schema."""
from __future__ import annotations

import os
import re
import sys

import pymysql

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ENV_PATH = os.path.join(ROOT, ".env")


def load_env(path: str) -> dict[str, str]:
    out: dict[str, str] = {}
    if not os.path.isfile(path):
        return out
    with open(path, encoding="utf-8") as f:
        for line in f:
            line = line.strip()
            if not line or line.startswith("#") or "=" not in line:
                continue
            k, v = line.split("=", 1)
            out[k.strip()] = v.strip().strip('"').strip("'")
    return out


SQL_AS_NEW = """
INSERT INTO user_currency_settings
(user_id, primary_currency, enabled_currencies, currency_order, convert_all_to_one,
 conversion_target_currency, conversion_rate_brl_to_usd, conversion_rate_brl_to_eur,
 conversion_rate_usd_to_brl, conversion_rate_usd_to_eur, conversion_rate_eur_to_brl, conversion_rate_eur_to_usd)
VALUES
(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
AS new
ON DUPLICATE KEY UPDATE
primary_currency = new.primary_currency,
enabled_currencies = new.enabled_currencies,
currency_order = new.currency_order,
convert_all_to_one = new.convert_all_to_one,
conversion_target_currency = new.conversion_target_currency,
conversion_rate_brl_to_usd = new.conversion_rate_brl_to_usd,
conversion_rate_brl_to_eur = new.conversion_rate_brl_to_eur,
conversion_rate_usd_to_brl = new.conversion_rate_usd_to_brl,
conversion_rate_usd_to_eur = new.conversion_rate_usd_to_eur,
conversion_rate_eur_to_brl = new.conversion_rate_eur_to_brl,
conversion_rate_eur_to_usd = new.conversion_rate_eur_to_usd,
updated_at = CURRENT_TIMESTAMP
"""

SQL_FIXED = """
INSERT INTO user_currency_settings
(user_id, primary_currency, enabled_currencies, currency_order, convert_all_to_one,
 conversion_target_currency, conversion_rate_brl_to_usd, conversion_rate_brl_to_eur,
 conversion_rate_usd_to_brl, conversion_rate_usd_to_eur, conversion_rate_eur_to_brl, conversion_rate_eur_to_usd)
VALUES
(%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)
ON DUPLICATE KEY UPDATE
primary_currency = VALUES(primary_currency),
enabled_currencies = VALUES(enabled_currencies),
currency_order = VALUES(currency_order),
convert_all_to_one = VALUES(convert_all_to_one),
conversion_target_currency = VALUES(conversion_target_currency),
conversion_rate_brl_to_usd = VALUES(conversion_rate_brl_to_usd),
conversion_rate_brl_to_eur = VALUES(conversion_rate_brl_to_eur),
conversion_rate_usd_to_brl = VALUES(conversion_rate_usd_to_brl),
conversion_rate_usd_to_eur = VALUES(conversion_rate_usd_to_eur),
conversion_rate_eur_to_brl = VALUES(conversion_rate_eur_to_brl),
conversion_rate_eur_to_usd = VALUES(conversion_rate_eur_to_usd),
updated_at = CURRENT_TIMESTAMP
"""

PARAMS = (
    None,  # filled with real user_id
    "BRL",
    '["BRL","USD","EUR"]',
    '["BRL","USD","EUR"]',
    0,
    None,
    0.18,
    0.18,
    5.61,
    0.85,
    5.61,
    1.18,
)


def run_probe(label: str, sql: str, params: tuple) -> None:
    print(f"\n=== PROBE: {label} ===")
    try:
        with conn.cursor() as cur:
            cur.execute(sql, params)
        conn.commit()
        print("RESULT: OK (executed + committed)")
    except pymysql.Error as e:
        conn.rollback()
        print(f"RESULT: FAIL")
        print(f"  errno: {e.args[0] if e.args else 'n/a'}")
        print(f"  sqlstate: {getattr(e, 'sqlstate', 'n/a')}")
        print(f"  message: {e.args[1] if len(e.args) > 1 else e}")


env = load_env(ENV_PATH)
conn = pymysql.connect(
    host=env.get("DB_HOST", "127.0.0.1"),
    port=int(env.get("DB_PORT", "3306")),
    user=env.get("DB_USERNAME", "root"),
    password=env.get("DB_PASSWORD", ""),
    database=env.get("DB_DATABASE", "novix_fin"),
    charset=env.get("DB_CHARSET", "utf8mb4"),
    connect_timeout=15,
)

with conn.cursor() as cur:
    cur.execute("SELECT VERSION()")
    version = cur.fetchone()[0]
    print("SERVER VERSION:", version)

    cur.execute("SHOW CREATE TABLE user_currency_settings")
    row = cur.fetchone()
    if row:
        print("TABLE user_currency_settings: EXISTS")
    else:
        print("TABLE user_currency_settings: MISSING")
        sys.exit(1)

    cur.execute("SELECT id FROM users ORDER BY id ASC LIMIT 1")
    user_row = cur.fetchone()
    if not user_row:
        print("No users in DB — cannot probe UPSERT")
        sys.exit(1)
    user_id = user_row[0]
    print("PROBE user_id:", user_id)

params = (user_id,) + PARAMS[1:]

run_probe("current v3 SQL (INSERT ... AS new)", SQL_AS_NEW, params)
run_probe("fixed v3.1 SQL (INSERT ... VALUES(col))", SQL_FIXED, params)

conn.close()
print("\nDone.")
