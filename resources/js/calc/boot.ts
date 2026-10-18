/**
 * The calculator and the tools (three.js, most of the code): a chunk of its own, loaded by app.ts once the page is
 * drawn, so the first screen does not wait for it (docs/AH.md §11, Core Web Vitals). Every boot finds its own
 * elements and does nothing on a page without them.
 */
import { boot } from './calculator';
import { bootSearch } from './search';
import { bootChat } from './chat';
import { bootMiniViewers } from './mini';
import { bootToolPage } from './tool_page';
import { bootSpare } from './spare';
import { bootFarmCta, bootFarmStart, bootFarmOrder, bootFarmAdminViewer, bootFarmDashboard } from './farm';
import { bootPhotobox } from './photobox';

export function bootCalc(): void {
    boot(); bootSearch(); bootChat(); bootMiniViewers(); bootToolPage(); bootSpare(); bootFarmCta(); bootFarmStart(); bootFarmOrder(); bootFarmAdminViewer(); bootFarmDashboard(); bootPhotobox();
}
