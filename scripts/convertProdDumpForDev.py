#!/usr/bin/env python3
"""
Convert a legacy Notenarchiv phpMyAdmin dump (unprefixed tables) into
INSERT-only SQL for the current archiv_ / archiv-dev_ schema.

Does not touch meldeliste_* (identity, termine, instruments, …).
Skips Config, Users, Log, Instruments, Registers, meldeliste_User.

Usage:
  python3 scripts/convertProdDumpForDev.py \\
    --prod /path/to/prod.sql \\
    --out  /path/to/archiv_catalog_from_prod.sql \\
    [--prefix archiv_] \\
    [--dev /path/to/melde-or-dev.sql]   # optional, for Instrument name matching
"""
from __future__ import annotations

import argparse
import html
import re
import sys
from pathlib import Path
from typing import Any, Dict, List, Optional, Tuple


# Prod Instruments.Index → meldeliste-dev_Instrument.Index (by name).
# Percussion variants and Partitur fall back to closest Melde instruments.
DEFAULT_INSTRUMENT_MAP = {
    1: 1,    # Querflöte → Flöte
    2: 2,    # Piccolo → Piccolo-Flöte
    3: 3,    # B-Klarinette
    4: 4,    # Es-Klarinette
    5: 5,    # Alt-Saxophon
    6: 6,    # Tenor-Saxophon
    7: 7,    # Bariton-Saxophon
    8: 20,   # Flügelhorn
    9: 15,   # Bassklarinette → Bass-Klarinette
    10: 24,  # Fagott
    11: 23,  # Oboe
    12: 25,  # Englischhorn
    13: 11,  # Trompete
    14: 27,  # Kornett
    15: 16,  # Posaune
    16: 17,  # Bassposaune → Bass-Posaune
    17: 26,  # Euphonium
    18: 13,  # Tenorhorn → Tenor-Horn
    19: 14,  # Baritonhorn → Bariton-Horn
    20: 18,  # Tuba
    21: 19,  # Kontrabass
    22: 9,   # Drumset → Schlagwerk
    23: 9,   # Pauke → Schlagwerk
    24: 9,   # Xylophone → Schlagwerk
    25: 9,   # Glockenspiel → Schlagwerk
    26: 9,   # Vibraphone → Schlagwerk
    27: 9,   # Percussion → Schlagwerk
    28: 9,   # Klavier (kein Melde-Äquivalent) → Schlagwerk placeholder
    29: 9,   # Keyboard → Schlagwerk placeholder
    30: 9,   # Orgel → Schlagwerk placeholder
    31: 19,  # Cello → Kontrabass (closest bass string)
    32: 12,  # Horn → Waldhorn
    33: 22,  # Partitur → Dirigent
}


def decode_text(value: str) -> str:
    return html.unescape(value)


def sql_quote(value: Optional[Any]) -> str:
    if value is None:
        return "NULL"
    if isinstance(value, bool):
        return "1" if value else "0"
    if isinstance(value, int):
        return str(value)
    if isinstance(value, float):
        # Keep ints clean when Grade is whole number
        if value.is_integer():
            return str(int(value))
        return repr(value)
    s = decode_text(str(value))
    s = s.replace("\\", "\\\\").replace("'", "''")
    return "'" + s + "'"


def split_sql_values(values_blob: str) -> List[str]:
    """Split INSERT value tuples, respecting quotes and escapes."""
    rows: List[str] = []
    i = 0
    n = len(values_blob)
    while i < n:
        while i < n and values_blob[i] in " \t\r\n,":
            i += 1
        if i >= n:
            break
        if values_blob[i] != "(":
            raise ValueError(f"Expected '(' at pos {i}: {values_blob[i:i+40]!r}")
        depth = 0
        in_str = False
        escape = False
        start = i
        while i < n:
            ch = values_blob[i]
            if in_str:
                if escape:
                    escape = False
                elif ch == "\\":
                    escape = True
                elif ch == "'":
                    # SQL '' escape
                    if i + 1 < n and values_blob[i + 1] == "'":
                        i += 2
                        continue
                    in_str = False
            else:
                if ch == "'":
                    in_str = True
                elif ch == "(":
                    depth += 1
                elif ch == ")":
                    depth -= 1
                    if depth == 0:
                        rows.append(values_blob[start : i + 1])
                        i += 1
                        break
            i += 1
        else:
            raise ValueError("Unterminated value tuple")
    return rows


