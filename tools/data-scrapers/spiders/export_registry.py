"""
Экспорт реестра Астаны в формат, близкий к institutions + заготовкам specialties.
"""
from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
REGISTRY = ROOT / "data" / "astana_universities.json"
OUT = ROOT / "output" / "astana_institutions.json"


def to_institution_row(u: dict) -> dict:
    return {
        "slug": u.get("slug"),
        "name": u["name"],
        "description1": u.get("notes") or u.get("directions"),
        "description2": None,
        "description3": None,
        "location": u.get("location") or "Астана, Казахстан",
        "address": u.get("address"),
        "latitude": u.get("latitude"),
        "longitude": u.get("longitude"),
        "email": u.get("email"),
        "phone": u.get("phone"),
        "website": u.get("website"),
        "logo_url": None,
        "photo_url": None,
        "type": u.get("type") or "university",
        "directions": u.get("directions"),
        "dormitory": u.get("dormitory"),
        "grants": u.get("grants"),
        "verified": u.get("verified") or "pending",
        "admission_url": u.get("admission_url"),
        "scrape_priority": u.get("scrape_priority"),
        "in_seed": u.get("in_seed", False),
        "specialties": [],  # заполняется отдельными spider'ами
        "scraped_at": datetime.now(timezone.utc).isoformat(),
    }


def main() -> None:
    data = json.loads(REGISTRY.read_text(encoding="utf-8"))
    rows = [to_institution_row(u) for u in data["universities"]]
    OUT.parent.mkdir(parents=True, exist_ok=True)
    payload = {
        "city": "Астана",
        "count": len(rows),
        "with_coords": sum(1 for r in rows if r["latitude"] and r["longitude"]),
        "with_website": sum(1 for r in rows if r["website"]),
        "missing_coords": [r["name"] for r in rows if not r["latitude"]],
        "missing_website": [r["name"] for r in rows if not r["website"]],
        "institutions": rows,
        "db_mapping": {
            "institutions": [
                "name", "description1", "description2", "description3",
                "location", "address", "latitude", "longitude",
                "email", "phone", "website", "logo_url", "photo_url",
                "type", "directions", "dormitory", "grants", "verified",
            ],
            "institution_specialties": ["cost", "duration", "university_specialization_id"],
            "specializations": ["name", "qualification_id", "description"],
        },
    }
    OUT.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Wrote {OUT} ({payload['count']} unis, coords={payload['with_coords']})")
    if payload["missing_coords"]:
        print("Missing coords:")
        for n in payload["missing_coords"]:
            print(f"  - {n}")
    if payload["missing_website"]:
        print("Missing website:")
        for n in payload["missing_website"]:
            print(f"  - {n}")


if __name__ == "__main__":
    main()
