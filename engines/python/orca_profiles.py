#!/usr/bin/env python3
"""
OrcaSlicer vendor profiles -> printer catalogue and ready-to-load setting bundles.

  orca_profiles.py catalog <profiles-dir>
      -> {"ok": true, "printers": [{id, vendor, vendor_label, model, machine, bed: {x,y,z},
                                    processes: {draft, standard, fine}, filaments: {PLA: name, ...}}]}
  orca_profiles.py bundle <profiles-dir> <vendor> <machine> <process> <filament> <out-dir> <overrides-json>
      -> writes machine.json, process.json, filament.json (inheritance resolved, compatible with each other)

Only 0.4 mm nozzle presets are offered: that is what nearly every home printer ships with.
Prints one JSON object.
"""
import glob
import json
import os
import re
import sys

VENDOR_LABELS = {"BBL": "Bambu Lab", "Anker": "AnkerMake", "Custom": "Generic / Klipper / Marlin"}
LAYER_TARGET = {"draft": 0.28, "standard": 0.20, "fine": 0.12}
MATERIALS = {"PLA": ["PLA"], "PETG": ["PETG", "PET"], "ASA": ["ASA", "ABS"], "TPU": ["TPU"], "PA": ["PA", "NYLON"]}
SPECIAL = re.compile(r"(CF|GF|SILK|MATTE|WOOD|GLOW|AERO|SUPPORT|MARBLE|METAL|SPARKLE|GALAXY|HF\b|HIGH FLOW|TOUGH|LW|DYNAMIC|TRANSLUCENT|RAPID|HS\b|HIGH SPEED|\+)", re.I)
ODD_PROCESS = re.compile(r"(SOLUBLE|INPUT ?SHAPER|\bIS\b|EXTRA|ULTRA|SILK|VASE|STRENGTH ONLY)", re.I)


def out(obj):
    print(json.dumps(obj, ensure_ascii=False))
    sys.exit(0)


def fail(msg):
    out({"ok": False, "error": str(msg)})


def slug(s):
    return re.sub(r"[^a-z0-9]+", "-", s.lower()).strip("-")


class Vendor:
    def __init__(self, root, name):
        self.name = name
        self.idx = {}
        for f in glob.glob(os.path.join(root, name, "**", "*.json"), recursive=True):
            try:
                d = json.load(open(f, encoding="utf-8"))
            except Exception:  # noqa: BLE001
                continue
            if isinstance(d, dict) and "name" in d:
                self.idx[d["name"]] = d

    def get(self, d, key):
        seen = 0
        while d is not None and seen < 20:
            if key in d:
                return d[key]
            d = self.idx.get(d.get("inherits"))
            seen += 1
        return None

    def flatten(self, name):
        d = self.idx[name]
        base = self.flatten(d["inherits"]) if d.get("inherits") in self.idx else {}
        base.update({k: v for k, v in d.items() if k != "inherits"})
        return base

    def of_type(self, t):
        return [d for d in self.idx.values() if d.get("type") == t and str(d.get("instantiation", "true")).lower() == "true"]


def first(v):
    return v[0] if isinstance(v, list) and v else v


def short_model(vendor, model):
    m = re.sub(r"^" + re.escape(VENDOR_LABELS.get(vendor, vendor)) + r"\s+", "", model, flags=re.I)
    return re.sub(r"^" + re.escape(vendor) + r"\s+", "", m, flags=re.I).strip()


def at_part(name):
    if "@" not in name:
        return ""
    part = name.split("@", 1)[1]
    part = re.sub(r"\b0\.\d+\b", " ", part)                       # nozzle sizes
    part = re.sub(r"\bnozzle\b", " ", part, flags=re.I)             # "HF" stays: a high-flow nozzle is different hardware
    return re.sub(r"\s+", " ", part).strip().lower()


def compatible(v, machine, model_short, candidates):
    name = machine["name"]
    hits = [d for d in candidates if name in (v.get(d, "compatible_printers") or [])]
    if hits:
        return hits
    # vendors that use compatible_printers_condition (Prusa): match the "@<model>" suffix of the preset name
    want = model_short.lower()
    loose = []
    for d in candidates:
        if at_part(d["name"]) == want:
            nz = re.findall(r"\b0\.\d+\b", d["name"].split("@", 1)[1])
            if not nz or "0.4" in nz:
                loose.append(d)
    return loose


def layer_of(v, d):
    try:
        return float(first(v.get(d, "layer_height")))
    except Exception:  # noqa: BLE001
        return None


