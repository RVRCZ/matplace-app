<?php

/*
 * Addresses of the old site (matplace.com before the switch) and where they lead now. Read by
 * App\Http\Middleware\LegacyRedirects, before routing, for GET requests only.
 *
 * Not listed because they simply keep working on the new application with the same content:
 *   /model/{slug}, /blog, /blog/{slug}, /faq, /cookies
 * /katalog → /model is a plain route (routes/web.php).
 */
return [
    // where the old site lives after the switch; its account, order and printer pages are sent there
    'host' => rtrim((string) env('LEGACY_HOST', 'https://legacy.matplace.com'), '/'),

    // old address → a page of ours (permanent: Google moves what it knows to the new address).
    // "route:<name>" = a named page in the visitor's language, anything else = a path.
    'pages' => [
        '/kalkulator-ceny' => 'route:home',
        '/jak-funguje-objednavka' => 'route:pages.faq',
        '/materialy' => 'route:materials',
        '/o-nas' => 'route:pages.about',
        '/kontakt' => 'route:pages.contact',
        '/reklamace' => 'route:pages.complaints',
        '/podminky-pouzivani' => 'route:pages.terms',
        '/obchodni-podminky' => 'route:pages.business_terms',
        '/zasady-ochrany-osobnich-udaju' => 'route:privacy',
        '/sitemap' => '/sitemap.xml',
    ],

    // first part of the address → /model/{slug}: old download and purchase pages of a model
    'to_model' => ['stahnout', 'stahnout-email', 'koupit-model', 'koupit-model-qr'],

    /*
     * First part of the address → the same address on the old site. Temporary (302): the old site will go away one
     * day and a permanent redirect would be remembered. Accounts, orders, payments and chats of the old site, its
     * printers and designers, and the files it serves.
     */
    'to_legacy' => [
        'tiskarny', 'tiskar', 'designer', 'ucet', 'moje-poptavka', 'poptat', 'poptat-vlastni-model', 'poptavka', 'objednat', 'kosik', 'pokladna',
        'platba', 'zaplatit', 'zaplatit-qr', 'zaplatit-prevodem', 'zaplatit-gopay', 'dekujeme', 'chat', 'ohodnotit', 'overit-poptavku', 'notifikace', 'stazeni',
        'prihlaseni', 'odhlaseni', 'zapomenute-heslo', 'heslo-reset', 'r',
        'thumbnails', 'stl', 'banner', 'blog-img', 'assets',
    ],

    /*
     * Addresses without a successor: answered 410 "gone" instead of a redirect or a 404, so that search engines drop
     * them and stop asking (docs/AP.md §14). Patterns over the path, checked before `to_legacy`; an address a route
     * of ours answers is never gone. Add what the admin's list "Adresy, které neexistují" and the server log show.
     *   /assets/thumbs/…   thumbnails of the old catalogue: Googlebot-Image fetched thousands a day through the 302
     *                      to the old site; our pages show their own copy or the old host directly
     *                      (CatalogModel::directPreview). LEGACY_THUMBS_GONE=false brings the 302 back.
     *   /14763             product numbers of the e-shop that was here before 2019
     *   /pla-luminous-filament-…, /klicenka-…   its product pages
     */
    'gone' => array_values(array_filter([
        env('LEGACY_THUMBS_GONE', true) ? '~^/assets/thumbs(/|$)~' : null,
        '~^/[0-9]+$~',
        '~^/[a-z0-9-]*filament[a-z0-9-]*$~',
        '~^/klicenka-[a-z0-9-]+$~',
    ])),
];
