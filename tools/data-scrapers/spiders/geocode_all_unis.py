"""
Геокодинг всех вузов: Nominatim OSM (уникальные точки по name+city+address).

Google Maps HTML без API-ключа отдаёт дефолтный центр карты — не используем.
Если есть GOOGLE_MAPS_API_KEY в env — можно добавить позже.

Запуск:
  python spiders/geocode_all_unis.py
  python spiders/geocode_all_unis.py --only-missing
"""
from __future__ import annotations

import argparse
import json
import os
import re
import time
from pathlib import Path

import requests

ROOT = Path(__file__).resolve().parents[1]
IN_PATH = ROOT / "output" / "institutions_for_geocode.json"
OUT_PATH = ROOT / "output" / "geocode_results.json"

UA = "TorapCareerGuidance/1.0 (education geocoding; university pins for applicants platform)"
SESSION = requests.Session()
SESSION.headers.update({"User-Agent": UA, "Accept-Language": "ru"})

LAT_MIN, LAT_MAX = 40.5, 55.5
LNG_MIN, LNG_MAX = 46.5, 87.5


def city_of(row: dict) -> tuple[str, str]:
    """Return (ru_city, en_city)."""
    t = f"{row.get('location') or ''} {row.get('address') or ''}".lower()
    if "алматы" in t or "almaty" in t:
        return "Алматы", "Almaty"
    if "астана" in t or "astana" in t or "нур-султан" in t or "nur-sultan" in t:
        return "Астана", "Astana"
    if "шымкент" in t or "shymkent" in t:
        return "Шымкент", "Shymkent"
    loc = (row.get("location") or "").split(",")[0].strip()
    return loc or "Казахстан", loc or "Kazakhstan"


def clean_name(name: str) -> str:
    n = re.sub(r"\s+", " ", (name or "").strip())
    # drop quotes noise
    n = n.replace("«", "").replace("»", "").replace('"', "")
    return n


def queries_for(row: dict) -> list[str]:
    name = clean_name(row.get("name") or "")
    city_ru, city_en = city_of(row)
    addr = (row.get("address") or "").strip()
    qs: list[str] = []

    if name:
        qs.append(f"{name}, {city_ru}, Казахстан")
        qs.append(f"{name}, {city_en}, Kazakhstan")
        qs.append(f"{name} {city_en}")

    # short address only
    if addr and 8 < len(addr) < 90 and not addr.lower().startswith(name.lower()[:20].lower()):
        qs.append(f"{addr}, {city_ru}, Казахстан")

    # known aliases boost
    aliases = {
        "назарбаев": "Nazarbayev University Astana",
        "гумил": "L.N. Gumilyov Eurasian National University Astana",
        "сейфулл": "Saken Seifullin Kazakh Agrotechnical University Astana",
        "astana it": "Astana IT University",
        "aitu": "Astana IT University",
        "narikbayev": "Maqsut Narikbayev University Astana",
        "казгюу": "Maqsut Narikbayev University Astana",
        "аль-фараби": "Al-Farabi Kazakh National University Almaty",
        "сатпаев": "Satbayev University Almaty",
        "satbayev": "Satbayev University Almaty",
        "кимеп": "KIMEP University Almaty",
        "kimep": "KIMEP University Almaty",
        "нархоз": "Narxoz University Almaty",
        "абай": "Abai Kazakh National Pedagogical University Almaty",
        "асфендияров": "Asfendiyarov Kazakh National Medical University Almaty",
        "iitu": "International Information Technology University Almaty",
        "информационных технологий": "International Information Technology University Almaty",
        "sdu": "SDU University Kaskelen",
        "turan": "Turan University Almaty" if "астана" not in name.lower() else "Turan-Astana University",
        "туран-астана": "Turan-Astana University",
        "туран астана": "Turan-Astana University",
    }
    low = name.lower()
    for needle, alias in aliases.items():
        if needle in low:
            qs.insert(0, alias)

    seen = set()
    out = []
    for q in qs:
        if q and q not in seen:
            seen.add(q)
            out.append(q)
    return out


