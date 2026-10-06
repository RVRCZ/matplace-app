import { boot } from './calc/calculator';
import { bootSearch } from './calc/search';
import { bootChat } from './calc/chat';
import { bootMiniViewers } from './calc/mini';
import { bootToolPage } from './calc/tool_page';
import { bootSpare } from './calc/spare';
import { bootFarmCta, bootFarmStart, bootFarmOrder, bootFarmAdminViewer, bootFarmDashboard } from './calc/farm';
import { bootPhotobox } from './calc/photobox';
import { bootSite } from './site/boot';

document.addEventListener('DOMContentLoaded', () => { boot(); bootSearch(); bootChat(); bootMiniViewers(); bootToolPage(); bootSpare(); bootFarmCta(); bootFarmStart(); bootFarmOrder(); bootFarmAdminViewer(); bootFarmDashboard(); bootPhotobox(); bootSite(); });
