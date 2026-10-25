#!/usr/bin/env python3
"""
Normalize PDF names in data/Notenarchiv/ to the Archiv convention (ARCHIV-11).

Convention (per file, inside work folder):
  {ord:02d}_{Instrument}_{Voice}.pdf
  00_Score.pdf
  01_Flute_1.pdf
  03_Clarinet_1-2.pdf
  12_Baritone_BC.pdf

Does NOT touch the Nextcloud/pCloud mount — only the local mirror.

Usage:
  python scripts/normalizeScoreMirrorNames.py --dry-run
  python scripts/normalizeScoreMirrorNames.py
  python scripts/normalizeScoreMirrorNames.py --mirror "D:/path/Notenarchiv"
"""
from __future__ import annotations

import argparse
import json
import re
import shutil
import unicodedata
from collections import defaultdict
from pathlib import Path

# Display token → sort key (orchestra-ish)
INSTRUMENT_ORDER = [
    "Score",
    "Piccolo",
    "Flute",
    "Oboe",
    "EnglishHorn",
    "Bassoon",
    "EbClarinet",
    "Clarinet",
    "AltoClarinet",
    "BassClarinet",
    "SopranoSax",
    "AltoSax",
    "TenorSax",
    "BariSax",
    "Trumpet",
    "Cornet",
    "Flugelhorn",
    "Horn",
    "Trombone",
    "Euphonium",
    "Baritone",
    "Tuba",
    "Bass",
    "Timpani",
    "Mallets",
    "Percussion",
    "Piano",
    "Harp",
    "Guitar",
    "Keyboard",
    "Voice",
    "Solo",
    "Unknown",
]

