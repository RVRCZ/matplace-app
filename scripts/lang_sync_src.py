"""Write the texts of one lang/src file into the older lang/src files that hold the same keys.

A key changed in a new source file would come back with its old text the day somebody applies the older file again
(scripts/lang_add.py takes whole files). Usage: python scripts/lang_sync_src.py lang/src/<new>.json

Only the changed strings are rewritten, where they stand, so an older file keeps its own layout. Every file written
is read back as JSON and compared key by key. Prints what it changed.
"""
import glob
import json
import os
import sys

new_path = sys.argv[1]
new = json.load(open(new_path, encoding='utf-8'))
enc = lambda v: json.dumps(v, ensure_ascii=False)
for path in sorted(glob.glob('lang/src/*.json')):
    if os.path.abspath(path) == os.path.abspath(new_path):
        continue
    raw = open(path, encoding='utf-8').read()
    old = json.loads(raw)
    if not isinstance(old, dict):
        continue
    changed = [k for k, v in new.items() if k in old and old[k] != v]
    if not changed:
        continue
    for k in changed:
        at = raw.index(enc(k) + ':')
        for was, now in zip(old[k], new[k]):
            if was == now:
                continue
            i = raw.index(enc(was), at)
            raw = raw[:i] + enc(now) + raw[i + len(enc(was)):]
    back = json.loads(raw)
    assert all(back[k] == new[k] for k in changed) and set(back) == set(old), path
    assert all(back[k] == old[k] for k in old if k not in changed), path
    open(path, 'w', encoding='utf-8', newline='\n').write(raw)
    print(path, len(changed), 'keys:', ', '.join(changed))