def parse_tuple(tuple_sql: str) -> List[Optional[Any]]:
    """Parse a single (... ) tuple into Python values."""
    assert tuple_sql[0] == "(" and tuple_sql[-1] == ")"
    body = tuple_sql[1:-1]
    values: List[Optional[Any]] = []
    i = 0
    n = len(body)
    while i < n:
        while i < n and body[i] in " \t\r\n":
            i += 1
        if i >= n:
            break
        if body.startswith("NULL", i) and (i + 4 == n or body[i + 4] in ", \t\r\n"):
            values.append(None)
            i += 4
        elif body[i] == "'":
            i += 1
            chars: List[str] = []
            while i < n:
                ch = body[i]
                if ch == "\\" and i + 1 < n:
                    chars.append(body[i + 1])
                    i += 2
                    continue
                if ch == "'":
                    if i + 1 < n and body[i + 1] == "'":
                        chars.append("'")
                        i += 2
                        continue
                    i += 1
                    break
                chars.append(ch)
                i += 1
            values.append("".join(chars))
        else:
            j = i
            while j < n and body[j] != ",":
                j += 1
            token = body[i:j].strip()
            if re.fullmatch(r"-?\d+", token):
                values.append(int(token))
            elif re.fullmatch(r"-?\d+\.\d+", token):
                values.append(float(token))
            else:
                raise ValueError(f"Cannot parse token {token!r}")
            i = j
        while i < n and body[i] in " \t\r\n":
            i += 1
        if i < n and body[i] == ",":
            i += 1
    return values


def _scan_sql_statement_end(sql: str, start: int) -> int:
    """Return index after terminating ';' of a SQL statement starting at start.

    Semicolons inside quoted strings (and HTML entities like &ouml; inside
    strings) must not end the statement.
    """
    i = start
    n = len(sql)
    in_str = False
    escape = False
    while i < n:
        ch = sql[i]
        if in_str:
            if escape:
                escape = False
            elif ch == "\\":
                escape = True
            elif ch == "'":
                if i + 1 < n and sql[i + 1] == "'":
                    i += 2
                    continue
                in_str = False
        else:
            if ch == "'":
                in_str = True
            elif ch == ";":
                return i + 1
        i += 1
    raise ValueError(f"Unterminated SQL statement at {start}")


def extract_inserts(sql: str, table: str) -> Tuple[List[str], List[List[Optional[Any]]]]:
    """Return (column_names, rows) for all INSERT INTO `table` statements."""
    header = re.compile(
        rf"INSERT INTO `{re.escape(table)}`\s*\(([^)]+)\)\s*VALUES\s*",
        re.S | re.I,
    )
    columns: Optional[List[str]] = None
    rows: List[List[Optional[Any]]] = []
    for m in header.finditer(sql):
        cols = [c.strip().strip("`") for c in m.group(1).split(",")]
        if columns is None:
            columns = cols
        elif columns != cols:
            raise ValueError(f"Column mismatch for {table}: {columns} vs {cols}")
        end = _scan_sql_statement_end(sql, m.end())
        values_blob = sql[m.end() : end - 1].strip()
        for tup in split_sql_values(values_blob):
            vals = parse_tuple(tup)
            if len(vals) != len(cols):
                raise ValueError(
                    f"{table}: expected {len(cols)} cols, got {len(vals)} in {tup[:80]}"
                )
            rows.append(vals)
    if columns is None:
        return [], []
    return columns, rows


def rows_as_dicts(columns: List[str], rows: List[List[Optional[Any]]]) -> List[Dict[str, Any]]:
    return [dict(zip(columns, row)) for row in rows]


