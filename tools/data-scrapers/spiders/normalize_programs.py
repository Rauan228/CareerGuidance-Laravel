"""
Глобальная нормализация специальностей:
- дедуп по коду 6Вxxxxx (или fingerprint имени)
- приоритет русского
- вырезание дат/индексов/кодов/мусора
- склейка cost/duration
- НЕ выкидывает валидные уникальные ОП

python spiders/normalize_programs.py
"""
from __future__ import annotations

import json
import re
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SRC_CANDIDATES = [
    ROOT / "output" / "card_programs_final.json",
    ROOT / "output" / "card_programs.json",
    ROOT / "output" / "institutions_specs_export.json",
]
OUT = ROOT / "output" / "card_programs_clean.json"

KK = "әғқңөұүһіӘҒҚҢӨҰҮҺһІі"
CODE_RE = re.compile(r"6[BbВв][0-9]{2,6}")


def norm(s: str) -> str:
    s = (s or "").replace("\xa0", " ")
    return re.sub(r"\s+", " ", s).strip()


def normalize_code(code: str | None) -> str | None:
    if not code:
        return None
    m = CODE_RE.search(str(code))
    if not m:
        return None
    return m.group(0).upper().replace("6B", "6В")


def detect_lang(text: str) -> str:
    t = (text or "").lower()
    if any(c in t for c in KK.lower()):
        return "kk"
    cyr = len(re.findall(r"[а-яё]", t))
    lat = len(re.findall(r"[a-z]", t))
    if cyr and not lat:
        return "ru"
    if lat and not cyr:
        return "en"
    if cyr and lat:
        return "mix"
    return "xx"


def is_hard_junk(text: str) -> bool:
    """Только явный мусор — не трогаем нормальные названия."""
    t = norm(text)
    low = t.lower()
    if len(t) < 3:
        return True
    if re.fullmatch(r"6[BbВв][0-9]{2,6}", t):
        return True
    if "ағылшын тілі деңгейін" in low or "білім беру бағдарламасына түсушілерге" in low:
        return True
    if re.match(r"^в\d{2,3}\b", low) and ("транспорт" in low or "воздуш" in low or "air " in low or "әуе" in low):
        return True
    if re.match(r"^–\s*(инженерия|көлік)", t, re.I):
        return True
    if re.fullmatch(
        r"(инженерия және инженерлік іс|инженерия и инженерное дело|көлік қызметтері|транспортные услуги|воздушный транспорт и технологии)",
        t,
        re.I,
    ):
        return True
    if t.endswith("(") or t.endswith("«"):
        return True
    # обрезки типа "амтамасыз ету", "к интеграциясы"
    if re.match(r"^[а-яё]{1,2}\s", t) and len(t) < 35 and detect_lang(t) != "ru":
        return True
    letters = len(re.findall(r"[A-Za-zА-Яа-яЁё" + KK + r"]", t))
    if letters < 6:
        return True
    return False


def is_mash(text: str) -> bool:
    low = text.lower()
    has_kk = any(c in low for c in KK.lower())
    has_ru = bool(re.search(r"[а-яё]", low))
    has_en = bool(re.search(r"[a-z]{4,}", text))
    return (has_kk and has_ru) or (has_kk and has_en) or (has_ru and has_en and len(text) > 48)


def extract_russian(text: str) -> str | None:
    parts = re.findall(
        rf"(?:(?![{KK}])[А-Яа-яЁё])+(?:\s+(?:(?![{KK}])[А-Яа-яЁё0-9\-\(\)«»\",./]+))+",
        text,
    )
    best = None
    for p in parts:
        p = norm(p)
        if len(p) < 10 or detect_lang(p) != "ru" or is_hard_junk(p):
            continue
        if best is None or len(p) > len(best):
            best = p
    return best


def strip_noise(s: str) -> str:
    s = re.sub(r"^[–—\-\•\*]+\s*", "", s)
    s = re.sub(r"^\d{1,2}\s+", "", s)
    s = re.sub(r"^6[BbВв][0-9]{2,6}\s*", "", s)
    s = re.sub(r"\d{2}\.\d{2}\.\d{4}\s*[-–—]\s*\d{2}\.\d{2}\.\d{4}", "", s)
    s = re.sub(r"\(?\s*первичн\w*\s*\)?", "", s, flags=re.I)
    s = re.sub(r"\b\d\s*(?:год|года|лет)\b", "", s, flags=re.I)
    s = re.sub(r"\b[ВB]\d{2,3}\b\s*[-–—:]?\s*", "", s)
    # leading short kk fragment before Russian title
    s = re.sub(r"^[а-яё]{1,3}\s+(?=[А-ЯЁ])", "", s)
    return norm(s)


def extract_duration(text: str) -> int | None:
    m = re.search(r"(\d)\s*(?:год|года|лет)", text, re.I)
    if not m:
        return None
    y = int(m.group(1))
    return y if 1 <= y <= 8 else None


