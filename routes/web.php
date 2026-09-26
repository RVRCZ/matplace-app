<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin\FarmCatalogController;
use App\Http\Controllers\Admin\FarmOrderController;
use App\Http\Controllers\Admin\FarmTestPhotoController;
use App\Http\Controllers\Admin\FarmTuningController;
use App\Http\Controllers\Admin\YouTubeController;
use App\Http\Controllers\Api\CalculationController;
use App\Http\Controllers\Api\ConfigController;
use App\Http\Controllers\Api\GenerationController;
use App\Http\Controllers\Api\InquiryController as ApiInquiryController;
use App\Http\Controllers\Api\ModelFileController;
use App\Http\Controllers\Api\SearchController;
use App\Http\Controllers\Api\ThreadController;
use App\Http\Controllers\Api\ToolsApiController;
use App\Http\Controllers\Api\UploadController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Auth\OAuthController;
use App\Http\Controllers\CalculatorController;
use App\Http\Controllers\Farm\CreditController;
use App\Http\Controllers\Farm\OrderController;
use App\Http\Controllers\InquiryController;
use App\Http\Controllers\Printer\InquiryController as PrinterInquiryController;
use App\Http\Controllers\Printer\PrinterController;
use App\Http\Controllers\Printer\QuoteController;
use App\Http\Controllers\PrinterPageController;
use App\Http\Controllers\ToolsController;
use Illuminate\Support\Facades\Route;

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

// Tools menu (everything that is not the one main screen)
Route::get('/tools', [ToolsController::class, 'index'])->name('tools');
Route::get('/tools/figure', [ToolsController::class, 'figure'])->name('tools.figure');
Route::get('/tools/sign', [ToolsController::class, 'param'])->defaults('kind', 'sign')->name('tools.sign');
Route::get('/tools/relief', [ToolsController::class, 'relief'])->name('tools.relief');
Route::get('/tools/spare-part', [ToolsController::class, 'spare'])->middleware('feature:marketplace')->name('tools.spare');
Route::get('/tools/check', [ToolsController::class, 'check'])->name('tools.check');
Route::get('/tools/mold', [ToolsController::class, 'mold'])->name('tools.mold');
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

// ── JSON API used by the calculator ──────────────────────────────────────────
Route::prefix('api')->name('api.')->group(function () {
    Route::get('config', [ConfigController::class, 'show'])->name('config');
    Route::post('uploads', [UploadController::class, 'store'])->middleware('throttle:uploads')->name('uploads.store');
    Route::get('files/{modelFile}', [UploadController::class, 'show'])->name('files.show');
    Route::get('files/{modelFile}/model.stl', [ModelFileController::class, 'stl'])->name('files.stl');
    Route::get('files/{modelFile}/project.3mf', [ModelFileController::class, 'project'])->middleware('throttle:20,1')->name('files.project');
    Route::post('files/{modelFile}/pedestal', [ModelFileController::class, 'pedestal'])->middleware('throttle:20,1')->name('files.pedestal');
    Route::post('files/{modelFile}/mold', [ModelFileController::class, 'mold'])->middleware('throttle:20,1')->name('files.mold');
    Route::get('printers', [ModelFileController::class, 'printers'])->name('printers');
    Route::post('calculations', [CalculationController::class, 'store'])->middleware('throttle:calculations')->name('calculations.store');
    Route::get('calculations/{calculation}', [CalculationController::class, 'show'])->name('calculations.show');
    Route::post('search', [SearchController::class, 'text'])->middleware('throttle:60,1')->name('search');
    Route::post('describe', [SearchController::class, 'describe'])->middleware('throttle:10,1')->name('describe');
    Route::post('generate', [GenerationController::class, 'store'])->middleware('throttle:10,1')->name('generate.store');
    Route::get('generate/{generation}', [GenerationController::class, 'show'])->name('generate.show');
    Route::post('generate/{generation}/refine', [GenerationController::class, 'refine'])->middleware('throttle:10,1')->name('generate.refine');
    Route::post('tools/sign', [ToolsApiController::class, 'sign'])->middleware('throttle:20,1')->name('tools.sign');
    Route::post('tools/artwork', [ToolsApiController::class, 'artwork'])->middleware('throttle:30,1')->name('tools.artwork');
    Route::post('tools/param/preview', [ToolsApiController::class, 'paramPreview'])->middleware('throttle:90,1')->name('tools.param.preview');
    Route::post('tools/param', [ToolsApiController::class, 'paramCreate'])->middleware('throttle:20,1')->name('tools.param');
    Route::get('tools/param/{modelFile}/{part}.stl', [ToolsApiController::class, 'paramPart'])->middleware('throttle:30,1')->name('tools.param.part');
    Route::post('tools/relief', [ToolsApiController::class, 'relief'])->middleware('throttle:12,1')->name('tools.relief');
    Route::post('inquiries', [ApiInquiryController::class, 'store'])->middleware(['feature:marketplace', 'throttle:10,1'])->name('inquiries.store');
    Route::post('spare-parts', [ApiInquiryController::class, 'spare'])->middleware(['feature:marketplace', 'throttle:6,1'])->name('spare');
    Route::get('threads/{thread}/messages', [ThreadController::class, 'messages'])->name('threads.messages');
    Route::post('threads/{thread}/messages', [ThreadController::class, 'post'])->middleware('throttle:30,1')->name('threads.post');
    Route::get('threads/{thread}/attachments/{message}', [ThreadController::class, 'attachment'])->name('threads.attachment');
});

