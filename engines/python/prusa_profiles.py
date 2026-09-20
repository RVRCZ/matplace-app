#!/usr/bin/env python3
"""
PrusaSlicer vendor bundle (PrusaResearch.ini) -> printer catalogue and 3MF projects PrusaSlicer opens with settings.

  prusa_profiles.py catalog <bundle.ini>
      -> {"ok": true, "printers": [{id, vendor, vendor_label, model, machine, bed, processes{draft,standard,fine}, filaments{PLA:..}}]}
  prusa_profiles.py project <bundle.ini> <printer> <print> <filament> <in.stl> <out.3mf> <overrides-json>
      -> 3MF with the mesh centred on the bed and Metadata/Slic3r_PE.config (full flattened configuration)

A preset in the bundle holds only what differs from PrusaSlicer's built-in defaults, so "flattened presets + overrides"
is exactly the configuration PrusaSlicer itself would build. Only single-tool 0.4 mm printers are offered.
"""
import json
import re
import sys
import zipfile

LAYER_TARGET = {"draft": 0.30, "standard": 0.20, "fine": 0.10}
MATERIALS = {"PLA": ["PLA"], "PETG": ["PETG"], "ASA": ["ASA"], "TPU": ["FLEX", "TPU"]}   # nylon presets are carbon filled: hardened nozzle only
STYLE_RANK = ["STRUCTURAL", "QUALITY", "BALANCED", "SPEED", "DETAIL", "DRAFT"]
SKIP_PRINTER = re.compile(r"(MMU|MM\b|Multi|\dT\b|INDX|SL1|SL1S|M1\b|IS Input|Input Shaper)", re.I)
SKIP_PRINT = re.compile(r"(SOLUBLE|VASE|MMU)", re.I)
DROP_KEYS = {"inherits", "compatible_printers", "compatible_printers_condition", "compatible_prints", "compatible_prints_condition", "renamed_from"}


def out(obj):
    print(json.dumps(obj, ensure_ascii=False))
    sys.exit(0)


def fail(msg):
    out({"ok": False, "error": str(msg)})


def slug(s):
    return re.sub(r"[^a-z0-9]+", "-", s.lower()).strip("-")


class Bundle:
    def __init__(self, path):
        self.sections = {}   # (kind, name) -> {key: value}
        cur = None
        for raw in open(path, encoding="utf-8", errors="replace"):
            line = raw.rstrip("\r\n")
            if not line.strip() or line.lstrip().startswith("#"):
                continue
            m = re.match(r"^\[([a-z_]+):(.*)\]$", line.strip())
            if m:
                cur = self.sections.setdefault((m.group(1), m.group(2)), {})
                continue
            if line.strip().startswith("["):
                cur = None
                continue
            if cur is not None and "=" in line:
                k, v = line.split("=", 1)
                cur[k.strip()] = v.strip()

    def flatten(self, kind, name, depth=0):
        d = self.sections.get((kind, name))
        if d is None or depth > 30:
            return {}
        merged = {}
        for parent in [p.strip() for p in d.get("inherits", "").split(";") if p.strip()]:
            merged.update(self.flatten(kind, parent, depth + 1))
        merged.update({k: v for k, v in d.items() if k != "inherits"})
        return merged

    def names(self, kind):
        return [n for (k, n) in self.sections if k == kind and not n.startswith("*")]


def value_of(cfg, key, index=None):
    raw = cfg.get(key, "")
    if index is not None:
        parts = re.split(r"[;,]", raw)
        raw = parts[index] if index < len(parts) else (parts[0] if parts else "")
    raw = raw.strip().strip('"')
    try:
        return float(raw)
    except ValueError:
        return raw


TOKEN = re.compile(r"""\s*(?:(=~|!~)\s*/((?:[^/\\]|\\.)*)/|(==|!=|>=|<=|>|<)|(\band\b|\bor\b|\bnot\b)|(!)|([()])|("(?:[^"\\]|\\.)*")|(\d+(?:\.\d+)?)|([A-Za-z_][A-Za-z_0-9]*)(?:\[(\d+)\])?)""")


def compatible(condition, printer_cfg):
    """PrusaSlicer's compatible_printers_condition, translated token by token into a Python expression."""
    if not condition.strip():
        return True
    py, pos, pending = [], 0, None
    while pos < len(condition):
        m = TOKEN.match(condition, pos)
        if not m or m.end() == pos:
            return False
        pos = m.end()
        rx_op, rx, cmp_op, word, bang, paren, string, number, ident, idx = m.groups()
        if rx_op:
            left = py.pop()
            py.append(("" if rx_op == "=~" else "not ") + "_m(%s, %r)" % (left, rx))
        elif cmp_op:
            py.append(cmp_op)
        elif word:
            py.append(word)
        elif bang:
            py.append("not")
        elif paren:
            py.append(paren)
        elif string:
            py.append(repr(string.strip('"')))
        elif number:
            py.append(number)
        elif ident:
            if ident in ("true", "false"):
                py.append("True" if ident == "true" else "False")
            else:
                py.append("_v(%r, %s)" % (ident, idx if idx is not None else "None"))
    try:
        return bool(eval(" ".join(py), {"__builtins__": {}}, {  # noqa: S307 - expression built from a closed token set
            "_m": lambda val, rx: re.search(rx, str(val)) is not None,
            "_v": lambda k, i: value_of(printer_cfg, k, i),
        }))
    except Exception:  # noqa: BLE001
        return False


