"""
B) Scaffold парсеров топ-6 официальных сайтов Астаны.

Сейчас: health-check + сохранение HTML «контакт/поступление» страниц
для дальнейшей тонкой настройки (у каждого вуза свой DOM).

Запуск:
  python spiders/official_top6_spider.py
"""
from __future__ import annotations

import json
import time
from datetime import datetime, timezone
from pathlib import Path

import requests

ROOT = Path(__file__).resolve().parents[1]
OUT_DIR = ROOT / "output" / "official_top6"
OUT_JSON = ROOT / "output" / "official_top6_status.json"

UA = {
    "User-Agent": "TorapCareerGuidance/1.0 (education data collection; local-dev)",
    "Accept-Language": "ru-RU,ru;q=0.9",
}

# Официальные точки входа (приоритет 1)
TARGETS = [
    {
        "slug": "enu",
        "name": "ЕНУ им. Л.Н. Гумилева",
        "home": "https://enu.kz/ru",
        "pages": [
            "https://enu.kz/ru",
            "https://enu.kz/ru/page/for-applicants",
        ],
    },
    {
        "slug": "nu",
        "name": "Nazarbayev University",
        "home": "https://nu.edu.kz/ru",
        "pages": [
            "https://nu.edu.kz/ru",
            "https://nu.edu.kz/ru/admissions",
        ],
    },
    {
        "slug": "kazatu",
        "name": "КазАТИУ Сейфуллина",
        "home": "https://kazatu.edu.kz/ru",
        "pages": ["https://kazatu.edu.kz/ru"],
    },
    {
        "slug": "amu",
        "name": "Медицинский университет Астана",
        "home": "https://amu.edu.kz/ru",
        "pages": [
            "https://amu.edu.kz/ru",
            "https://admission.amu.kz",
        ],
    },
    {
        "slug": "mnu",
        "name": "Maqsut Narikbayev University",
        "home": "https://mnu.kz/ru",
        "pages": ["https://mnu.kz/ru"],
    },
    {
        "slug": "aitu",
        "name": "Astana IT University",
        "home": "https://astanait.edu.kz",
        "pages": ["https://astanait.edu.kz"],
    },
]


def fetch(url: str) -> tuple[int | None, str | None, str | None]:
    try:
        r = requests.get(url, headers=UA, timeout=35, allow_redirects=True)
        return r.status_code, r.url, r.text
    except Exception as e:
        return None, None, str(e)


def main() -> None:
    OUT_DIR.mkdir(parents=True, exist_ok=True)
    results = []
    for t in TARGETS:
        entry = {
            "slug": t["slug"],
            "name": t["name"],
            "home": t["home"],
            "pages": [],
            "checked_at": datetime.now(timezone.utc).isoformat(),
        }
        print(f"== {t['name']}")
        for url in t["pages"]:
            code, final, body = fetch(url)
            time.sleep(0.8)
            ok = code == 200 and body and len(body) > 500
            page_info = {
                "url": url,
                "final_url": final,
                "status": code,
                "ok": bool(ok),
                "bytes": len(body) if body and ok else 0,
            }
            if ok:
                safe = url.replace("https://", "").replace("http://", "").replace("/", "_")[:80]
                path = OUT_DIR / f"{t['slug']}__{safe}.html"
                path.write_text(body, encoding="utf-8", errors="replace")
                page_info["saved"] = str(path)
                print(f"  {code} {url} ({page_info['bytes']} bytes)")
            else:
                page_info["error"] = body if code is None else None
                print(f"  FAIL {code} {url}")
            entry["pages"].append(page_info)
        results.append(entry)

    OUT_JSON.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    ok_pages = sum(1 for r in results for p in r["pages"] if p.get("ok"))
    total = sum(len(r["pages"]) for r in results)
    print(f"\nOK pages {ok_pages}/{total} → {OUT_JSON}")
    print("Next: написать per-site extractors в spiders/official_parsers/<slug>.py")


if __name__ == "__main__":
    main()