ALIASES: dict[str, str] = {
    # flute family
    "flute": "Flute",
    "fluit": "Flute",
    "fluet": "Flute",  # typo
    "floete": "Flute",
    "flote": "Flute",
    "flöte": "Flute",
    "piccolo": "Piccolo",
    "cpiccolo": "Piccolo",
    "oboe": "Oboe",
    "englishhorn": "EnglishHorn",
    "coranglais": "EnglishHorn",
    "bassoon": "Bassoon",
    "fagott": "Bassoon",
    # clarinets
    "clarinet": "Clarinet",
    "klarinette": "Clarinet",
    "bbclarinet": "Clarinet",
    "clarinetinbb": "Clarinet",
    "clarinete": "Clarinet",
    "clar": "Clarinet",
    "klar": "Clarinet",
    "klareb": "EbClarinet",
    "ebclarinet": "EbClarinet",
    "ebklarinette": "EbClarinet",
    "clarinetines": "EbClarinet",
    "clarebalt": "EbClarinet",
    "bassclarinet": "BassClarinet",
    "bbbassclarinet": "BassClarinet",
    "basclarinet": "BassClarinet",
    "bassklarinette": "BassClarinet",
    "altoclarinet": "AltoClarinet",
    "altklarinette": "AltoClarinet",
    # sax
    "sopranosaxophone": "SopranoSax",
    "sopranosax": "SopranoSax",
    "sopransax": "SopranoSax",
    "bbsopranosax": "SopranoSax",
    "bbsopransax": "SopranoSax",
    "sopraansaxofoonbb": "SopranoSax",
    "saxophonsopran": "SopranoSax",
    "saxophonsopraninb": "SopranoSax",
    "altosaxophone": "AltoSax",
    "altosax": "AltoSax",
    "altsax": "AltoSax",
    "altsaxophon": "AltoSax",
    "altsex": "AltoSax",  # typo
    "ebaltsax": "AltoSax",
    "ebaltsaxofoon": "AltoSax",
    "ebaltsex": "AltoSax",
    "aktsaxeb": "AltoSax",
    "saxeb": "AltoSax",
    "saxophonalt": "AltoSax",
    "saxophonaltones": "AltoSax",
    "saxophonealtoeb": "AltoSax",
    "tenorsaxophone": "TenorSax",
    "tenorsax": "TenorSax",
    "tensax": "TenorSax",
    "tensaxb": "TenorSax",
    "tenorsaxophon": "TenorSax",
    "saxtenor": "TenorSax",
    "saxophontenor": "TenorSax",
    "saxophontenorinb": "TenorSax",
    "saxophonetenorbb": "TenorSax",
    "baritonesaxophone": "BariSax",
    "baritonesax": "BariSax",
    "barisax": "BariSax",
    "baritonsaxophon": "BariSax",
    # brass
    "trumpet": "Trumpet",
    "trompete": "Trumpet",
    "trompet": "Trumpet",
    "tromp": "Trumpet",
    "trp": "Trumpet",
    "cornet": "Cornet",
    "kornett": "Cornet",
    "eskornett": "Cornet",
    "piston": "Cornet",
    "pistones": "Cornet",
    "flugel": "Flugelhorn",
    "flugelhorn": "Flugelhorn",
    "flugeland": "Flugelhorn",
    "bbflugel": "Flugelhorn",
    "ebflugel": "Flugelhorn",
    "horn": "Horn",
    "frenchhorn": "Horn",
    "hornin": "Horn",
    "trombone": "Trombone",
    "posaune": "Trombone",
    "poseune": "Trombone",  # typo
    "pos": "Trombone",
    "trb": "Trombone",
    "tenorh": "Baritone",
    "tenorhorn": "Baritone",
    "euphonium": "Euphonium",
    "baritone": "Baritone",
    "baryton": "Baritone",
    "bariton": "Baritone",
    "tuba": "Tuba",
    "bass": "Bass",
    "bas": "Bass",
    "ebbas": "Bass",
    "stringbass": "Bass",
    "bbbass": "Bass",
    "bbbacc": "Bass",
    "bacc": "Bass",
    "contrabass": "Bass",
    # percussion / other
    "percussion": "Percussion",
    "perkussion": "Percussion",
    "perc": "Percussion",
    "drums": "Percussion",
    "drumset": "Percussion",
    "schlagzeug": "Percussion",
    "snaredrum": "Percussion",
    "snare": "Percussion",
    "cymbals": "Percussion",
    "cymbal": "Percussion",
    "cymbalsasetc": "Percussion",
    "becken": "Percussion",
    "bassdrum": "Percussion",
    "grosstrommel": "Percussion",
    "kleinetrommel": "Percussion",
    "kltrommel": "Percussion",
    "toms": "Percussion",
    "bongo": "Percussion",
    "bongotimbales": "Percussion",
    "timbales": "Percussion",
    "claves": "Percussion",
    "clavesascowbell": "Percussion",
    "cowbell": "Percussion",
    "conga": "Percussion",
    "maracas": "Percussion",
    "maracasascabasa": "Percussion",
    "cabasa": "Percussion",
    "timpani": "Timpani",
    "pauke": "Timpani",
    "pauken": "Timpani",
    "mallets": "Mallets",
    "malletperc": "Mallets",
    "xylophone": "Mallets",
    "vibraphone": "Mallets",
    "glockenspiel": "Mallets",
    "bells": "Mallets",
    "lyra": "Mallets",
    "drum": "Percussion",
    "tambourine": "Percussion",
    "triangle": "Percussion",
    "keyboard": "Keyboard",
    "piano": "Piano",
    "harp": "Harp",
    "guitar": "Guitar",
    "gitarre": "Guitar",
    "egitarre": "Guitar",
    "egitar": "Guitar",
    "orgel": "Keyboard",
    "organ": "Keyboard",
    "schlagwerk": "Percussion",
    "stimmen": "Score",
    "gesang": "Voice",
    "voice": "Voice",
    "vocal": "Voice",
    "choir": "Voice",
    "solo": "Solo",
    "bagpipes": "Unknown",
    # score
    "score": "Score",
    "fullscore": "Score",
    "condensedscore": "Score",
    "conductorscore": "Score",
    "direktion": "Score",
    "dirigat": "Score",
    "partitur": "Score",
    "partituur": "Score",
    "conductor": "Score",
    "komplett": "Score",
    "complete": "Score",
    "compress": "Score",
}


