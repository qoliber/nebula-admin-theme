/**
 * Nebula System Config dependency wiring.
 *
 * Replaces Magento's RequireJS-wrapped FormElementDependenceController boot
 * (which never runs because Nebula strips RequireJS from the admin layout).
 * Reads a JSON payload emitted by Qoliber\Nebula\Block\Widget\Form\Element\Dependence
 * and wires DOM listeners on each source field, toggling row visibility AND
 * disabling/enabling all controls in the row so hidden fields don't post.
 */

interface DependsRule {
    from: string;
    values: string[];
    negative?: boolean;
}

type DependsMap = Record<string, DependsRule[]>;

const PAYLOAD_ID = 'nebula-config-depends-data';

function readSourceValue(src: HTMLInputElement | HTMLSelectElement | HTMLElement): string {
    if (src instanceof HTMLInputElement) {
        if (src.type === 'checkbox' || src.type === 'radio') {
            return src.checked ? '1' : '0';
        }
        return src.value;
    }
    if (src instanceof HTMLSelectElement) {
        return src.value;
    }
    return (src as HTMLInputElement).value ?? '';
}

function setRowState(row: HTMLElement, visible: boolean): void {
    row.style.display = visible ? '' : 'none';
    row.querySelectorAll<HTMLInputElement | HTMLSelectElement | HTMLTextAreaElement>(
        'input, select, textarea',
    ).forEach((field) => {
        if (field.classList.contains('nebula-inherit-checkbox')) {
            return;
        }
        field.disabled = !visible;
        if (!visible) {
            field.classList.add('ignore-validate');
        } else {
            field.classList.remove('ignore-validate');
        }
    });
}

function wireDepends(map: DependsMap): void {
    Object.keys(map).forEach((toId) => {
        const row = document.getElementById(`row_${toId}`);
        if (!row) {
            console.warn(`[nebula] depends: missing row_${toId}`);
            return;
        }
        const rules = map[toId] ?? [];

        const evaluate = (): void => {
            let visible = true;
            for (const rule of rules) {
                const src = document.getElementById(rule.from);
                if (!src) {
                    visible = false;
                    break;
                }
                const value = readSourceValue(src);
                let match = rule.values.indexOf(String(value)) !== -1;
                if (rule.negative) match = !match;
                if (!match) {
                    visible = false;
                    break;
                }
            }
            setRowState(row, visible);
        };

        rules.forEach((rule) => {
            const src = document.getElementById(rule.from);
            if (src) {
                src.addEventListener('change', evaluate);
                src.addEventListener('input', evaluate);
            }
        });

        evaluate();
    });
}

export function bootConfigDepends(): void {
    const node = document.getElementById(PAYLOAD_ID);
    if (!node) return;

    let map: DependsMap;
    try {
        map = JSON.parse(node.textContent ?? '{}') as DependsMap;
    } catch (e) {
        console.error('[nebula] depends: invalid payload', e);
        return;
    }

    const ruleCount = Object.values(map).reduce((sum, rules) => sum + rules.length, 0);
    console.debug(`[nebula] depends boot: ${Object.keys(map).length} dependents, ${ruleCount} rules`);

    wireDepends(map);
}
