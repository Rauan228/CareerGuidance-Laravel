"""
Финальная сборка программ для БД:
  1) rebuild from vipusknik (+ official if any)
  2) apply tuition_overrides (официальные тарифы топ-вузов)
  3) write output/card_programs_final.json

Запуск:
  python spiders/finalize_card_programs.py
"""
from __future__ import annotations

import json
import re
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "output"
OVERRIDES = ROOT / "data" / "tuition_overrides.json"
CARD = OUT / "card_programs.json"
FINAL = OUT / "card_programs_final.json"


def run_build() -> None:
    script = ROOT / "spiders" / "build_card_programs.py"
    subprocess.check_call([sys.executable, str(script)], cwd=str(ROOT))


def apply_overrides(payload: dict, overrides: dict) -> dict:
    by_id = overrides.get("by_institution_id") or {}
    name_rules = overrides.get("name_rules") or []

    for inst in payload.get("institutions", []):
        iid = str(inst.get("institution_id"))
        conf = by_id.get(iid)
        programs = inst.get("programs") or []
        if not programs:
            continue

        # per-program name rules first
        for rule in name_rules:
            if int(rule.get("institution_id", -1)) != int(iid):
                continue
            pat = re.compile(rule["match"])
            for p in programs:
                if pat.search(p.get("name") or ""):
                    p["cost"] = float(rule["cost"])
                    if rule.get("duration"):
                        p["duration"] = int(rule["duration"])
                    p["cost_source"] = "official_name_rule"

        if not conf:
            # recount
            inst["with_cost"] = sum(1 for p in programs if p.get("cost") is not None)
            inst["programs_count"] = len(programs)
            continue

        fill_null = bool(conf.get("fill_null_only", True))
        default_cost = conf.get("default_cost")
        default_dur = conf.get("default_duration")

        for p in programs:
            # skip if already set by name rule
            if p.get("cost_source") == "official_name_rule":
                continue
            has_cost = p.get("cost") is not None and float(p.get("cost") or 0) > 0
            if fill_null:
                if not has_cost and default_cost:
                    p["cost"] = float(default_cost)
                    p["cost_source"] = "official_default_fill"
            else:
                if default_cost:
                    p["cost"] = float(default_cost)
                    p["cost_source"] = "official_default_force"
            if (p.get("duration") in (None, "", 0)) and default_dur:
                p["duration"] = int(default_dur)

        inst["with_cost"] = sum(1 for p in programs if p.get("cost") is not None)
        inst["programs_count"] = len(programs)
        inst["tuition_override"] = conf.get("note") or True

    payload["total_programs"] = sum(i.get("programs_count") or 0 for i in payload["institutions"])
    payload["total_with_cost"] = sum(i.get("with_cost") or 0 for i in payload["institutions"])
    payload["ok_count"] = sum(1 for i in payload["institutions"] if i.get("ok"))
    payload["finalized_at"] = datetime.now(timezone.utc).isoformat()
    payload["source"] = "vipusknik+official_sites+tuition_overrides"
    return payload


def main() -> None:
    print("1) rebuild card_programs from vipusknik/official…")
    run_build()

    payload = json.loads(CARD.read_text(encoding="utf-8"))
    overrides = json.loads(OVERRIDES.read_text(encoding="utf-8"))
    print("2) apply tuition overrides…")
    payload = apply_overrides(payload, overrides)

    FINAL.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(
        f"OK institutions={payload['ok_count']}/{payload['count']} "
        f"programs={payload['total_programs']} with_cost={payload['total_with_cost']}"
    )
    print(f"→ {FINAL}")

    # top summary
    for iid in (1, 2, 7, 9, 63, 82, 84, 91):
        row = next((i for i in payload["institutions"] if i.get("institution_id") == iid), None)
        if not row:
            continue
        print(
            f"  #{iid} {row['name'][:40]:40} n={row.get('programs_count')} "
            f"costed={row.get('with_cost')}"
        )


if __name__ == "__main__":
    main()
