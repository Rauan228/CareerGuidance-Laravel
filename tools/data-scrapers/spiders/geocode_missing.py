"""
Добивает latitude/longitude через OpenStreetMap Nominatim.
Не парсит Google Maps. Rate limit: 1 req/sec.
"""
from __future__ import annotations

import json
import time
from pathlib import Path

import requests

ROOT = Path(__file__).resolve().parents[1]
REGISTRY = ROOT / "data" / "astana_universities.json"
OUT = ROOT / "output" / "astana_universities_geocoded.json"

UA = "TorapCareerGuidance/1.0 (education platform; contact: local-dev)"
NOMINATIM = "https://nominatim.openstreetmap.org/search"


def geocode(address: str) -> tuple[float | None, float | None]:
    if not address:
        return None, None
    r = requests.get(
        NOMINATIM,
        params={"q": address, "format": "json", "limit": 1, "countrycodes": "kz"},
        headers={"User-Agent": UA},
        timeout=30,
    )
    r.raise_for_status()
    data = r.json()
    if not data:
        # fallback: city + name not used here; try soft address
        r2 = requests.get(
            NOMINATIM,
            params={"q": f"{address}, Astana, Kazakhstan", "format": "json", "limit": 1},
            headers={"User-Agent": UA},
            timeout=30,
        )
        r2.raise_for_status()
        data = r2.json()
        time.sleep(1)
    if not data:
        return None, None
    return float(data[0]["lat"]), float(data[0]["lon"])


def main() -> None:
    payload = json.loads(REGISTRY.read_text(encoding="utf-8"))
    updated = 0
    for u in payload["universities"]:
        if u.get("latitude") and u.get("longitude"):
            continue
        addr = u.get("address") or f"{u.get('name')}, Астана"
        print(f"Geocoding: {u['name']} ...")
        try:
            lat, lon = geocode(addr)
            time.sleep(1.1)
            if lat and lon:
                u["latitude"] = lat
                u["longitude"] = lon
                u["geocode_source"] = "nominatim"
                updated += 1
                print(f"  OK {lat}, {lon}")
            else:
                print("  NOT FOUND")
        except Exception as e:
            print(f"  ERR {e}")
            time.sleep(1.1)

    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Updated {updated} universities → {OUT}")


if __name__ == "__main__":
    main()
