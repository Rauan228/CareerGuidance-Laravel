"""
Краулинг ОФИЦИАЛЬНЫХ сайтов вузов: специальности / ОП / квалификации / цены.

Стратегия:
  1) homepage → ссылки (абитуриент, специальности, бакалавриат, стоимость…)
  2) fetch до N страниц
  3) извлечь ОП (коды 6B/6В, названия) + стоимость + срок
  4) если цены нет — cost=null (на фронте «-»)

Запуск:
  python spiders/official_programs_spider.py
  python spiders/official_programs_spider.py --limit 5
  python spiders/official_programs_spider.py --ids 1,2,9

Выход: output/official_programs.json
"""
from __future__ import annotations

import argparse
import json
import re
import time
from collections import defaultdict
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin, urlparse

import requests
from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parents[1]
IN_PATH = ROOT / "output" / "institutions_for_official_scrape.json"
OUT_PATH = ROOT / "output" / "official_programs.json"

UA = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
        "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
    ),
    "Accept-Language": "ru-RU,ru;q=0.9,kk;q=0.8,en;q=0.7",
    "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
}

LINK_HINTS = re.compile(
    r"(абитуриент|поступ|admission|applicant|бакалавр|bachelor|"
    r"специальн|образовательн\w*\s*програм|program|tuition|стоимост|"
    r"price|fee|прайс|образован|faculty|факультет|epvo|univer|"
    r"catalog|каталог|оплата|платн)",
    re.I,
)

CODE_RE = re.compile(r"(6[BbВв][0-9A-Za-zА-Яа-я]{2,6})")
MONEY_RE = re.compile(
    r"(?:стоимость|цена|оплата|tuition|fee|платн\w*)[^\d]{0,40}"
    r"([\d\s\u00a0]{3,9})\s*(?:тг|тенге|₸|tg|kzt)?",
    re.I,
)
MONEY_ANY_RE = re.compile(r"([\d\s\u00a0]{4,9})\s*(?:тг|тенге|₸)\b", re.I)
YEAR_RE = re.compile(r"(\d)\s*(?:год|года|лет|years?|г\.)", re.I)

# per-host extra seed paths (relative)
HOST_SEEDS: dict[str, list[str]] = {
    "nu.edu.kz": ["/ru/admissions", "/ru/schools", "/admissions"],
    "enu.kz": ["/ru", "/ru/page/applicants", "/ru/page/bachelor"],
    "astanait.edu.kz": ["/", "/ru", "/bachelor", "/admission"],
    "mnu.kz": ["/ru", "/ru/admission", "/admission"],
    "amu.edu.kz": ["/ru", "/ru/applicant", "/ru/education"],
    "kazatu.edu.kz": ["/ru", "/ru/applicant"],
    "aiu.kz": ["/", "/admission", "/programs"],
    "mui.kz": ["/", "/admission"],
    "tau-edu.kz": ["/", "/admission", "/bachelor"],
    "kaznui.edu.kz": ["/", "/admission"],
    "satbayev.university": ["/", "/ru", "/admission"],
    "kaznu.kz": ["/ru", "/ru/admission", "/ru/education"],
    "farabi.university": ["/", "/admission"],
    "kbtu.kz": ["/", "/admission", "/bachelor"],
    "kimep.kz": ["/", "/admissions", "/academics"],
    "narxoz.kz": ["/", "/admission", "/programs"],
    "iitu.edu.kz": ["/", "/admission"],
    "sdu.edu.kz": ["/", "/admission", "/programs"],
    "turan-edu.kz": ["/", "/admission"],
    "almau.edu.kz": ["/", "/admission", "/programs"],
    "dku.kz": ["/", "/admission"],
    "ablaikhan.kz": ["/", "/admission"],
}


def fetch(url: str, timeout: int = 28) -> tuple[int | None, str | None, str | None]:
    try:
        r = requests.get(url, headers=UA, timeout=timeout, allow_redirects=True)
        # skip non-html
        ctype = (r.headers.get("content-type") or "").lower()
        if "pdf" in ctype or "image" in ctype or "zip" in ctype:
            return r.status_code, r.url, None
        r.encoding = r.apparent_encoding or "utf-8"
        return r.status_code, r.url, r.text
    except Exception as e:
        return None, url, None