def max_index(rows: List[Dict[str, Any]]) -> int:
    if not rows:
        return 1
    return max(int(r["Index"]) for r in rows) + 1


def emit_delete(prefix: str, logical: str) -> str:
    return f"DELETE FROM `{prefix}{logical}`;"


def emit_insert(prefix: str, logical: str, columns: List[str], rows: List[Dict[str, Any]]) -> str:
    if not rows:
        return f"-- (no rows for {prefix}{logical})\n"
    lines = [
        f"INSERT INTO `{prefix}{logical}` (`" + "`, `".join(columns) + "`) VALUES"
    ]
    value_lines = []
    for row in rows:
        vals = [sql_quote(row.get(c)) for c in columns]
        value_lines.append("(" + ", ".join(vals) + ")")
    lines.append(",\n".join(value_lines) + ";")
    return "\n".join(lines)


def emit_auto_increment(prefix: str, logical: str, next_id: int) -> str:
    return (
        f"ALTER TABLE `{prefix}{logical}` "
        f"MODIFY `Index` int NOT NULL AUTO_INCREMENT, AUTO_INCREMENT={next_id};"
    )


def normalize_name(name: str) -> str:
    s = decode_text(name).lower()
    s = re.sub(r"[^a-z0-9äöüß]+", "", s)
    return s


def build_instrument_map_from_dumps(
    prod_sql: str, dev_sql: str
) -> Tuple[Dict[int, int], List[str]]:
    """Prefer explicit map; annotate with name-based verification notes."""
    notes: List[str] = []
    cols, prod_rows = extract_inserts(prod_sql, "Instruments")
    prod = rows_as_dicts(cols, prod_rows)

    cols, dev_rows = extract_inserts(dev_sql, "meldeliste-dev_Instrument")
    if not cols:
        cols, dev_rows = extract_inserts(dev_sql, "meldeliste_Instrument")
    dev = rows_as_dicts(cols, dev_rows)

    by_norm: Dict[str, int] = {}
    for d in dev:
        by_norm[normalize_name(str(d["Name"]))] = int(d["Index"])

    aliases = {
        "querflote": "flote",
        "piccolo": "piccoloflote",
        "bassklarinette": "bassklarinette",
        "bassposaune": "bassposaune",
        "tenorhorn": "tenorhorn",
        "baritonhorn": "baritonhorn",
        "flugelhorn": "flugelhorn",
        "horn": "waldhorn",
        "partitur": "dirigent",
        "drumset": "schlagwerk",
        "pauke": "schlagwerk",
        "xylophone": "schlagwerk",
        "glockenspiel": "schlagwerk",
        "vibraphone": "schlagwerk",
        "percussion": "schlagwerk",
        "klavier": "schlagwerk",
        "keyboard": "schlagwerk",
        "orgel": "schlagwerk",
        "cello": "kontrabass",
    }

    mapping = dict(DEFAULT_INSTRUMENT_MAP)
    for p in prod:
        pid = int(p["Index"])
        pname = str(p["Name"])
        norm = normalize_name(pname)
        target = by_norm.get(norm)
        if target is None:
            alias = aliases.get(norm)
            if alias:
                target = by_norm.get(alias)
        # also try alias keys that strip hyphens already in normalize
        if target is None and pid in mapping:
            target = mapping[pid]
            notes.append(
                f"-- Instrument {pid} {pname!r} → Melde {target} (default map)"
            )
        elif target is not None:
            if mapping.get(pid) != target:
                notes.append(
                    f"-- Instrument {pid} {pname!r}: name-match {target}, "
                    f"default was {mapping.get(pid)}"
                )
            mapping[pid] = target
        else:
            notes.append(f"-- WARN: no Melde instrument for prod {pid} {pname!r}")
            mapping.setdefault(pid, pid)

    return mapping, notes


