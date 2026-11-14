<?php

namespace App\Domain\Intake;

use App\Domain\Tools\Artwork;

/**
 * The picture a wish names in words, found in our own library of silhouettes (engines/artwork, docs/AN.md §25): "a bat
 * charm", "vykrajovátko ve tvaru dýně", "un colgante de ángel". A tool that makes a thing of a picture (a charm,
 * earrings, a cookie cutter, a coaster, an ornament…) opens with that picture instead of its own sample, which has
 * nothing to do with the wish. A wish that speaks of the visitor's own picture (a drawing, a photo, a logo) or names a
 * motif the library does not have gets none: the page then asks for the picture instead of showing ours.
 *
 * No picture is generated here and nothing is asked of a model: the library is all there is.
 */
final class IntakeMotif
{
    /** The wish is about a picture the visitor has: nothing of ours stands in for it. */
    private const OWN = '/kresb|kresl|nakresl|obr[áa]zk|obr[áa]zek|fotk|fotografi|\bfot[oa]\b|\blog[oau]\b|logem|podle (na[šs]|m[ée]|moj)|drawing|\bdrawn\b|sketch|picture|photo|\bimage\b|\blogo\b|dibujo|\bfoto\b|imagen/u';

    /**
     * Motifs of the library by the words that name them (cs, en, es; with and without diacritics): the silhouette for
     * a flat thing, and the plainer outline of the cookie cutters where there is one. The first that fits wins, so the
     * longer names come before the words they hold (a Christmas tree before a tree, a snowman before snow).
     *
     * @var array<string, array{0: string, 1: string, 2?: string}> key → pattern, picture, the cutter's outline
     */
    private const MOTIFS = [
        'angel' => ['/and[ěe]l|and[ěe]l[íi][čc]|\bangel|[áa]ngel/u', 'holidays/angel'],
        'bat' => ['/netop[ýy]r|\bbats?\b|murci[ée]lago/u', 'holidays/bat', 'cookies/cookie-bat'],
        'jack' => ['/vy[řr]ez[áa]van\w* d[ýy]n|jack.?o.?lantern/u', 'holidays/jack-o-lantern', 'cookies/cookie-pumpkin'],
        'pumpkin' => ['/\bd[ýy]n[ěeíi]|pumpkin|calabaza/u', 'holidays/pumpkin', 'cookies/cookie-pumpkin'],
        'ghost' => ['/\bduch[aeůy]?\b|du[šs][íi]k|stra[šs]idl|bub[áa]k|\bghost|fantasma/u', 'colour/happy-ghost', 'cookies/cookie-ghost'],
        'witch' => ['/[čc]arod[ěe]jn|\bwitch|\bbruja/u', 'holidays/witch', 'cookies/cookie-witch-hat'],
        'skull' => ['/\blebk|\bskull|calavera/u', 'holidays/sugar-skull', 'cookies/cookie-skull'],
        'spider' => ['/\bpavou[kc]|\bspider|ara[ñn]a/u', 'cookies/cookie-spider', 'cookies/cookie-spider'],
        'egg' => ['/vaj[íi][čc]k|vejce|kraslic|easter egg|\begg\b|huevo/u', 'holidays/easter-egg'],
        'bunny' => ['/zaj[íi][čc]|zaj[íi]c|kr[áa]l[íi][čc]|\bbunny|\brabbit|conej/u', 'holidays/easter-bunny'],
        'gingerbread' => ['/pern[íi][čc]|pan[áa][čc]\w* z pern|gingerbread|jengibre/u', 'colour/gingerbread-man', 'cookies/cookie-gingerbread-man'],
        'snowman' => ['/sn[ěe]hul[áa]|snowman|mu[ñn]eco de nieve/u', 'colour/snowman', 'cookies/cookie-snowman'],
        'snowflake' => ['/vlo[čc]k|snowflake|copo de nieve/u', 'holidays/snowflake', 'cookies/cookie-snowflake'],
        'xmas_tree' => ['/v[áa]no[čc]n\w* strom|strome[čc]|stromk|christmas tree|[áa]rbol de navidad|arbolito/u', 'holidays/christmas-tree', 'cookies/cookie-tree'],
        'reindeer' => ['/\bsob[aíiy]?\b|sob[íi]k|\breindeer|\breno\b/u', 'holidays/reindeer', 'cookies/cookie-reindeer'],
        'santa' => ['/\bsanta|pap[áa] noel/u', 'cookies/cookie-santa', 'cookies/cookie-santa'],
        'bell' => ['/zvone[čc]|zvonk|\bzvon\b|\bbell\b|campan/u', 'holidays/bell', 'cookies/cookie-bell'],
        'candy' => ['/cukrov\w* h[ůu]l|candy cane|bast[óo]n de caramelo/u', 'holidays/candy-cane', 'cookies/cookie-candy-cane'],
        'gift' => ['/ve tvaru d[áa]rk|d[áa]re[čc]ek|shape of a (gift|present)|forma de regalo/u', 'holidays/gift', 'cookies/cookie-gift'],
        'cake' => ['/ve tvaru dort|shape of a cake|forma de (tarta|pastel)/u', 'holidays/cake'],
        'clover' => ['/[čc]ty[řr]l[íi]st|\bclover|tr[ée]bol/u', 'hearts-stars/four-leaf-clover'],
        'crown' => ['/korun[kau]|\bcrown|\bcorona/u', 'hearts-stars/crown'],
        'moon' => ['/m[ěe]s[íi][čc]|p[ůu]lm[ěe]s|\bmoon|\bluna\b/u', 'hearts-stars/moon', 'cookies/cookie-moon'],
        'sun' => ['/slun[íi][čc]k|\bslunce|\bsun\b|\bsol\b/u', 'hearts-stars/sun'],
        'lightning' => ['/\bblesk|lightning|\brayo\b/u', 'hearts-stars/lightning'],
        'note' => ['/\bnot[auy]\b|houslov|music note|nota musical/u', 'hearts-stars/music-note'],
        'heart' => ['/srd[cí]e|srd[ií][čc]k|\bheart|coraz[óo]n/u', 'hearts-stars/heart', 'cookies/cookie-heart'],
        'star' => ['/hv[ěe]zd|\bstars?\b|estrella/u', 'hearts-stars/star', 'cookies/cookie-star'],
        'paw' => ['/tlapk|tlapa|pack[auy]\b|\bpaw|huella/u', 'animals/paw'],
        'cat' => ['/ko[čc]k|ko[čc]i[čc]k|kot[ěe]|\bcat\b|kitten|\bgato\b|gatito/u', 'animals/cat', 'cookies/cookie-cat'],
        'dog' => ['/\bpes\b|\bpsa\b|pejs|\bps[íi]k|[šs]t[ěe]n|\bdog\b|\bpuppy|\bperro|perrito/u', 'animals/dog'],
        'horse' => ['/\bk[ůu][ňn]|\bkon[ěeí]|kon[íi][čc]|\bhorse|caballo/u', 'animals/horse'],
        'butterfly' => ['/mot[ýy]l|butterfly|mariposa/u', 'animals/butterfly'],
        'owl' => ['/\bsov[auy]\b|sovi[čc]k|\bowl\b|b[úu]ho|lechuza/u', 'animals/owl', 'cookies/cookie-owl'],
        'fox' => ['/li[šs]k|li[šs][áa]k|\bfox\b|\bzorro/u', 'animals/fox'],
        'bear' => ['/medv[ěe]d|m[ée][ďd]|\bbear\b|\boso\b|osito/u', 'animals/bear'],
        'dinosaur' => ['/dinosau|\bdino\b|t-?rex/u', 'animals/dinosaur'],
        'elephant' => ['/\bslon|sl[ůu]n|elephant|elefante/u', 'animals/elephant'],
        'turtle' => ['/[žz]elv|turtle|tortuga/u', 'animals/turtle'],
        'dolphin' => ['/delf[íi]n|dolphin/u', 'animals/dolphin'],
        'deer' => ['/\bjelen|\bsrn|\bdeer\b|ciervo/u', 'animals/deer'],
        'fish' => ['/\bryb[auyk]|\bfish\b|\bpez\b|pescado/u', 'animals/fish'],
        'bird' => ['/\bpt[áa][čc]|\bbird|p[áa]jaro/u', 'animals/bird'],
        'rose' => ['/\br[ůu][žz][eií]\b|r[ůu][žz]i[čc]k|\brose\b|\brosa roja|una rosa/u', 'nature/rose'],
        'tulip' => ['/tulip[áa]n|\btulip/u', 'nature/tulip'],
        'flower' => ['/kv[ěe]tin|kyti[čc]|kv[ěe]t\b|\bflower|\bflor\b/u', 'nature/flower'],
        'tree' => ['/\bstrom\b|\bstromu\b|\btree\b|[áa]rbol/u', 'nature/tree'],
        'leaf' => ['/\blist\b|l[íi]ste[čc]|\bleaf\b|\bhoja\b/u', 'nature/leaf'],
        'cloud' => ['/mr[áa][čc]|obl[áa][čc]|\bcloud|\bnube/u', 'nature/cloud'],
        'fire' => ['/\bohe[ňn]|plam[íe]n|\bflame|\bfire\b|\bfuego|llama\b/u', 'nature/fire'],
        'football' => ['/fotbal|\bfootball|\bsoccer|f[úu]tbol/u', 'sport/football-player'],
        'trophy' => ['/\bpoh[áa]r|trophy|trofeo/u', 'sport/trophy'],
        'bicycle' => ['/j[íi]zdn\w* kol|cykl|bicycle|\bbike\b|bicicleta/u', 'sport/bicycle'],
        'rocket' => ['/raket[auy]\b|\brocket|cohete/u', 'transport/rocket'],
        'airplane' => ['/letadl|letad[ée]l|airplane|aeroplane|\bplane\b|avi[óo]n/u', 'transport/airplane'],
        'tractor' => ['/traktor|tractor/u', 'transport/tractor'],
        'train' => ['/\bvlak|vl[áa][čc]|ma[šs]ink|\btrain\b|\btren\b/u', 'transport/train'],
        'car' => ['/\baut[oa]\b|aut[íi][čc]k|\bcar\b|\bcoche|\bcarro\b/u', 'transport/car'],
        'anchor' => ['/\bkotv|\banchor|\bancla/u', 'transport/anchor'],
        'guitar' => ['/kytar|guitar/u', 'jobs/guitar'],
    ];