def in_kz(lat: float, lng: float) -> bool:
    return LAT_MIN <= lat <= LAT_MAX and LNG_MIN <= lng <= LNG_MAX


def city_bbox_ok(lat: float, lng: float, city_en: str) -> bool:
    """Loose city bbox to reject wrong-city matches."""
    if city_en == "Astana":
        return 50.9 <= lat <= 51.35 and 71.2 <= lng <= 71.7
    if city_en == "Almaty":
        # include Kaskelen/SDU
        return 43.05 <= lat <= 43.45 and 76.55 <= lng <= 77.15
    if city_en == "Shymkent":
        return 42.2 <= lat <= 42.5 and 69.4 <= lng <= 69.8
    return True


def nominatim(query: str, city_en: str) -> dict | None:
    r = SESSION.get(
        "https://nominatim.openstreetmap.org/search",
        params={
            "q": query,
            "format": "json",
            "limit": 8,
            "countrycodes": "kz",
            "addressdetails": 1,
        },
        timeout=35,
    )
    r.raise_for_status()
    items = r.json()
    if not items:
        return None

    scored = []
    for it in items:
        lat, lng = float(it["lat"]), float(it["lon"])
        if not in_kz(lat, lng):
            continue
        # жёстко отбрасываем точки вне города (Q University → Павлодар и т.п.)
        if city_en in ("Astana", "Almaty", "Shymkent") and not city_bbox_ok(lat, lng, city_en):
            continue
        score = 10
        blob = f"{it.get('type','')} {it.get('class','')} {it.get('display_name','')}".lower()
        if any(k in blob for k in ("university", "college", "campus", "school", "университет", "институт", "академия")):
            score += 5
        if it.get("class") == "amenity" and it.get("type") == "university":
            score += 8
        if it.get("class") == "building" and "university" in (it.get("type") or ""):
            score += 4
        scored.append((score, it, lat, lng))

    if not scored:
        return None
    scored.sort(key=lambda x: x[0], reverse=True)
    score, it, lat, lng = scored[0]
    if city_en in ("Astana", "Almaty", "Shymkent") and not city_bbox_ok(lat, lng, city_en):
        return None

    return {
        "lat": lat,
        "lng": lng,
        "source": "nominatim",
        "query": query,
        "display": it.get("display_name"),
        "score": score,
    }


def google_geocode_api(query: str, key: str, city_en: str) -> dict | None:
    r = SESSION.get(
        "https://maps.googleapis.com/maps/api/geocode/json",
        params={"address": query, "key": key, "language": "ru", "region": "kz"},
        timeout=30,
    )
    r.raise_for_status()
    data = r.json()
    if data.get("status") != "OK" or not data.get("results"):
        return None
    loc = data["results"][0]["geometry"]["location"]
    lat, lng = float(loc["lat"]), float(loc["lng"])
    if not in_kz(lat, lng):
        return None
    if not city_bbox_ok(lat, lng, city_en):
        # try next results
        for res in data["results"][1:4]:
            loc = res["geometry"]["location"]
            lat2, lng2 = float(loc["lat"]), float(loc["lng"])
            if city_bbox_ok(lat2, lng2, city_en):
                return {
                    "lat": lat2,
                    "lng": lng2,
                    "source": "google_geocoding_api",
                    "query": query,
                    "display": res.get("formatted_address"),
                }
        return None
    return {
        "lat": lat,
        "lng": lng,
        "source": "google_geocoding_api",
        "query": query,
        "display": data["results"][0].get("formatted_address"),
    }


