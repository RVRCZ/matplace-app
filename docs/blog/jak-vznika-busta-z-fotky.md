# Jak vzniká busta z fotky (blog, TODO #17)

Článek pro `/admin/content/posts`, formát **markdown**. Jeden slug pro všechny jazyky, pole `title`, `excerpt` a `body`
podle jazyka (bloky `## cs`, `## en`, `## es` níže; tělo je všechno pod řádkem `body:` až po další `## `).
Obálka (`cover_path`): `public/img/blog/busta/obalka.webp` (1600 × 800). Obrázky v těle odkazují na
`/img/blog/busta/…`. Odkud jsou čísla a co je na které fotce: `docs/AG.md`.

slug: jak-vznika-busta-z-fotky

## cs

title: Jak vzniká busta z fotky

excerpt: Jedna fotka zepředu, minuta čekání a máte 3D model busty se jménem na podstavci. Co poslat, co se s fotkou děje, jak velká busta bude, kolik tisk stojí a co od výtisku čekat.

body:

Bustu z fotky vytvoříte v nástroji [Busta nebo figurka z fotky](/tools/figure). Stačí jedna fotka zepředu. Asi za minutu máte 3D model s podstavcem a jménem, vidíte cenu tisku a model si můžete stáhnout. Tady je celý postup krok za krokem, včetně toho, co se mezitím děje u nás.

### 1. Co poslat

Nutná je jedna fotka zepředu: jedna osoba od temene po pas, světlé jednobarevné pozadí, ostrý snímek. Největší rozdíl udělá pár drobností:

- Odstupte na 1,5 až 2 metry. Fotka zblízka zkresluje obličej.
- Držte telefon ve výšce obličeje, ne shora ani zdola.
- Foťte před jednobarevnou zdí, bez předmětů v pozadí, při rovnoměrném světle.
- Ruce nechte volně podél těla a kolem ramen nechte volné místo.

Volitelně přidáte fotky zleva, zezadu a zprava ze stejné vzdálenosti a výšky, pokaždé otočení o 90°. Model pak lépe vystihne podobu i účes. Stačí i jedna další strana; fotku, kterou nepůjde použít, vynecháme. Tenké obroučky brýlí a jednotlivé prameny vlasů se tisknou špatně, vlasy stažené nebo učesané k hlavě vyjdou lépe.

Co neposílat: fotku cizího člověka bez jeho souhlasu. Ve formuláři zaškrtnutím potvrzujete, že na fotce jste vy, nebo že máte souhlas vyfoceného. Dítě foťte jen jako jeho rodič, nebo s jeho souhlasem. Každou fotku před vytvořením modelu automaticky kontrolujeme a nahotu, násilí, zbraně nebo fotku bez jednoho jasného námětu odmítneme.

![Krok Fotka v nástroji na telefonu: busta nebo figurka, fotka zepředu a nepovinné fotky z dalších stran](/img/blog/busta/telefon-fotka-cs.webp)

### 2. Co se děje během minuty

Po klepnutí na „Vytvořit model“ pošleme fotku našemu dodavateli generování 3D modelů (Tripo). Ten z ní vytvoří tvar, bez barev. Pak model převezmeme my: natočíme obličej dopředu, hruď rovně uřízneme jako u sochařské busty, odstraníme drobné úlomky a volně trčící prameny, uzavřeme všechny díry a spojíme vše do jednoho plného tělesa, které jde vytisknout. Nakonec přidáme podstavec se jménem a model nastavíme na zvolenou výšku. Celé to trvá asi minutu, zůstaňte prosím mezitím na stránce.

**Soukromí.** Fotku smažeme hned po vytvoření modelu. Model vidí jen ten, kdo má odkaz, a modely nepřihlášených po 30 dnech mažeme. Kdo fotku kromě nás zpracuje, najdete v [zásadách ochrany osobních údajů](/privacy).

**Kolik modelů zdarma.** Jeden model denně bez účtu, tři s účtem.

### 3. Provedení, podstavec a jméno

Na výběr jsou tři provedení busty:

- **Moderní busta** má zaoblenou hruď na soustruženém soklu a jméno po obvodu.
- **Antická busta** stojí na tabulce se jménem a na soklu se širokou patou.
- **Sochařský řez** nemá podstavec. Busta stojí na vlastním rovném řezu se šikmo seříznutými rameny.

K tomu jsou jednoduché podstavce: kulatý, čtvercový, šestihran, sloupek, podstavec se jmenovkou (i s věnováním, třeba „k 80. narozeninám“) a bez podstavce.