def same_host(a: str, b: str) -> bool:
    try:
        ha = urlparse(a).netloc.lower().removeprefix("www.")
        hb = urlparse(b).netloc.lower().removeprefix("www.")
        return ha == hb or ha.endswith("." + hb) or hb.endswith("." + ha)
    except Exception:
        return False


def discover_links(base_url: str, html: str, limit: int = 12) -> list[str]:
    soup = BeautifulSoup(html, "lxml")
    found: list[tuple[int, str]] = []
    for a in soup.find_all("a", href=True):
        href = a["href"].strip()
        if href.startswith(("#", "mailto:", "tel:", "javascript:")):
            continue
        full = urljoin(base_url, href)
        if not full.startswith("http"):
            continue
        if not same_host(base_url, full):
            continue
        # drop files
        if re.search(r"\.(pdf|docx?|xlsx?|zip|rar|jpg|png)(\?|$)", full, re.I):
            # keep PDFs about tuition — separate list
            text = (a.get_text(" ", strip=True) or "") + " " + full
            if LINK_HINTS.search(text):
                found.append((2, full.split("#")[0]))
            continue
        text = (a.get_text(" ", strip=True) or "") + " " + full
        score = 0
        if LINK_HINTS.search(text):
            score += 3
        if re.search(r"бакалавр|6[BbВв]|bachelor|специальн", text, re.I):
            score += 2
        if re.search(r"стоимост|tuition|price|платн", text, re.I):
            score += 2
        if score:
            found.append((score, full.split("#")[0]))

    # host seeds
    host = urlparse(base_url).netloc.lower().removeprefix("www.")
    for h, paths in HOST_SEEDS.items():
        if h in host:
            for p in paths:
                found.append((5, urljoin(base_url, p)))

    # unique by url, sort by score
    best: dict[str, int] = {}
    for sc, u in found:
        best[u] = max(sc, best.get(u, 0))
    ranked = sorted(best.items(), key=lambda x: -x[1])
    return [u for u, _ in ranked[:limit]]


def parse_money(text: str) -> float | None:
    if not text:
        return None
    m = MONEY_RE.search(text)
    if not m:
        m = MONEY_ANY_RE.search(text)
    if not m:
        return None
    raw = re.sub(r"[\s\u00a0]", "", m.group(1))
    if not raw.isdigit():
        return None
    val = int(raw)
    if val < 50000 or val > 50_000_000:
        return None
    return float(val)


def parse_years(text: str) -> int | None:
    m = YEAR_RE.search(text or "")
    if not m:
        return None
    y = int(m.group(1))
    return y if 1 <= y <= 8 else None


def clean_name(s: str) -> str:
    s = re.sub(r"\s+", " ", (s or "").strip())
    s = re.sub(r"^(ОП|образовательная программа|программа)\s*[:\-]?\s*", "", s, flags=re.I)
    return s[:220]


def extract_from_tables(soup: BeautifulSoup) -> list[dict]:
    out = []
    for table in soup.find_all("table"):
        rows = table.find_all("tr")
        headers = []
        if rows:
            headers = [c.get_text(" ", strip=True).lower() for c in rows[0].find_all(["th", "td"])]
        for tr in rows[1:] if headers else rows:
            cells = [c.get_text(" ", strip=True) for c in tr.find_all(["td", "th"])]
            if len(cells) < 1:
                continue
            row_text = " | ".join(cells)
            code = None
            cm = CODE_RE.search(row_text)
            if cm:
                code = cm.group(1).upper().replace("В", "B").replace("в", "B")
                code = code.replace("B", "В") if "6B" in code or "6b" in code else code
                # normalize to 6В
                code = re.sub(r"^6[Bb]", "6В", cm.group(1))

            # pick name cell: longest without pure digits
            name = None
            for c in cells:
                if CODE_RE.fullmatch(c.strip()):
                    continue
                if re.fullmatch(r"[\d\s]+", c or ""):
                    continue
                if len(c) >= 4 and (name is None or len(c) > len(name)):
                    name = c
            if not name and not code:
                continue
            if not name:
                name = f"Программа {code}"
            # skip navigation junk
            if len(name) < 4 or len(name) > 200:
                continue
            if re.search(r"^(главная|меню|вход|login|cookie)", name, re.I):
                continue
            cost = parse_money(row_text)
            duration = parse_years(row_text)
            out.append(
                {
                    "name": clean_name(name),
                    "code": code,
                    "global_specialty": None,
                    "qualification": None,
                    "cost": cost,
                    "duration": duration,
                    "source_hint": "table",
                }
            )
    return out


