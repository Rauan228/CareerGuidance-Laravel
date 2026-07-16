"""
Парсер vipusknik.kz по городу.

Примеры:
  python spiders/vipusknik_city_spider.py --city 5 --city-name Астана
  python spiders/vipusknik_city_spider.py --city 3 --city-name Алматы
  python spiders/vipusknik_city_spider.py --city 4 --city-name Шымкент

city_name codes (vipusknik):
  5 Астана, 3 Алматы, 4 Шымкент, ...
"""
from __future__ import annotations

import argparse
import json
import re
import time
from datetime import datetime, timezone
from pathlib import Path
from urllib.parse import urljoin

import requests
from bs4 import BeautifulSoup

ROOT = Path(__file__).resolve().parents[1]

UA = {
    "User-Agent": (
        "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
        "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36"
    ),
    "Accept-Language": "ru-RU,ru;q=0.9,en;q=0.8",
}
DELAY_SEC = 1.0

# optional registry only for Astana enrichment
ASTANA_REG = ROOT / "data" / "astana_universities.json"

VIP_TO_REGISTRY = {
    "astana-it-university": "aitu",
    "evraziyskiy-natsionalnyy-universitet-imeni-l-n-gumileva": "enu",
    "kazakhskiy-gumanitarno-yuridicheskiy-universitet": "mnu",
    "kazakhskiy-agrotekhnicheskiy-universitet-imeni-sseyfullina": "kazatu",
    "meditsinskiy-universitet-astana": "amu",
    "nazarbaev-universitet": "nu",
    "universitet-turan-astana": "tau",
    "esil-university": "esil",
    "mezhdunarodnyy-universitet-astana": "aiu",
    "kazakhskaya-natsionalnaya-akademiya-khoreografii": "balletacademy",
    "kazakhskiy-natsionalnyy-universitet-iskusstv": "kaznui",
    "kazakhskiy-universitet-tekhnologiy-i-biznesa": "kutb",
    "akademiya-gosudarstvennogo-upravleniya-pri-prezidente-respubliki-kazahstan": "apa",
    "moskovskiy-gosudarstvennyy-universitet-imeni-m-v-lomonosova": "msu-kz",
    "evraziyskiy-gumanitarnyy-institut": "eagi",
    "karagandinskiy-universitet-kazpotrebsoyuza-filial-v-g-astana": "keuk-astana",
    "akademiya-fizicheskoy-kultury-i-massovogo-sporta": "sport-academy",
    "coventry-university-kazakhstan": "coventry-kz",
    "universitet-sinergiya-astana": "sinergiya-astana",
}


def fetch(url: str) -> str:
    r = requests.get(url, headers=UA, timeout=45)
    r.raise_for_status()
    r.encoding = r.apparent_encoding or "utf-8"
    return r.text


def parse_list(html: str) -> list[dict]:
    soup = BeautifulSoup(html, "lxml")
    seen: set[str] = set()
    unis: list[dict] = []
    for a in soup.find_all("a", href=True):
        m = re.search(r"/institutions/university/([a-z0-9\-]+)/?$", a["href"])
        if not m:
            continue
        slug = m.group(1)
        if slug in seen:
            continue
        name = " ".join(a.get_text(" ", strip=True).split())
        if not name or len(name) < 3:
            continue
        seen.add(slug)
        unis.append(
            {
                "vip_slug": slug,
                "name": name,
                "vip_url": f"https://www.vipusknik.kz/institutions/university/{slug}",
            }
        )
    return unis


def _money(text: str | None) -> float | None:
    if not text:
        return None
    m = re.search(r"([\d\s\u00a0]{3,})\s*(?:тг|тенге|₸)?", text, re.I)
    if not m:
        return None
    raw = re.sub(r"[\s\u00a0]", "", m.group(1))
    if not raw.isdigit():
        return None
    val = int(raw)
    if val < 50000:
        return None
    return float(val)


def _years(text: str | None) -> int | None:
    if not text:
        return None
    m = re.search(r"(\d+)\s*(?:год|года|лет|г\.)", text, re.I)
    if m:
        return int(m.group(1))
    m = re.search(r"^(\d+)$", text.strip())
    if m and 1 <= int(m.group(1)) <= 8:
        return int(m.group(1))
    return None