def norm(s: str) -> str:
    s = unicodedata.normalize("NFKD", s)
    s = "".join(c for c in s if not unicodedata.combining(c))
    s = s.lower().replace("ä", "ae").replace("ö", "oe").replace("ü", "ue").replace("ß", "ss")
    return re.sub(r"[^a-z0-9]+", "", s)


def roman_or_digit(v: str) -> str:
    v = v.strip().upper()
    mapping = {"I": "1", "II": "2", "III": "3", "IV": "4", "V": "5", "VI": "6"}
    if v in mapping:
        return mapping[v]
    if v.isdigit():
        return str(int(v))
    return v


def strip_work_prefix(stem: str, folder_title: str) -> str:
    """Remove leading catalog number and optional work title from stem."""
    s = stem.strip()
    # "1 - 01_Flute…" / "7 - 05_Klarinette…"
    m = re.match(r"^\d+\s*[-–]\s+(.+)$", s)
    if m:
        s = m.group(1).strip()
    # Title prefix: "Napoleon Marsch - Alto Sax 1" / "Miss Saigon Alto Saxophone 1"
    title_norm = norm(folder_title)
    if title_norm and len(title_norm) >= 4:
        # dash-separated title
        m = re.match(r"^(.+?)\s+[-–]\s+(.+)$", s)
        if m and norm(m.group(1))[:8] == title_norm[:8]:
            s = m.group(2).strip()
        else:
            # glued title without dash
            words = s.split()
            for i in range(1, min(len(words), 6)):
                head = norm(" ".join(words[:i]))
                if title_norm.startswith(head) or head.startswith(title_norm[: max(4, len(head))]):
                    rest = " ".join(words[i:]).strip()
                    if rest:
                        s = rest
                        break
    return s


def detect_score(stem: str) -> bool:
    n = norm(stem)
    n = re.sub(r"^\d+", "", n)
    keys = (
        "fullscore",
        "condensedscore",
        "conductorscore",
        "score",
        "direktion",
        "dirigat",
        "partitur",
        "conductor",
    )
    return n in keys or any(n.endswith(k) for k in keys)


def map_instrument(token: str) -> str | None:
    n = norm(token)
    if not n:
        return None
    if n in ALIASES:
        return ALIASES[n]
    # substring / contains
    for key, val in sorted(ALIASES.items(), key=lambda kv: -len(kv[0])):
        if key in n or n in key:
            return val
    return None


def parse_clefs(text: str) -> str | None:
    t = text.lower()
    if re.search(r"\b\(?\s*b\.?\s*c\.?\s*\)?", t) or "bassclef" in norm(t) or "bassschlussel" in norm(t):
        return "BC"
    if re.search(r"\b\(?\s*t\.?\s*c\.?\s*\)?", t) or "treble" in norm(t):
        return "TC"
    return None


def parse_concert_key(text: str) -> str | None:
    """Transposition / concert key hint from filename (Bb/Eb/F/C/Es/B)."""
    t = text
    m = re.search(r"\bin\s+([A-G])([b#es]*)\b", t, re.I)
    if m:
        note = m.group(1).upper()
        acc = (m.group(2) or "").lower()
        if acc in ("b", "es"):
            return "Eb" if note == "E" else ("Bb" if note == "B" else f"{note}b")
        if note == "B" and acc == "":
            return "Bb"
        if note == "E" and acc in ("s", "es"):
            return "Eb"
        return note + acc
    # standalone Eb / Bb / Es tokens
    if re.search(r"(?i)(?<![a-z])eb(?![a-z])", t) or re.search(r"(?i)(?<![a-z])es(?![a-z])", t):
        return "Eb"
    if re.search(r"(?i)(?<![a-z])bb(?![a-z])", t):
        return "Bb"
    # trailing " B" / " Es" / " C" after instrument (German parts)
    m = re.search(r"[\s_]([BECbec]|Es|AS|As)\s*$", t)
    if m:
        k = m.group(1)
        kl = k.lower()
        if kl == "es":
            return "Eb"
        if kl == "b":
            return "Bb"
        if kl == "c":
            return "C"
        if kl in ("as",):
            return "Ab"
        if kl == "e":
            return "E"
    n = norm(t)
    if ("clarinet" in n or "klarinette" in n) and ("ines" in n or n.endswith("es")):
        return "Eb"
    return None


