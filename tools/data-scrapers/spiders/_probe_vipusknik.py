"""Probe vipusknik.kz HTML structure."""
from __future__ import annotations

import json
import re
from pathlib import Path

import requests
from bs4 import BeautifulSoup

UA = {
    "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
    "(KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36",
    "Accept-Language": "ru-RU,ru;q=0.9",
}
ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "output"


def fetch(url: str) -> str:
    r = requests.get(url, headers=UA, timeout=40)
    r.raise_for_status()
    r.encoding = r.apparent_encoding or "utf-8"
    return r.text


def parse_list(html: str) -> list[dict]:
    soup = BeautifulSoup(html, "lxml")
    seen = set()
    unis = []
    for a in soup.find_all("a", href=True):
        href = a["href"]
        m = re.search(r"/institutions/university/([a-z0-9\-]+)/?$", href)
        if not m:
            continue
        slug = m.group(1)
        if slug in seen:
            continue
        name = " ".join(a.get_text(" ", strip=True).split())
        if not name or len(name) < 3:
            continue
        # skip filter noise
        if slug in {"university", "college"}:
            continue
        seen.add(slug)
        unis.append(
            {
                "slug": slug,
                "name": name,
                "url": f"https://www.vipusknik.kz/institutions/university/{slug}",
            }
        )
    return unis


def parse_detail(html: str, url: str) -> dict:
    soup = BeautifulSoup(html, "lxml")
    title = soup.find("h1")
    name = title.get_text(strip=True) if title else None

    # meta-ish blocks
    text = soup.get_text("\n", strip=True)

    website = None
    for a in soup.find_all("a", href=True):
        h = a["href"]
        if h.startswith("http") and "vipusknik" not in h and "facebook" not in h and "instagram" not in h:
            t = a.get_text(strip=True).lower()
            if "сайт" in t or "www" in h or ".kz" in h or ".edu" in h:
                website = h
                break

    phone = None
    email = None
    m = re.search(r"(\+?7[\s\-\(]?\d{3}[\s\-\)]?\d{3}[\s\-]?\d{2}[\s\-]?\d{2})", text)
    if m:
        phone = m.group(1)
    m = re.search(r"[\w.\-+]+@[\w.\-]+\.\w+", text)
    if m:
        email = m.group(0)

    address = None
    for label in ("Адрес", "адрес"):
        if label in text:
            # crude line after label
            for line in text.split("\n"):
                if label in line and len(line) > len(label) + 3:
                    address = line.replace(label, "").strip(" :")
                    break

    # specialties tables / cards
    specialties = []
    # try table rows
    for table in soup.find_all("table"):
        headers = [th.get_text(" ", strip=True).lower() for th in table.find_all("th")]
        for tr in table.find_all("tr"):
            cells = [td.get_text(" ", strip=True) for td in tr.find_all(["td", "th"])]
            if len(cells) < 2:
                continue
            row_text = " | ".join(cells)
            if "специаль" in " ".join(headers) or re.search(r"6[BbВв]\d", row_text) or "бакалавр" in row_text.lower():
                cost = None
                for c in cells:
                    cm = re.search(r"([\d\s]{3,})\s*(тенге|тг|₸|tg)", c, re.I)
                    if cm:
                        cost = int(re.sub(r"\s+", "", cm.group(1)))
                specialties.append({"raw": cells, "cost": cost})

    # list items that look like programs
    for el in soup.select("div, li, tr, a"):
        t = " ".join(el.get_text(" ", strip=True).split())
        if not t or len(t) > 200 or len(t) < 8:
            continue
        if re.search(r"6[BbВв]\d{2,}", t) or re.search(r"\(\d{2}\.\d{2}\.\d{2}", t):
            cost = None
            cm = re.search(r"([\d\s]{4,})\s*(тенге|тг|₸)", t, re.I)
            if cm:
                cost = int(re.sub(r"\s+", "", cm.group(1)))
            specialties.append({"name": t, "cost": cost})

    # dedup by name
    seen = set()
    uniq = []
    for s in specialties:
        key = s.get("name") or str(s.get("raw"))
        if key in seen:
            continue
        seen.add(key)
        uniq.append(s)

    # find possible cost blocks
    cost_samples = re.findall(r"([\d\s]{4,9})\s*(тенге|тг|₸)", text, re.I)[:20]

    # dump structure classes for debugging
    classes = sorted({c for tag in soup.find_all(class_=True) for c in tag.get("class", [])})[:80]

    return {
        "url": url,
        "name": name,
        "website": website,
        "phone": phone,
        "email": email,
        "address": address,
        "specialties_found": len(uniq),
        "specialties_sample": uniq[:30],
        "cost_samples": cost_samples,
        "classes_sample": classes,
        "text_len": len(text),
    }


def main():
    OUT.mkdir(parents=True, exist_ok=True)
    list_url = "https://www.vipusknik.kz/institutions/university?city_name=5"
    print("Fetching list", list_url)
    html = fetch(list_url)
    (OUT / "vip_list.html").write_text(html, encoding="utf-8")
    unis = parse_list(html)
    (OUT / "vip_uni_links.json").write_text(
        json.dumps(unis, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print(f"Found {len(unis)} university links")
    for u in unis[:15]:
        print(" -", u["name"], u["url"])

    # probe 3 detail pages
    probes = []
    for u in unis[:5]:
        print("Detail", u["url"])
        try:
            dhtml = fetch(u["url"])
            slug = u["slug"]
            (OUT / f"vip_detail_{slug}.html").write_text(dhtml, encoding="utf-8")
            info = parse_detail(dhtml, u["url"])
            info["list_name"] = u["name"]
            probes.append(info)
            print("  name=", info["name"], "specs=", info["specialties_found"], "costs=", info["cost_samples"][:5])
        except Exception as e:
            print("  ERR", e)
            probes.append({"url": u["url"], "error": str(e)})

    (OUT / "vip_probe.json").write_text(
        json.dumps(probes, ensure_ascii=False, indent=2), encoding="utf-8"
    )
    print("Wrote vip_probe.json")


if __name__ == "__main__":
    main()
