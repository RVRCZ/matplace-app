# One-off helper: wires the relief tool into the front end and adds its texts to all three dictionaries.
import json


def patch(p, pairs):
    s = open(p, encoding='utf-8').read()
    for a, b in pairs:
        assert a in s, (p, a[:60])
        s = s.replace(a, b, 1)
    open(p, 'w', encoding='utf-8', newline='\n').write(s)


patch('resources/js/app.ts', [
    ("import { bootSign } from './calc/sign';", "import { bootSign } from './calc/sign';\nimport { bootRelief } from './calc/relief';"),
    ("bootSign(); }", "bootSign(); bootRelief(); }"),
])
patch('resources/js/calc/calculator.ts', [
    ("    state.localFile = file;\n    showResult();", "    state.localFile = file;\n    showKindTip(undefined);\n    showResult();"),
])
patch('resources/views/calculator/index.blade.php', [
    ("'search.gen_text_hint',\n", "'search.gen_text_hint',\n        'calc.tip.generated','calc.tip.lithophane','calc.tip.relief','calc.tip.sign',\n"),
    ("                <div class=\"absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-xs text-slate-600 shadow\" id=\"dims-badge\"></div>\n            </div>\n",
     "                <div class=\"absolute bottom-3 left-3 rounded-full bg-white/90 px-3 py-1 text-xs text-slate-600 shadow\" id=\"dims-badge\"></div>\n            </div>\n            <p id=\"kind-tip\" class=\"hidden rounded-xl bg-teal-50 px-4 py-2 text-sm text-teal-900 lg:col-span-2\"></p>\n"),
])

p = 'resources/views/tools/index.blade.php'
s = open(p, encoding='utf-8').read()
a = s.index("        @foreach (['relief'")
b = s.index("        @endforeach") + len("        @endforeach\n")
s = s[:a] + '''        <a href="{{ route('tools.relief') }}" class="rounded-2xl border border-slate-200 bg-white p-5 hover:border-teal-400">
            <div class="text-2xl">🖼️</div>
            <div class="mt-1 text-lg font-bold">{{ __('tools.relief.title') }}</div>
            <div class="text-sm text-slate-500">{{ __('tools.relief.hint') }}</div>
        </a>
''' + s[b:]
open(p, 'w', encoding='utf-8', newline='\n').write(s)

L = {
    'tools.relief.hint': ('Litofanie, která se rozsvítí proti světlu, nebo plastický obrázek na zeď.', 'A lithophane that lights up against a lamp, or a raised picture for the wall.', 'Una litofanía que se ilumina a contraluz o un cuadro en relieve para la pared.'),
    'relief.lead': ('Nahrajte fotku a hned uvidíte model i ceny. Fotku nikam neukládáme.', 'Upload a photo and see the model and prices right away. We do not keep the photo.', 'Suba una foto y verá el modelo y los precios al instante. No guardamos la foto.'),
    'relief.mode.lithophane': ('Litofanie', 'Lithophane', 'Litofanía'),
    'relief.mode.lithophane.hint': ('Obrázek se objeví, když za ni dáte světlo.', 'The picture appears when you put a light behind it.', 'La imagen aparece al poner una luz detrás.'),
    'relief.mode.relief': ('Reliéf', 'Relief', 'Relieve'),
    'relief.mode.relief.hint': ('Světlá místa vystoupí nahoru, dekorace na zeď.', 'Bright areas rise up, a wall decoration.', 'Las zonas claras sobresalen, decoración de pared.'),
    'relief.pick': ('Vyberte nebo vyfoťte obrázek', 'Choose or take a photo', 'Elija o tome una foto'),
    'relief.width': ('Delší strana', 'Longer side', 'Lado más largo'),
    'relief.frame': ('Rámeček', 'Frame', 'Marco'),
    'relief.invert': ('Obrátit světlá a tmavá místa', 'Swap bright and dark', 'Invertir claros y oscuros'),
    'relief.submit': ('Vytvořit model a ukázat ceny', 'Create the model and show prices', 'Crear el modelo y ver precios'),
    'relief.working': ('Vytvářím model, pár vteřin…', 'Creating the model, a few seconds…', 'Creando el modelo, unos segundos…'),
    'relief.failed': ('Nepovedlo se to. Zkuste jinou fotku.', 'That did not work. Try another photo.', 'No ha funcionado. Pruebe con otra foto.'),
    'relief.privacy': ('Fotka slouží jen k výpočtu modelu a hned ji zahodíme. Nejlépe vychází portréty a fotky s výrazným kontrastem.', 'The photo is only used to compute the model and is discarded right away. Portraits and high-contrast photos work best.', 'La foto solo se usa para calcular el modelo y se descarta enseguida. Los retratos y las fotos con buen contraste quedan mejor.'),
    'calc.tip.generated': ('Model vytvořila AI z vaší předlohy. Počítáme se stromovými podpěrami, které jdou po tisku snadno odlomit.', 'This model was made by AI from your input. We count with tree supports, which snap off easily after printing.', 'Este modelo lo creó la IA a partir de su referencia. Contamos con soportes de árbol, que se quitan fácilmente tras imprimir.'),
    'calc.tip.lithophane': ('Litofanie se tiskne nastojato, plná a z bílého materiálu. Náhled ukazuje, jak bude vypadat proti světlu.', 'A lithophane is printed standing, solid and in white material. The preview shows how it looks against light.', 'La litofanía se imprime de pie, maciza y en material blanco. La vista previa muestra cómo se ve a contraluz.'),
    'calc.tip.relief': ('Reliéf se tiskne naležato bez podpěr. Světlejší barva v náhledu znamená vyšší místo.', 'A relief prints lying flat without supports. A lighter colour in the preview means a higher spot.', 'El relieve se imprime tumbado y sin soportes. Un color más claro en la vista previa indica una zona más alta.'),
    'calc.tip.sign': ('Cedulka se tiskne naležato bez podpěr. Písmo jde udělat jinou barvou výměnou filamentu na poslední vrstvy.', 'The sign prints lying flat without supports. The text can be a different colour by swapping filament for the last layers.', 'El cartel se imprime tumbado y sin soportes. El texto puede ir en otro color cambiando el filamento en las últimas capas.'),
}
for i, lang in enumerate(['cs', 'en', 'es']):
    p = f'lang/{lang}.json'
    d = json.load(open(p, encoding='utf-8'))
    for k, v in L.items():
        d[k] = v[i]
    json.dump(d, open(p, 'w', encoding='utf-8', newline='\n'), ensure_ascii=False, indent=4)
print('ok')