![Moderní busta na soustruženém soklu](/img/blog/busta/moderni-busta.webp)

![Antická busta na tabulce a soklu se širokou patou](/img/blog/busta/anticka-busta.webp)

Jméno má nejvýš 24 znaků a vystoupí na přední straně velkými písmeny jako součást modelu. Volte krátký text: písmo menší než 3 mm se nevytiskne čitelně, takže u malé busty pomůže kratší jméno nebo větší výška.

Výšku nastavíte posuvníkem od 30 do 250 mm, výchozích je 80 mm. Je to výška celého výtisku včetně podstavce. Pro portrét na poličku se hodí 80 až 150 mm.

Bez nového generování, zdarma a za pár vteřin, změníte v kalkulaci podstavec, jméno a věnování, směr pohledu (dopředu, doleva, doprava, dozadu), hloubku, do jaké busta sedí v podstavci, a ořez volných pramenů. Model zůstane stejný. Nová fotka nebo jiný výraz znamená nový model.

![Krok Podstavec na telefonu: výška výtisku, provedení, jméno na podstavci a souhlas](/img/blog/busta/telefon-podstavec-cs.webp)

### 4. Tisk

Hotový model se otevře v kalkulaci. Tam uvidíte přesnou dobu tisku, spotřebu materiálu a cenu a stáhnete si soubor STL nebo hotový projekt pro svou tiskárnu, bez registrace.

Pro představu jedna skutečná moderní busta z kalkulace, z PLA+:

| Výška | Materiál | Doba tisku | Cena tisku u nás |
|---|---|---|---|
| 80 mm | 14 g | asi 1 h | 99 Kč |
| 120 mm | 40 g | asi 1 h 50 min | 130 Kč |
| 150 mm | 72 g | asi 2 h 40 min | 200 Kč |

Cena je bez dopravy a 99 Kč je nejnižší cena jednoho tisku. Busta se širšími rameny vyjde ve 150 mm na 82 g, 3 hodiny a 220 Kč. Přesné číslo pro tu svou uvidíte hned po vytvoření modelu.

Busta se tiskne z jedné barvy, model nese jen tvar. Barvu vybíráte při objednávce z cívek, které jsou právě v tiskárnách, a u každé vidíte kód cívky. U portrétu se vyplatí PLA+: matný povrch skryje vrstvy, lesklý zvýrazní tvar.

Tisk na naší farmě zatím otevíráme postupně. Kde už ho kalkulace nabízí, objednáte ho tlačítkem „Vytisknout“: vyberete materiál a barvu, dostanete závaznou cenu a tisk se spustí, jakmile je tiskárna volná. Výtisk posíláme Zásilkovnou na výdejní místo nebo domů. Osobní převzetí zatím nenabízíme.

### 5. Co od výtisku čekat a co ne

- **Podoba, ne kopie.** Tvar je umělecký dojem. Co na fotce není vidět, třeba záda nebo profil, generátor odhaduje. S fotkami z boku a zezadu odhaduje méně.
- **Obličej podle fotky.** Rysy, úsměv, vousy i výrazné brýle vyjdou. Tenké obroučky a jednotlivé prameny vlasů se tisknou špatně.
- **Vrstvy a podpěry.** Tiskne se po vrstvách 0,2 mm, takže na malé bustě jsou vrstvy vidět víc než na velké. Podpěry přidáváme jen tam, kde jsou potřeba. Busty v tabulce je nepotřebovaly; u odstávajících vlasů nebo paží mohou po jejich odlomení zůstat drobné stopy.
- **Velikost dělá detail.** Ve 150 mm má obličej skoro dvakrát víc místa než v 80 mm. Tisk trvá asi 2 h 40 min místo hodiny a stojí 200 Kč místo 99 Kč. Jméno na podstavci vyjde ve větší bustě ostřeji.

![Moderní busta: dlouhé vlasy, brýle a halenka s knoflíky](/img/blog/busta/moderni-busta-2.webp)

### 6. Vyzkoušejte to

Nahrajte fotku a za minutu uvidíte svůj model i cenu: [vytvořit bustu z fotky](/tools/figure?utm_source=blog&utm_medium=article&utm_campaign=busta).

Matplace tiskne na vlastní farmě tiskáren Anycubic, ne přes prostředníka. Tiskárny i cívky jsou naše a barvu vybíráte z těch, které v nich právě jsou.