def convert(
    prod_path: Path,
    out_path: Path,
    prefix: str,
    dev_path: Optional[Path] = None,
) -> None:
    prod_sql = prod_path.read_text(encoding="utf-8", errors="replace")

    if dev_path is not None:
        dev_sql = dev_path.read_text(encoding="utf-8", errors="replace")
        instrument_map, instrument_notes = build_instrument_map_from_dumps(prod_sql, dev_sql)
    else:
        instrument_map = dict(DEFAULT_INSTRUMENT_MAP)
        instrument_notes = [
            "-- Instrument map: Melde InstrumentDefaults (no --dev dump provided)",
        ]
        cols, prod_rows = extract_inserts(prod_sql, "Instruments")
        for p in rows_as_dicts(cols, prod_rows):
            pid = int(p["Index"])
            pname = str(p["Name"])
            if pid not in instrument_map:
                instrument_notes.append(
                    f"-- WARN: prod Instrument {pid} {pname!r} not in default map"
                )
            else:
                instrument_notes.append(
                    f"-- Instrument {pid} {decode_text(pname)!r} → Melde {instrument_map[pid]}"
                )

    # --- Composers → Composer ---
    cols, rows = extract_inserts(prod_sql, "Composers")
    composers = rows_as_dicts(cols, rows)

    # --- Publishers → Publisher ---
    cols, rows = extract_inserts(prod_sql, "Publishers")
    publishers = rows_as_dicts(cols, rows)

    # --- Collections → Collection ---
    cols, rows = extract_inserts(prod_sql, "Collections")
    collections = rows_as_dicts(cols, rows)

    # --- Collection → CollectionItem (reorder columns) ---
    cols, rows = extract_inserts(prod_sql, "Collection")
    collection_items = []
    for r in rows_as_dicts(cols, rows):
        collection_items.append(
            {
                "Index": r["Index"],
                "Collections": r["Collections"],
                "CollectionNumber": r.get("CollectionNumber"),
                "Composition": r["Composition"],
            }
        )

    # --- Compositions → Composition (Grade/PerformanceTime order) ---
    cols, rows = extract_inserts(prod_sql, "Compositions")
    compositions = []
    for r in rows_as_dicts(cols, rows):
        compositions.append(
            {
                "Index": r["Index"],
                "RegistrationNumber": r.get("RegistrationNumber"),
                "Title": r["Title"],
                "Composer": r.get("Composer"),
                "Arranger": r.get("Arranger"),
                "Publisher": r.get("Publisher"),
                "Year": r.get("Year"),
                "PerformanceTime": r.get("PerformanceTime"),
                "Grade": r.get("Grade"),
                "FilePath": r.get("FilePath"),
            }
        )

    # --- Parts → ScoreFile ---
    cols, rows = extract_inserts(prod_sql, "Parts")
    score_files = []
    unmapped_instruments = set()
    for r in rows_as_dicts(cols, rows):
        prod_inst = int(r["Instrument"])
        if prod_inst not in instrument_map:
            unmapped_instruments.add(prod_inst)
        score_files.append(
            {
                "Index": r["Index"],
                "Composition": r["Composition"],
                "Instrument": instrument_map.get(prod_inst, prod_inst),
                "VoiceLabel": str(r["Part"]),
                "NextcloudPath": None,
                "PageCount": None,
                "Checksum": None,
                "FilePath": r.get("FilePath") if r.get("FilePath") not in ("", None) else None,
            }
        )

    out: List[str] = []
    out.append(f"-- Converted Notenarchiv prod catalog → {prefix}* schema")
    out.append("-- Generated by scripts/convertProdDumpForDev.py")
    out.append("--")
    out.append(f"-- Imports ONLY catalog data into {prefix}* tables.")
    out.append("-- Does NOT touch meldeliste_* (User, Termine, Instrument, …).")
    out.append("-- Skips Config, Users, Log, Instruments, Registers, meldeliste_User.")
    out.append("--")
    out.append("-- phpMyAdmin / mysql: select the SHARED Melde+Archiv database, then import.")
    out.append(f"-- Existing {prefix}* catalog rows are deleted before insert (archiv only).")
    out.append("")
    out.append("SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";")
    out.append("SET AUTOCOMMIT = 0;")
    out.append("START TRANSACTION;")
    out.append("SET time_zone = \"+00:00\";")
    out.append("SET NAMES utf8mb4;")
    out.append("SET FOREIGN_KEY_CHECKS = 0;")
    out.append("")

    if instrument_notes:
        out.append("-- Instrument ID mapping notes:")
        out.extend(instrument_notes)
        out.append("")

    if unmapped_instruments:
        out.append(
            "-- WARN unmapped prod Instrument IDs in Parts: "
            + ", ".join(str(i) for i in sorted(unmapped_instruments))
        )
        out.append("")

    # Child tables first for delete (no FKs, but safer mentally)
    for logical in (
        "ScoreFile",
        "CollectionItem",
        "RehearsalPiece",
        "Composition",
        "Collection",
        "Composer",
        "Publisher",
    ):
        out.append(emit_delete(prefix, logical))
    out.append("")

    out.append("-- Composer")
    out.append(
        emit_insert(prefix, "Composer", ["Index", "FirstName", "LastName"], composers)
    )
    out.append("")

    out.append("-- Publisher")
    out.append(
        emit_insert(prefix, "Publisher", ["Index", "Name", "Address"], publishers)
    )
    out.append("")

    out.append("-- Collection")
    out.append(emit_insert(prefix, "Collection", ["Index", "Name"], collections))
    out.append("")

    out.append("-- Composition")
    out.append(
        emit_insert(
            prefix,
            "Composition",
            [
                "Index",
                "RegistrationNumber",
                "Title",
                "Composer",
                "Arranger",
                "Publisher",
                "Year",
                "PerformanceTime",
                "Grade",
                "FilePath",
            ],
            compositions,
        )
    )
    out.append("")

    out.append("-- CollectionItem")
    out.append(
        emit_insert(
            prefix,
            "CollectionItem",
            ["Index", "Collections", "CollectionNumber", "Composition"],
            collection_items,
        )
    )
    out.append("")

    out.append("-- ScoreFile (from Parts; Instrument IDs remapped to Melde)")
    out.append(
        emit_insert(
            prefix,
            "ScoreFile",
            [
                "Index",
                "Composition",
                "Instrument",
                "VoiceLabel",
                "NextcloudPath",
                "PageCount",
                "Checksum",
                "FilePath",
            ],
            score_files,
        )
    )
    out.append("")

    out.append("-- AUTO_INCREMENT")
    out.append(emit_auto_increment(prefix, "Composer", max_index(composers)))
    out.append(emit_auto_increment(prefix, "Publisher", max_index(publishers)))
    out.append(emit_auto_increment(prefix, "Collection", max_index(collections)))
    out.append(emit_auto_increment(prefix, "Composition", max_index(compositions)))
    out.append(emit_auto_increment(prefix, "CollectionItem", max_index(collection_items)))
    out.append(emit_auto_increment(prefix, "ScoreFile", max_index(score_files)))
    out.append("")

    out.append("SET FOREIGN_KEY_CHECKS = 1;")
    out.append("COMMIT;")
    out.append("")

    out_path.write_text("\n".join(out), encoding="utf-8")

    summary = (
        f"Wrote {out_path}\n"
        f"  Composer:       {len(composers)}\n"
        f"  Publisher:      {len(publishers)}\n"
        f"  Collection:     {len(collections)}\n"
        f"  Composition:    {len(compositions)}\n"
        f"  CollectionItem: {len(collection_items)}\n"
        f"  ScoreFile:      {len(score_files)}\n"
    )
    sys.stderr.write(summary)


def main() -> int:
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--prod", required=True, type=Path, help="Prod SQL dump")
    ap.add_argument(
        "--dev",
        type=Path,
        default=None,
        help="Optional Melde/dev SQL dump for Instrument ID name-matching "
        "(otherwise uses Melde InstrumentDefaults map)",
    )
    ap.add_argument("--out", required=True, type=Path, help="Output SQL path")
    ap.add_argument(
        "--prefix",
        default="archiv_",
        help="Target table prefix (default: archiv_)",
    )
    args = ap.parse_args()
    convert(args.prod, args.out, args.prefix, args.dev)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
