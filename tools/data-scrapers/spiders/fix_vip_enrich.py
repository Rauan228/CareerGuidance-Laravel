"""Повторно обогащает vipusknik_astana.json (website/coords) без повторного краула."""
from __future__ import annotations

import json
from pathlib import Path

from vipusknik_astana_spider import VIP_TO_REGISTRY, enrich_from_registry

ROOT = Path(__file__).resolve().parents[1]
SRC = ROOT / "output" / "vipusknik_astana.json"
REG = ROOT / "data" / "astana_universities.json"


def main() -> None:
    data = json.loads(SRC.read_text(encoding="utf-8"))
    registry = json.loads(REG.read_text(encoding="utf-8"))
    fixed = 0
    for row in data["institutions"]:
        if row.get("website") and "zero.kz" in (row.get("website") or ""):
            row["website"] = None
        # сбрасываем старый кривой маппинг
        row["registry_slug"] = None
        enrich_from_registry(row, registry)
        fixed += 1
    SRC.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Fixed/enriched → {SRC}")
    for row in data["institutions"]:
        print(
            f"  {row.get('name')[:40]:40} reg={row.get('registry_slug')} web={row.get('website')}"
        )


if __name__ == "__main__":
    main()
