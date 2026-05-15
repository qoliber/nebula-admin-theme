/**
 * Entry bundle for grid pages. Registers the `nebulaGrid` Alpine component.
 * Must be loaded after nebula-core.js (which ships Alpine).
 */

import { registerGrid } from './grid';

registerGrid();