def apply_key_to_instrument(inst: str, key: str | None) -> tuple[str, str | None]:
    """Map Clarinet+Eb → EbClarinet; return leftover key for voice suffix if needed."""
    if not key:
        return inst, None
    if inst == "Clarinet" and key in ("Eb", "Es"):
        return "EbClarinet", None
    if inst == "Clarinet" and key in ("Bb", "B"):
        return "Clarinet", None
    return inst, key


def parse_part(stem: str, folder_title: str) -> dict | None:
    raw = stem
    if re.match(r"^doc\d+", stem, re.I):
        return {"ok": False, "reason": "scan_name", "raw": raw}

    if detect_score(stem) or detect_score(strip_work_prefix(stem, folder_title)):
        return {"ok": True, "instrument": "Score", "voice": "1", "raw": raw}

    s = strip_work_prefix(stem, folder_title)
    # Underscore packs: Frenesi_Saxophon_Alt_in_Es_I → spaces
    s = s.replace("_", " ")
    s = re.sub(r"\s+", " ", s).strip()
    clef = parse_clefs(s)
    key = parse_concert_key(s)
    s_clean = re.sub(r"\((?:B\.?C\.?|T\.?C\.?|Bass|Treble|etc\.?|Fanfare|Musikkorps|opt\.?)[^)]*\)", "", s, flags=re.I)
    s_clean = re.sub(r"\bin\s+[A-G][b#es]*\b", "", s_clean, flags=re.I)
    s_clean = re.sub(r"\s+", " ", s_clean).strip(" -_")

    # "01_FluteI-II" / "03_BbClarinet1" / "05_Klarinette 1 B"
    m = re.match(r"^(?:(\d{1,3})[_.\-\s]+)(.+)$", s_clean)
    ord_hint = None
    body = s_clean
    if m:
        ord_hint = int(m.group(1))
        body = m.group(2).strip()

    # Drop trailing German key letters from body for instrument match (kept in `key`)
    body_nokey = re.sub(r"[\s_]+(B|Es|AS|As|C|F)$", "", body, flags=re.I).strip()

    # Stimme N patterns: "1._Stimme_(B)"
    m = re.search(r"(\d+)\.?\s*_?stimme", body, re.I)
    if m:
        voice = str(int(m.group(1)))
        if key:
            voice = f"{voice}_{key}"
        return {
            "ok": True,
            "instrument": "Unknown",
            "voice": voice,
            "ord_hint": ord_hint,
            "raw": raw,
            "note": "generic_stimme",
        }

    def finalize(inst: str, voice: str) -> dict:
        inst2, key_left = apply_key_to_instrument(inst, key)
        if clef and inst2 in ("Baritone", "Bass", "Euphonium", "Trombone"):
            voice = clef if voice in ("1", "") else f"{voice}_{clef}"
        elif key_left:
            voice = f"{voice}_{key_left}" if voice else key_left
        return {
            "ok": True,
            "instrument": inst2,
            "voice": voice,
            "ord_hint": ord_hint,
            "raw": raw,
        }

    # Compact: Instrument + roman/digit
    m = re.match(
        r"^(.+?)[\s_-]*(I{1,3}|IV|V|VI{0,3}|\d+)(?:\s*[-–+/]\s*(I{1,3}|IV|V|VI{0,3}|\d+))?([A-Za-z]*)$",
        body_nokey,
    )
    if m:
        inst_raw = m.group(1)
        trail = m.group(4) or ""
        if trail and re.fullmatch(r"(?i)b|es|as|f|c|eb|bb", trail):
            inst_raw = inst_raw + trail
        inst = map_instrument(inst_raw)
        if inst:
            voice = roman_or_digit(m.group(2))
            if m.group(3):
                voice = f"{voice}-{roman_or_digit(m.group(3))}"
            return finalize(inst, voice)

    # Spaced: "Alto Saxophone 1" / "Clarinet in Bb 1" / "Bassoon 1+2"
    m = re.match(
        r"^(.+?)\s+(I{1,3}|IV|V|\d+)(?:\s*[-–+/]\s*(I{1,3}|IV|V|\d+))?$",
        body_nokey,
        re.I,
    )
    if m:
        inst = map_instrument(m.group(1))
        if inst:
            voice = roman_or_digit(m.group(2))
            if m.group(3):
                voice = f"{voice}-{roman_or_digit(m.group(3))}"
            return finalize(inst, voice)

    # Instrument only → voice 1
    inst = map_instrument(body_nokey) or map_instrument(body)
    if inst:
        return finalize(inst, "1")

    # Section bundles: (Brass)/(Winds)/(Percussion)
    m = re.search(r"\((Brass|Winds|Percussion|Holz|Blech)\)", stem, re.I)
    if m:
        section = m.group(1).capitalize()
        if section.lower() in ("holz",):
            section = "Winds"
        if section.lower() in ("blech",):
            section = "Brass"
        return {
            "ok": True,
            "instrument": "Score",
            "voice": section,
            "ord_hint": ord_hint or 0,
            "raw": raw,
            "note": "section_score",
        }

    return {"ok": False, "reason": "unmatched", "raw": raw}


