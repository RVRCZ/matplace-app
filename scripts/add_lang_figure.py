# Adds Tools + figure/bust keys to cs/en/es. Run: python scripts/add_lang_figure.py
import json
import os

ROOT = os.path.join(os.path.dirname(__file__), "..")

K = {
    "tools.title": ("Nástroje", "Tools", "Herramientas"),
    "tools.lead": ("Všechno zdarma a bez registrace. Výsledek vždycky skončí u ceny a tiskaře.", "All free, no sign-up. Every result ends with a price and a printer.", "Todo gratis y sin registro. Cada resultado termina con un precio y un impresor."),
    "tools.figure.title": ("Busta nebo figurka z fotky", "Bust or figure from a photo", "Busto o figura a partir de una foto"),
    "tools.figure.hint": ("Nahraj fotku, za minutu máš 3D model a cenu výtisku.", "Upload a photo; a minute later you have a 3D model and the print price.", "Sube una foto; en un minuto tienes el modelo 3D y el precio."),
    "tools.calc.title": ("Cena ze souboru", "Price from a file", "Precio desde un archivo"),
    "tools.calc.hint": ("STL, 3MF, OBJ nebo STEP → otočný model, cena, doba výroby.", "STL, 3MF, OBJ or STEP → rotatable model, price, production time.", "STL, 3MF, OBJ o STEP → modelo giratorio, precio y plazo."),
    "tools.sign.title": ("Cedulka, jmenovka, klíčenka", "Sign, name tag, keychain", "Cartel, etiqueta, llavero"),
    "tools.sign.hint": ("Napiš text, vyber tvar a písmo.", "Type the text, pick a shape and font.", "Escribe el texto, elige forma y letra."),
    "tools.relief.title": ("Reliéf a litofanie z fotky", "Relief and lithophane from a photo", "Relieve y litofanía de una foto"),
    "tools.relief.hint": ("Obrázek převedeme na plastický reliéf.", "We turn a picture into a raised relief.", "Convertimos una imagen en relieve."),
    "figure.lead": ("Portrét, mazlíček nebo oblíbená hračka jako 3D výtisk. Tvar je umělecký dojem z jedné fotky, ne přesná kopie.", "A portrait, a pet or a favourite toy as a 3D print. The shape is an artistic impression from one photo, not an exact copy.", "Un retrato, una mascota o un juguete como impresión 3D. La forma es una impresión artística de una sola foto, no una copia exacta."),
    "figure.unavailable": ("Generování modelů je teď vypnuté. Zkus to později.", "Model generation is switched off right now. Try later.", "La generación de modelos está desactivada ahora. Prueba más tarde."),
    "figure.kind.bust": ("Busta", "Bust", "Busto"),
    "figure.kind.bust.hint": ("hlava a ramena, fotka zepředu", "head and shoulders, front photo", "cabeza y hombros, foto de frente"),
    "figure.kind.figure": ("Figurka", "Figure", "Figura"),
    "figure.kind.figure.hint": ("celá postava nebo předmět", "whole body or object", "cuerpo entero u objeto"),
    "figure.pick_photo": ("Vyber nebo vyfoť fotku", "Choose or take a photo", "Elige o haz una foto"),
    "figure.photo_tips": ("Jeden objekt, celý v záběru, světlé pozadí, ostrá fotka.", "One subject, fully in frame, plain background, sharp photo.", "Un solo sujeto, entero en el encuadre, fondo claro, foto nítida."),
    "figure.size": ("Výška výtisku", "Print height", "Altura de la pieza"),
    "figure.consent": ("Na fotce jsem já, nebo mám souhlas vyfoceného. Souhlasím, že fotku zpracuje náš dodavatel generování modelů.", "The photo shows me, or I have the person’s consent. I agree that our model-generation provider processes the photo.", "En la foto salgo yo o tengo el consentimiento de la persona. Acepto que nuestro proveedor de generación procese la foto."),
    "figure.privacy": ("Fotku smažeme hned po vytvoření modelu. Model vidí jen ten, kdo má odkaz; nepřihlášeným ho po 30 dnech mažeme.", "We delete the photo as soon as the model is created. Only people with the link see the model; for guests it is deleted after 30 days.", "Borramos la foto en cuanto se crea el modelo. Solo quien tenga el enlace ve el modelo; a los invitados se les borra a los 30 días."),
    "figure.submit": ("Vytvořit model", "Create model", "Crear modelo"),
    "figure.limits": ("Zdarma: :n model denně bez účtu, :m s účtem.", "Free: :n model a day without an account, :m with one.", "Gratis: :n modelo al día sin cuenta, :m con cuenta."),
    "figure.generating": ("Vytvářím model, trvá to asi minutu…", "Creating the model, about a minute…", "Creando el modelo, tarda un minuto…"),
    "figure.done": ("Hotovo, otevírám kalkulaci…", "Done, opening the calculation…", "Listo, abriendo el cálculo…"),
    "figure.failed": ("Nepovedlo se. Zkus jinou fotku: jeden objekt, světlé pozadí.", "That did not work. Try another photo: one subject, plain background.", "No ha funcionado. Prueba otra foto: un sujeto, fondo claro."),
    "figure.rejected": ("Tuhle fotku použít nemůžeme. Zkus jinou.", "We cannot use this photo. Try another one.", "No podemos usar esta foto. Prueba otra."),
    "figure.limit": ("Dnešní limit (:n) je vyčerpaný. S účtem máš :m modely denně.", "Today’s limit (:n) is used up. With an account you get :m models a day.", "El límite de hoy (:n) está agotado. Con cuenta tienes :m modelos al día."),
    "figure.global_limit": ("Dnes jsme už vygenerovali maximum modelů. Zkus to zítra.", "We have generated the maximum number of models today. Try tomorrow.", "Hoy ya hemos generado el máximo de modelos. Prueba mañana."),
    "figure.need_photo": ("Nejdřív vyber fotku.", "Choose a photo first.", "Primero elige una foto."),
    "figure.need_consent": ("Zaškrtni prosím souhlas.", "Please tick the consent box.", "Marca la casilla de consentimiento."),
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
