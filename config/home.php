<?php

/*
 * The homepage. `tools` are the cards under the first screen, in this order: what people make most often with
 * a 3D printer (a name as a gift, a photo as a picture, order in a drawer…). Keys of config/tools.php; a tool that
 * is switched off or has no page is left out, the next one takes its place, at most `tools_shown` cards are shown.
 * Tools the admin switches on at /admin/tools come before these (ToolVisibility::onHome).
 *
 * `intake` is the first screen, "Co chcete vyrobit?" (docs/AN.md §23): how many wishes one visitor may send, the cheap
 * rules that keep advertising away from the paid model (App\Domain\Intake\IntakeSpam), and the calendar of the
 * examples under the field (App\Domain\Intake\IntakeHints).
 */
return [
    'tools' => ['qr', 'figure', 'gifts', 'relief', 'organizer', 'pet', 'phone_stand', 'cutter', 'vase', 'repair', 'holder', 'sign', 'lightbox'],
    'tools_shown' => 8,
    // what the farm prints most often (Roman, 13 Oct 2026): first on the page, with a badge saying so
    'popular' => ['qr', 'figure'],

    'intake' => [
        // wishes (and "try it without a designer") per browser session and per IP address; the admin tries freely
        'limits' => ['per_hour' => 5, 'per_day' => 20],

        /*
         * One press of "Vytvořit" ends on a design (docs/AN.md §24). A tool the assistant is sure of from
         * `direct_confidence` on opens right away with the wish filled in. A wish no tool has a template for (or a tool
         * under that confidence) gets a free design made by the model generator from the words: that costs money, so it
         * is OFF until the admin switches it on at /admin/intake/design, where the counts, the price and the confidence
         * are changed too (stored as `home.design` in the farm's settings; these are the defaults). Roman, 13 Nov 2026:
         * `free_guest` designs or changes in words for a visitor without an account, `free_user` more once they sign
         * in, after that a run costs `price` crowns from the credit; no price yet (null) = nothing is sold, the visitor
         * is told honestly that there is no further run. `per_day` caps the whole site and day (our cost).
         * `target_mm` is the size of a design whose wish names none.
         */
        'design' => ['enabled' => false, 'free_guest' => 10, 'free_user' => 10, 'price' => null, 'per_day' => 100, 'direct_confidence' => 0.6, 'target_mm' => 80],

        /*
         * Advertising is held before the model is asked (the model costs money). A hard rule holds on its own: the
         * unseen field filled in, the form sent sooner than a person can, no stamp of the page at all. The rules over
         * the words give points; `threshold` points hold the wish. A held wish is stored with the status `spam`, is not
         * sent to the model, and the admin can give it back at /admin/intake.
         */
        'spam' => [
            'enabled' => true,
            'honeypot' => 'topic',          // the name of the unseen field; a person never fills it
            'require_stamp' => true,        // the page's script sends the stamp of the page; a bare POST of the form has none
            'min_seconds' => 3,             // from the page being made to the wish being sent
            'threshold' => 3,
            'points' => [
                'markup' => 3,              // <a href=…>, any HTML tag, [url=…]
                'links' => 3,               // two or more addresses
                'link' => 2,                // one address outside `link_hosts`
                'strong' => 3,              // a phrase only an advertisement says
                'weak' => 1,                // a phrase an advertisement says often (each, two points at most)
                'script' => 3,              // most letters in a script the page's languages do not use, and no photo
                'language' => 3,            // no word of a language we answer in, no number, no photo
                'long' => 1,                // longer than `long_chars` without a photo
            ],
            'long_chars' => 600,
            // a link to a model or to a thing somebody wants printed is no advertisement
            'link_hosts' => ['matplace.com', 'printables.com', 'thingiverse.com', 'makerworld.com', 'thangs.com', 'cults3d.com', 'myminifactory.com', 'youtube.com', 'youtu.be'],
            'strong' => [
                'fantastic post', 'great post', 'nice post', 'awesome post', 'this post', 'this article', 'on this topic', 'your blog', 'writer for your', 'guest post',
                'backlink', 'seo', 'search engine', 'improve your posture', 'posture corrector', 'click here', 'visit our website', 'unsubscribe', 'casino', 'viagra',
                'cialis', 'rent a car', 'car rental', 'rental car', 'followers', 'increase your sales', 'make money',
            ],
            'weak' => [
                'blog', 'your website', 'your web site', 'your site', 'your business', 'your company', 'we offer', 'we provide', 'our company', 'our services',
                'free shipping', 'order now', 'buy now', 'shop now', 'limited time', 'special offer', '% off', 'discount', 'best price', 'lowest price', 'crypto',
                'bitcoin', 'loan', 'whatsapp', 'telegram', 'booking', 'looking to improve',
            ],
            // the scripts a wish may be written in (Unicode script names); a visitor from abroad still writes in Latin letters
            'scripts' => ['Latin'],
            'script_share' => 0.5,
            // "no word of a language we answer in": only for four words and more, without a number and without a photo
            'language_min_words' => 4,
            // the small words of Czech, Slovak, English, Spanish, German and Polish; one of them (or a word the router knows) is enough
            'known_words' => [
                // cs, sk
                'a', 'i', 'o', 'u', 'v', 've', 'z', 'ze', 'k', 'ke', 's', 'se', 'na', 'do', 'od', 'po', 'pro', 'pre', 'pod', 'nad', 'za', 'bez', 'jako', 'ako', 'je', 'jsou', 'su',
                'to', 'ten', 'ta', 'tu', 'ty', 'co', 'čo', 'kde', 'jak', 'aby', 'nebo', 'alebo', 'ani', 'ale', 'by', 'bych', 'chci', 'chcem', 'chtěl', 'chtěla', 'potřebuji', 'potřebuju',
                'potrebujem', 'prosím', 'dobrý', 'den', 'ahoj', 'mám', 'máte', 'můj', 'moje', 'náš', 'naše', 'naši', 'jeden', 'jedna', 'dva', 'dvě', 'tři', 'kus', 'kusy', 'kusů', 'cm',
                'mm', 'barva', 'barvy', 'malý', 'malá', 'velký', 'velká', 'dárek', 'fotka', 'fotky', 'podle', 'asi', 'už', 'si', 'mi', 'mě', 'vyrobit', 'vytisknout', 'tisk',
                // en
                'the', 'an', 'of', 'for', 'to', 'in', 'on', 'at', 'with', 'from', 'and', 'or', 'is', 'are', 'it', 'my', 'our', 'your', 'me', 'we', 'you', 'this', 'that', 'need', 'want',
                'would', 'like', 'can', 'could', 'please', 'hello', 'hi', 'make', 'print', 'one', 'two', 'three', 'piece', 'pieces', 'small', 'big', 'large', 'gift', 'photo', 'about',
                'red', 'blue', 'green', 'black', 'white', 'yellow',
                // es
                'el', 'la', 'los', 'las', 'un', 'una', 'de', 'del', 'en', 'con', 'para', 'por', 'y', 'es', 'mi', 'mis', 'nuestro', 'nuestra', 'quiero', 'necesito', 'hola', 'favor',
                'como', 'que', 'regalo', 'foto', 'pieza', 'piezas', 'rojo', 'azul', 'verde', 'negro', 'blanco',
                // de, pl
                'der', 'die', 'das', 'ein', 'eine', 'und', 'für', 'mit', 'von', 'ich', 'bitte', 'brauche', 'möchte', 'hallo', 'und', 'w', 'dla', 'jest', 'chcę', 'potrzebuję',
                'proszę', 'dzień', 'cześć',
            ],
            // a question about the price that names nothing to make ("I wanted to know your price"): no model is asked,
            // the visitor gets the honest answer that a price needs a thing; only short texts without a number and a photo
            'price_max_words' => 12,
            'price_words' => ['price', 'prices', 'pricing', 'cost', 'costs', 'how much', 'quote', 'cena', 'cenu', 'ceny', 'ceník', 'cenik', 'kolik stojí', 'kolik to stojí', 'precio', 'precios', 'cuánto cuesta', 'cuanto cuesta', 'presupuesto'],
        ],

        /*
         * The examples under the field: a short label and a sample wish the click writes into the field (the visitor
         * changes it; nothing is sent). `total` chips are shown, at most `seasonal` of them from the events whose
         * window is open, the rest from the events that last all year (`always`), in the order written here.
         *
         * An event: `date` is the day it happens (MM-DD, `easter`, or `sunday:2:5` = the second Sunday of May), one
         * for all languages or one per language; a language that is not named does not have the event (St Nicholas is
         * Czech, the Three Kings Spanish). It is offered from `lead` days before the day until `stop` days before it:
         * a thing has to be designed, printed and delivered, so the offer ends before the day, not on it.
         * `until_setting` names a farm setting (YYYY-MM-DD) that ends the offer when it is set. `examples` lists where
         * each sample wish is expected to go (a tool's key, or `human`): the texts are `router.hint.<event>.<n>` (the
         * label) and `router.hint.<event>.<n>.fill` (the wish), `:year` in them is the event's year plus `year_offset`.
         * The admin switches an event off and changes its dates and texts at /admin/intake/hints, without a deploy.
         */
        'hints' => [
            'total' => 6,
            'seasonal' => 3,
            'events' => [
                'steady' => ['name' => 'Stálé příklady', 'always' => true, 'examples' => ['figure', 'pet', 'map', 'sign']],
                'birthday' => ['name' => 'Narozeniny', 'always' => true, 'examples' => ['topper', 'keychain']],
                'wedding' => ['name' => 'Svatba', 'always' => true, 'examples' => ['topper', 'sign']],
                'mikulas' => ['name' => 'Mikuláš', 'date' => ['cs' => '12-05'], 'lead' => 30, 'stop' => 4, 'examples' => ['headband', 'charm', 'sign']],
                'christmas' => ['name' => 'Vánoce', 'date' => ['cs' => '12-24', 'en' => '12-25', 'es' => '12-25'], 'lead' => 50, 'stop' => 9, 'until_setting' => 'christmas_deadline', 'examples' => ['ornament', 'coaster', 'cutter', 'headband']],
                'silvestr' => ['name' => 'Silvestr', 'date' => '12-31', 'lead' => 30, 'stop' => 4, 'year_offset' => 1, 'examples' => ['text', 'topper']],
                'reyes' => ['name' => 'Tři králové (jen es)', 'date' => ['es' => '01-06'], 'lead' => 36, 'stop' => 5, 'examples' => ['keychain', 'pet']],
                // (the carnival moves with Easter; the costumes are wanted through February whichever day it falls on)
                'carnival' => ['name' => 'Karneval a masopust', 'date' => '02-20', 'lead' => 40, 'stop' => 5, 'examples' => ['headband', 'headband']],
                'valentine' => ['name' => 'Valentýn', 'date' => '02-14', 'lead' => 35, 'stop' => 4, 'examples' => ['charm', 'text', 'coaster']],
                'easter' => ['name' => 'Velikonoce', 'date' => 'easter', 'lead' => 35, 'stop' => 5, 'examples' => ['cutter', 'sign', 'charm', 'headband']],
                'mothers' => ['name' => 'Den matek', 'date' => ['cs' => 'sunday:2:5', 'en' => 'sunday:2:5', 'es' => 'sunday:1:5'], 'lead' => 30, 'stop' => 5, 'examples' => ['charm', 'text', 'coaster']],
                'fathers' => ['name' => 'Den otců', 'date' => ['cs' => 'sunday:3:6', 'en' => 'sunday:3:6', 'es' => '03-19'], 'lead' => 30, 'stop' => 5, 'examples' => ['keychain', 'medallion', 'coaster']],
                'school_end' => ['name' => 'Konec školního roku', 'date' => ['cs' => '06-30', 'en' => '06-20', 'es' => '06-20'], 'lead' => 35, 'stop' => 5, 'examples' => ['medallion', 'sign', 'charm']],
                'school_start' => ['name' => 'Začátek školy', 'date' => ['cs' => '09-01', 'en' => '09-01', 'es' => '09-10'], 'lead' => 31, 'stop' => 3, 'examples' => ['sign', 'keychain', 'topper']],
                'halloween' => ['name' => 'Halloween a Dušičky', 'date' => '10-31', 'lead' => 35, 'stop' => 4, 'examples' => ['cutter', 'charm', 'sign', 'headband']],
            ],
        ],
    ],
];