def bed_of(cfg):
    pts = [p.split("x") for p in cfg.get("bed_shape", "").split(",") if "x" in p]
    xs, ys = [float(p[0]) for p in pts] or [0], [float(p[1]) for p in pts] or [0]
    try:
        z = float(cfg.get("max_print_height", 0))
    except ValueError:
        z = 0
    return {"x": round(max(xs) - min(xs)), "y": round(max(ys) - min(ys)), "z": round(z)}, (min(xs) + max(xs)) / 2, (min(ys) + max(ys)) / 2


def pick_print(b, names, target):
    best, score = None, 1e9
    for n in names:
        if SKIP_PRINT.search(n):
            continue
        try:
            lh = float(b.flatten("print", n).get("layer_height", "x"))
        except ValueError:
            continue
        rank = next((i for i, w in enumerate(STYLE_RANK) if w in n.upper()), len(STYLE_RANK))
        s = abs(lh - target) * 100 + rank * 0.1 + len(n) * 0.0001
        if s < score:
            best, score = n, s
    return best


def pick_filament(names, words):
    best, score = None, 1e9
    for n in names:
        base = n.split("@", 1)[0].upper()
        if not any(re.search(r"(^|[^A-Z])" + w + r"([^A-Z+-]|$)", base) for w in words):
            continue
        if re.search(r"(CF|GF|SILK|MATTE|WOOD|GLOW|RPLA|BLEND|PVB|TOUGH|\+)", base):
            continue
        s = (0 if base.startswith("GENERIC") else 1 if base.startswith("PRUSAMENT") else 2) + len(n) * 0.001
        if s < score:
            best, score = n, s
    return best


def catalog(path):
    b = Bundle(path)
    models = {n: b.sections[("printer_model", n)].get("name", n) for (k, n) in b.sections if k == "printer_model"}
    printers = []
    for pname in b.names("printer"):
        cfg = b.flatten("printer", pname)
        if SKIP_PRINTER.search(pname) or cfg.get("printer_technology", "FFF") != "FFF":
            continue
        if cfg.get("printer_variant", "") not in ("0.4", "HF0.4") or "," in cfg.get("nozzle_diameter", "0.4"):
            continue
        prints = [n for n in b.names("print") if compatible(b.flatten("print", n).get("compatible_printers_condition", ""), cfg)]
        fils = [n for n in b.names("filament") if compatible(b.flatten("filament", n).get("compatible_printers_condition", ""), cfg)]
        procs = {q: pick_print(b, prints, t) for q, t in LAYER_TARGET.items()}
        mats = {c: pick_filament(fils, w) for c, w in MATERIALS.items()}
        mats = {c: n for c, n in mats.items() if n}
        if not procs["standard"] or "PLA" not in mats:
            continue
        hf = cfg.get("printer_variant") == "HF0.4"
        model = re.sub(r"^(Original\s+)?Prusa\s+(i3\s+)?", "", pname)
        model = re.sub(r"\s*(HF)?0\.4 nozzle$", "", model).strip() or models.get(cfg.get("printer_model", ""), pname)
        bed, _, _ = bed_of(cfg)
        printers.append({
            "id": slug("prusaslicer " + model + (" hf" if hf else "")), "vendor": "PrusaResearch", "vendor_label": "Prusa",
            "model": model + (" HF" if hf else ""), "machine": pname, "bed": bed,
            "processes": {q: n for q, n in procs.items() if n}, "filaments": mats,
        })
    uniq = {}
    for p in printers:
        uniq.setdefault(p["id"], p)
    out({"ok": True, "printers": sorted(uniq.values(), key=lambda p: p["model"].lower())})


def project(path, printer, print_name, filament, stl, dst, overrides):
    import numpy as np
    import trimesh
    b = Bundle(path)
    for kind, n in (("printer", printer), ("print", print_name), ("filament", filament)):
        if (kind, n) not in b.sections:
            fail("unknown preset: " + n)
    pc, rc, fc = b.flatten("printer", printer), b.flatten("print", print_name), b.flatten("filament", filament)
    cfg = {}
    for part in (pc, fc, rc):
        cfg.update({k: v for k, v in part.items() if k not in DROP_KEYS})
    cfg.update({"printer_settings_id": printer, "print_settings_id": print_name, "filament_settings_id": '"%s"' % filament})
    cfg.update({k: str(v) for k, v in (overrides or {}).items()})

    m = trimesh.load(stl, force="mesh")
    _, cx, cy = bed_of(pc)
    lo, hi = m.bounds
    m.apply_translation([cx - (lo[0] + hi[0]) / 2.0, cy - (lo[1] + hi[1]) / 2.0, -lo[2]])
    m.export(dst, file_type="3mf")
    lines = ["; generated by PrusaSlicer 2.9.0 (matplace project)", ""] + ["; %s = %s" % (k, cfg[k]) for k in sorted(cfg)]
    with zipfile.ZipFile(dst, "a", zipfile.ZIP_DEFLATED) as z:
        z.writestr("Metadata/Slic3r_PE.config", "\n".join(lines) + "\n")
    out({"ok": True, "out": dst, "keys": len(cfg), "layer_height": cfg.get("layer_height"), "triangles": int(len(m.faces)), "np": np.__version__})


def main(argv):
    try:
        if len(argv) >= 3 and argv[1] == "catalog":
            catalog(argv[2])
        if len(argv) >= 9 and argv[1] == "project":
            project(argv[2], argv[3], argv[4], argv[5], argv[6], argv[7], json.loads(argv[8] or "{}"))
        fail("usage")
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        fail(e)


if __name__ == "__main__":
    main(sys.argv)
