/** Everything outside the calculator and the tools: account pages, lists of models, delivery. */
import { bootThumbs } from './thumbs';
import { bootPrinterPick } from './printer_pick';
import { bootPickup } from './pickup';
import { bootDesigner } from './designer';
import { bootModelPage } from './model_page';
import { bootMeasure } from './measure';
import { bootMenu } from './menu';
import { bootBar } from './bar';
import { mountActionBars } from './sticky';
import { bootFilamentShop } from './filament_shop';
import { bootIntake } from './intake';
import { bootClipRecorders } from './clip_recorder';

export function bootSite(): void {
    bootThumbs();
    bootPrinterPick();
    bootPickup();
    bootDesigner();
    bootModelPage();
    bootMeasure();
    bootMenu();
    bootBar();
    mountActionBars();   // the bars fixed to the bottom with the price and the main action (docs/AN.md §18)
    bootFilamentShop();  // admin: the filament card from an e-shop link (docs/AL.md §21)
    bootIntake();        // the home page's router: a photo, a file or words → a tool, a person or the calculator (docs/AN.md §22)
    bootClipRecorders(); // admin: five supplementary clips for a print Short (docs/AL.md §13.9)
}
