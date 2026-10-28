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
}
