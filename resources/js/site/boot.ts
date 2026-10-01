/** Everything outside the calculator and the tools: account pages, lists of models, delivery. */
import { bootThumbs } from './thumbs';
import { bootPrinterPick } from './printer_pick';
import { bootPickup } from './pickup';

export function bootSite(): void {
    bootThumbs();
    bootPrinterPick();
    bootPickup();
}
