"""
feapder AirSpider — базовая проверка доступности сайтов вузов Астаны.

Запуск:
  pip install feapder
  python spiders/astana_registry_spider.py

Дальше: на каждый топ-вуз — отдельный spider с правилами парсинга ОП/цен.
"""
from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path

try:
    import feapder
except ImportError:
    feapder = None

ROOT = Path(__file__).resolve().parents[1]
REGISTRY = ROOT / "data" / "astana_universities.json"
OUT = ROOT / "output" / "site_health.json"


def load_sites() -> list[dict]:
    data = json.loads(REGISTRY.read_text(encoding="utf-8"))
    sites = []
    for u in data["universities"]:
        if u.get("website"):
            sites.append({"slug": u["slug"], "name": u["name"], "url": u["website"]})
    return sites


if feapder is not None:

    class AstanaSiteHealthSpider(feapder.AirSpider):
        __custom_setting__ = dict(
            SPIDER_THREAD_COUNT=2,
            SPIDER_MAX_RETRY_TIMES=2,
            REQUEST_TIMEOUT=25,
            LOG_LEVEL="INFO",
        )

        def __init__(self, *args, **kwargs):
            super().__init__(*args, **kwargs)
            self.results: list[dict] = []

        def start_requests(self):
            for site in load_sites():
                yield feapder.Request(
                    site["url"],
                    slug=site["slug"],
                    name=site["name"],
                    filter_repeat=False,
                )

        def parse(self, request, response):
            self.results.append(
                {
                    "slug": request.slug,
                    "name": request.name,
                    "url": request.url,
                    "status": response.status_code if response else None,
                    "ok": bool(response and response.status_code == 200),
                    "title": (response.xpath("//title/text()").get() or "").strip()
                    if response
                    else None,
                    "checked_at": datetime.now(timezone.utc).isoformat(),
                }
            )

        def end_callback(self):
            OUT.parent.mkdir(parents=True, exist_ok=True)
            OUT.write_text(
                json.dumps(self.results, ensure_ascii=False, indent=2),
                encoding="utf-8",
            )
            ok = sum(1 for r in self.results if r["ok"])
            print(f"Health: {ok}/{len(self.results)} OK → {OUT}")


def main_without_feapder() -> None:
    """Fallback без feapder: простой requests-check."""
    import requests

    results = []
    for site in load_sites():
        try:
            r = requests.get(site["url"], timeout=20, headers={"User-Agent": "TorapBot/1.0"})
            results.append(
                {
                    **site,
                    "status": r.status_code,
                    "ok": r.status_code == 200,
                    "checked_at": datetime.now(timezone.utc).isoformat(),
                }
            )
            print(f"{r.status_code} {site['url']}")
        except Exception as e:
            results.append(
                {
                    **site,
                    "status": None,
                    "ok": False,
                    "error": str(e),
                    "checked_at": datetime.now(timezone.utc).isoformat(),
                }
            )
            print(f"ERR {site['url']}: {e}")
    OUT.parent.mkdir(parents=True, exist_ok=True)
    OUT.write_text(json.dumps(results, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"Wrote {OUT}")


if __name__ == "__main__":
    if feapder is None:
        print("feapder not installed — using requests fallback")
        main_without_feapder()
    else:
        AstanaSiteHealthSpider().start()
