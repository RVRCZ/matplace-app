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
import { bootMoldPage } from './calc/mold';
import { bootFarmCta, bootFarmStart, bootFarmOrder, bootFarmAdminViewer, bootFarmDashboard } from './calc/farm';
import { bootPhotobox } from './calc/photobox';

document.addEventListener('DOMContentLoaded', () => { boot(); bootSearch(); bootChat(); bootMiniViewers(); bootFigure(); bootSign(); bootRelief(); bootParam(); bootSpare(); bootCheckPage(); bootMoldPage(); bootFarmCta(); bootFarmStart(); bootFarmOrder(); bootFarmAdminViewer(); bootFarmDashboard(); bootPhotobox(); });
