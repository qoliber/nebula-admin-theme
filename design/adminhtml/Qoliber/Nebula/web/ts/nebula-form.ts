/**
 * Entry bundle for form pages. Registers the `nebulaForm` Alpine component
 * + the Ctrl/Cmd-S shortcut + the save-loader overlay.
 * Must be loaded after nebula-core.js.
 */

import { registerForm } from './form';

registerForm();
