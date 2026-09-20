import { boot } from './calc/calculator';
import { bootSearch } from './calc/search';
import { bootChat } from './calc/chat';
import { bootMiniViewers } from './calc/mini';
import { bootFigure } from './calc/figure';
import { bootSign } from './calc/sign';

document.addEventListener('DOMContentLoaded', () => { boot(); bootSearch(); bootChat(); bootMiniViewers(); bootFigure(); bootSign(); });