def pick_process(v, procs, target):
    best, score = None, 1e9
    for d in procs:
        lh = layer_of(v, d)
        if lh is None:
            continue
        s = abs(lh - target) + (0.05 if ODD_PROCESS.search(d["name"]) else 0) + (0.001 * len(d["name"]))
        if s < score:
            best, score = d, s
    return best


def pick_filament(fils, words):
    best, score = None, 1e9
    for d in fils:
        n = d["name"].upper()
        base = n.split("@", 1)[0]
        if not any(re.search(r"(^|[^A-Z])" + w + r"([^A-Z]|$)", base) for w in words):
            continue
        if SPECIAL.search(base):
            continue                                   # filled / abrasive / effect filaments need other hardware or settings
        s = (0 if "GENERIC" in n else 1) + 0.001 * len(n)
        if s < score:
            best, score = d, s
    return best


def catalog(root):
    printers = []
    for vendor in sorted(os.listdir(root)):
        if not os.path.isdir(os.path.join(root, vendor)):
            continue
        v = Vendor(root, vendor)
        procs_all, fils_all = v.of_type("process"), v.of_type("filament")
        for m in v.of_type("machine"):
            try:
                nozzle = float(first(v.get(m, "nozzle_diameter")))
            except Exception:  # noqa: BLE001
                continue
            if abs(nozzle - 0.4) > 0.001:
                continue
            model = v.get(m, "printer_model") or m["name"]
            ms = short_model(vendor, model)
            procs = compatible(v, m, ms, procs_all)
            fils = compatible(v, m, ms, fils_all)
            chosen = {q: pick_process(v, procs, t) for q, t in LAYER_TARGET.items()}
            mats = {code: pick_filament(fils, words) for code, words in MATERIALS.items()}
            mats = {c: d["name"] for c, d in mats.items() if d}
            if not chosen["standard"] or "PLA" not in mats:
                continue
            area = v.get(m, "printable_area") or []
            xs, ys = [], []
            for pt in area:
                try:
                    px, py = str(pt).split("x")[:2]
                    xs.append(float(px))
                    ys.append(float(py))
                except Exception:  # noqa: BLE001
                    continue
            xs, ys = xs or [0], ys or [0]
            try:
                z = float(first(v.get(m, "printable_height")) or 0)
            except Exception:  # noqa: BLE001
                z = 0
            printers.append({
                "id": slug(vendor + " " + ms),
                "vendor": vendor, "vendor_label": VENDOR_LABELS.get(vendor, vendor), "model": ms or model, "machine": m["name"],
                "bed": {"x": round(max(xs) - min(xs)), "y": round(max(ys) - min(ys)), "z": round(z)},
                "processes": {q: d["name"] for q, d in chosen.items() if d},
                "filaments": mats,
            })
    # one entry per id (some vendors ship duplicates of a preset)
    uniq = {}
    for p in printers:
        uniq.setdefault(p["id"], p)
    out({"ok": True, "printers": sorted(uniq.values(), key=lambda p: (p["vendor_label"].lower(), p["model"].lower()))})


def bundle(root, vendor, machine, process, filament, out_dir, overrides):
    v = Vendor(root, vendor)
    for n in (machine, process, filament):
        if n not in v.idx:
            fail("unknown preset: " + n)
    os.makedirs(out_dir, exist_ok=True)
    m, p, f = v.flatten(machine), v.flatten(process), v.flatten(filament)
    m["printer_settings_id"] = machine
    p["print_settings_id"] = process
    f["filament_settings_id"] = [filament]
    for d in (p, f):
        d["compatible_printers"] = [machine]          # the CLI refuses presets it considers incompatible (-17)
        d["compatible_printers_condition"] = ""
    for d in (m, p, f):
        d["from"] = "system"
    p.update({k: str(val) for k, val in (overrides or {}).items()})
    for name, d in (("machine", m), ("process", p), ("filament", f)):
        json.dump(d, open(os.path.join(out_dir, name + ".json"), "w", encoding="utf-8"))
    out({"ok": True, "dir": out_dir, "layer_height": p.get("layer_height")})


def main(argv):
    try:
        if len(argv) >= 3 and argv[1] == "catalog":
            catalog(argv[2])
        if len(argv) >= 9 and argv[1] == "bundle":
            bundle(argv[2], argv[3], argv[4], argv[5], argv[6], argv[7], json.loads(argv[8] or "{}"))
        fail("usage")
    except SystemExit:
        raise
    except Exception as e:  # noqa: BLE001
        fail(e)


if __name__ == "__main__":
    main(sys.argv)
