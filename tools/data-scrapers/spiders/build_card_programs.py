"""
Собирает итоговый набор программ для карточек вузов:

  1) vipusknik (структура: группа / код / ОП / цена / срок) — база
  2) official_programs.json — дополняет / перекрывает, если есть
  3) cost=null если цены нет → на UI «-»

Запуск:
  python spiders/build_card_programs.py

Выход: output/card_programs.json  (формат import-official-programs)
"""
from __future__ import annotations

import json
import re
from datetime import datetime, timezone
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "output"
INST = OUT / "institutions_for_official_scrape.json"
VIP_ASTANA = OUT / "vipusknik_astana.json"
VIP_ALMATY = OUT / "vipusknik_алматы.json"
OFFICIAL = OUT / "official_programs.json"
RESULT = OUT / "card_programs.json"


def norm(s: str) -> str:
    s = (s or "").lower().replace("ё", "е")
    s = re.sub(r"[«»\"'().,\-–—]", " ", s)
    s = re.sub(r"\s+", " ", s).strip()
    return s


def tokens(s: str) -> set[str]:
    stop = {
        "университет", "university", "институт", "академия", "имени", "им",
        "казахский", "казахская", "казахстанский", "национальный", "республики",
        "казахстан", "г", "в", "и", "of", "the", "at", "имени",
    }
    # allow short acronyms (sdu, alt, q, mnu, aitu)
    return {t for t in re.split(r"\s+", norm(s)) if t not in stop and (len(t) >= 3 or t.isascii())}


def score_names(a: str, b: str) -> int:
    na, nb = norm(a), norm(b)
    if not na or not nb:
        return 0
    if na == nb:
        return 20
    # exact acronym / short brand match
    ta, tb = tokens(a), tokens(b)
    if ta and tb:
        inter = ta & tb
        if inter:
            return len(inter) * 4 + (3 if abs(len(ta) - len(tb)) <= 1 else 0)
    # soft contains (whole normalized)
    if len(na) >= 5 and (na in nb or nb in na):
        return 6
    # significant word contains
    for t in ta:
        if len(t) >= 4 and t in nb:
            return 4
    for t in tb:
        if len(t) >= 4 and t in na:
            return 4
    return 0


def load_vip_maps() -> list[dict]:
    rows = []
    for p in (VIP_ASTANA, VIP_ALMATY):
        if not p.exists():
            continue
        data = json.loads(p.read_text(encoding="utf-8"))
        for inst in data.get("institutions", []):
            if inst.get("error"):
                continue
            rows.append(inst)
    return rows


def vip_to_programs(vip_inst: dict) -> list[dict]:
    out = []
    for s in vip_inst.get("specialties") or []:
        name = (s.get("name") or "").strip()
        if len(name) < 3:
            continue
        cost = s.get("cost")
        if cost is not None:
            try:
                cost = float(cost)
                if cost <= 0:
                    cost = None
            except (TypeError, ValueError):
                cost = None
        duration = s.get("duration")
        try:
            duration = int(duration) if duration not in (None, "") else None
        except (TypeError, ValueError):
            duration = None
        code = s.get("code")
        g = s.get("global_specialty") or "Образовательные программы"
        out.append(
            {
                "name": name,
                "code": code,
                "global_specialty": g,
                # qualification = группа ОП; code хранится отдельно
                "qualification": g,
                "cost": cost,  # null → «-»
                "duration": duration,
                "source": "vipusknik",
            }
        )
    return out


def official_to_programs(off_inst: dict) -> list[dict]:
    """Берём с офиц. сайтов только осмысленные ОП (код 6В / цена / нормальное имя)."""
    out = []
    for s in off_inst.get("programs") or []:
        name = (s.get("name") or "").strip()
        if len(name) < 5 or len(name) > 160:
            continue
        # отсев мусора меню/футера
        low = name.lower()
        if any(x in low for x in ("cookie", "меню", "главная", "войти", "login", "privacy", "политика", "подробнее", "скачать")):
            continue
        code = s.get("code")
        cost = s.get("cost")
        if cost is not None:
            try:
                cost = float(cost)
                if cost <= 0:
                    cost = None
            except (TypeError, ValueError):
                cost = None
        # без кода и без цены — почти всегда шум HTML
        if not code and cost is None:
            continue
        out.append(
            {
                "name": name,
                "code": code,
                "global_specialty": s.get("global_specialty") or "Образовательные программы",
                "qualification": s.get("qualification") or s.get("global_specialty") or "Образовательные программы",
                "cost": cost,
                "duration": s.get("duration"),
                "source": "official",
            }
        )
    # cap noise
    if len(out) > 120:
        out = [p for p in out if p.get("code") or p.get("cost")][:120]
    return out