    /**
     * The library picture the words name, for a tool: `ref` (lib:<category>/<slug>) and `key`; null when the words
     * speak of the visitor's own picture or name nothing the library has.
     *
     * @return array{key: string, ref: string}|null
     */
    public static function fromWords(?string $text, string $tool = ''): ?array
    {
        $t = mb_strtolower(trim((string) $text));
        if ($t === '' || self::own($t)) {
            return null;
        }
        foreach (self::MOTIFS as $key => $motif) {
            if (preg_match($motif[0], $t)) {
                $ref = 'lib:'.($tool === 'cutter' ? ($motif[2] ?? $motif[1]) : $motif[1]);

                return Artwork::path($ref) !== null ? ['key' => $key, 'ref' => $ref] : null;
            }
        }

        return null;
    }

    /** True when the wish speaks of a picture the visitor has (a drawing, a photo, a logo): only they can give it. */
    public static function own(?string $text): bool
    {
        return (bool) preg_match(self::OWN, mb_strtolower((string) $text));
    }

    /** The slug of a library picture ("bat" of lib:holidays/bat), for its name in the visitor's language (artwork.items.<slug>). */
    public static function slug(string $ref): string
    {
        return (string) substr($ref, (int) strrpos($ref, '/') + 1);
    }

    /** @return list<string> every picture the table names: all of them have to be in the library (tested) */
    public static function refs(): array
    {
        $out = [];
        foreach (self::MOTIFS as $motif) {
            $out[] = 'lib:'.$motif[1];
            if (isset($motif[2])) {
                $out[] = 'lib:'.$motif[2];
            }
        }

        return array_values(array_unique($out));
    }
}