def parse_specialties_table(soup: BeautifulSoup) -> list[dict]:
    result: list[dict] = []
    tbody = soup.select_one("tbody.college-specialty-table-body")
    if not tbody:
        return result
    current_group = None
    current_code = None
    for tr in tbody.find_all("tr"):
        tds = tr.find_all("td")
        if not tds:
            continue
        if tds[0].has_attr("colspan") or len(tds) == 1:
            a = tds[0].find("a")
            text = tds[0].get_text(" ", strip=True)
            m = re.search(r"\((6[BbВв][\dA-Za-zА-Яа-я]+)\)", text)
            current_code = m.group(1) if m else None
            current_group = a.get_text(strip=True) if a else re.sub(r"\s*\(.*\)\s*$", "", text)
            continue
        name_a = tds[0].find("a")
        name = name_a.get_text(strip=True) if name_a else tds[0].get_text(strip=True)
        cost = _money(tds[2].get_text(" ", strip=True)) if len(tds) > 2 else None
        duration = _years(tds[3].get_text(" ", strip=True)) if len(tds) > 3 else None
        if name:
            result.append(
                {
                    "global_specialty": current_group,
                    "code": current_code,
                    "name": name,
                    "cost": cost,
                    "duration": duration,
                    "level": "bachelor",
                }
            )
    return result


def parse_specialties(soup: BeautifulSoup) -> list[dict]:
    result: list[dict] = []
    root = soup.select_one(".institution-specialty-list")
    if not root:
        return parse_specialties_table(soup)

    current_group = None
    current_code = None
    for block in root.find_all("div", class_="px-3", recursive=False):
        header = block.find("div", class_=re.compile(r"border-b"))
        if header:
            a = header.find("a")
            code_el = header.select_one(".flex-no-shrink, .ml-5")
            current_group = a.get_text(strip=True) if a else None
            current_code = code_el.get_text(strip=True) if code_el else None

        for qual in block.select(".college-qualification"):
            qa = qual.find("a")
            name = qa.get_text(strip=True) if qa else None
            if not name:
                continue
            cost = None
            duration = None
            for col in qual.select(".flex.flex-col, .flex-col"):
                label = col.get_text(" ", strip=True).lower()
                if "стоимость" in label:
                    cost = _money(col.get_text(" ", strip=True))
                if "срок" in label:
                    duration = _years(col.get_text(" ", strip=True))
            full = qual.get_text(" ", strip=True)
            if cost is None:
                cost = _money(full)
            if duration is None:
                duration = _years(full)
            result.append(
                {
                    "global_specialty": current_group,
                    "code": current_code,
                    "name": name,
                    "cost": cost,
                    "duration": duration,
                    "level": "bachelor",
                }
            )
    return result or parse_specialties_table(soup)


def parse_detail(html: str, meta: dict, city_label: str) -> dict:
    soup = BeautifulSoup(html, "lxml")
    h1 = soup.find("h1")
    name = h1.get_text(strip=True) if h1 else meta["name"]

    logo = None
    for img in soup.find_all("img"):
        src = img.get("src") or ""
        alt = (img.get("alt") or "").lower()
        if "logo" in src.lower() or "logo" in alt or "storage" in src:
            if any(x in src for x in (".png", ".jpg", ".jpeg", ".webp", ".svg")):
                logo = urljoin(meta["vip_url"], src)
                break

    descriptions: list[str] = []
    for p in soup.find_all("p"):
        t = " ".join(p.get_text(" ", strip=True).split())
        if len(t) > 80:
            descriptions.append(t)
        if len(descriptions) >= 3:
            break
    while len(descriptions) < 3:
        descriptions.append(None)

    website = None
    email = None
    phone = None
    address = None
    text = soup.get_text("\n", strip=True)

    junk = (
        "facebook", "instagram", "youtube", "tiktok", "telegram", "vk.com",
        "zero.kz", "google.", "goo.gl", "bit.ly", "t.me", "wa.me",
        "whatsapp", "twitter", "x.com", "linkedin", "play.google",
    )
    for a in soup.find_all("a", href=True):
        href = a["href"].strip()
        if href.startswith("mailto:"):
            email = href.replace("mailto:", "").split("?")[0]
        elif href.startswith("tel:"):
            phone = href.replace("tel:", "")
        elif href.startswith("http") and "vipusknik" not in href:
            low = href.lower()
            if any(s in low for s in junk):
                continue
            if any(s in low for s in (".edu", ".kz", "university", "academy", "college")):
                if website is None:
                    website = href

    if not email:
        m = re.search(r"[\w.\-+]+@[\w.\-]+\.\w+", text)
        if m:
            email = m.group(0)
    if not phone:
        m = re.search(r"(\+?7[\s\-\(]?\d{3}[\s\-\)]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2})", text)
        if m:
            phone = re.sub(r"\s+", " ", m.group(1)).strip()

    city_re = re.escape(city_label.split(",")[0].strip())
    for line in text.split("\n"):
        if re.search(rf"{city_re}|пр\.|ул\.|шоссе|даңғылы|мкр", line, re.I):
            if 10 < len(line) < 160 and "cookie" not in line.lower():
                address = line.strip()
                break

    specialties = parse_specialties(soup)
    return {
        "vip_slug": meta["vip_slug"],
        "vip_url": meta["vip_url"],
        "name": name,
        "description1": descriptions[0],
        "description2": descriptions[1],
        "description3": descriptions[2],
        "location": f"{city_label}, Казахстан" if "Казахстан" not in city_label else city_label,
        "address": address,
        "email": email,
        "phone": phone,
        "website": website,
        "logo_url": logo,
        "photo_url": None,
        "type": "university",
        "directions": None,
        "dormitory": None,
        "grants": None,
        "verified": "accepted",
        "latitude": None,
        "longitude": None,
        "specialties": specialties,
        "specialties_count": len(specialties),
        "specialties_with_cost": sum(1 for s in specialties if s.get("cost")),
        "source": "vipusknik.kz",
        "scraped_at": datetime.now(timezone.utc).isoformat(),
    }


