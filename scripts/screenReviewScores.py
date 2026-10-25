#!/usr/bin/env python3
"""
Screen _REVIEW_*.pdf in data/Notenarchiv/: assign instrument/voice from
filename heuristics + PDF text/OCR, then rename to {nn}_{Instrument}_{Voice}.pdf.

Local mirror only — never touches the cloud mount.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
import unicodedata
from collections import defaultdict
from pathlib import Path

# Reuse normalizer helpers
sys.path.insert(0, str(Path(__file__).resolve().parent))
from normalizeScoreMirrorNames import (  # noqa: E402
    INSTRUMENT_ORDER,
    default_ord,
    map_instrument,
    norm,
    parse_part,
    target_name,
    unique_path,
)

try:
    import pymupdf as fitz
except ImportError:
    import fitz  # type: ignore


def folder_title(folder_name: str) -> str:
    m = re.match(r"^\d+\s*[-–]?\s*(.+)$", folder_name.strip())
    return m.group(1).strip() if m else folder_name


def strip_review_prefix(name: str) -> str:
    return re.sub(r"^_REVIEW_", "", name)


# Extra filename patterns not covered by normalizeScoreMirrorNames.parse_part
FILENAME_HINTS: list[tuple[re.Pattern[str], str, str]] = [
    (re.compile(r"(?i)bugel|flugel|flügel"), "Flugelhorn", "1"),
    (re.compile(r"(?i)kleine\s*bugel"), "Flugelhorn", "1"),
    (re.compile(r"(?i)(\d+)(?:de|ste)?\s*bugel"), "Flugelhorn", ""),  # voice from group
    (re.compile(r"(?i)\bhobo\b|\boboe\b"), "Oboe", "1"),
    (re.compile(r"(?i)\bfagot\b|\bbassoon\b"), "Bassoon", "1"),
    (re.compile(r"(?i)\bhoorn\b|\bhorn\b"), "Horn", "1"),
    (re.compile(r"(?i)saxofoon\s*alt|altsax"), "AltoSax", "1"),
    (re.compile(r"(?i)saxofoon\s*tenor|tenorsax"), "TenorSax", "1"),
    (re.compile(r"(?i)sopraan\s*sax|sopranosax"), "SopranoSax", "1"),
    (re.compile(r"(?i)pikkolo|piccolo"), "Piccolo", "1"),
    (re.compile(r"(?i)directie|dirigat|partituur|direktion|\bdir\b"), "Score", "1"),
    (re.compile(r"(?i)omslag|cover"), "Score", "Cover"),
    (re.compile(r"(?i)gro(?:ß|ss)e\s*trommel|bass\s*drum"), "Percussion", "BD"),
    (re.compile(r"(?i)kleine\s*trom(?:mel)?|snare"), "Percussion", "SD"),
    (re.compile(r"(?i)bekkens|cymbal"), "Percussion", "Cymbals"),
    (re.compile(r"(?i)grote\s*trom"), "Percussion", "BD"),
    (re.compile(r"(?i)schlagwerk|percussion"), "Percussion", "1"),
]


def parse_stmartin(stem: str) -> dict | None:
    # StMartin_1.in_b / StMartin_3.in_b_ten / StMartin_4.in_es_bari / StMartin_1.in_c_fl
    m = re.match(
        r"(?i)StMartin[_ ]?(\d+)\.in[_ ]?(b|es|c)(?:[_ ]?(fl|ten|bari|trp|pos))?",
        stem,
    )
    if not m:
        return None
    part = m.group(1)
    key = {"b": "Bb", "es": "Eb", "c": "C"}[m.group(2).lower()]
    kind = (m.group(3) or "").lower()
    inst = {
        "": "Unknown",
        "fl": "Flute",
        "ten": "TenorSax",
        "bari": "BariSax",
        "trp": "Trumpet",
        "pos": "Trombone",
    }.get(kind, "Unknown")
    if inst == "Unknown" and key == "C":
        inst = "Flute"  # in C often flute/oboe books — fl already handled
    if inst == "Unknown":
        # generic band part by key
        inst = {"Bb": "Clarinet", "Eb": "AltoSax", "C": "Flute"}.get(key, "Unknown")
    return {
        "ok": True,
        "instrument": inst,
        "voice": f"{part}_{key}",
        "source": "stmartin",
    }


def parse_dutch_filename(stem: str) -> dict | None:
    s = stem
    # "2de bugel(B)" / "3de bugel(B)" / "kleine bugel(Es)"
    m = re.search(r"(?i)(\d+)(?:de|ste|e)?\s*bugel", s)
    if m:
        return {"ok": True, "instrument": "Flugelhorn", "voice": m.group(1), "source": "nl"}
    if re.search(r"(?i)kleine\s*bugel", s):
        return {"ok": True, "instrument": "Flugelhorn", "voice": "Sop", "source": "nl"}
    m = re.search(r"(?i)hobo\s*(\d+)", s)
    if m:
        return {"ok": True, "instrument": "Oboe", "voice": m.group(1), "source": "nl"}
    if re.search(r"(?i)\bhobo\b", s):
        return {"ok": True, "instrument": "Oboe", "voice": "1", "source": "nl"}
    if re.search(r"(?i)\bfagot\b", s):
        return {"ok": True, "instrument": "Bassoon", "voice": "1", "source": "nl"}
    m = re.search(r"(?i)hoorn\s*(\d+)\s*en\s*(\d+)", s)
    if m:
        return {"ok": True, "instrument": "Horn", "voice": f"{m.group(1)}-{m.group(2)}", "source": "nl"}
    m = re.search(r"(?i)hoorn\s*(\d+)", s)
    if m:
        return {"ok": True, "instrument": "Horn", "voice": m.group(1), "source": "nl"}
    if re.search(r"(?i)f\s*hoorn|hoorn", s):
        return {"ok": True, "instrument": "Horn", "voice": "1", "source": "nl"}
    m = re.search(r"(?i)saxofoon\s*alt\s*(\d+)", s)
    if m:
        return {"ok": True, "instrument": "AltoSax", "voice": m.group(1), "source": "nl"}
    if re.search(r"(?i)saxofoon\s*alt", s):
        return {"ok": True, "instrument": "AltoSax", "voice": "1", "source": "nl"}
    if re.search(r"(?i)saxofoon\s*tenor", s):
        return {"ok": True, "instrument": "TenorSax", "voice": "1", "source": "nl"}
    if re.search(r"(?i)sopraan\s*sax", s):
        return {"ok": True, "instrument": "SopranoSax", "voice": "1", "source": "nl"}
    if re.search(r"(?i)trb\s*c\s*(\d+)", s):
        m = re.search(r"(?i)trb\s*c\s*(\d+)", s)
        return {"ok": True, "instrument": "Trombone", "voice": f"{m.group(1)}_C", "source": "nl"}
    if re.search(r"(?i)directie|\bdir\.?\b|dirig", s):
        return {"ok": True, "instrument": "Score", "voice": "1", "source": "nl"}
    if re.search(r"(?i)omslag", s):
        return {"ok": True, "instrument": "Score", "voice": "Cover", "source": "nl"}
    if re.search(r"(?i)grote\s*trom|gro(?:ß|ss)e\s*trommel", s):
        return {"ok": True, "instrument": "Percussion", "voice": "BD", "source": "nl"}
    if re.search(r"(?i)kleine\s*trom", s):
        return {"ok": True, "instrument": "Percussion", "voice": "SD", "source": "nl"}
    if re.search(r"(?i)bekkens", s):
        return {"ok": True, "instrument": "Percussion", "voice": "Cymbals", "source": "nl"}
    if re.search(r"(?i)^\d+\s*pikkolo|pikkolo", s):
        return {"ok": True, "instrument": "Piccolo", "voice": "1", "source": "nl"}
    return None


INSTRUMENT_KEYWORDS: list[tuple[str, str]] = [
    ("piccolo", "Piccolo"),
    ("pikkolo", "Piccolo"),
    ("flute", "Flute"),
    ("flöte", "Flute"),
    ("floete", "Flute"),
    ("oboe", "Oboe"),
    ("hobo", "Oboe"),
    ("english horn", "EnglishHorn"),
    ("bassoon", "Bassoon"),
    ("fagott", "Bassoon"),
    ("fagot", "Bassoon"),
    ("eb clarinet", "EbClarinet"),
    ("e-flat clarinet", "EbClarinet"),
    ("clarinet", "Clarinet"),
    ("klarinette", "Clarinet"),
    ("alto clarinet", "AltoClarinet"),
    ("bass clarinet", "BassClarinet"),
    ("soprano sax", "SopranoSax"),
    ("alto sax", "AltoSax"),
    ("tenor sax", "TenorSax"),
    ("baritone sax", "BariSax"),
    ("altsax", "AltoSax"),
    ("tenorsax", "TenorSax"),
    ("trumpet", "Trumpet"),
    ("trompete", "Trumpet"),
    ("cornet", "Cornet"),
    ("flugelhorn", "Flugelhorn"),
    ("flügelhorn", "Flugelhorn"),
    ("bugel", "Flugelhorn"),
    ("horn in f", "Horn"),
    ("french horn", "Horn"),
    ("horn", "Horn"),
    ("hoorn", "Horn"),
    ("trombone", "Trombone"),
    ("posaune", "Trombone"),
    ("euphonium", "Euphonium"),
    ("baritone", "Baritone"),
    ("tuba", "Tuba"),
    ("percussion", "Percussion"),
    ("drums", "Percussion"),
    ("snare", "Percussion"),
    ("timpani", "Timpani"),
    ("mallets", "Mallets"),
    ("conductor", "Score"),
    ("full score", "Score"),
    ("score", "Score"),
    ("direktion", "Score"),
    ("directie", "Score"),
]


def extract_pdf_text(path: Path, max_pages: int = 2) -> str:
    try:
        doc = fitz.open(path)
    except Exception:
        return ""
    parts: list[str] = []
    for i, page in enumerate(doc):
        if i >= max_pages:
            break
        parts.append(page.get_text("text") or "")
    return "\n".join(parts)


_ocr_engine = None


def get_ocr():
    global _ocr_engine
    if _ocr_engine is False:
        return None
    if _ocr_engine is not None:
        return _ocr_engine
    try:
        from rapidocr_onnxruntime import RapidOCR

        _ocr_engine = RapidOCR()
        return _ocr_engine
    except Exception:
        _ocr_engine = False
        return None


def _ocr_pixmap(engine, pix) -> str:
    try:
        import numpy as np
        from io import BytesIO
        from PIL import Image

        img = Image.open(BytesIO(pix.tobytes("png"))).convert("RGB")
        result, _ = engine(np.array(img))
        if not result:
            return ""
        return "\n".join(row[1] for row in result if row and len(row) > 1)
    except Exception:
        return ""


def ocr_pdf_headers(path: Path) -> str:
    """OCR top-left + full top strip of page 1 (where part names live)."""
    engine = get_ocr()
    if engine is None:
        return ""
    try:
        doc = fitz.open(path)
    except Exception:
        return ""
    if doc.page_count < 1:
        return ""
    page = doc[0]
    r = page.rect
    clips = [
        fitz.Rect(r.x0, r.y0, r.x0 + r.width * 0.5, r.y0 + r.height * 0.2),
        fitz.Rect(r.x0, r.y0, r.x1, r.y0 + r.height * 0.22),
    ]
    chunks: list[str] = []
    for clip in clips:
        pix = page.get_pixmap(matrix=fitz.Matrix(3.2, 3.2), clip=clip, alpha=False)
        t = _ocr_pixmap(engine, pix)
        if t.strip():
            chunks.append(t)
    return "\n".join(chunks)


def roman_voice(v: str) -> str:
    v = v.strip().lower()
    mapping = {"i": "1", "ii": "2", "iii": "3", "iv": "4", "v": "5"}
    return mapping.get(v, v)


def classify_from_text(text: str) -> dict | None:
    if not text or not text.strip():
        return None
    low = text.lower().replace("\n", " ")

    # Condensed / multi-cue score pages
    if re.search(r"(?i)\bscore\b|bezetting|instrumentation|besetzung", low):
        return {"ok": True, "instrument": "Score", "voice": "1", "source": "pdf_text"}
    if re.search(
        r"(?i)w\.w\.|crm?n?\.?\s*/\s*trp|1\.clar|/flug|/alto sax|hms\b|bar\./euph|"
        r"hrns\s*/|clar\./\s*hrns|euph\.?/bar|hrms?/trb|2/3\s*clar",
        low,
    ):
        return {"ok": True, "instrument": "Score", "voice": "Condensed", "source": "pdf_text"}
    if re.search(r"(?i)\+?\s*pic\b|piccolo", low):
        return {"ok": True, "instrument": "Piccolo", "voice": "1", "source": "pdf_text"}
    if re.search(r"(?i)1\.?\s*fl[oö]te\b|flöte\s*1", low):
        return {"ok": True, "instrument": "Flute", "voice": "1", "source": "pdf_text"}
    if re.search(r"(?i)\bpiano\b", low):
        return {"ok": True, "instrument": "Piano", "voice": "1", "source": "pdf_text"}
    if re.search(r"(?i)carnival of venice|arban", low):
        return {"ok": True, "instrument": "Trumpet", "voice": "Solo", "source": "pdf_text"}
    if re.search(r"(?i)die post|la poste|violinstimme", low):
        return {"ok": True, "instrument": "Cornet", "voice": "Solo", "source": "pdf_text"}

    patterns: list[tuple[str, str, str]] = [
        (r"(?i)eb\s*alto\s*sax\.?\s*(i{1,3}|\d+)?", "AltoSax", "1"),
        (r"(?i)bb\s*clarinet\s*(i{1,3}|\d+)?", "Clarinet", "1"),
        (r"(?i)oboe\s*(i{1,3}|\d+)?", "Oboe", "1"),
        (r"(?i)f\s*horn\s*(i{1,3}|\d+)?", "Horn", "1"),
        (r"(?i)bb\s*flugelhorn\s*(i{1,3}|\d+)?", "Flugelhorn", "1"),
        (r"(?i)eb\s*cornet\s*(i{1,3}|\d+)?", "Cornet", "1"),
        (r"(?i)bb\s*baritone\s*(?:\(|\s)*(i{1,3}|\d+)?", "Baritone", "1"),
        (r"(?i)baritone\s*c\b", "Baritone", "C"),
        (r"(?i)eb\s*bass\s*(i{1,3}|\d+|[:.])?", "Bass", "Eb"),
        (r"(?i)bb\s*bass\s*(i{1,3}|\d+|[:.])?", "Bass", "Bb"),
        (r"(?i)flute\s*(i{1,3}|\d+)?", "Flute", "1"),
        (r"(?i)piccolo", "Piccolo", "1"),
        (r"(?i)trombone\s*(i{1,3}|\d+)?", "Trombone", "1"),
        (r"(?i)trumpet\s*(i{1,3}|\d+)?", "Trumpet", "1"),
        (r"(?i)tuba", "Tuba", "1"),
        (r"(?i)percussion|drums", "Percussion", "1"),
    ]
    for pat, inst, default_voice in patterns:
        m = re.search(pat, text)
        if not m:
            continue
        voice = default_voice
        if m.lastindex:
            g = m.group(1)
            if g and re.fullmatch(r"i{1,3}|\d+", g.strip(), re.I):
                voice = roman_voice(g)
        return {"ok": True, "instrument": inst, "voice": voice, "source": "pdf_text"}

    for needle, inst in sorted(INSTRUMENT_KEYWORDS, key=lambda kv: -len(kv[0])):
        if needle in low:
            return {"ok": True, "instrument": inst, "voice": "1", "source": "pdf_text"}
    return None


# Manual overrides after OCR/filename pass (relative path under mirror, with _REVIEW_ name)
MANUAL_OVERRIDES: dict[str, tuple[str, str]] = {
    # Spiritual Moments fragments without printable part header
    "0021 - SPIRITUAL MOMENTS/_REVIEW_doc00228920240919211924.pdf": ("Flute", "1"),
    "0021 - SPIRITUAL MOMENTS/_REVIEW_doc00229020240919211944.pdf": ("Piccolo", "1"),
    "0021 - SPIRITUAL MOMENTS/_REVIEW_doc00230520240919213228.pdf": ("Score", "1"),
    "0021 - SPIRITUAL MOMENTS/_REVIEW_doc00231320240919213400.pdf": ("Score", "Condensed"),
    "0021 - SPIRITUAL MOMENTS/_REVIEW_doc00231520240919213421.pdf": ("Score", "Condensed"),
    "0021 - SPIRITUAL MOMENTS/_REVIEW_doc00232420240919213610.pdf": ("Score", "Condensed"),
    "0134 - CELTIC CREST/_REVIEW_PasiniForYour.pdf": ("Score", "Extra"),
    "ZZZ_(noch) ohne Nummer/Hymne Russland/_REVIEW_Russland.pdf": ("Flute", "1"),
    "ZZZ_(noch) ohne Nummer/Ostermann/_REVIEW_Ostermann.pdf": ("Score", "1"),
    "ZZZ_(noch) ohne Nummer/trp_orgel/_REVIEW_234810.pdf": ("Trumpet", "Solo"),
    "ZZZ_(noch) ohne Nummer/trp_orgel/_REVIEW_257077.pdf": ("Piano", "1"),
    "ZZZ_(noch) ohne Nummer/trp_orgel/_REVIEW_289016.pdf": ("Trumpet", "Solo"),
    "ZZZ_(noch) ohne Nummer/trp_orgel/_REVIEW_567593.pdf": ("Cornet", "Solo"),
}


def classify_review_file(path: Path, title: str, use_ocr: bool, rel: str = "") -> dict:
    stem = Path(strip_review_prefix(path.name)).stem
    if rel in MANUAL_OVERRIDES:
        inst, voice = MANUAL_OVERRIDES[rel]
        return {"ok": True, "instrument": inst, "voice": voice, "source": "manual"}

    sm = parse_stmartin(stem)
    if sm:
        return sm

    nl = parse_dutch_filename(stem)
    if nl:
        return nl

    parsed = parse_part(stem, title)
    if parsed and parsed.get("ok"):
        parsed["source"] = "filename"
        return parsed

    # Whole-work leftovers often titled as the piece itself
    if norm(stem) == norm(title) or norm(stem).startswith(norm(title)[:8]):
        # likely full score / book
        return {"ok": True, "instrument": "Score", "voice": "1", "source": "title_match"}

    text = extract_pdf_text(path)
    hit = classify_from_text(text)
    if hit:
        return hit

    if use_ocr:
        ocr_text = ocr_pdf_headers(path)
        hit = classify_from_text(ocr_text)
        if hit:
            hit["source"] = "ocr"
            hit["ocr_snippet"] = ocr_text[:240]
            return hit
        # Short single-page cue sheets without part title → condensed score fragment
        try:
            doc = fitz.open(path)
            if doc.page_count == 1 and len(ocr_text.strip()) < 40:
                return {
                    "ok": True,
                    "instrument": "Score",
                    "voice": "Fragment",
                    "source": "ocr_heuristic",
                    "ocr_snippet": ocr_text[:240],
                }
        except Exception:
            pass
        if ocr_text.strip():
            return {
                "ok": False,
                "reason": "ocr_no_instrument",
                "ocr_snippet": ocr_text[:300],
            }

    return {"ok": False, "reason": "still_unmatched", "raw": stem}


def process(mirror: Path, dry_run: bool, use_ocr: bool) -> dict:
    report = {"assigned": [], "failed": [], "errors": []}
    reviews = sorted(mirror.rglob("_REVIEW_*.pdf"))
    by_dir: dict[Path, list[Path]] = defaultdict(list)
    for p in reviews:
        by_dir[p.parent].append(p)

    for parent, files in by_dir.items():
        # title from nearest numbered ancestor
        title = parent.name
        for anc in [parent, *parent.parents]:
            if anc == mirror:
                break
            if re.match(r"^\d+", anc.name) or anc.name.startswith("ZZZ"):
                title = folder_title(anc.name)
                break

        name_claims: dict[str, int] = defaultdict(int)
        planned: list[tuple[Path, str, dict]] = []

        for pdf in files:
            rel = str(pdf.relative_to(mirror)).replace("\\", "/")
            result = classify_review_file(pdf, title, use_ocr=use_ocr, rel=rel)
            if not result.get("ok"):
                report["failed"].append(
                    {
                        "path": str(pdf.relative_to(mirror)).replace("\\", "/"),
                        "reason": result.get("reason"),
                        "ocr_snippet": result.get("ocr_snippet"),
                    }
                )
                continue
            inst = result["instrument"]
            voice = str(result["voice"])
            ord_num = 0 if inst == "Score" and voice == "1" else default_ord(inst)
            # try keep numeric voice as ord when present
            m = re.match(r"^(\d+)", voice)
            if m and inst != "Score":
                ord_num = int(m.group(1))
            new_name = target_name(inst, voice, ord_num)
            name_claims[new_name] += 1
            if name_claims[new_name] > 1:
                new_name = f"{Path(new_name).stem}__{name_claims[new_name]}.pdf"
            planned.append((pdf, new_name, result))

        for pdf, new_name, meta in planned:
            rel = str(pdf.relative_to(mirror)).replace("\\", "/")
            entry = {
                "from": rel,
                "to": f"{parent.relative_to(mirror).as_posix()}/{new_name}",
                "instrument": meta.get("instrument"),
                "voice": meta.get("voice"),
                "source": meta.get("source"),
            }
            if dry_run:
                report["assigned"].append(entry)
                continue
            tmp = pdf.with_name(f".__tmp_screen__{pdf.name}")
            try:
                pdf.rename(tmp)
                dest = unique_path(parent, new_name)
                tmp.rename(dest)
                entry["to"] = str(dest.relative_to(mirror)).replace("\\", "/")
                report["assigned"].append(entry)
            except OSError as e:
                report["errors"].append(f"{rel}: {e}")
                try:
                    tmp.rename(pdf)
                except OSError:
                    pass

    return report


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--dry-run", action="store_true")
    ap.add_argument("--no-ocr", action="store_true")
    ap.add_argument(
        "--mirror",
        default=str(Path(__file__).resolve().parents[1] / "data" / "Notenarchiv"),
    )
    args = ap.parse_args()
    mirror = Path(args.mirror)
    report = process(mirror, dry_run=args.dry_run, use_ocr=not args.no_ocr)
    out = mirror / "_screen_report.json"
    payload = {
        "dryRun": args.dry_run,
        "useOcr": not args.no_ocr,
        "counts": {
            "assigned": len(report["assigned"]),
            "failed": len(report["failed"]),
            "errors": len(report["errors"]),
        },
        **report,
    }
    out.write_text(json.dumps(payload, indent=2, ensure_ascii=False), encoding="utf-8")
    print(f"OK dry_run={args.dry_run}")
    for k, v in payload["counts"].items():
        print(f"STAT\t{k}\t{v}")
    print(f"REPORT\t{out}")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
