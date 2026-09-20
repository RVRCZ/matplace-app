# Adds generation UI keys to cs/en/es. Run: python scripts/add_lang_generate.py
import json
import os

ROOT = os.path.join(os.path.dirname(__file__), "..")

K = {
    "search.gen_size": ("Největší rozměr", "Largest side", "Lado más largo"),
    "search.generating": ("Vytvářím hrubý model, trvá to asi minutu…", "Creating a rough model, about a minute…", "Creando un modelo aproximado, tarda un minuto…"),
    "search.gen_done": ("Model je hotový, počítám cenu…", "The model is ready, calculating the price…", "El modelo está listo, calculando el precio…"),
    "search.gen_failed": ("Model se nepodařilo vytvořit. Zkus jinou fotku (jeden předmět, světlé pozadí) nebo popis.", "Could not create the model. Try another photo (one object, plain background) or a description.", "No se pudo crear el modelo. Prueba otra foto (un objeto, fondo claro) o una descripción."),
    "search.gen_daily_limit": ("Dnešní limit (:n) je vyčerpaný. Po přihlášení máš :m modely denně.", "Today’s limit (:n) is used up. Signed-in users get :m models a day.", "El límite de hoy (:n) está agotado. Con cuenta tienes :m modelos al día."),
    "search.gen_global_limit": ("Dnes už jsme vygenerovali maximum modelů. Zkus to prosím zítra.", "We have generated the maximum number of models today. Please try tomorrow.", "Hoy ya hemos generado el máximo de modelos. Prueba mañana."),
    "search.gen_text_hint": ("Nenašel jsi, co hledáš? Necháme z popisu vytvořit hrubý model. Tvar je přibližný, rozměr nastav podle skutečnosti.", "Not what you were looking for? We can create a rough model from the description. The shape is approximate; set the real size.", "¿No es lo que buscabas? Creamos un modelo aproximado a partir de la descripción. La forma es aproximada; indica el tamaño real."),
}

for i, lang in enumerate(("cs", "en", "es")):
    p = os.path.join(ROOT, "lang", f"{lang}.json")
    with open(p, encoding="utf-8") as f:
        d = json.load(f)
    for k, v in K.items():
        d[k] = v[i]
    with open(p, "w", encoding="utf-8") as f:
        json.dump(d, f, ensure_ascii=False, indent=4)
        f.write("\n")
    print(lang, len(d))