def prepare(p: dict) -> dict | None:
    raw = norm(p.get("name") or "")
    if not raw or is_hard_junk(raw):
        return None

    code = normalize_code(p.get("code")) or normalize_code(
        CODE_RE.search(raw).group(0) if CODE_RE.search(raw) else None
    )
    # code from qualification field if any
    if not code:
        code = normalize_code(p.get("qualification") or p.get("qualification_name"))

    working = raw
    if is_mash(raw):
        ru = extract_russian(raw)
        if ru:
            working = ru

    clean = strip_noise(working)
    if not clean or is_hard_junk(clean):
        clean = strip_noise(raw)
    if not clean or is_hard_junk(clean):
        return None

    if is_mash(clean):
        ru = extract_russian(clean)
        if ru:
            clean = ru

    lang = detect_lang(clean)
    score = {"ru": 100, "kk": 30, "en": 20, "mix": 8}.get(lang, 0)
    if code:
        score += 20
    if not is_mash(raw):
        score += 15
    if 8 <= len(clean) <= 130:
        score += 5
    # prefer names without date leftovers in original
    if not re.search(r"\d{2}\.\d{2}\.\d{4}", raw):
        score += 5

    cost = p.get("cost")
    try:
        cost = float(cost) if cost not in (None, "") else None
        if cost is not None and cost <= 0:
            cost = None
    except (TypeError, ValueError):
        cost = None

    dur = p.get("duration") or extract_duration(raw)
    try:
        dur = int(dur) if dur not in (None, "") else None
        if dur is not None and not (1 <= dur <= 8):
            dur = None
    except (TypeError, ValueError):
        dur = None

    g = p.get("global_specialty") or "Образовательные программы"
    return {
        "name": clean,
        "code": code,
        "global_specialty": g,
        "qualification": f"{code} · {g}" if code else g,
        "cost": cost,
        "duration": dur,
        "_lang": lang,
        "_score": score,
    }


def fingerprint(name: str) -> str:
    s = re.sub(r"[^a-zа-яё0-9]+", "", name.lower())
    return s


def group_key(c: dict) -> str:
    """
    Полный код ОП (6В07115) — дедуп по коду.
    Код группы (6В071 / 6В032) — дедуп по код+имя, иначе схлопнутся разные ОП.
    """
    code = c.get("code") or ""
    fp = fingerprint(c["name"])
    if re.fullmatch(r"6В\d{5,}", code):
        return "full:" + code
    if re.fullmatch(r"6В\d{2,4}", code):
        return f"grp:{code}:{fp}"
    return "n:" + fp


def dedupe(programs: list[dict]) -> list[dict]:
    groups: dict[str, list[dict]] = {}
    for p in programs:
        c = prepare(p)
        if not c:
            continue
        groups.setdefault(group_key(c), []).append(c)

    out = []
    for items in groups.values():
        items.sort(key=lambda x: x["_score"], reverse=True)
        ru = [x for x in items if x["_lang"] == "ru"]
        best = dict(ru[0] if ru else items[0])
        for x in items:
            if best.get("cost") is None and x.get("cost") is not None:
                best["cost"] = x["cost"]
            if best.get("duration") is None and x.get("duration") is not None:
                best["duration"] = x["duration"]
        best.pop("_lang", None)
        best.pop("_score", None)
        out.append(best)

    out.sort(key=lambda x: (x.get("code") or "яяя", x["name"]))
    return out


def load_source() -> tuple[Path, dict]:
    for p in SRC_CANDIDATES:
        if not p.exists():
            continue
        data = json.loads(p.read_text(encoding="utf-8"))
        # unify shape
        if "institutions" not in data and isinstance(data, list):
            data = {"institutions": data}
        insts = data.get("institutions") or []
        # convert export shape specialties -> programs
        for i in insts:
            if "programs" not in i and "specialties" in i:
                i["programs"] = i["specialties"]
            if "institution_id" not in i and "id" in i:
                i["institution_id"] = i["id"]
        return p, data
    raise SystemExit("no source json found")


def main() -> None:
    src, data = load_source()
    before = sum(len(i.get("programs") or []) for i in data["institutions"])
    for inst in data["institutions"]:
        cleaned = dedupe(inst.get("programs") or [])
        inst["programs"] = cleaned
        inst["programs_count"] = len(cleaned)
        inst["with_cost"] = sum(1 for p in cleaned if p.get("cost") is not None)
        inst["ok"] = len(cleaned) >= 1
        inst["error"] = None if cleaned else "no_programs_after_clean"

    after = sum(i["programs_count"] for i in data["institutions"])
    data["total_programs"] = after
    data["total_with_cost"] = sum(i["with_cost"] for i in data["institutions"])
    data["ok_count"] = sum(1 for i in data["institutions"] if i["ok"])
    data["source"] = str(data.get("source") or src.name) + "+global_clean"
    OUT.write_text(json.dumps(data, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"src={src.name} programs {before} → {after} cost={data['total_with_cost']} ok={data['ok_count']}")
    # samples
    for want in (65, 1, 9, 82):
        for i in data["institutions"]:
            if i.get("institution_id") == want:
                print(f"\n#{want} {i.get('name','')[:40]} n={i['programs_count']}")
                for p in i["programs"][:8]:
                    print(f"  {p.get('code')} | {p['name'][:70]} | {p.get('cost')}")
                if i["programs_count"] > 8:
                    print(f"  ... +{i['programs_count']-8} more")


if __name__ == "__main__":
    main()
