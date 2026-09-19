# Adds step-3 (search / photo) translation keys to cs/en/es. Run: python scripts/add_lang_search.py
import json
import os

ROOT = os.path.join(os.path.dirname(__file__), "..")

keys = {
    "search.placeholder": {"cs": "Co potřebuješ? Např. „kryt na dálkové ovládání Samsung“", "en": "What do you need? e.g. “Samsung remote battery cover”", "es": "¿Qué necesitas? P. ej. «tapa de mando Samsung»"},
    "search.button": {"cs": "Najít", "en": "Search", "es": "Buscar"},
    "search.searching": {"cs": "Hledám hotové modely…", "en": "Searching ready-made models…", "es": "Buscando modelos listos…"},
    "search.identifying": {"cs": "Poznávám, co je na fotce…", "en": "Identifying the object…", "es": "Identificando el objeto…"},
    "search.none": {"cs": "Nic hotového jsme nenašli. Nahraj vlastní soubor, nebo počkej na generování a designéry.", "en": "Nothing ready-made found. Upload your own file, or wait for generation and designers.", "es": "No hemos encontrado nada hecho. Sube tu archivo o espera a la generación y a los diseñadores."},
    "search.error": {"cs": "Hledání se nepovedlo, zkus to znovu.", "en": "Search failed, please try again.", "es": "La búsqueda ha fallado, inténtalo de nuevo."},
    "search.not_image": {"cs": "Tohle není fotka. Nahraj JPG nebo PNG.", "en": "That is not a photo. Upload a JPG or PNG.", "es": "Eso no es una foto. Sube un JPG o PNG."},
    "search.daily_limit": {"cs": "Dnes už jsi vyčerpal :n rozpoznání z fotky. Zkus to zítra nebo popiš díl slovy.", "en": "You have used today’s :n photo identifications. Try tomorrow or describe the part in words.", "es": "Has agotado las :n identificaciones de hoy. Prueba mañana o describe la pieza con palabras."},
    "search.open_source": {"cs": "Otevřít na :s", "en": "Open on :s", "es": "Abrir en :s"},
    "search.results_title": {"cs": "Hotové modely pro:", "en": "Ready-made models for:", "es": "Modelos listos para:"},
    "search.results_hint": {"cs": "Soubor stáhni u zdroje a nahoře ho nahraj, hned uvidíš cenu. Licenci si zkontroluj u autora.", "en": "Download the file at the source and upload it above to see the price. Check the licence with the author.", "es": "Descarga el archivo en la fuente y súbelo arriba para ver el precio. Comprueba la licencia con el autor."},
    "search.size_guess": {"cs": "odhad", "en": "guess", "es": "estimación"},
    "search.price_range": {"cs": "Cena přibližně", "en": "Price roughly", "es": "Precio aproximado"},
    "search.range_hint": {"cs": "Rozpětí podle odhadnuté velikosti. Přesnou cenu ukážeme, jakmile bude model.", "en": "Range based on the estimated size. The exact price appears once there is a model.", "es": "Rango según el tamaño estimado. El precio exacto aparece cuando haya un modelo."},
    "search.have_file": {"cs": "Mám soubor, nahrát", "en": "I have a file, upload", "es": "Tengo un archivo, subir"},
    "search.generate": {"cs": "Vygenerovat hrubý model", "en": "Generate a rough model", "es": "Generar un modelo aproximado"},
    "search.designer_soon": {"cs": "Přesný model od designéra do 24 h – připravujeme", "en": "Exact model by a designer within 24 h – coming soon", "es": "Modelo exacto por un diseñador en 24 h – muy pronto"},
}

for lang in ("cs", "en", "es"):
    p = os.path.join(ROOT, "lang", f"{lang}.json")
    with open(p, encoding="utf-8") as f:
        d = json.load(f)
    for k, v in keys.items():
        d[k] = v[lang]
    with open(p, "w", encoding="utf-8") as f:
        json.dump(d, f, ensure_ascii=False, indent=4)
        f.write("\n")
    print(lang, len(d))
