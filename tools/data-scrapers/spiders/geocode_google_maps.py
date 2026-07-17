"""
Геокодинг вузов: Google Maps (поиск name+address) → fallback Nominatim.

Запуск:
  python spiders/geocode_google_maps.py
  python spiders/geocode_google_maps.py --only-missing
  python spiders/geocode_google_maps.py --limit 5

Вход:  output/institutions_for_geocode.json
Выход: output/geocode_results.json
"""
from __future__ import annotations

import argparse
import json
import re
import time
from pathlib import Path
from urllib.parse import quote_plus

import requests

ROOT = Path(__file__).resolve().parents[1]
IN_PATH = ROOT / "output" / "institutions_for_geocode.json"
OUT_PATH = ROOT / "output" / "geocode_results.json"

UA = (
    "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
)
SESSION = requests.Session()
SESSION.headers.update(
    {
        "User-Agent": UA,
        "Accept-Language": "ru-RU,ru;q=0.9,en;q=0.8",
        "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
    }
)

# Kazakhstan rough bbox
LAT_MIN, LAT_MAX = 40.0, 56.0
LNG_MIN, LNG_MAX = 46.0, 88.0


def city_of(row: dict) -> str:
    t = f"{row.get('location') or ''} {row.get('address') or ''}".lower()
    if "алматы" in t or "almaty" in t:
        return "Алматы"
    if "астана" in t or "astana" in t or "нур-султан" in t:
        return "Астана"
    if "шымкент" in t or "shymkent" in t:
        return "Шымкент"
    loc = row.get("location") or ""
    return loc.split(",")[0].strip() if loc else "Казахстан"


def build_queries(row: dict) -> list[str]:
    name = (row.get("name") or "").strip()
    city = city_of(row)
    addr = (row.get("address") or "").strip()
    qs = []
    if name:
        qs.append(f"{name}, {city}, Казахстан")
        qs.append(f"{name} {city}")
    if addr and len(addr) < 120 and name:
        qs.append(f"{name}, {addr}, Казахстан")
    if addr and len(addr) < 80:
        qs.append(f"{addr}, {city}, Казахстан")
    # unique preserve order
    seen = set()
    out = []
    for q in qs:
        if q not in seen:
            seen.add(q)
            out.append(q)
    return out


def in_kz(lat: float, lng: float) -> bool:
    return LAT_MIN <= lat <= LAT_MAX and LNG_MIN <= lng <= LNG_MAX


def extract_coords_from_text(text: str) -> tuple[float, float] | None:
    if not text:
        return None
    # !3dLAT!4dLNG  (most reliable in maps HTML)
    m = re.search(r"!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)", text)
    if m:
        lat, lng = float(m.group(1)), float(m.group(2))
        if in_kz(lat, lng):
            return lat, lng
    # @lat,lng,zoom
    for m in re.finditer(r"@(-?\d+\.\d+),(-?\d+\.\d+)(?:,\d+[.\d]*z)?", text):
        lat, lng = float(m.group(1)), float(m.group(2))
        if in_kz(lat, lng):
            return lat, lng
    # center=lat%2Clng
    m = re.search(r"center=(-?\d+\.\d+)%2C(-?\d+\.\d+)", text)
    if m:
        lat, lng = float(m.group(1)), float(m.group(2))
        if in_kz(lat, lng):
            return lat, lng
    # "/maps/preview/place/.../@lat,lng"
    m = re.search(r"/maps/place/[^\"']*@(-?\d+\.\d+),(-?\d+\.\d+)", text)
    if m:
        lat, lng = float(m.group(1)), float(m.group(2))
        if in_kz(lat, lng):
            return lat, lng
    return None


def geocode_google_maps(query: str) -> dict | None:
    """Resolve coords via Google Maps search page/URL."""
    url = "https://www.google.com/maps/search/?api=1&query=" + quote_plus(query)
    try:
        r = SESSION.get(url, timeout=35, allow_redirects=True)
    except Exception as e:
        return {"error": str(e)}

    coords = extract_coords_from_text(r.url) or extract_coords_from_text(r.text[:500000])
    if coords:
        return {
            "lat": coords[0],
            "lng": coords[1],
            "source": "google_maps",
            "query": query,
            "final_url": r.url[:300],
        }

    # secondary endpoint style
    url2 = "https://www.google.com/maps?q=" + quote_plus(query) + "&hl=ru"
    try:
        r2 = SESSION.get(url2, timeout=35, allow_redirects=True)
        coords = extract_coords_from_text(r2.url) or extract_coords_from_text(r2.text[:500000])
        if coords:
            return {
                "lat": coords[0],
                "lng": coords[1],
                "source": "google_maps",
                "query": query,
                "final_url": r2.url[:300],
            }
    except Exception as e:
        return {"error": str(e)}

    return None


def geocode_nominatim(query: str) -> dict | None:
    try:
        r = SESSION.get(
            "https://nominatim.openstreetmap.org/search",
            params={
                "q": query,
                "format": "json",
                "limit": 5,
                "countrycodes": "kz",
            },
            headers={"User-Agent": "TorapCareerGuidance/1.0 (geocoding; contact local-dev)"},
            timeout=30,
        )
        r.raise_for_status()
        items = r.json()
    except Exception as e:
        return {"error": str(e)}

    if not items:
        return None

    best = items[0]
    for it in items:
        blob = f"{it.get('type','')} {it.get('class','')} {it.get('display_name','')}".lower()
        if any(k in blob for k in ("university", "college", "campus", "school", "университет", "институт", "академия")):
            best = it
            break

    lat, lng = float(best["lat"]), float(best["lon"])
    if not in_kz(lat, lng):
        return None
    return {
        "lat": lat,
        "lng": lng,
        "source": "nominatim",
        "query": query,
        "display": best.get("display_name"),
    }


def geocode_row(row: dict) -> dict:
    result = {
        "id": row["id"],
        "name": row["name"],
        "old_lat": row.get("latitude"),
        "old_lng": row.get("longitude"),
    }
    for q in build_queries(row):
        g = geocode_google_maps(q)
        time.sleep(1.2)
        if g and g.get("lat") is not None:
            result.update(g)
            result["ok"] = True
            return result
        # if hard error on first google try, still try nominatim later

    for q in build_queries(row):
        n = geocode_nominatim(q)
        time.sleep(1.1)
        if n and n.get("lat") is not None:
            result.update(n)
            result["ok"] = True
            return result

    result["ok"] = False
    result["error"] = "not_found"
    return result


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--only-missing", action="store_true")
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--force", action="store_true", help="re-geocode even if coords exist")
    args = ap.parse_args()

    rows = json.loads(IN_PATH.read_text(encoding="utf-8"))
    if args.only_missing and not args.force:
        rows = [
            r
            for r in rows
            if r.get("latitude") in (None, "", 0, "0")
            or r.get("longitude") in (None, "", 0, "0")
        ]
    if args.limit:
        rows = rows[: args.limit]

    print(f"Geocoding {len(rows)} institutions…")
    results = []
    for i, row in enumerate(rows, 1):
        print(f"[{i}/{len(rows)}] #{row['id']} {row['name']}")
        res = geocode_row(row)
        if res.get("ok"):
            print(f"  OK {res['lat']:.6f},{res['lng']:.6f} via {res.get('source')}")
        else:
            print(f"  FAIL {res.get('error')}")
        results.append(res)
        # checkpoint
        if i % 5 == 0:
            OUT_PATH.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")

    OUT_PATH.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    ok = sum(1 for r in results if r.get("ok"))
    print(f"\nDone: {ok}/{len(results)} → {OUT_PATH}")


if __name__ == "__main__":
    main()