// ── Auth ─────────────────────────────────────────────────────────────────────
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:6,1');
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::get('/forgot-password', [AuthController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [AuthController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:6,1')->name('password.update');
    Route::get('/auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('oauth.redirect');
    Route::get('/auth/{provider}/callback', [OAuthController::class, 'callback'])->name('oauth.callback');
});
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth')->name('logout');

// ── Account (any role) ───────────────────────────────────────────────────────
Route::middleware('auth')->prefix('account')->name('account')->group(function () {
    Route::get('/', [AccountController::class, 'index']);
    Route::get('/profile', [AccountController::class, 'profile'])->name('.profile');
    Route::post('/profile', [AccountController::class, 'updateProfile'])->name('.profile.update');
    Route::post('/roles/{role}/enable', [AccountController::class, 'enableRole'])->middleware('feature:marketplace')->name('.roles.enable');
    Route::post('/roles/{role}/disable', [AccountController::class, 'disableRole'])->middleware('feature:marketplace')->name('.roles.disable');
});

// ── Print farm: "Rent a printer" (logged-in users; credit from the payment gateway) ──
Route::get('/farm/terms', [OrderController::class, 'terms'])->name('farm.terms');
Route::middleware('auth')->group(function () {
    Route::get('/farm', [OrderController::class, 'start'])->name('farm.start');
    Route::get('/farm/orders', [OrderController::class, 'index'])->name('farm.orders');
    Route::post('/farm/orders', [OrderController::class, 'store'])->middleware('throttle:20,1')->name('farm.orders.store');
    Route::get('/farm/orders/{order}', [OrderController::class, 'show'])->name('farm.orders.show');
    Route::get('/farm/orders/{order}/status', [OrderController::class, 'status'])->name('farm.orders.status');
    Route::post('/farm/orders/{order}/reslice', [OrderController::class, 'reslice'])->middleware('throttle:20,1')->name('farm.orders.reslice');
    Route::post('/farm/orders/{order}/pay', [OrderController::class, 'pay'])->middleware('throttle:10,1')->name('farm.orders.pay');
    Route::post('/farm/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('farm.orders.cancel');
    Route::get('/farm/orders/{order}/model.stl', [OrderController::class, 'model'])->name('farm.orders.model');
    Route::get('/farm/orders/{order}/supports.bin', [OrderController::class, 'supports'])->name('farm.orders.supports');
    Route::get('/farm/orders/{order}/snapshot', [OrderController::class, 'snapshot'])->name('farm.orders.snapshot');
    Route::get('/farm/orders/{order}/timelapse.mp4', [OrderController::class, 'timelapse'])->name('farm.orders.timelapse');
    Route::post('/farm/orders/{order}/video-consent', [OrderController::class, 'videoConsent'])->middleware('throttle:10,1')->name('farm.orders.video_consent');

    Route::get('/account/credit', [CreditController::class, 'index'])->name('account.credit');
    Route::post('/account/credit', [CreditController::class, 'topUp'])->middleware('throttle:10,1')->name('account.credit.topup');
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
    Route::post('/orders/{order}/actuals', [$orders, 'actuals'])->name('orders.actuals');
    Route::post('/orders/{order}/refund', [$orders, 'refund'])->name('orders.refund');
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

    $tuning = FarmTuningController::class;
    Route::get('/tuning', [$tuning, 'index'])->name('tuning');
    Route::post('/tuning/spool', [$tuning, 'spoolRow'])->name('tuning.spool');
    Route::get('/tuning/{row}', [$tuning, 'edit'])->name('tuning.edit');
    Route::post('/tuning/{row}', [$tuning, 'save'])->name('tuning.save');
    Route::post('/tuning/{row}/test', [$tuning, 'test'])->name('tuning.test');
    Route::post('/tuning/{row}/adopt/{order}', [$tuning, 'adopt'])->name('tuning.adopt');
    Route::post('/tuning/{row}/evaluate/{order}', [$tuning, 'evaluate'])->name('tuning.evaluate');
    Route::post('/tuning/{row}/apply/{order}', [$tuning, 'apply'])->name('tuning.apply');

    $photos = FarmTestPhotoController::class;
    Route::get('/photobox', [$photos, 'box'])->name('photobox');
    Route::post('/orders/{order}/photos', [$photos, 'store'])->name('photos.store');
    Route::get('/orders/{order}/photos/{index}', [$photos, 'show'])->whereNumber('index')->name('photos.show');
    Route::post('/orders/{order}/photos/{index}/delete', [$photos, 'destroy'])->whereNumber('index')->name('photos.destroy');
    Route::post('/orders/{order}/judge', [$photos, 'judge'])->name('photos.judge');
});

// ── Admin: print videos on the YouTube channel (the callback URI is registered in Google Cloud, keep it) ──
Route::middleware(['auth', 'role:admin'])->prefix('admin/youtube')->name('admin.youtube.')->group(function () {
    $yt = YouTubeController::class;
    Route::get('/', [$yt, 'index'])->name('index');
    Route::post('/connect', [$yt, 'connect'])->name('connect');
    Route::get('/callback', [$yt, 'callback'])->name('callback');
    Route::post('/disconnect', [$yt, 'disconnect'])->name('disconnect');
    Route::post('/orders/{order}/queue', [$yt, 'queue'])->name('queue');
    Route::post('/videos/{video}/publish', [$yt, 'publish'])->name('publish');
    Route::post('/videos/{video}/reject', [$yt, 'reject'])->name('reject');
    Route::post('/videos/{video}/retry', [$yt, 'retry'])->name('retry');
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