# hand-verified / well-known pins (fallback if geocoder fails)
KNOWN: dict[str, tuple[float, float]] = {
    # Astana (seed / maps-verified area)
    "назарбаев": (51.09067, 71.39816),
    "гумил": (51.15831, 71.46734),
    "сейфулл": (51.18732, 71.40906),
    "медицинский университет астана": (51.18177, 71.41642),
    "astana it": (51.09090, 71.41817),
    "narikbayev": (51.14839, 71.37944),
    "казгюу": (51.14839, 71.37944),
    "туран-астана": (51.18188, 71.43151),
    "esil": (51.1695, 71.4168),
    "евразийский гуманитарный": (51.1750, 71.4300),
    "кусаинов": (51.1750, 71.4300),
    "alem": (51.1350, 71.4100),
    "kuef": (51.14384, 71.42743),
    "экономики, финансов": (51.14384, 71.42743),
    "государственного управления": (51.16833, 71.42026),
    "международный университет астан": (51.14484, 71.42293),
    "технологии и бизнеса": (51.14483, 71.36904),
    "хореограф": (51.09907, 71.42030),
    "казнуи": (51.12293, 71.47330),
    "национальный университет искусств": (51.12293, 71.47330),
    "ломоносов": (51.15581, 71.46937),
    "казпотребсоюз": (51.16679, 71.44971),
    "обороны": (51.1450, 71.4700),
    "cardiff": (51.13126, 71.37186),
    "coventry": (51.1280, 71.4150),
    "университет спорта": (51.05957, 71.38821),
    "mgimo": (51.1200, 71.4300),
    "мгимо": (51.1200, 71.4300),
    # Almaty
    "аль-фараби": (43.2245, 76.9235),
    "satbayev": (43.2365, 76.9307),
    "сатпаев": (43.2365, 76.9307),
    "кимеп": (43.2416, 76.9553),
    "kimep": (43.2416, 76.9553),
    "нархоз": (43.2149, 76.8706),
    "информационных технологий": (43.2300, 76.9099),
    "абай": (43.2563, 76.9534),
    "асфендияров": (43.2530, 76.9312),
    "sdu": (43.2070, 76.6690),
    "metu": (43.2380, 76.9050),
    "инженерно-технологический": (43.2380, 76.9050),
    "de montfort": (43.2385, 76.9450),
    "dmuk": (43.2385, 76.9450),
    "нур-мубарак": (43.2250, 76.9100),
    "нур мубарак": (43.2250, 76.9100),
    "гончаров": (43.2600, 76.9300),
    "автомобильно-дорожный": (43.2600, 76.9300),
    "кунаев": (43.2500, 76.9400),
    "uib": (43.2380, 76.9450),
    "международного бизнеса": (43.2380, 76.9450),
    "сагадиев": (43.2380, 76.9450),
    "транспортно-гуманитарный": (43.2500, 76.9200),
    "мифи": (43.2400, 76.9100),
    "пограничной службы": (43.2500, 76.9000),
    "q» university": (43.2400, 76.9200),
    "q university": (43.2400, 76.9200),
    "синергия": (43.2500, 76.9400),
}


def known_pin(name: str, city_en: str) -> dict | None:
    low = clean_name(name).lower()
    # city-specific overrides first
    if city_en == "Astana":
        if "синергия" in low:
            return {"lat": 51.1280, "lng": 71.4300, "source": "known_pin", "query": "sinergiya-astana", "display": name}
        if "esil" in low:
            return {"lat": 51.1695, "lng": 71.4168, "source": "known_pin", "query": "esil", "display": name}
    if city_en == "Almaty" and "синергия" in low:
        return {"lat": 43.2500, "lng": 76.9400, "source": "known_pin", "query": "sinergiya-almaty", "display": name}

    for needle, (lat, lng) in KNOWN.items():
        if needle in low:
            # skip almaty-only pins for astana names and vice versa
            if city_en == "Astana" and lat < 45:
                continue
            if city_en == "Almaty" and lat > 50:
                continue
            return {
                "lat": lat,
                "lng": lng,
                "source": "known_pin",
                "query": needle,
                "display": name,
            }
    return None


