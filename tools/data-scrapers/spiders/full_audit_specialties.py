"""
Полный аудит специальностей всех вузов (из JSON snapshot или card_programs).
Пишет отчёт output/audit_report.json + markdown summary.

Также может работать поверх export из Laravel (institutions_specs_export.json).
"""
from __future__ import annotations

import json
import re
from collections import Counter, defaultdict
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "output"
EXPORT = OUT / "institutions_specs_export.json"
REPORT = OUT / "audit_report.json"
REPORT_MD = OUT / "audit_report.md"

KK = "әғқңөұүһіӘҒҚҢӨҰҮҺһІі"
CODE_RE = re.compile(r"6[BbВв][0-9]{2,6}")


def norm(s: str) -> str:
    s = (s or "").replace("\xa0", " ")
    return re.sub(r"\s+", " ", s).strip()


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


def flags_for_name(name: str) -> list[str]:
    n = norm(name)
    low = n.lower()
    flags = []
    if not n or len(n) < 3:
        flags.append("empty_or_too_short")
    if re.fullmatch(r"6[BbВв][0-9]{2,6}", n):
        flags.append("code_only")
    if re.search(r"\d{2}\.\d{2}\.\d{4}", n):
        flags.append("has_accreditation_date")
    if re.search(r"первичн", low):
        flags.append("has_accreditation_label")
    if re.match(r"^\d{1,2}\s+", n):
        flags.append("leading_index")
    if re.match(r"^6[BbВв][0-9]{2,6}\s+", n):
        flags.append("leading_code_in_name")
    if re.match(r"^в\d{2,3}\b", low):
        flags.append("group_code_label")
    if "ағылшын тілі деңгейін" in low:
        flags.append("english_test_junk")
    if detect_lang(n) == "kk":
        flags.append("kazakh_only")
    if detect_lang(n) == "en":
        flags.append("english_only")
    if detect_lang(n) == "mix":
        flags.append("mixed_script")
    # trilingual mash heuristic
    has_kk = any(c in low for c in KK.lower())
    has_ru = bool(re.search(r"[а-яё]", low))
    has_en = bool(re.search(r"[a-z]{4,}", n))
    if (has_kk and has_ru) or (has_kk and has_en) or (has_ru and has_en and len(n) > 50):
        flags.append("multilang_mash")
    if re.match(r"^[а-яё]{1,3}\s+[А-ЯЁ]", n) and len(n) < 40:
        flags.append("possible_fragment")
    if n.endswith("(") or n.endswith("«"):
        flags.append("broken_trailing")
    if re.search(r"cookie|javascript|privacy|войти|login|меню|главная", low):
        flags.append("ui_junk")
    if len(n) > 160:
        flags.append("too_long")
    # low letter density
    letters = len(re.findall(r"[A-Za-zА-Яа-яЁёӘәҒғҚқҢңӨөҰұҮүҺһІі]", n))
    if letters < 8:
        flags.append("too_few_letters")
    return flags


def extract_code(name: str, qual: str = "") -> str | None:
    for t in (name, qual):
        m = CODE_RE.search(t or "")
        if m:
            return m.group(0).upper().replace("6B", "6В")
    return None


def audit_institution(inst: dict) -> dict:
    specs = inst.get("specialties") or inst.get("programs") or []
    issues = []
    flag_counter: Counter = Counter()
    by_code: dict[str, list] = defaultdict(list)
    names_norm: dict[str, list] = defaultdict(list)

    for s in specs:
        name = s.get("name") or ""
        qual = s.get("qualification") or s.get("qualification_name") or ""
        code = extract_code(name, qual) or normalize_maybe(s.get("code"))
        flags = flags_for_name(name)
        if not s.get("cost") and s.get("cost") != 0:
            # null cost is ok (shows as -) — not an error by itself
            pass
        if flags:
            flag_counter.update(flags)
            issues.append(
                {
                    "name": name,
                    "code": code,
                    "flags": flags,
                    "cost": s.get("cost"),
                    "duration": s.get("duration"),
                }
            )
        if code:
            by_code[code].append(name)
        names_norm[re.sub(r"[^a-zа-яё0-9]+", "", name.lower())].append(name)

    # duplicate groups by code
    code_dups = {c: names for c, names in by_code.items() if len(names) > 1}
    # near-duplicate names
    name_dups = {k: v for k, v in names_norm.items() if len(v) > 1 and k}

    severity = 0
    severity += sum(flag_counter.values())
    severity += sum(len(v) - 1 for v in code_dups.values()) * 2

    return {
        "id": inst.get("id") or inst.get("institution_id"),
        "name": inst.get("name"),
        "specs_count": len(specs),
        "with_cost": sum(1 for s in specs if s.get("cost") not in (None, "", 0)),
        "issue_rows": len(issues),
        "flag_counts": dict(flag_counter),
        "code_duplicate_groups": len(code_dups),
        "name_duplicate_groups": len(name_dups),
        "severity": severity,
        "sample_issues": issues[:12],
        "code_dups_sample": {c: v[:5] for c, v in list(code_dups.items())[:8]},
    }


