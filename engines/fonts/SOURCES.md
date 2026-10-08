# Typefaces of the tools

Everything here is under the SIL Open Font License 1.1. The files are the originals, byte for byte (only the square
brackets of variable files are left out of the names); the licence of each family lies next to it as `<Family>-OFL.txt`.
A family that comes as one variable file (`…-wght.ttf`) is read at its boldest weight by `engines/python/shape2d.py`.

The registry is `ParametricGenerator::FONTS` (key, file, name, group); the pictures of the font picker are drawn by
`php artisan matplace:font-previews` into `public/img/fonts/`. A new face must have the whole Czech and Spanish
alphabet (`tests/Feature/FontsTest.php` sets a line of both in every face) and strokes that print.

| key | file | family | taken from | copyright |
|---|---|---|---|---|
| `script` | `Pacifico-Regular.ttf` | Pacifico | `github.com/google/fonts/ofl/pacifico` | Copyright 2018 The Pacifico Project Authors (https://github.com/googlefonts/Pacifico) |
| `montserrat` | `Montserrat-wght.ttf` | Montserrat | `github.com/google/fonts/ofl/montserrat` | Copyright 2024 The Montserrat.Git Project Authors (https://github.com/JulietaUla/Montserrat.git) |
| `oswald` | `Oswald-wght.ttf` | Oswald | `github.com/google/fonts/ofl/oswald` | Copyright 2016 The Oswald Project Authors (https://github.com/googlefonts/OswaldFont) |
| `bebas` | `BebasNeue-Regular.ttf` | Bebas Neue | `github.com/google/fonts/ofl/bebasneue` | Copyright © 2010 by Dharma Type. |
| `anton` | `Anton-Regular.ttf` | Anton | `github.com/google/fonts/ofl/anton` | Copyright 2020 The Anton Project Authors (https://github.com/googlefonts/AntonFont.git) |
| `archivo` | `ArchivoBlack-Regular.ttf` | Archivo Black | `github.com/google/fonts/ofl/archivoblack` | Copyright 2017 The Archivo Black Project Authors (https://github.com/Omnibus-Type/ArchivoBlack) |
| `russo` | `RussoOne-Regular.ttf` | Russo One | `github.com/google/fonts/ofl/russoone` | Copyright (c) 2011-2012, Jovanny Lemonad (jovanny.ru), with Reserved Font Name "Russo" |
| `comfortaa` | `Comfortaa-wght.ttf` | Comfortaa | `github.com/google/fonts/ofl/comfortaa` | Copyright 2011 The Comfortaa Project Authors (https://github.com/alexeiva/comfortaa), with Reserved Font Name "Comfortaa". |
| `playfair` | `PlayfairDisplay-wght.ttf` | Playfair Display | `github.com/google/fonts/ofl/playfairdisplay` | Copyright 2017 The Playfair Display Project Authors (https://github.com/clauseggers/Playfair-Display), with Reserved Font Name "Playfair Display" |
| `alfa_slab` | `AlfaSlabOne-Regular.ttf` | Alfa Slab One | `github.com/google/fonts/ofl/alfaslabone` | Copyright 2016 The Alfa Slab One Project Authors (http://www.jmsole.cl / info@jmsole.cl), with Reserved Font Name "Alfa Slab". |
| `abril` | `AbrilFatface-Regular.ttf` | Abril Fatface | `github.com/google/fonts/ofl/abrilfatface` | Copyright (c) 2011, TypeTogether (www.type-together.com), |
| `lobster` | `Lobster-Regular.ttf` | Lobster | `github.com/google/fonts/ofl/lobster` | Copyright 2010 The Lobster Project Authors (https://github.com/impallari/The-Lobster-Font), with Reserved Font Name "Lobster". |
| `caveat` | `Caveat-wght.ttf` | Caveat | `github.com/google/fonts/ofl/caveat` | Copyright 2014 The Caveat Project Authors (https://github.com/googlefonts/caveat) |
| `dancing` | `DancingScript-wght.ttf` | Dancing Script | `github.com/google/fonts/ofl/dancingscript` | Copyright 2016 The Dancing Script Project Authors (https://github.com/googlefonts/DancingScript), with Reserved Font Name 'Dancing Script'. |
| `great_vibes` | `GreatVibes-Regular.ttf` | Great Vibes | `github.com/google/fonts/ofl/greatvibes` | Copyright 2015 The Great Vibes Pro Project Authors (https://github.com/googlefonts/great-vibes) |
| `sacramento` | `Sacramento-Regular.ttf` | Sacramento | `github.com/google/fonts/ofl/sacramento` | Copyright (c) 2012, Brian J. Bonislawsky DBA Astigmatic (AOETI) (astigma@astigmatic.com), with Reserved Font Names 'Sacramento' |
| `kaushan` | `KaushanScript-Regular.ttf` | Kaushan Script | `github.com/google/fonts/ofl/kaushanscript` | Copyright (c) 2011, Pablo Impallari (www.impallari.com/impallari@gmail.com), |
| `courgette` | `Courgette-Regular.ttf` | Courgette | `github.com/google/fonts/ofl/courgette` | Copyright (c) 2012 by Sorkin Type Co (www.sorkintype.com), with Reserved Font Name "Courgette". |
| `patrick_hand` | `PatrickHand-Regular.ttf` | Patrick Hand | `github.com/google/fonts/ofl/patrickhand` | Copyright (c) 2010-2012 Patrick Wagesreiter (mail@patrickwagesreiter.at) |
| `amatic` | `AmaticSC-Bold.ttf` | Amatic SC | `github.com/google/fonts/ofl/amaticsc` | Copyright 2015 The Amatic SC Project Authors (https://github.com/googlefonts/AmaticSC) |
| `bangers` | `Bangers-Regular.ttf` | Bangers | `github.com/google/fonts/ofl/bangers` | Copyright 2010 The Bangers Project Authors (https://github.com/googlefonts/bangers) |
| `titan` | `TitanOne-Regular.ttf` | Titan One | `github.com/google/fonts/ofl/titanone` | Copyright (c) 2011, Rodrigo Fuenzalida (www.rfuenzalida.com/hello@rfuenzalida.com), |
| `paytone` | `PaytoneOne-Regular.ttf` | Paytone One | `github.com/google/fonts/ofl/paytoneone` | Copyright 2011 The Paytone Project Authors (https://github.com/googlefonts/paytoneFont), |
| `bungee` | `Bungee-Regular.ttf` | Bungee | `github.com/google/fonts/ofl/bungee` | Copyright 2023 The Bungee Project Authors (https://github.com/djrrb/Bungee) |
| `righteous` | `Righteous-Regular.ttf` | Righteous | `github.com/google/fonts/ofl/righteous` | Copyright (c) 2011 by Brian J. Bonislawsky DBA Astigmatic (AOETI) |
| `baloo` | `Baloo2-wght.ttf` | Baloo 2 | `github.com/google/fonts/ofl/baloo2` | Copyright 2019 The Baloo 2 Project Authors (https://github.com/EkType/Baloo2) |
| `press_start` | `PressStart2P-Regular.ttf` | Press Start 2P | `github.com/google/fonts/ofl/pressstart2p` | Copyright 2012 The Press Start 2P Project Authors (cody@zone38.net), with Reserved Font Name "Press Start 2P". |

`NotoEmoji.ttf` is the spare face for symbols a typeface does not have (♥ ★ 🐾 …); the three DejaVu faces (`sans`, `serif`,
`mono`) come with dompdf (`vendor/dompdf/dompdf/lib/fonts`, Bitstream Vera licence).

Downloaded on 8 October 2026.