def extract_from_lists_and_text(soup: BeautifulSoup, page_text: str) -> list[dict]:
    out = []
    # elements that look like program rows
    candidates = []
    for el in soup.find_all(["li", "tr", "p", "div", "td", "a", "h3", "h4"]):
        t = " ".join(el.get_text(" ", strip=True).split())
        if not t or len(t) < 8 or len(t) > 280:
            continue
        if CODE_RE.search(t) or re.search(r"бакалавр|образовательн", t, re.I):
            candidates.append(t)

    # also split page by newlines for PDF-like text dumps
    for line in page_text.split("\n"):
        line = " ".join(line.split())
        if CODE_RE.search(line) and 10 < len(line) < 260:
            candidates.append(line)

    seen = set()
    for t in candidates:
        if t in seen:
            continue
        seen.add(t)
        cm = CODE_RE.search(t)
        code = None
        if cm:
            code = re.sub(r"^6[Bb]", "6В", cm.group(1))
        # name without code/cost noise
        name = CODE_RE.sub("", t)
        name = MONEY_ANY_RE.sub("", name)
        name = re.sub(r"стоимость.*$", "", name, flags=re.I)
        name = clean_name(name)
        if len(name) < 4:
            continue
        if re.search(r"^(меню|главная|cookie|подробнее|читать|скачать)", name, re.I):
            continue
        out.append(
            {
                "name": name,
                "code": code,
                "global_specialty": None,
                "qualification": None,
                "cost": parse_money(t),
                "duration": parse_years(t),
                "source_hint": "text",
            }
        )
    return out


def extract_page_programs(html: str, url: str) -> list[dict]:
    soup = BeautifulSoup(html, "lxml")
    # drop nav/footer noise somewhat
    for tag in soup(["script", "style", "noscript", "svg"]):
        tag.decompose()
    page_text = soup.get_text("\n", strip=True)
    items = extract_from_tables(soup) + extract_from_lists_and_text(soup, page_text)

    # page-level default cost (if single tuition for all)
    page_cost = parse_money(page_text[:8000])
    if page_cost:
        for it in items:
            if it.get("cost") is None:
                # only apply if page looks like tuition page
                if re.search(r"стоимост|tuition|платн", page_text[:3000], re.I):
                    it["cost"] = page_cost
                    it["cost_inherited"] = True

    for it in items:
        it["source_url"] = url
    return items


def dedupe_programs(items: list[dict]) -> list[dict]:
    best: dict[str, dict] = {}
    for it in items:
        name = clean_name(it.get("name") or "")
        if not name:
            continue
        key = (it.get("code") or "") + "||" + name.lower()
        prev = best.get(key)
        if not prev:
            best[key] = it
            continue
        # prefer one with cost / longer meta
        score = (1 if it.get("cost") else 0) + (1 if it.get("duration") else 0) + (1 if it.get("code") else 0)
        pscore = (1 if prev.get("cost") else 0) + (1 if prev.get("duration") else 0) + (1 if prev.get("code") else 0)
        if score > pscore:
            best[key] = it
        elif score == pscore and it.get("cost") and not prev.get("cost"):
            best[key] = it
    # filter obvious junk
    out = []
    for it in best.values():
        n = it["name"].lower()
        if any(x in n for x in ("cookie", "javascript", "error", "404", "privacy", "политик")):
            continue
        if len(it["name"]) < 4:
            continue
        out.append(it)
    return out


def infer_group(item: dict) -> dict:
    """Fill global_specialty / qualification from code prefixes if possible."""
    code = item.get("code") or ""
    # rough groups by 6В0xx first digits (simplified classifier)
    groups = {
        "6В01": "Педагогические науки",
        "6В02": "Искусство и гуманитарные науки",
        "6В03": "Социальные науки, журналистика и информация",
        "6В04": "Бизнес, управление и право",
        "6В05": "Естественные науки, математика и статистика",
        "6В06": "Информационно-коммуникационные технологии",
        "6В07": "Инженерные, обрабатывающие и строительные отрасли",
        "6В08": "Сельское, лесное, рыбное хозяйство",
        "6В09": "Здравоохранение и социальное обеспечение",
        "6В10": "Услуги",
        "6В11": "Национальная безопасность",
        "6В12": "Национальная безопасность / военное дело",
    }
    g = None
    for pref, title in groups.items():
        if code.upper().replace("B", "В").startswith(pref):
            g = title
            break
    item["global_specialty"] = item.get("global_specialty") or g or "Образовательные программы"
    item["qualification"] = item.get("qualification") or (code if code else item["global_specialty"])
    return item


