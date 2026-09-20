"""Merge translations into lang/{cs,en,es}.json.  Usage: python scripts/lang_add.py <file.json>   ({"key": ["cs", "en", "es"], …})"""
import json
import sys

data = json.load(open(sys.argv[1], encoding='utf-8'))
for i, lang in enumerate(['cs', 'en', 'es']):
    path = f'lang/{lang}.json'
    d = json.load(open(path, encoding='utf-8'))
    for key, values in data.items():
        assert isinstance(values, list) and len(values) == 3, key
        d[key] = values[i]
    json.dump(d, open(path, 'w', encoding='utf-8', newline='\n'), ensure_ascii=False, indent=4)
print(len(data), 'keys merged')