def merge_programs(base: list[dict], extra: list[dict]) -> list[dict]:
    """base preferred for structure; extra adds missing names / fills cost."""
    by_key: dict[str, dict] = {}

    def key(p: dict) -> str:
        code = (p.get("code") or "").upper().replace("B", "В")
        return code + "||" + norm(p.get("name") or "")

    for p in base:
        by_key[key(p)] = dict(p)

    for p in extra:
        k = key(p)
        if k not in by_key:
            # soft match by name only
            found = None
            for bk, bv in by_key.items():
                if norm(bv.get("name")) == norm(p.get("name")):
                    found = bk
                    break
            if found:
                k = found
            else:
                by_key[k] = dict(p)
                continue
        cur = by_key[k]
        # fill empty cost/duration from either side
        if cur.get("cost") is None and p.get("cost") is not None:
            cur["cost"] = p["cost"]
        if cur.get("duration") is None and p.get("duration") is not None:
            cur["duration"] = p["duration"]
        if not cur.get("code") and p.get("code"):
            cur["code"] = p["code"]
        if p.get("source") == "official":
            cur["sources"] = list({*(cur.get("sources") or [cur.get("source")]), "official"})
        by_key[k] = cur

    return list(by_key.values())


def main() -> None:
    institutions = json.loads(INST.read_text(encoding="utf-8"))
    vip_rows = load_vip_maps()
    official = {}
    if OFFICIAL.exists():
        od = json.loads(OFFICIAL.read_text(encoding="utf-8"))
        for o in od.get("institutions", []):
            if o.get("institution_id"):
                official[o["institution_id"]] = o

    results = []
    for inst in institutions:
        iid = inst["id"]
        name = inst["name"]
        # best vip match
        best = None
        best_score = 0
        for v in vip_rows:
            sc = score_names(name, v.get("name") or "")
            # city bias
            loc = norm(inst.get("location") or "")
            vloc = norm(v.get("location") or "")
            if "алматы" in loc and "алматы" in vloc:
                sc += 1
            if "астана" in loc and "астана" in vloc:
                sc += 1
            if sc > best_score:
                best_score = sc
                best = v

        vip_programs = vip_to_programs(best) if best and best_score >= 4 else []
        off = official.get(iid)
        off_programs = official_to_programs(off) if off and off.get("ok") else []

        # prefer vip structure as base (cleaner), merge official
        if vip_programs:
            programs = merge_programs(vip_programs, off_programs)
            source = "vipusknik+official" if off_programs else "vipusknik"
        elif off_programs:
            programs = off_programs
            source = "official"
        else:
            programs = []
            source = None

        # quality filter
        programs = [p for p in programs if len(p.get("name") or "") >= 3]

        results.append(
            {
                "institution_id": iid,
                "name": name,
                "website": inst.get("website"),
                "matched_vip": best.get("name") if best and best_score >= 3 else None,
                "match_score": best_score,
                "ok": len(programs) >= 1,
                "error": None if programs else "no_programs",
                "programs": programs,
                "programs_count": len(programs),
                "with_cost": sum(1 for p in programs if p.get("cost") is not None),
                "source": source,
            }
        )

    payload = {
        "source": "card_merge_vipusknik_official",
        "scraped_at": datetime.now(timezone.utc).isoformat(),
        "count": len(results),
        "ok_count": sum(1 for r in results if r["ok"]),
        "total_programs": sum(r["programs_count"] for r in results),
        "total_with_cost": sum(r["with_cost"] for r in results),
        "institutions": results,
    }
    RESULT.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
    print(f"ok={payload['ok_count']}/{payload['count']}")
    print(f"programs={payload['total_programs']} with_cost={payload['total_with_cost']}")
    print(f"→ {RESULT}")
    empty = [r["name"] for r in results if not r["ok"]]
    if empty:
        print("without programs:")
        for n in empty:
            print(" -", n)


if __name__ == "__main__":
    main()
