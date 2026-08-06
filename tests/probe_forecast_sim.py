"""Probes estaticos para o olhinho de simulacao + "Carregar mais".

Local nao tem PHP, entao estas checagens cobrem o contrato entre
public/dashboard.php e public/scripts/forecast-sim.js: ids, data-attributes
e a aritmetica da simulacao (replicada aqui a partir da regra do
includes/forecast.php). Nao substituem `php -l` no servidor.
"""
import pathlib
import re

ROOT = pathlib.Path(__file__).resolve().parent.parent
DASH = (ROOT / "public" / "dashboard.php").read_text(encoding="utf-8")
JS = (ROOT / "public" / "scripts" / "forecast-sim.js").read_text(encoding="utf-8")
CSS = (ROOT / "public" / "styles" / "dashboard.css").read_text(encoding="utf-8")


def test_ids_do_contrato_existem_no_dashboard():
    for el_id in (
        "forecastHero", "forecastToday", "forecastIncoming", "forecastOutgoing",
        "forecastFinal", "forecastSimNotice", "forecastSimCount",
        "forecastSimReset", "forecastSimTag", "forecastBreakdown",
        "dashRecentMore",
    ):
        assert f'id="{el_id}"' in DASH, f"id {el_id} sumiu do dashboard"
        assert el_id in JS, f"id {el_id} nao e consumido pelo forecast-sim.js"


def test_linha_carrega_os_data_attributes_da_simulacao():
    for attr in ("data-sim-id", "data-sim-kind", "data-sim-type",
                 "data-sim-amount", "data-sim-eligible", "data-recent-seq"):
        assert attr in DASH
        assert attr in JS


def test_script_registrado_e_base_exposta():
    assert "scripts/forecast-sim.js" in DASH
    assert "window.NOVIX_FORECAST_BASE" in DASH
    assert "window.NOVIX_FORECAST_BASE" in JS


def test_hidden_vence_o_display_flex():
    # As linhas tem display:flex inline; sem o !important o atributo hidden
    # nao esconderia nada.
    assert ".dash-recent-item[hidden]" in CSS
    assert "display: none !important" in CSS


def test_nada_persiste_no_servidor():
    # A simulacao e client-side: o script nao pode falar com endpoint algum.
    assert "fetch(" not in JS
    assert "XMLHttpRequest" not in JS
    assert "sendBeacon" not in JS


def test_previsao_base_do_servidor_intacta():
    # A regra deterministica continua vindo do PHP, nao do JS.
    assert "novix_forecast_balance($consolidatedBalance, $projNet)" in DASH


# --------------------------------------------------------------- aritmetica
def simulate(base_today, base_in, base_out, hidden_rows):
    """Replica recalc() do forecast-sim.js."""
    h_in = h_out = h_eff = 0.0
    for kind, ttype, amount in hidden_rows:
        if kind == "pending":
            if ttype == "income":
                h_in += amount
            else:
                h_out += amount
        else:
            h_eff += amount if ttype == "income" else -amount
    today = base_today - h_eff
    incoming = base_in - h_in
    outgoing = base_out - h_out
    return today, incoming, outgoing, today + incoming - outgoing


def test_ocultar_pendente_a_pagar_sobe_o_saldo_final():
    _, _, out, final = simulate(1000, 500, 300, [("pending", "expense", 300)])
    assert out == 0
    assert final == 1500  # antes era 1200


def test_ocultar_pendente_a_receber_derruba_o_saldo_final():
    _, inc, _, final = simulate(1000, 500, 300, [("pending", "income", 500)])
    assert inc == 0
    assert final == 700


def test_ocultar_receita_efetivada_estorna_do_saldo_hoje():
    today, _, _, final = simulate(1000, 500, 300, [("effective", "income", 500)])
    assert today == 500
    assert final == 700  # cai exatamente os 500


def test_ocultar_despesa_efetivada_devolve_ao_saldo_hoje():
    today, _, _, final = simulate(1000, 500, 300, [("effective", "expense", 200)])
    assert today == 1200
    assert final == 1400


def test_restaurar_volta_ao_valor_real():
    base = (1000.0, 500.0, 300.0)
    assert simulate(*base, [])[3] == 1200
