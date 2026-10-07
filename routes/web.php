<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountSecurityController;
use App\Http\Controllers\Admin\CatalogController as AdminCatalogController;
use App\Http\Controllers\Admin\CollectionController as AdminCollectionController;
use App\Http\Controllers\Admin\ContentController as AdminContentController;
use App\Http\Controllers\Admin\EmailController as AdminEmailController;
use App\Http\Controllers\Admin\FarmCatalogController;
use App\Http\Controllers\Admin\FarmOrderController;
use App\Http\Controllers\Admin\FarmTestPhotoController;
use App\Http\Controllers\Admin\FarmTuningController;
use App\Http\Controllers\Admin\MetaController as AdminMetaController;
use App\Http\Controllers\Admin\StatsController as AdminStatsController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Admin\YouTubeController;
use App\Http\Controllers\Api\AdviceController;
use App\Http\Controllers\Api\ArtworkController;
use App\Http\Controllers\Api\CalculationController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\GenerationController;
use App\Http\Controllers\Api\InquiryController as ApiInquiryController;
use App\Http\Controllers\Api\ModelFileController;
use App\Http\Controllers\Api\ModelPreviewController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\SeenController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\ToolsApiController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\Auth\VerificationController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\CollectionPageController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\Designer\BulkUploadController as DesignerBulkController;
use App\Http\Controllers\Designer\CardController as DesignerCardController;
use App\Http\Controllers\Designer\ImportController as DesignerImportController;
use App\Http\Controllers\Designer\ProfileController as DesignerProfileController;
use App\Http\Controllers\Designer\VerificationController as DesignerVerificationController;
use App\Http\Controllers\DesignerPageController;
use App\Http\Controllers\Farm\CreditController;
use App\Http\Controllers\Farm\OrderController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\InspirationController;
use App\Http\Controllers\LocaleRedirectController;
use App\Http\Controllers\ModelCatalogController;
use App\Http\Controllers\OgController;
use App\Http\Controllers\PageController;
use App\Http\Controllers\Printer\InquiryController as PrinterInquiryController;
use App\Http\Controllers\Printer\PrinterController;
use App\Http\Controllers\Printer\QuoteController;
use App\Http\Controllers\PrinterPageController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\SocialVideoController;
use App\Http\Controllers\ToolsController;
use App\Support\Locales;
use Illuminate\Support\Facades\Route;