## en

title: How a bust is made from a photo

excerpt: One front photo, a minute of waiting, and you have a 3D model of a bust with a name on its base. What to send, what happens to the photo, how big the bust will be, what printing costs and what to expect.

body:

You make a bust from a photo with the [Bust or figure from a photo](/en/tools/figure) tool. One front photo is enough. In about a minute you have a 3D model with a base and a name, you see the print price, and you can download the model. Here is the whole process, including what happens on our side.

### 1. What to send

You need one photo from the front: one person from the top of the head to the waist, a plain light background, a sharp picture. A few small things make the biggest difference:

- Step back 1.5 to 2 metres. A close-up distorts the face.
- Hold the phone at face height, not from above or below.
- Stand in front of a plain wall, nothing in the background, in even light.
- Let the arms hang loosely and leave space around the shoulders.

Optionally add photos from the left, the back and the right, from the same distance and height, turning 90° each time. The model then catches the likeness and the hair better. One more side is enough; a photo that cannot be used is left out. Thin spectacle frames and single strands of hair print badly, hair tied back or combed close to the head comes out better.

What not to send: a photo of someone else without their consent. In the form you confirm that the photo shows you or that you have the consent of the person in it. Photograph a child only as their parent or with a parent's consent. We check every photo automatically and turn down nudity, violence, weapons or a photo without one clear subject.

![The Photo step on a phone: bust or figure, the front photo and optional photos from other sides](/img/blog/busta/telefon-fotka-en.webp)

### 2. What happens during that minute

When you tap "Create model", we send the photo to our 3D model generation provider (Tripo), which makes the shape, without colours. Then we take over: we turn the face to the front, cut the chest flat like on a sculpted bust, remove small fragments and loose strands, close every hole and join everything into one solid, printable body. Finally we add the base with the name and set the height you chose. It takes about a minute; please stay on the page meanwhile.

**Privacy.** We delete the photo as soon as the model is created. Only people with the link see the model, and models of visitors without an account are deleted after 30 days. Who else processes the photo is listed in the [privacy policy](/en/privacy).

**How many for free.** One model a day without an account, three with one.

### 3. Style, base and name

There are three styles of bust:

- The **modern bust** has a rounded chest on a turned socle with the name around it.
- The **antique bust** stands on a tablet with the name and a socle with a wide foot.
- The **sculptor's cut** has no base; the bust stands on its own flat cut with the shoulders cut at a slant.

Besides these there are simple bases: round, square, hexagon, column, a name plate (with a dedication too, say "for your 80th birthday") and no base.

![A modern bust on a turned socle](/img/blog/busta/moderni-busta.webp)

![An antique bust on a tablet and a socle with a wide foot](/img/blog/busta/anticka-busta.webp)

The name has up to 24 characters and is raised on the front in capitals as part of the model. Keep it short: letters under 3 mm do not print legibly, so a small bust needs a shorter name or more height.

The height slider runs from 30 to 250 mm, 80 mm by default, measured for the whole print including the base. For a portrait on a shelf, 80 to 150 mm works well.

Without a new generation, free and in a few seconds, you can change in the calculator the base, the name and dedication, the direction of the gaze (front, left, right, back), how deep the bust sits in its base, and the trimming of loose strands. The model stays the same. A new photo or another expression means a new model.

![The Base step on a phone: print height, style, name on the base and consent](/img/blog/busta/telefon-podstavec-en.webp)

### 4. Printing

The finished model opens in the calculator. You see the exact print time, the material and the price, and you can download the STL file or a ready project for your own printer, without signing up.

For an idea, one real modern bust from the calculator, in PLA+:

| Height | Material | Print time | Price to print with us |
|---|---|---|---|
| 80 mm | 14 g | about 1 h | 99 CZK |
| 120 mm | 40 g | about 1 h 50 min | 130 CZK |
| 150 mm | 72 g | about 2 h 40 min | 200 CZK |

Shipping is extra, and 99 CZK is the lowest price of a print. A bust with wider shoulders comes to 82 g, 3 hours and 220 CZK at 150 mm. The calculator shows the price in euros too, and your own figure right after the model is made.

A bust is printed in one colour, as the model carries only the shape. You pick the colour when ordering, from the spools loaded in the printers, each with its spool code. For a portrait PLA+ is worth it: a matte surface hides the layers, a glossy one brings out the shape.