def enrich_astana(row: dict, registry: dict) -> dict:
    if not registry:
        return row
    unis = {u.get("slug"): u for u in registry.get("universities", []) if u.get("slug")}
    vip = row.get("vip_slug") or ""
    name_l = (row.get("name") or "").lower()
    best = None
    reg_slug = VIP_TO_REGISTRY.get(vip)
    if reg_slug and reg_slug in unis:
        best = unis[reg_slug]
    else:
        rules = [
            ("astana it", "aitu"), ("гумил", "enu"), ("narikbayev", "mnu"),
            ("сейфулл", "kazatu"), ("медицин", "amu"), ("назарбаев", "nu"),
            ("туран", "tau"), ("esil", "esil"), ("международный университет астан", "aiu"),
            ("хореограф", "balletacademy"), ("искусств", "kaznui"), ("ломоносов", "msu-kz"),
            ("кулажан", "kutb"), ("технологи", "kutb"), ("государственн", "apa"),
            ("гуманитарный институт", "eagi"), ("казпотребсоюз", "keuk-astana"),
        ]
        for needle, slug in rules:
            if needle in name_l and slug in unis:
                best = unis[slug]
                break
    if not best:
        return row
    row["registry_slug"] = best.get("slug")
    if not row.get("address") and best.get("address"):
        row["address"] = best["address"]
    if best.get("latitude"):
        row["latitude"] = best["latitude"]
    if best.get("longitude"):
        row["longitude"] = best["longitude"]
    if best.get("website"):
        row["website"] = best["website"]
    if not row.get("email") and best.get("email"):
        row["email"] = best["email"]
    if not row.get("phone") and best.get("phone"):
        row["phone"] = best["phone"]
    if best.get("dormitory") is not None:
        row["dormitory"] = best["dormitory"]
    if best.get("grants") is not None:
        row["grants"] = best["grants"]
    if best.get("directions"):
        row["directions"] = best["directions"]
    return row


def main() -> None:
    ap = argparse.ArgumentParser()
    ap.add_argument("--city", type=int, required=True, help="vipusknik city_name id")
    ap.add_argument("--city-name", type=str, required=True, help="label e.g. Алматы")
    ap.add_argument("--out", type=str, default=None)
    args = ap.parse_args()

    list_url = f"https://www.vipusknik.kz/institutions/university?city_name={args.city}"
    slug = re.sub(r"[^\w]+", "_", args.city_name.lower(), flags=re.U).strip("_")
    out = Path(args.out) if args.out else ROOT / "output" / f"vipusknik_{slug}.json"

    registry = {}
    if args.city == 5 and ASTANA_REG.exists():
        registry = json.loads(ASTANA_REG.read_text(encoding="utf-8"))

    print("List:", list_url)
    unis = parse_list(fetch(list_url))
    print(f"Found {len(unis)} universities")

    results = []
    for i, u in enumerate(unis, 1):
        print(f"[{i}/{len(unis)}] {u['name']}")
        try:
            html = fetch(u["vip_url"])
            time.sleep(DELAY_SEC)
            row = parse_detail(html, u, args.city_name)
            if registry:
                row = enrich_astana(row, registry)
            if row.get("website") and "zero.kz" in row["website"]:
                row["website"] = None
            print(f"  specs={row['specialties_count']} cost={row['specialties_with_cost']}")
            results.append(row)
        except Exception as e:
            print(f"  ERR {e}")
            results.append({**u, "error": str(e), "specialties": [], "location": args.city_name})

    payload = {
        "city": args.city_name,
        "city_id": args.city,
        "source": "vipusknik.kz",
        "list_url": list_url,
        "scraped_at": datetime.now(timezone.utc).isoformat(),
        "count": len(results),
        "total_specialties": sum(r.get("specialties_count") or 0 for r in results),
        "total_with_cost": sum(r.get("specialties_with_cost") or 0 for r in results),
        "institutions": results,
    }
    out.parent.mkdir(parents=True, exist_ok=True)
    out.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"\nOK → {out}")
    print(f"institutions={payload['count']} specs={payload['total_specialties']} cost={payload['total_with_cost']}")


if __name__ == "__main__":
    main()
