/**
 * Exposes Chart.js as a browser global (`window.Chart`) the same way the old
 * vendored `chart.min.js` did. Admin dashboard templates read it off window.
 */

import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

type ChartCtor = typeof Chart;

declare global {
    interface Window {
        Chart: ChartCtor;
    }
}

window.Chart = Chart;