We are opening printing on our farm step by step. Where the calculator already offers it, you order with the "Print it with us" button: pick the material and colour, get a binding price, and the print starts as soon as a printer is free. We ship with Packeta to a pick-up point or to your door; personal pick-up is not offered yet.

### 5. What to expect, and what not

- **A likeness, not a copy.** The shape is an artistic impression. What the photo does not show, such as the back or the profile, the generator guesses. Photos from the sides and the back mean less guessing.
- **The face follows the photo.** Features, a smile, a beard and bold glasses come out. Thin frames and single strands of hair print badly.
- **Layers and supports.** The print is built in 0.2 mm layers, which show more on a small bust than on a large one. We add supports only where needed. The busts in the table needed none; under sticking-out hair or arms, small marks can remain after snapping them off.
- **Size makes detail.** At 150 mm the face has almost twice the room it has at 80 mm. Printing takes about 2 h 40 min instead of an hour and costs 200 CZK instead of 99. The name on the base comes out sharper too.

![A modern bust: long hair, glasses and a blouse with buttons](/img/blog/busta/moderni-busta-2.webp)

### 6. Try it

Upload a photo and in a minute you see your model and its price: [make a bust from a photo](/en/tools/figure?utm_source=blog&utm_medium=article&utm_campaign=busta).

Matplace prints on its own farm of Anycubic printers, not through a middleman. The printers and spools are ours, and you pick the colour from those loaded right now.

## es

title: Cómo se hace un busto a partir de una foto

excerpt: Una foto de frente, un minuto de espera y tiene el modelo 3D de un busto con un nombre en la base. Qué enviar, qué pasa con la foto, qué tamaño tendrá, cuánto cuesta imprimirlo y qué esperar.

body:

El busto a partir de una foto se crea con la herramienta [Busto o figura a partir de una foto](/es/tools/figure). Basta una foto de frente. En más o menos un minuto tiene un modelo 3D con base y nombre, ve el precio de impresión y puede descargar el modelo. Este es el proceso completo, incluido lo que hacemos nosotros.

### 1. Qué enviar

Hace falta una foto de frente: una persona desde la coronilla hasta la cintura, fondo claro y liso, foto nítida. Unos pocos detalles marcan la mayor diferencia:

- Aléjese de 1,5 a 2 metros. Una foto de cerca deforma la cara.
- Sostenga el teléfono a la altura de la cara, ni desde arriba ni desde abajo.
- Delante de una pared lisa, sin objetos al fondo y con luz uniforme.
- Brazos sueltos a lo largo del cuerpo y espacio libre alrededor de los hombros.

Si quiere, añada fotos desde la izquierda, desde atrás y desde la derecha, a la misma distancia y altura, girando 90° cada vez. Así el modelo capta mejor el parecido y el peinado. Basta un lado más; una foto que no se pueda usar se omite. Las monturas finas de las gafas y los mechones sueltos se imprimen mal, el pelo recogido o peinado pegado a la cabeza sale mejor.

Qué no enviar: la foto de otra persona sin su consentimiento. En el formulario confirma que en la foto sale usted o que tiene el consentimiento de la persona fotografiada. Fotografíe a un niño solo si es su padre o madre, o con su consentimiento. Revisamos cada foto automáticamente y rechazamos desnudos, violencia, armas o fotos sin un único motivo claro.

![El paso Foto en el teléfono: busto o figura, la foto de frente y fotos opcionales de otros lados](/img/blog/busta/telefon-fotka-es.webp)

### 2. Qué pasa durante ese minuto

Al pulsar «Crear modelo», enviamos la foto a nuestro proveedor de generación de modelos 3D (Tripo), que crea la forma, sin colores. Después seguimos nosotros: giramos la cara hacia delante, cortamos el pecho en plano como en un busto esculpido, quitamos fragmentos y mechones sueltos, cerramos los agujeros y unimos todo en un solo cuerpo macizo e imprimible. Por último añadimos la base con el nombre y ajustamos la altura elegida. Tarda más o menos un minuto; quédese en la página mientras tanto.

**Privacidad.** Borramos la foto en cuanto se crea el modelo. Solo quien tenga el enlace ve el modelo, y los modelos de los invitados se borran a los 30 días. Quién más procesa la foto está en la [política de privacidad](/es/privacy).

**Cuántos gratis.** Un modelo al día sin cuenta, tres con cuenta.

### 3. Estilo, base y nombre

Hay tres estilos de busto:

- El **busto moderno** tiene el pecho redondeado sobre un zócalo torneado con el nombre alrededor.
- El **busto clásico** se apoya en una placa con el nombre y en un zócalo de pie ancho.
- El **corte de escultor** no lleva base; el busto se apoya en su propio corte plano, con los hombros cortados en diagonal.

Además hay bases sencillas: redonda, cuadrada, hexagonal, columna, con placa (también con dedicatoria, por ejemplo «por tus 80 años») y sin base.

![Busto moderno sobre un zócalo torneado](/img/blog/busta/moderni-busta.webp)

![Busto clásico sobre una placa y un zócalo de pie ancho](/img/blog/busta/anticka-busta.webp)

El nombre tiene hasta 24 caracteres y sale en relieve delante, en mayúsculas, como parte del modelo. Que sea corto: las letras de menos de 3 mm no se imprimen legibles, así que un busto pequeño pide un nombre más corto o más altura.

La altura va de 30 a 250 mm, 80 mm por defecto, y es la de toda la impresión con la base. Para un retrato en una estantería van bien de 80 a 150 mm.

Sin generar de nuevo, gratis y en unos segundos, en la calculadora puede cambiar la base, el nombre y la dedicatoria, la dirección de la mirada (al frente, a la izquierda, a la derecha, atrás), cuánto se hunde el busto en la base y el recorte de mechones sueltos. El modelo sigue siendo el mismo. Una foto nueva u otra expresión es un modelo nuevo.

![El paso Base en el teléfono: altura, estilo, nombre en la base y consentimiento](/img/blog/busta/telefon-podstavec-es.webp)

### 4. La impresión

El modelo terminado se abre en la calculadora. Allí ve el tiempo exacto de impresión, el material y el precio, y puede descargar el archivo STL o un proyecto listo para su impresora, sin registrarse.

Para hacerse una idea, un busto moderno real en la calculadora, en PLA+:

| Altura | Material | Tiempo de impresión | Precio con nosotros |
|---|---|---|---|
| 80 mm | 14 g | cerca de 1 h | 99 CZK |
| 120 mm | 40 g | cerca de 1 h 50 min | 130 CZK |
| 150 mm | 72 g | cerca de 2 h 40 min | 200 CZK |

El envío va aparte y 99 CZK es el precio mínimo de una impresión. Un busto con hombros más anchos sale a 82 g, 3 horas y 220 CZK con 150 mm. La calculadora también muestra el precio en euros, y el de su busto justo después de crear el modelo.

El busto se imprime en un solo color, ya que el modelo solo lleva la forma. El color se elige al pedir, entre las bobinas cargadas en las impresoras, cada una con su código. Para un retrato merece la pena el PLA+: la superficie mate disimula las capas, la brillante realza la forma.

Estamos abriendo la impresión en nuestra granja poco a poco. Donde la calculadora ya la ofrece, se pide con el botón «Imprimirlo con nosotros»: elige material y color, recibe un precio vinculante y la impresión empieza en cuanto haya una impresora libre. Enviamos con Packeta a un punto de recogida o a domicilio; la recogida en persona aún no está disponible.

### 5. Qué esperar y qué no

- **Un parecido, no una copia.** La forma es una impresión artística. Lo que la foto no muestra, como la espalda o el perfil, el generador lo supone. Con fotos de lado y de atrás supone menos.
- **La cara sigue la foto.** Los rasgos, la sonrisa, la barba y las gafas gruesas salen bien. Las monturas finas y los mechones sueltos se imprimen mal.
- **Capas y soportes.** Se imprime en capas de 0,2 mm, que se notan más en un busto pequeño que en uno grande. Añadimos soportes solo donde hacen falta. Los bustos de la tabla no los necesitaron; bajo pelo o brazos salientes pueden quedar pequeñas marcas al quitarlos.
- **El tamaño da detalle.** Con 150 mm la cara tiene casi el doble de espacio que con 80 mm. La impresión tarda unas 2 h 40 min en vez de una hora y cuesta 200 CZK en vez de 99. El nombre en la base también sale más nítido.

![Busto moderno: pelo largo, gafas y una blusa con botones](/img/blog/busta/moderni-busta-2.webp)

### 6. Pruébelo

Suba una foto y en un minuto verá su modelo y su precio: [crear un busto a partir de una foto](/es/tools/figure?utm_source=blog&utm_medium=article&utm_campaign=busta).

Matplace imprime en su propia granja de impresoras Anycubic, sin intermediarios. Las impresoras y las bobinas son nuestras, y el color se elige entre las que están cargadas en ese momento.