def target_name(instrument: str, voice: str, ord_num: int) -> str:
    voice = re.sub(r"[^\w\-]+", "", voice.replace(" ", ""))
    if instrument == "Score" and voice in ("1", ""):
        return f"{ord_num:02d}_Score.pdf"
    return f"{ord_num:02d}_{instrument}_{voice}.pdf"


def folder_title(folder_name: str) -> str:
    m = re.match(r"^\d+\s*[-–]?\s*(.+)$", folder_name.strip())
    return m.group(1).strip() if m else folder_name


def default_ord(instrument: str) -> int:
    try:
        return INSTRUMENT_ORDER.index(instrument)
    except ValueError:
        return 90


def unique_path(directory: Path, filename: str) -> Path:
    dest = directory / filename
    if not dest.exists():
        return dest
    stem = Path(filename).stem
    for i in range(2, 1000):
        cand = directory / f"{stem}__{i}.pdf"
        if not cand.exists():
            return cand
    raise RuntimeError(f"Cannot uniqueify {filename}")


def normalize_folder_name(name: str) -> str | None:
    """Ensure '{NNNN} - Title' form when a leading number exists."""
    if name.startswith("ZZZ"):
        return None
    m = re.match(r"^(\d+)\s+([^-].+)$", name)
    if m and " - " not in name:
        return f"{int(m.group(1)):04d} - {m.group(2).strip()}"
    m = re.match(r"^(\d+)\s*[-–]\s*(.+)$", name)
    if m:
        return f"{int(m.group(1)):04d} - {m.group(2).strip()}"
    return None