// ── Pages ────────────────────────────────────────────────────────────────────
// Everything a person opens in a browser exists in every language: Czech without a prefix, /en/… and /es/…
// (App\Support\Locales). The group below is registered twice; route() picks the twin of the language being rendered.
// Outside stay: /api/*, /admin/*, webhooks, OAuth and files of an order.
$pages = function () {
    // ── Public: the one screen ───────────────────────────────────────────────────
    Route::get('/', [CalculatorController::class, 'index'])->name('home');
    Route::get('/c/{calculation}', [CalculatorController::class, 'share'])->name('calc.share');

    // Marketplace (config/features.php): public printer pages, quotes, customer inquiries — 404 while switched off
    Route::middleware('feature:marketplace')->group(function () {
        Route::get('/printers/id/{id}', [PrinterPageController::class, 'byId'])->whereNumber('id')->name('printers.by_id');
        Route::get('/printers/{printerProfile:slug}', [PrinterPageController::class, 'show'])->name('printers.show');
        Route::get('/q/{quote}', [QuoteController::class, 'publicShow'])->name('quote.public');
        Route::get('/q/{quote}/pdf', [QuoteController::class, 'publicPdf'])->name('quote.public.pdf');
        Route::post('/q/{quote}/accept', [QuoteController::class, 'accept'])->name('quote.accept');
        Route::post('/q/{quote}/decline', [QuoteController::class, 'decline'])->middleware('throttle:20,1')->name('quote.decline');
        Route::post('/q/{quote}/change', [QuoteController::class, 'requestChange'])->middleware('throttle:10,1')->name('quote.change');
        Route::get('/i/{inquiry}', [InquiryController::class, 'show'])->name('inquiry.show');
        Route::get('/i/{inquiry}/verify/{code}', [InquiryController::class, 'verify'])->name('inquiry.verify');
        Route::post('/i/{inquiry}/accept/{quote}', [InquiryController::class, 'accept'])->name('inquiry.accept');
        Route::post('/i/{inquiry}/done', [InquiryController::class, 'done'])->name('inquiry.done');
        Route::post('/i/{inquiry}/cancel', [InquiryController::class, 'cancel'])->name('inquiry.cancel');
        Route::post('/i/{inquiry}/rate', [InquiryController::class, 'rate'])->name('inquiry.rate');
    });

    // Designers: the public portfolio (the designer's own pages are under /account/designer below)
    Route::get('/d/{designer}', [DesignerPageController::class, 'show'])->name('designers.show');

    // Models the farm prints (designers' cards with a file)
    // Collections: hand-picked sets of models from both catalogues
    Route::get('/collections', [CollectionPageController::class, 'index'])->name('collections.index');
    Route::get('/collections/{collection}', [CollectionPageController::class, 'show'])->name('collections.show');

    // The blog (old addresses kept) and the static pages
    Route::get('/blog', [BlogController::class, 'index'])->name('blog.index');
    Route::get('/blog/{post}', [BlogController::class, 'show'])->name('blog.show');
    Route::get('/materials', [PageController::class, 'materials'])->name('materials');
    foreach (PageController::PAGES as $page => $path) {
        Route::get('/'.$path, PageController::class)->defaults('page', $page)->name('pages.'.$page);
    }

    Route::get('/models', [ModelCatalogController::class, 'index'])->name('models.index');
    Route::get('/models/{designerModel}', [ModelCatalogController::class, 'show'])->name('models.show');
    // The inspiration catalogue of the old site, at its old addresses: models that live elsewhere
    Route::get('/model', [InspirationController::class, 'index'])->name('catalog.index');
    Route::get('/model/kategorie/{category}', [InspirationController::class, 'index'])->name('catalog.category');
    Route::get('/model/{catalogModel}', [InspirationController::class, 'show'])->name('catalog.show');

    // Tools menu (everything that is not the one main screen)
    Route::get('/tools', [ToolsController::class, 'index'])->name('tools');
    Route::get('/gifts', [ToolsController::class, 'gifts'])->name('tools.gifts');
    Route::get('/tools/figure', [ToolsController::class, 'figure'])->name('tools.figure');
    Route::get('/tools/sign', [ToolsController::class, 'param'])->defaults('kind', 'sign')->name('tools.sign');
    Route::get('/tools/relief', [ToolsController::class, 'relief'])->name('tools.relief');
    Route::get('/tools/spare-part', [ToolsController::class, 'spare'])->middleware('feature:marketplace')->name('tools.spare');
    Route::get('/tools/check', [ToolsController::class, 'check'])->name('tools.check');
    Route::get('/tools/mold', [ToolsController::class, 'mold'])->name('tools.mold');
    Route::get('/tools/repair', [ToolsController::class, 'repair'])->name('tools.repair');
    Route::get('/tools/organizer', [ToolsController::class, 'param'])->defaults('kind', 'organizer')->name('tools.organizer');
    Route::get('/tools/modular-organizer', [ToolsController::class, 'param'])->defaults('kind', 'modular')->name('tools.modular');
    Route::get('/tools/box', [ToolsController::class, 'param'])->defaults('kind', 'box')->name('tools.box');
    Route::get('/tools/phone-stand', [ToolsController::class, 'param'])->defaults('kind', 'phone_stand')->name('tools.phone_stand');
    Route::get('/tools/vase', [ToolsController::class, 'param'])->defaults('kind', 'vase')->name('tools.vase');
    Route::get('/tools/logo', [ToolsController::class, 'param'])->defaults('kind', 'logo')->name('tools.logo');
    Route::get('/tools/stamp', [ToolsController::class, 'param'])->defaults('kind', 'stamp')->name('tools.stamp');
    Route::get('/tools/stencil', [ToolsController::class, 'param'])->defaults('kind', 'stencil')->name('tools.stencil');
    Route::get('/tools/illuminated-sign', [ToolsController::class, 'param'])->defaults('kind', 'lightbox')->name('tools.lightbox');
    Route::get('/tools/qr', [ToolsController::class, 'param'])->defaults('kind', 'qr')->name('tools.qr');
    Route::get('/tools/cable-holder', [ToolsController::class, 'param'])->defaults('kind', 'cable_holder')->name('tools.cable_holder');
    Route::get('/tools/holder', [ToolsController::class, 'param'])->defaults('kind', 'holder')->name('tools.holder');
    Route::get('/tools/cap', [ToolsController::class, 'param'])->defaults('kind', 'cap')->name('tools.cap');
    Route::get('/tools/cookie-cutter', [ToolsController::class, 'param'])->defaults('kind', 'cutter')->name('tools.cutter');
    // a picture or a name in the colours of filaments: one builder, every product its own page
    Route::get('/tools/charm', [ToolsController::class, 'param'])->defaults('kind', 'charm')->name('tools.charm');
    Route::get('/tools/keychain', [ToolsController::class, 'param'])->defaults('kind', 'keychain')->name('tools.keychain');
    Route::get('/tools/earrings', [ToolsController::class, 'param'])->defaults('kind', 'earrings')->name('tools.earrings');
    Route::get('/tools/ornament', [ToolsController::class, 'param'])->defaults('kind', 'ornament')->name('tools.ornament');
    Route::get('/tools/magnet', [ToolsController::class, 'param'])->defaults('kind', 'magnet')->name('tools.magnet');
    Route::get('/tools/coaster', [ToolsController::class, 'param'])->defaults('kind', 'coaster')->name('tools.coaster');
    Route::get('/tools/gingerbread', [ToolsController::class, 'param'])->defaults('kind', 'gingerbread')->name('tools.gingerbread');

    // ── Auth ─────────────────────────────────────────────────────────────────────
    Route::middleware('guest')->group(function () {
        Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
        Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1')->name('login.attempt');
        Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
        Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1')->name('register.store');
        Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
        Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:6,1')->name('password.email');
        Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
        Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
    });
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

    // E-mail verification: the link is the proof, so it works in any browser; "send again" three times an hour
    Route::get('/email/verify/{id}/{hash}', [VerificationController::class, 'verify'])->whereNumber('id')->middleware('throttle:12,1,verify')->name('verification.verify');
    Route::post('/email/verification-notification', [VerificationController::class, 'send'])->middleware(['auth', 'throttle:3,60,verify-send'])->name('verification.send');
    // links from e-mails about the account (new address, deletion without a password)
    Route::get('/account/email/confirm/{token}', [AccountSecurityController::class, 'confirmEmail'])->middleware('throttle:12,1,email-confirm')->name('account.email.confirm');
    Route::get('/account/delete/confirm/{user}', [AccountSecurityController::class, 'confirmDelete'])->whereNumber('user')->name('account.delete.confirm');
    Route::post('/account/delete/confirm/{user}', [AccountSecurityController::class, 'confirmedDelete'])->whereNumber('user')->name('account.delete.confirmed');

    // ── Account (any role) ───────────────────────────────────────────────────────
    Route::middleware('auth')->prefix('account')->name('account')->group(function () {
        Route::get('/', [AccountController::class, 'index']);
        Route::get('/profile', [AccountController::class, 'profile'])->name('.profile');
        Route::post('/profile', [AccountController::class, 'updateProfile'])->name('.profile.update');
        Route::get('/orders', [OrderController::class, 'index'])->name('.orders');
        Route::get('/models', [AccountController::class, 'models'])->name('.models');
        Route::post('/models/{modelFile}/delete', [AccountController::class, 'deleteModel'])->name('.models.delete');
        Route::get('/calculations', [AccountController::class, 'calculations'])->name('.calculations');
        Route::post('/email', [AccountSecurityController::class, 'changeEmail'])->middleware('throttle:5,60,email-change')->name('.email.change');
        Route::post('/email/cancel', [AccountSecurityController::class, 'cancelEmail'])->name('.email.cancel');
        Route::post('/password', [AccountSecurityController::class, 'password'])->middleware('throttle:10,60,password-change')->name('.password');
        Route::post('/logins/{identity}/disconnect', [AccountSecurityController::class, 'disconnect'])->whereNumber('identity')->name('.logins.disconnect');
        Route::post('/delete', [AccountSecurityController::class, 'delete'])->middleware('throttle:5,60,account-delete')->name('.delete');
    });

    // ── Designer profile: portfolio, import from Printables / MakerWorld, files for the farm ──
    Route::post('/account/designer/enable', [DesignerProfileController::class, 'enable'])->middleware(['auth', 'verified.email'])->name('designer.enable');
    Route::middleware(['auth', 'role:designer'])->prefix('account/designer')->name('designer.')->group(function () {
        Route::get('/', [DesignerProfileController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [DesignerProfileController::class, 'edit'])->name('profile');
        Route::post('/profile', [DesignerProfileController::class, 'update'])->name('profile.update');

        Route::get('/verify/{source}', [DesignerVerificationController::class, 'show'])->name('verify');
        Route::post('/verify/{source}', [DesignerVerificationController::class, 'check'])->middleware('throttle:12,10,designer-verify')->name('verify.check');
        Route::get('/import/{source}', [DesignerImportController::class, 'form'])->name('import');
        Route::post('/import/{source}', [DesignerImportController::class, 'start'])->middleware('throttle:10,10,designer-import')->name('import.start');
        Route::get('/imports/{import}', [DesignerImportController::class, 'show'])->whereNumber('import')->name('imports.show');
        Route::get('/imports/{import}/status', [DesignerImportController::class, 'status'])->whereNumber('import')->name('imports.status');

        Route::get('/models/new', [DesignerCardController::class, 'create'])->name('models.create');
        Route::post('/models', [DesignerCardController::class, 'store'])->name('models.store');
        Route::get('/models/{card}', [DesignerCardController::class, 'edit'])->whereNumber('card')->name('models.edit');
        Route::post('/models/{card}', [DesignerCardController::class, 'update'])->whereNumber('card')->name('models.update');
        Route::post('/models/{card}/delete', [DesignerCardController::class, 'destroy'])->whereNumber('card')->name('models.delete');
        Route::post('/models/{card}/file', [DesignerCardController::class, 'file'])->whereNumber('card')->middleware('throttle:30,10,designer-file')->name('models.file');
        Route::post('/models/{card}/file/remove', [DesignerCardController::class, 'removeFile'])->whereNumber('card')->name('models.file.remove');
        Route::post('/models/{card}/images', [DesignerCardController::class, 'addImages'])->whereNumber('card')->name('models.images.add');
        Route::post('/models/{card}/images/{image}/delete', [DesignerCardController::class, 'removeImage'])->whereNumber(['card', 'image'])->name('models.images.delete');
        Route::post('/models/{card}/images/{image}/cover', [DesignerCardController::class, 'coverImage'])->whereNumber(['card', 'image'])->name('models.images.cover');

        Route::get('/upload', [DesignerBulkController::class, 'form'])->name('bulk');
        Route::post('/upload', [DesignerBulkController::class, 'store'])->middleware('throttle:10,10,designer-zip')->name('bulk.store');
        Route::get('/upload/{token}', [DesignerBulkController::class, 'match'])->name('bulk.match');
        Route::post('/upload/{token}', [DesignerBulkController::class, 'confirm'])->name('bulk.confirm');
    });

    // ── Print farm: "Rent a printer" (logged-in users; credit from the payment gateway) ──
    Route::get('/farm/terms', [OrderController::class, 'terms'])->name('farm.terms');
    Route::view('/privacy', 'pages.privacy')->name('privacy');
    Route::middleware('auth')->group(function () {
        Route::get('/farm', [OrderController::class, 'start'])->name('farm.start');
        Route::get('/farm/orders', fn () => redirect()->route('account.orders', [], 301))->name('farm.orders');
        Route::post('/farm/orders', [OrderController::class, 'store'])->middleware(['verified.email', 'throttle:20,1,farm_order'])->name('farm.orders.store');
        Route::get('/farm/orders/{order}', [OrderController::class, 'show'])->name('farm.orders.show');
        Route::get('/farm/orders/{order}/repeat', [OrderController::class, 'repeat'])->name('farm.orders.repeat');
        Route::get('/farm/orders/{order}/status', [OrderController::class, 'status'])->name('farm.orders.status');
        Route::post('/farm/orders/{order}/reslice', [OrderController::class, 'reslice'])->middleware('throttle:20,1,farm_reslice')->name('farm.orders.reslice');
        Route::post('/farm/orders/{order}/quote', [OrderController::class, 'quote'])->middleware('throttle:120,1,farm_quote')->name('farm.orders.quote');
        Route::post('/farm/orders/{order}/pay', [OrderController::class, 'pay'])->middleware(['verified.email', 'throttle:10,1,farm_pay'])->name('farm.orders.pay');
        Route::post('/farm/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('farm.orders.cancel');
        Route::post('/farm/orders/{order}/video-consent', [OrderController::class, 'videoConsent'])->middleware('throttle:10,1,video-consent')->name('farm.orders.video_consent');

        Route::get('/account/credit', [CreditController::class, 'index'])->name('account.credit');
        Route::post('/account/credit', [CreditController::class, 'topUp'])->middleware(['verified.email', 'throttle:10,1,topup'])->name('account.credit.topup');
    });

    // ── Printer tools (role switch "I own a printer") ────────────────────────────
    Route::middleware(['feature:marketplace', 'auth', 'role:printer'])->prefix('printer')->name('printer.')->group(function () {
        Route::get('/', [PrinterController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [PrinterController::class, 'profile'])->name('profile');
        Route::post('/profile', [PrinterController::class, 'updateProfile'])->name('profile.update');
        Route::get('/calculator', [PrinterController::class, 'calculator'])->name('calculator');
        Route::get('/calculator/{calculation}', [PrinterController::class, 'calculator'])->name('calculator.open');

        Route::get('/quotes', [QuoteController::class, 'index'])->name('quotes');
        Route::post('/quotes', [QuoteController::class, 'store'])->name('quotes.store');
        Route::get('/quotes/{quote}', [QuoteController::class, 'edit'])->name('quotes.edit');
        Route::post('/quotes/{quote}', [QuoteController::class, 'update'])->name('quotes.update');
        Route::post('/quotes/{quote}/send', [QuoteController::class, 'send'])->name('quotes.send');
        Route::post('/quotes/{quote}/duplicate', [QuoteController::class, 'duplicate'])->name('quotes.duplicate');
        Route::post('/quotes/{quote}/revoke', [QuoteController::class, 'revoke'])->name('quotes.revoke');
        Route::post('/quotes/{quote}/relink', [QuoteController::class, 'relink'])->name('quotes.relink');
        Route::get('/quotes/{quote}/pdf', [QuoteController::class, 'pdf'])->name('quotes.pdf');

        Route::get('/inquiries', [PrinterInquiryController::class, 'index'])->name('inquiries');
        Route::get('/inquiries/{inquiry}', [PrinterInquiryController::class, 'show'])->name('inquiries.show');
        Route::post('/inquiries/{inquiry}/offer', [PrinterInquiryController::class, 'offer'])->name('inquiries.offer');
        Route::post('/inquiries/{inquiry}/decline', [PrinterInquiryController::class, 'decline'])->name('inquiries.decline');
    });
};

Route::group([], $pages);
Route::prefix('{locale}')->where(['locale' => Locales::pattern()])->name(Locales::NAME_PREFIX)->group($pages);
// Czech has no prefix: /cs/tools is the same page as /tools
Route::get('/cs/{path?}', LocaleRedirectController::class)->where('path', '.*')->name('locale.cs');

// ── Sign-in through Google and Facebook (the callback address is registered there, keep it) ──
// Open to logged-in people too: "link Google" in the profile goes the same way with ?link=1.
Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');

// the old site's list of models lived at /katalog
Route::permanentRedirect('/katalog', '/model');

// ── The Kč / € switch in the header (not a page: one address for every language) ──
Route::post('/currency', CurrencyController::class)->middleware('throttle:30,1,currency')->name('currency');

// ── Pictures for link previews (drawn on demand, one address for every language unless the text differs) ──
Route::get('/og/{type}/{id}.png', [OgController::class, 'show'])->where(['type' => implode('|', OgController::TYPES), 'id' => '[a-z0-9_-]+'])->name('og');
Route::get('/og/{locale}/{type}/{id}.png', [OgController::class, 'showIn'])->where(['locale' => 'en|es', 'type' => implode('|', OgController::TYPES), 'id' => '[a-z0-9_-]+'])->name('og.localized');

// ── For search engines: sitemaps written by `matplace:sitemap`, robots.txt that points to them ──
Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');
Route::get('/sitemap-{name}.xml', [SitemapController::class, 'show'])->where('name', '[a-z0-9-]+')->name('sitemap.file');
Route::get('/robots.txt', [SitemapController::class, 'robots'])->name('robots');

// ── Files of a farm order (not pages: one address for every language) ────────
Route::middleware('auth')->group(function () {
    Route::get('/farm/orders/{order}/model.stl', [OrderController::class, 'model'])->name('farm.orders.model');
    Route::get('/farm/orders/{order}/supports.bin', [OrderController::class, 'supports'])->name('farm.orders.supports');
    Route::get('/farm/orders/{order}/snapshot', [OrderController::class, 'snapshot'])->name('farm.orders.snapshot');
    Route::get('/farm/orders/{order}/timelapse.mp4', [OrderController::class, 'timelapse'])->name('farm.orders.timelapse');
    Route::get('/farm/orders/{order}/short.mp4', [OrderController::class, 'short'])->name('farm.orders.short');
});
// a print video for Instagram to fetch (signed address that expires; App\Domain\Social\VideoSharer)
Route::get('/social/videos/{video}.mp4', [SocialVideoController::class, 'show'])->whereNumber('video')->middleware('signed')->name('social.video');

// ── JSON API used by the calculator ──────────────────────────────────────────
// Every "throttle:N,1" below carries its own prefix: without one Laravel counts all of them on ONE key per visitor,
// so a minute of live previews (90/min) would lock the create button (20/min) with "Too Many Attempts".
Route::prefix('api')->name('api.')->group(function () {
    Route::get('config', [ConfigController::class, 'show'])->name('config');
    Route::post('uploads', [UploadController::class, 'store'])->middleware('throttle:uploads')->name('uploads.store');
    Route::get('files/{modelFile}', [UploadController::class, 'show'])->name('files.show');
    Route::get('files/{modelFile}/model.stl', [ModelFileController::class, 'stl'])->middleware('file:stl')->name('files.stl');
    Route::get('files/{modelFile}/preview.webp', [ModelPreviewController::class, 'show'])->name('files.preview');
    Route::post('files/{modelFile}/preview', [ModelPreviewController::class, 'store'])->middleware('throttle:60,1,preview-store')->name('files.preview.store');
    Route::get('files/{modelFile}/project.3mf', [ModelFileController::class, 'project'])->middleware(['file:project', 'throttle:20,1,project'])->name('files.project');
    Route::post('files/{modelFile}/pedestal', [ModelFileController::class, 'pedestal'])->middleware(['file', 'throttle:20,1,pedestal'])->name('files.pedestal');
    Route::post('files/{modelFile}/mold', [ModelFileController::class, 'mold'])->middleware(['file', 'throttle:20,1,mold'])->name('files.mold');
    Route::post('files/{modelFile}/mold/analysis', [ModelFileController::class, 'moldAnalysis'])->middleware(['file', 'throttle:30,1,moldanalysis'])->name('files.mold.analysis');
    Route::get('files/{modelFile}/mold/{name}', [ModelFileController::class, 'moldCast'])->middleware('file')->where('name', 'cast\\.(stl|bin)')->name('files.mold.cast');
    Route::post('files/{modelFile}/repair', [ModelFileController::class, 'repair'])->middleware(['file', 'throttle:12,1,repair'])->name('files.repair');
    Route::post('files/{modelFile}/advice', [AdviceController::class, 'store'])->middleware(['file', 'throttle:20,1,advice'])->name('files.advice');
    Route::get('advice/{token}', [AdviceController::class, 'show'])->name('advice.show');
    Route::get('printers', [ModelFileController::class, 'printers'])->name('printers');
    Route::get('models/{designerModel}/quote', [ModelCatalogController::class, 'quote'])->middleware('throttle:120,1,model-quote')->name('models.quote');
    Route::post('calculations', [CalculationController::class, 'store'])->middleware('throttle:calculations')->name('calculations.store');
    Route::get('calculations/{calculation}', [CalculationController::class, 'show'])->name('calculations.show');
    // the page's script confirms that a browser really showed the page (our own statistics: people, not robots)
    Route::post('seen', SeenController::class)->middleware('throttle:120,1,seen')->name('seen');
    Route::post('search', [SearchController::class, 'text'])->middleware('throttle:60,1,search')->name('search');
    Route::post('describe', [SearchController::class, 'describe'])->middleware('throttle:10,1,describe')->name('describe');
    Route::post('generate', [GenerationController::class, 'store'])->middleware('throttle:10,1,generate')->name('generate.store');
    Route::get('generate/{generation}', [GenerationController::class, 'show'])->name('generate.show');
    Route::post('generate/{generation}/refine', [GenerationController::class, 'refine'])->middleware('throttle:10,1,refine')->name('generate.refine');
    Route::post('tools/sign', [ToolsApiController::class, 'sign'])->middleware('throttle:20,1,sign')->name('tools.sign');
    Route::post('tools/artwork', [ToolsApiController::class, 'artwork'])->middleware('throttle:30,1,artwork')->name('tools.artwork');
    Route::post('tools/param/preview', [ToolsApiController::class, 'paramPreview'])->middleware('throttle:90,1,preview')->name('tools.param.preview');
    Route::post('tools/param', [ToolsApiController::class, 'paramCreate'])->middleware('throttle:20,1,create')->name('tools.param');
    Route::post('tools/param/zip', [ToolsApiController::class, 'paramZip'])->middleware('throttle:12,1,zip')->name('tools.param.zip');
    // the picture window of the tools: our library of silhouettes (CC0) and the visitor's own uploads
    Route::get('artwork/library', [ArtworkController::class, 'library'])->middleware('throttle:120,1,artlib')->name('artwork.library');
    Route::get('artwork/library/{category}/{slug}.svg', [ArtworkController::class, 'item'])->where(['category' => '[a-z-]+', 'slug' => '[a-z0-9-]+'])->name('artwork.item');
    Route::get('artwork/mine', [ArtworkController::class, 'mine'])->name('artwork.mine');
    Route::get('artwork/file/{id}', [ArtworkController::class, 'file'])->where('id', '[0-9a-f-]{36}')->name('artwork.file');
    Route::get('tools/param/{modelFile}/{part}.stl', [ToolsApiController::class, 'paramPart'])->middleware(['file', 'throttle:30,1,part'])->name('tools.param.part');
    Route::post('tools/relief', [ToolsApiController::class, 'relief'])->middleware('throttle:12,1,relief')->name('tools.relief');
    Route::post('inquiries', [ApiInquiryController::class, 'store'])->middleware(['feature:marketplace', 'throttle:10,1,inquiry'])->name('inquiries.store');
    Route::post('spare-parts', [ApiInquiryController::class, 'spare'])->middleware(['feature:marketplace', 'throttle:6,1,spare'])->name('spare');
    Route::get('threads/{thread}/messages', [ThreadController::class, 'messages'])->name('threads.messages');
    Route::post('threads/{thread}/messages', [ThreadController::class, 'post'])->middleware('throttle:30,1,thread')->name('threads.post');
    Route::get('threads/{thread}/attachments/{message}', [ThreadController::class, 'attachment'])->name('threads.attachment');
});

// ── Admin: farm operation and data ───────────────────────────────────────────
Route::middleware(['auth', 'role:admin'])->prefix('admin/farm')->name('admin.farm.')->group(function () {
    $orders = FarmOrderController::class;
    $catalog = FarmCatalogController::class;

    Route::get('/', [$orders, 'dashboard'])->name('dashboard');
    Route::get('/orders', [$orders, 'index'])->name('orders');
    Route::get('/orders/{order}', [$orders, 'show'])->name('orders.show');
    Route::get('/orders/{order}/print.gcode', [$orders, 'gcode'])->name('orders.gcode');
    Route::get('/orders/{order}/snapshot', [$orders, 'snapshot'])->name('orders.snapshot');
    Route::get('/orders/{order}/timelapse.mp4', [$orders, 'timelapse'])->name('orders.timelapse');
    Route::post('/orders/{order}/approve', [$orders, 'approve'])->name('orders.approve');
    Route::post('/orders/{order}/status', [$orders, 'status'])->name('orders.status');
    Route::post('/orders/{order}/overrides', [$orders, 'overrides'])->name('orders.overrides');
    Route::post('/orders/{order}/actuals', [$orders, 'actuals'])->name('orders.actuals');
    Route::post('/orders/{order}/refund', [$orders, 'refund'])->name('orders.refund');
    Route::post('/orders/{order}/ship', [$orders, 'ship'])->name('orders.ship');
    Route::get('/orders/{order}/label.pdf', [$orders, 'label'])->name('orders.label');
    Route::post('/printers/{printer}/bed', [$orders, 'bed'])->name('printers.bed');
    Route::post('/printers/{printer}/command', [$orders, 'command'])->name('printers.command');
    Route::get('/printers/{printer}/snapshot', [$orders, 'printerSnapshot'])->name('printers.snapshot');

    Route::get('/printers', [$catalog, 'printers'])->name('printers');
    Route::get('/printers/new', [$catalog, 'editPrinter'])->name('printers.new');
    Route::post('/printers/new', [$catalog, 'savePrinter'])->name('printers.create');
    Route::get('/printers/{printer}', [$catalog, 'editPrinter'])->name('printers.edit');
    Route::post('/printers/{printer}', [$catalog, 'savePrinter'])->name('printers.update');
    Route::get('/materials', [$catalog, 'materials'])->name('materials');
    Route::post('/materials/new', [$catalog, 'saveMaterial'])->name('materials.create');
    Route::post('/materials/{material}', [$catalog, 'saveMaterial'])->name('materials.update');
    Route::post('/colors/fill', [$catalog, 'fillColors'])->name('colors.fill');
    Route::post('/colors/import', [$catalog, 'importColors'])->name('colors.import');
    Route::post('/colors/new', [$catalog, 'saveColor'])->name('colors.create');
    Route::post('/colors/{color}', [$catalog, 'saveColor'])->name('colors.update');
    Route::get('/settings', [$catalog, 'settings'])->name('settings');
    Route::post('/settings', [$catalog, 'saveSettings'])->name('settings.save');
    Route::get('/agents', [$catalog, 'agents'])->name('agents');
    Route::post('/agents', [$catalog, 'createAgent'])->name('agents.create');
    Route::post('/agents/{agent}/rotate', [$catalog, 'rotateAgent'])->name('agents.rotate');
    Route::post('/agents/{agent}/revoke', [$catalog, 'revokeAgent'])->name('agents.revoke');
    Route::get('/credit', [$catalog, 'credit'])->name('credit');
    Route::post('/credit', [$catalog, 'adjustCredit'])->name('credit.adjust');
    Route::post('/credit/currency', [$catalog, 'accountCurrency'])->name('credit.currency');

    $tuning = FarmTuningController::class;
    Route::get('/tuning', [$tuning, 'index'])->name('tuning');
    Route::post('/tuning/spool', [$tuning, 'spoolRow'])->name('tuning.spool');
    Route::get('/tuning/{row}', [$tuning, 'edit'])->name('tuning.edit');
    Route::post('/tuning/{row}', [$tuning, 'save'])->name('tuning.save');
    Route::post('/tuning/{row}/test', [$tuning, 'test'])->name('tuning.test');
    Route::post('/tuning/{row}/adopt/{order}', [$tuning, 'adopt'])->name('tuning.adopt');
    Route::post('/tuning/{row}/evaluate/{order}', [$tuning, 'evaluate'])->name('tuning.evaluate');
    Route::post('/tuning/{row}/apply/{order}', [$tuning, 'apply'])->name('tuning.apply');
    Route::post('/tuning/{row}/hide/{order}', [$tuning, 'hide'])->name('tuning.hide');

    $photos = FarmTestPhotoController::class;
    Route::get('/photobox', [$photos, 'box'])->name('photobox');
    Route::post('/orders/{order}/photos', [$photos, 'store'])->name('photos.store');
    Route::get('/orders/{order}/photos/{index}', [$photos, 'show'])->whereNumber('index')->name('photos.show');
    Route::post('/orders/{order}/photos/{index}/delete', [$photos, 'destroy'])->whereNumber('index')->name('photos.destroy');
    Route::post('/orders/{order}/judge', [$photos, 'judge'])->name('photos.judge');
});

// ── Admin: catalogues, collections, content, Meta, statistics, AI, e-mails (step F) ──
Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.stats.funnel'))->name('home');

    $catalog = AdminCatalogController::class;
    Route::get('/catalog', [$catalog, 'index'])->name('catalog.index');
    Route::get('/catalog/search', [$catalog, 'search'])->name('catalog.search');
    Route::post('/catalog/import', [$catalog, 'import'])->name('catalog.import');
    Route::get('/catalog/imports/{import}', [$catalog, 'showImport'])->whereNumber('import')->name('catalog.imports.show');
    Route::get('/catalog/review', [$catalog, 'review'])->name('catalog.review');
    Route::post('/catalog/review', [$catalog, 'resolve'])->name('catalog.resolve');
    Route::post('/catalog/classify', [$catalog, 'classifyBatch'])->name('catalog.classify');
    Route::get('/catalog/cards', [$catalog, 'cards'])->name('catalog.cards');
    Route::post('/catalog/cards/{card:id}', [$catalog, 'updateCard'])->whereNumber('card')->name('catalog.cards.update');
    Route::post('/catalog/cards/{card:id}/reslice', [$catalog, 'reslice'])->whereNumber('card')->name('catalog.cards.reslice');
    Route::get('/catalog/models/{model:id}', [$catalog, 'edit'])->whereNumber('model')->name('catalog.edit');
    Route::post('/catalog/models/{model:id}', [$catalog, 'update'])->whereNumber('model')->name('catalog.update');
    Route::post('/catalog/models/{model:id}/toggle', [$catalog, 'toggle'])->whereNumber('model')->name('catalog.toggle');
    Route::post('/catalog/models/{model:id}/text', [$catalog, 'text'])->whereNumber('model')->middleware('throttle:30,1,admin-ai')->name('catalog.text');
    Route::post('/catalog/models/{model:id}/classify', [$catalog, 'classify'])->whereNumber('model')->middleware('throttle:60,1,admin-ai')->name('catalog.classify.one');

    $collections = AdminCollectionController::class;
    Route::get('/collections', [$collections, 'index'])->name('collections.index');
    Route::post('/collections', [$collections, 'store'])->name('collections.store');
    Route::get('/collections/suggestions', [$collections, 'suggestions'])->name('collections.suggestions');
    Route::post('/collections/suggestions', [$collections, 'suggest'])->middleware('throttle:10,1,admin-ai')->name('collections.suggest');
    Route::post('/collections/from-suggestion', [$collections, 'fromSuggestion'])->name('collections.from_suggestion');
    Route::get('/collections/{collection}', [$collections, 'edit'])->whereNumber('collection')->name('collections.edit');
    Route::post('/collections/{collection}', [$collections, 'update'])->whereNumber('collection')->name('collections.update');
    Route::post('/collections/{collection}/delete', [$collections, 'destroy'])->whereNumber('collection')->name('collections.delete');

    $content = AdminContentController::class;
    Route::get('/content', fn () => redirect()->route('admin.content.posts'))->name('content');
    Route::get('/content/posts', [$content, 'posts'])->name('content.posts');
    Route::get('/content/posts/new', [$content, 'editPost'])->name('content.posts.new');
    Route::post('/content/posts/new', [$content, 'savePost'])->name('content.posts.create');
    Route::post('/content/posts/image', [$content, 'uploadImage'])->name('content.posts.image');
    Route::get('/content/posts/{post}', [$content, 'editPost'])->whereNumber('post')->name('content.posts.edit');
    Route::post('/content/posts/{post}', [$content, 'savePost'])->whereNumber('post')->name('content.posts.update');
    Route::post('/content/posts/{post}/delete', [$content, 'deletePost'])->whereNumber('post')->name('content.posts.delete');
    Route::get('/content/banners', [$content, 'banners'])->name('content.banners');
    Route::post('/content/banners/new', [$content, 'saveBanner'])->name('content.banners.create');
    Route::post('/content/banners/{banner}', [$content, 'saveBanner'])->whereNumber('banner')->name('content.banners.update');
    Route::post('/content/banners/{banner}/delete', [$content, 'deleteBanner'])->whereNumber('banner')->name('content.banners.delete');

    Route::get('/content/meta', [AdminMetaController::class, 'index'])->name('meta.index');
    Route::get('/content/meta/compose', [AdminMetaController::class, 'compose'])->middleware('throttle:30,1,admin-ai')->name('meta.compose');
    Route::post('/content/meta/publish', [AdminMetaController::class, 'publish'])->name('meta.publish');

    Route::get('/stats', [AdminStatsController::class, 'funnel'])->name('stats.funnel');
    Route::get('/stats/search', [AdminStatsController::class, 'search'])->name('stats.search');
    Route::get('/stats/speed', [AdminStatsController::class, 'speed'])->name('stats.speed');
    Route::get('/ai', [AdminStatsController::class, 'ai'])->name('ai.index');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::get('/users/{user}', [AdminUserController::class, 'show'])->whereNumber('user')->name('users.show');
    Route::post('/users/{user}/role', [AdminUserController::class, 'role'])->whereNumber('user')->name('users.role');
    Route::post('/users/{user}/reset-link', [AdminUserController::class, 'resetLink'])->whereNumber('user')->middleware('throttle:10,1,admin-reset')->name('users.reset');
    Route::post('/users/{user}/erase', [AdminUserController::class, 'erase'])->whereNumber('user')->name('users.erase');

    Route::get('/emails', [AdminEmailController::class, 'index'])->name('emails.index');
    Route::post('/emails/write', [AdminEmailController::class, 'write'])->middleware('throttle:30,1,admin-ai')->name('emails.write');
    Route::get('/emails/inbox', [AdminEmailController::class, 'inbox'])->name('emails.inbox');
    Route::get('/emails/inbox/{id}', [AdminEmailController::class, 'inboxShow'])->where('id', '[A-Za-z0-9_-]+')->name('emails.inbox.show');
    Route::post('/emails/inbox/{id}/reply', [AdminEmailController::class, 'reply'])->where('id', '[A-Za-z0-9_-]+')->middleware('throttle:30,1,admin-ai')->name('emails.inbox.reply');
    Route::post('/emails/inbox/{id}/read', [AdminEmailController::class, 'markRead'])->where('id', '[A-Za-z0-9_-]+')->name('emails.inbox.read');
    Route::get('/emails/{email}', [AdminEmailController::class, 'show'])->whereNumber('email')->name('emails.show');
    Route::post('/emails/{email}', [AdminEmailController::class, 'update'])->whereNumber('email')->name('emails.update');
});

// ── Admin: print videos on the YouTube channel (the callback URI is registered in Google Cloud, keep it) ──
Route::middleware(['auth', 'role:admin'])->prefix('admin/youtube')->name('admin.youtube.')->group(function () {
    $yt = YouTubeController::class;
    Route::get('/', [$yt, 'index'])->name('index');
    Route::post('/connect', [$yt, 'connect'])->name('connect');
    Route::get('/callback', [$yt, 'callback'])->name('callback');
    Route::post('/disconnect', [$yt, 'disconnect'])->name('disconnect');
    Route::post('/orders/{order}/queue', [$yt, 'queue'])->name('queue');
    Route::get('/orders/{order}/video.mp4', [$yt, 'file'])->name('file');
    Route::post('/videos/{video}/publish', [$yt, 'publish'])->name('publish');
    Route::post('/videos/{video}/reject', [$yt, 'reject'])->name('reject');
    Route::post('/videos/{video}/retry', [$yt, 'retry'])->name('retry');
    Route::post('/videos/{video}/replace', [$yt, 'replace'])->name('replace');
    Route::post('/videos/{video}/share/{platform}/retry', [$yt, 'shareRetry'])->name('share_retry');
    Route::post('/stats', [$yt, 'stats'])->name('stats');
    Route::post('/showcase', [$yt, 'showcase'])->name('showcase');
});