def normalize_maybe(code) -> str | None:
    if not code:
        return None
    m = CODE_RE.search(str(code))
    return m.group(0).upper().replace("6B", "6В") if m else None


def main() -> None:
    if not EXPORT.exists():
        raise SystemExit(f"Missing {EXPORT}. Export from Laravel first.")

    data = json.loads(EXPORT.read_text(encoding="utf-8"))
    institutions = data if isinstance(data, list) else data.get("institutions", [])

    audits = [audit_institution(i) for i in institutions]
    audits.sort(key=lambda a: (-a["severity"], -a["issue_rows"], a["name"] or ""))

    total_specs = sum(a["specs_count"] for a in audits)
    total_issues = sum(a["issue_rows"] for a in audits)
    bad_unis = [a for a in audits if a["severity"] > 0]
    clean_unis = [a for a in audits if a["severity"] == 0 and a["specs_count"] > 0]
    empty_unis = [a for a in audits if a["specs_count"] == 0]

    global_flags: Counter = Counter()
    for a in audits:
        global_flags.update(a.get("flag_counts") or {})

    report = {
        "summary": {
            "universities": len(audits),
            "with_specs": len([a for a in audits if a["specs_count"] > 0]),
            "empty_specs": len(empty_unis),
            "clean_universities": len(clean_unis),
            "universities_with_issues": len(bad_unis),
            "total_specialties": total_specs,
            "rows_with_flags": total_issues,
            "global_flag_counts": dict(global_flags.most_common()),
        },
        "worst_universities": audits[:25],
        "empty_universities": empty_unis,
        "all": audits,
    }
    REPORT.write_text(json.dumps(report, ensure_ascii=False, indent=2), encoding="utf-8")

    lines = []
    lines.append("# Аудит специальностей вузов\n")
    s = report["summary"]
    lines.append(f"- Вузов: **{s['universities']}**")
    lines.append(f"- С ОП: **{s['with_specs']}**")
    lines.append(f"- Пустые: **{s['empty_specs']}**")
    lines.append(f"- Чистые (без флагов): **{s['clean_universities']}**")
    lines.append(f"- С ошибками: **{s['universities_with_issues']}**")
    lines.append(f"- Всего ОП: **{s['total_specialties']}**")
    lines.append(f"- Строк с флагами: **{s['rows_with_flags']}**\n")
    lines.append("## Глобальные типы ошибок\n")
    for k, v in s["global_flag_counts"].items():
        lines.append(f"- `{k}`: {v}")
    lines.append("\n## Худшие вузы (top 25)\n")
    for a in audits[:25]:
        lines.append(
            f"### #{a['id']} {a['name']}\n"
            f"- ОП: {a['specs_count']}, с ценой: {a['with_cost']}, "
            f"проблемных строк: {a['issue_rows']}, severity: {a['severity']}\n"
            f"- флаги: `{a['flag_counts']}`\n"
        )
        for iss in a["sample_issues"][:5]:
            lines.append(f"  - [{', '.join(iss['flags'])}] {iss['name'][:120]}")
        lines.append("")
    if empty_unis:
        lines.append("## Без специальностей\n")
        for a in empty_unis:
            lines.append(f"- #{a['id']} {a['name']}")
    REPORT_MD.write_text("\n".join(lines), encoding="utf-8")

    print(json.dumps(report["summary"], ensure_ascii=False, indent=2))
    print(f"\n→ {REPORT}")
    print(f"→ {REPORT_MD}")
    print("\nTop 10 worst:")
    for a in audits[:10]:
        print(f"  #{a['id']} sev={a['severity']} issues={a['issue_rows']} n={a['specs_count']} {a['name'][:50]}")


if __name__ == "__main__":
    main()