def process_mirror(mirror: Path, dry_run: bool) -> dict:
    report = {
        "renamed": [],
        "unchanged": [],
        "unmatched": [],
        "folders_renamed": [],
        "errors": [],
    }

    # Optional folder renames first
    for folder in sorted([p for p in mirror.iterdir() if p.is_dir()], key=lambda p: p.name):
        new_name = normalize_folder_name(folder.name)
        if new_name and new_name != folder.name:
            dest = mirror / new_name
            report["folders_renamed"].append({"from": folder.name, "to": new_name})
            if not dry_run:
                if dest.exists():
                    report["errors"].append(f"folder exists: {new_name}")
                else:
                    folder.rename(dest)

    # Refresh folder list after renames
    folders = [p for p in mirror.iterdir() if p.is_dir() and not p.name.startswith("_")]

    for folder in sorted(folders, key=lambda p: p.name):
        title = folder_title(folder.name)
        pdfs = sorted(folder.rglob("*.pdf"))
        # Group by parent dir so nested ZZZ subfolders stay local
        by_dir: dict[Path, list[Path]] = defaultdict(list)
        for pdf in pdfs:
            by_dir[pdf.parent].append(pdf)

        for parent, files in by_dir.items():
            planned: list[tuple[Path, str, dict]] = []
            name_claims: dict[str, int] = defaultdict(int)

            for pdf in files:
                if pdf.name.startswith("_REVIEW_"):
                    report["unmatched"].append(
                        {
                            "path": str(pdf.relative_to(mirror)).replace("\\", "/"),
                            "reason": "already_review",
                        }
                    )
                    continue
                parsed = parse_part(pdf.stem, title)
                if not parsed or not parsed.get("ok"):
                    reason = (parsed or {}).get("reason", "unmatched")
                    rel = str(pdf.relative_to(mirror)).replace("\\", "/")
                    report["unmatched"].append({"path": rel, "reason": reason, "raw": pdf.name})
                    new_name = f"_REVIEW_{pdf.name}"
                    if new_name != pdf.name:
                        planned.append((pdf, new_name, {"ok": False, "reason": reason}))
                    continue

                inst = parsed["instrument"]
                voice = str(parsed["voice"])
                hint = parsed.get("ord_hint")
                ord_num = int(hint) if hint is not None else default_ord(inst)
                if inst == "Score" and voice == "1":
                    ord_num = 0
                new_name = target_name(inst, voice, ord_num)
                # Disambiguate duplicate target names in same folder
                name_claims[new_name] += 1
                if name_claims[new_name] > 1:
                    stem = Path(new_name).stem
                    new_name = f"{stem}__{name_claims[new_name]}.pdf"

                if pdf.name == new_name:
                    report["unchanged"].append(str(pdf.relative_to(mirror)).replace("\\", "/"))
                    continue
                planned.append((pdf, new_name, parsed))

            # Two-phase rename to avoid collisions
            temps: list[tuple[Path, Path, str, dict]] = []
            for pdf, new_name, meta in planned:
                rel = str(pdf.relative_to(mirror)).replace("\\", "/")
                if dry_run:
                    report["renamed"].append({"from": rel, "to": f"{parent.relative_to(mirror).as_posix()}/{new_name}", "meta": {
                        "instrument": meta.get("instrument"),
                        "voice": meta.get("voice"),
                        "reason": meta.get("reason"),
                    }})
                    continue
                tmp = pdf.with_name(f".__tmp_rename__{pdf.name}")
                try:
                    pdf.rename(tmp)
                    temps.append((tmp, parent, new_name, meta))
                except OSError as e:
                    report["errors"].append(f"{rel}: {e}")

            for tmp, parent, new_name, meta in temps:
                dest = unique_path(parent, new_name)
                try:
                    tmp.rename(dest)
                    report["renamed"].append({
                        "from": str(tmp.name).replace(".__tmp_rename__", ""),
                        "to": str(dest.relative_to(mirror)).replace("\\", "/"),
                        "meta": {
                            "instrument": meta.get("instrument"),
                            "voice": meta.get("voice"),
                            "reason": meta.get("reason"),
                        },
                    })
                except OSError as e:
                    report["errors"].append(f"{tmp}: {e}")
                    # try restore
                    try:
                        tmp.rename(parent / str(tmp.name).replace(".__tmp_rename__", ""))
                    except OSError:
                        pass

    return report


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument(
        "--mirror",
        default=str(Path(__file__).resolve().parents[1] / "data" / "Notenarchiv"),
    )
    ap.add_argument(
        "--report",
        default="",
        help="Write JSON report path (default: mirror/_normalize_report.json)",
    )
    args = ap.parse_args()
    mirror = Path(args.mirror)
    if not mirror.is_dir():
        print(f"Mirror missing: {mirror}")
        return 1

    report = process_mirror(mirror, dry_run=args.dry_run)
    out = Path(args.report) if args.report else mirror / "_normalize_report.json"
    payload = {
        "dryRun": args.dry_run,
        "mirror": str(mirror),
        "counts": {
            "renamed": len(report["renamed"]),
            "unchanged": len(report["unchanged"]),
            "unmatched": len(report["unmatched"]),
            "folders_renamed": len(report["folders_renamed"]),
            "errors": len(report["errors"]),
        },
        **report,
    }
    out.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"OK dry_run={args.dry_run}")
    for k, v in payload["counts"].items():
        print(f"STAT\t{k}\t{v}")
    print(f"REPORT\t{out}")
    return 0 if not report["errors"] else 2


if __name__ == "__main__":
    raise SystemExit(main())
