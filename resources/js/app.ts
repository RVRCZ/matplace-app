import { boot } from './calc/calculator';
import { bootSearch } from './calc/search';
import { bootChat } from './calc/chat';
import { bootMiniViewers } from './calc/mini';
import { bootFigure } from './calc/figure';

document.addEventListener('DOMContentLoaded', () => { boot(); bootSearch(); bootChat(); bootMiniViewers(); bootFigure(); });