def geocode_row(row: dict, google_key: str | None) -> dict:
    res = {
        "id": row["id"],
        "name": row["name"],
        "old_lat": row.get("latitude"),
        "old_lng": row.get("longitude"),
    }
    _, city_en = city_of(row)

    # 1) Google official API if key
    if google_key:
        for q in queries_for(row):
            try:
                g = google_geocode_api(q, google_key, city_en)
                time.sleep(0.15)
                if g:
                    res.update(g)
                    res["ok"] = True
                    return res
            except Exception as e:
                res["google_err"] = str(e)
                time.sleep(0.3)

    # 2) Nominatim
    for q in queries_for(row):
        try:
            n = nominatim(q, city_en)
            time.sleep(1.1)
            if n:
                res.update(n)
                res["ok"] = True
                return res
        except Exception as e:
            res["nominatim_err"] = str(e)
            time.sleep(1.1)

    # 3) known pins
    k = known_pin(row.get("name") or "", city_en)
    if k:
        res.update(k)
        res["ok"] = True
        return res

    # 4) keep old coords only if in correct city AND not the polluted Google default
    BAD_DEFAULTS = {
        (51.09813, 71.42552),
        (51.09067, 71.39816),  # often NU pin wrongly copied
    }
    try:
        olat = float(row["latitude"]) if row.get("latitude") not in (None, "") else None
        olng = float(row["longitude"]) if row.get("longitude") not in (None, "") else None
    except (TypeError, ValueError):
        olat = olng = None
    if olat and olng and in_kz(olat, olng) and city_bbox_ok(olat, olng, city_en):
        rounded = (round(olat, 5), round(olng, 5))
        # allow NU itself to keep NU pin
        is_nu = "назарбаев" in (row.get("name") or "").lower()
        if rounded not in BAD_DEFAULTS or is_nu:
            # still skip if this is clearly NU pin on non-NU
            if not is_nu and abs(olat - 51.09067) < 0.0002 and abs(olng - 71.39816) < 0.0002:
                pass
            else:
                res.update({"lat": olat, "lng": olng, "source": "existing", "ok": True})
                return res

    res["ok"] = False
    res["error"] = "not_found"
    return res


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--only-missing", action="store_true")
    ap.add_argument("--limit", type=int, default=0)
    args = ap.parse_args()

    google_key = os.environ.get("GOOGLE_MAPS_API_KEY") or os.environ.get("GOOGLE_GEOCODING_API_KEY")
    rows = json.loads(IN_PATH.read_text(encoding="utf-8"))
    if args.only_missing:
        rows = [
            r
            for r in rows
            if r.get("latitude") in (None, "", 0, "0")
            or r.get("longitude") in (None, "", 0, "0")
        ]
    if args.limit:
        rows = rows[: args.limit]

    print(f"Geocoding {len(rows)} unis (google_key={'yes' if google_key else 'no'})…")
    results = []
    for i, row in enumerate(rows, 1):
        print(f"[{i}/{len(rows)}] #{row['id']} {row['name']}")
        r = geocode_row(row, google_key)
        if r.get("ok"):
            print(f"  OK {r['lat']:.6f},{r['lng']:.6f} via {r.get('source')} score={r.get('score')}")
        else:
            print(f"  FAIL {r.get('error')}")
        results.append(r)
        if i % 5 == 0:
            OUT_PATH.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")

    OUT_PATH.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    ok = sum(1 for r in results if r.get("ok"))
    sources = {}
    for r in results:
        if r.get("ok"):
            sources[r.get("source")] = sources.get(r.get("source"), 0) + 1
    # uniqueness check
    pts = [(round(r["lat"], 5), round(r["lng"], 5)) for r in results if r.get("ok")]
    unique = len(set(pts))
    print(f"\nDone {ok}/{len(results)} unique_points={unique} sources={sources}")
    print(f"→ {OUT_PATH}")


if __name__ == "__main__":
    main()