def scrape_institution(inst: dict, max_pages: int = 10) -> dict:
    website = (inst.get("website") or "").strip()
    result = {
        "institution_id": inst["id"],
        "name": inst["name"],
        "website": website,
        "programs": [],
        "pages_fetched": [],
        "ok": False,
        "error": None,
        "scraped_at": datetime.now(timezone.utc).isoformat(),
    }
    if not website:
        result["error"] = "no_website"
        return result
    if not website.startswith("http"):
        website = "https://" + website
        result["website"] = website

    status, final, html = fetch(website)
    time.sleep(0.7)
    if not html or status not in (200, 301, 302) and not html:
        # try https www
        result["error"] = f"homepage_fail status={status}"
        return result

    base = final or website
    result["pages_fetched"].append({"url": base, "status": status})
    links = discover_links(base, html, limit=max_pages)
    # always include homepage extract
    pages = [base] + [u for u in links if u.rstrip("/") != base.rstrip("/")]
    pages = pages[: max_pages + 1]

    all_items: list[dict] = []
    all_items.extend(extract_page_programs(html, base))

    for url in pages[1:]:
        st, fin, body = fetch(url)
        time.sleep(0.75)
        result["pages_fetched"].append({"url": fin or url, "status": st})
        if not body or (st and st >= 400):
            continue
        all_items.extend(extract_page_programs(body, fin or url))

    programs = [infer_group(p) for p in dedupe_programs(all_items)]
    # quality gate: drop if too few meaningful or too many garbage
    if len(programs) > 400:
        # keep ones with codes preferentially
        with_code = [p for p in programs if p.get("code")]
        programs = with_code if len(with_code) >= 5 else programs[:250]

    result["programs"] = programs
    result["programs_count"] = len(programs)
    result["with_cost"] = sum(1 for p in programs if p.get("cost"))
    result["ok"] = len(programs) >= 1
    if not result["ok"]:
        result["error"] = "no_programs_found"
    return result


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--limit", type=int, default=0)
    ap.add_argument("--ids", type=str, default="")
    ap.add_argument("--max-pages", type=int, default=8)
    args = ap.parse_args()

    rows = json.loads(IN_PATH.read_text(encoding="utf-8"))
    if args.ids:
        idset = {int(x) for x in args.ids.split(",") if x.strip()}
        rows = [r for r in rows if r["id"] in idset]
    if args.limit:
        rows = rows[: args.limit]

    print(f"Official scrape: {len(rows)} institutions")
    results = []
    for i, inst in enumerate(rows, 1):
        print(f"[{i}/{len(rows)}] #{inst['id']} {inst['name'][:55]}")
        print(f"  web: {inst.get('website')}")
        try:
            res = scrape_institution(inst, max_pages=args.max_pages)
        except Exception as e:
            res = {
                "institution_id": inst["id"],
                "name": inst["name"],
                "website": inst.get("website"),
                "programs": [],
                "ok": False,
                "error": str(e),
            }
        print(
            f"  → programs={res.get('programs_count', 0)} "
            f"cost={res.get('with_cost', 0)} err={res.get('error')}"
        )
        results.append(res)
        if i % 3 == 0:
            OUT_PATH.write_text(
                json.dumps(
                    {
                        "scraped_at": datetime.now(timezone.utc).isoformat(),
                        "count": len(results),
                        "institutions": results,
                    },
                    ensure_ascii=False,
                    indent=2,
                ),
                encoding="utf-8",
            )

    payload = {
        "source": "official_sites",
        "scraped_at": datetime.now(timezone.utc).isoformat(),
        "count": len(results),
        "ok_count": sum(1 for r in results if r.get("ok")),
        "total_programs": sum(r.get("programs_count") or 0 for r in results),
        "total_with_cost": sum(r.get("with_cost") or 0 for r in results),
        "institutions": results,
    }
    OUT_PATH.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(
        f"\nDone ok={payload['ok_count']}/{payload['count']} "
        f"programs={payload['total_programs']} with_cost={payload['total_with_cost']}"
    )
    print(f"→ {OUT_PATH}")


if __name__ == "__main__":
    main()
