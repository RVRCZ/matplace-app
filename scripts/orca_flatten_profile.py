"""
Flatten an OrcaSlicer vendor preset into one self-contained JSON the farm can load (engines/orca/profiles).

The vendor bundle (resources/profiles/<Vendor>/{machine,process,filament}/*.json) builds a preset from its `inherits`
chain, then layers the own keys of every `include`d preset in the listed order, then the preset's own keys
(PresetBundle.cpp, Orca 2.5). The farm's slicer loads a profile file alone (--load-settings / --load-filaments), so the
chain has to be resolved up front; the result keeps the preset's Orca `name` (the pairing of machine, process and
filament goes by it) and gets `inherits: ""` like every other profile in engines/orca/profiles.

Usage:
  python scripts/orca_flatten_profile.py <vendor dir> <type> "<preset name>" <out.json> [key=value ...]

  <vendor dir>   the vendor folder (holding machine/, process/, filament/), e.g. an unpacked OrcaSlicer's
                 resources/profiles/Prusa, or the same folder of the git tag (the portable 2.5 packs vendors into
                 .opc containers, which are not zips)
  <type>         machine | process | filament
  key=value      extra overrides written last (a value that parses as JSON is taken as JSON, else as a string)

Example (docs/AM.md §12):
  python scripts/orca_flatten_profile.py ../orca25prusa-src machine "Prusa CORE One INDX 8T HF 0.4 nozzle" engines/orca/profiles/machine_coreone_indx8t.json
"""
from __future__ import annotations

import glob
import json
import os
import sys

# keys that describe the preset itself, not the printer / print / filament; never taken over from parents or includes
META = {"type", "name", "inherits", "include", "from", "instantiation", "setting_id", "filament_id", "renamed_from", "alias", "version", "is_custom_defined"}


def load_index(vendor_dir: str, kind: str) -> dict:
    index = {}
    for path in glob.glob(os.path.join(vendor_dir, kind, "*.json")):
        try:
            with open(path, encoding="utf-8") as fh:
                data = json.load(fh)
        except (OSError, ValueError) as e:
            print(f"skip {path}: {e}", file=sys.stderr)
            continue
        if isinstance(data, dict) and data.get("name"):
            index[data["name"]] = data
    return index


def own_keys(preset: dict) -> dict:
    return {k: v for k, v in preset.items() if k not in META}


def resolve(index: dict, name: str, chain: list) -> dict:
    preset = index.get(name)
    if preset is None:
        raise SystemExit(f"preset not found: {name!r} (needed by {' -> '.join(chain) or 'the command line'})")
    if name in chain:
        raise SystemExit(f"inheritance loop: {' -> '.join(chain + [name])}")
    chain = chain + [name]
    config = resolve(index, preset["inherits"], chain) if preset.get("inherits") else {}
    includes = preset.get("include") or []
    for inc in [includes] if isinstance(includes, str) else includes:
        config.update(own_keys(resolve_included(index, inc, chain)))
    config.update(own_keys(preset))
    return config


def resolve_included(index: dict, name: str, chain: list) -> dict:
    """An include contributes what it states itself, with its own inherits chain resolved behind it."""
    preset = index.get(name)
    if preset is None:
        raise SystemExit(f"included preset not found: {name!r} (needed by {' -> '.join(chain)})")
    out = resolve(index, preset["inherits"], chain + [name]) if preset.get("inherits") else {}
    out.update(own_keys(preset))
    return out


def parse_override(arg: str) -> tuple:
    key, _, raw = arg.partition("=")
    if not key or not _:
        raise SystemExit(f"override must be key=value: {arg!r}")
    try:
        return key, json.loads(raw)
    except ValueError:
        return key, raw


def main(argv: list) -> None:
    if len(argv) < 5:
        raise SystemExit(__doc__)
    vendor_dir, kind, name, out_path = argv[1:5]
    if kind not in ("machine", "process", "filament"):
        raise SystemExit("type must be machine, process or filament")
    index = load_index(vendor_dir, kind)
    preset = index.get(name)
    if preset is None:
        raise SystemExit(f"preset not found: {name!r} in {os.path.join(vendor_dir, kind)}")
    flat = {
        "type": kind, "from": "system", "setting_id": preset.get("setting_id", ""), "is_custom_defined": "0",
        "instantiation": "true", "version": preset.get("version", ""), "inherits": "", "name": name,
    }
    if kind == "filament" and preset.get("filament_id"):
        flat["filament_id"] = preset["filament_id"]
    flat.update(resolve(index, name, []))
    for arg in argv[5:]:
        key, value = parse_override(arg)
        flat[key] = value
    with open(out_path, "w", encoding="utf-8", newline="\n") as fh:
        json.dump(flat, fh, ensure_ascii=False, indent=4)
        fh.write("\n")
    with open(out_path, encoding="utf-8") as fh:
        json.load(fh)        # the file written is valid JSON, or we hear it now
    print(f"{out_path}: {len(flat)} keys from {name!r}")


if __name__ == "__main__":
    main(sys.argv)
