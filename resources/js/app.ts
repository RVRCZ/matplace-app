import { boot } from './calc/calculator';
import { bootSearch } from './calc/search';
import { bootChat } from './calc/chat';
import { bootMiniViewers } from './calc/mini';
import { bootFigure } from './calc/figure';
import { bootSign } from './calc/sign';
import { bootRelief } from './calc/relief';
import { bootParam } from './calc/param';
import { bootSpare } from './calc/spare';
import { bootCheckPage } from './calc/check';
import { bootFarmCta, bootFarmStart, bootFarmOrder, bootFarmAdminViewer } from './calc/farm';

document.addEventListener('DOMContentLoaded', () => { boot(); bootSearch(); bootChat(); bootMiniViewers(); bootFigure(); bootSign(); bootRelief(); bootParam(); bootSpare(); bootCheckPage(); bootFarmCta(); bootFarmStart(); bootFarmOrder(); bootFarmAdminViewer(); });
