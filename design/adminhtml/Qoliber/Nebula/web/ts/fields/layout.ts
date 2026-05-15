/**
 * nebulaLayout — Alpine helper for rendering pre-flattened layout trees
 * (used by grid/form renderers that ship the tree via LayoutProcessor).
 */

interface LayoutNodeInput {
    type?: string;
    props?: Record<string, unknown> & { width?: string; colClass?: string };
    children?: LayoutNodeInput[];
}

interface LayoutNodeOutput {
    type: string;
    props: Record<string, unknown> & { colClass?: string };
    children: LayoutNodeOutput[];
}

const widthClassMap: Record<string, string> = {
    '1/2': 'nebula-col--6',
    '1/3': 'nebula-col--4',
    '2/3': 'nebula-col--8',
    '1/4': 'nebula-col--3',
    '3/4': 'nebula-col--9',
    full: 'nebula-col--12',
};

export function registerLayoutComponent(): void {
    document.addEventListener('alpine:init', () => {
        window.Alpine.data('nebulaLayout', (layoutTree?: LayoutNodeInput) => ({
            layout: layoutTree ?? {},
            activeTab: 0,
            getWidthClass(w: string): string {
                return widthClassMap[w] ?? 'nebula-col--12';
            },
            setActiveTab(this: { activeTab: number }, i: number): void {
                this.activeTab = i;
            },
            isActiveTab(this: { activeTab: number }, i: number): boolean {
                return this.activeTab === i;
            },

            renderNode(node: LayoutNodeInput | null | undefined): LayoutNodeOutput | null {
                if (!node || !node.type) return null;
                const props: Record<string, unknown> & { colClass?: string } = { ...(node.props ?? {}) };
                if (node.props && typeof node.props.width === 'string') {
                    props.colClass = widthClassMap[node.props.width] ?? 'nebula-col--12';
                }
                const result: LayoutNodeOutput = {
                    type: node.type,
                    props,
                    children: [],
                };
                if (node.children && node.children.length) {
                    result.children = node.children
                        .map((c) => this.renderNode(c))
                        .filter((x): x is LayoutNodeOutput => x !== null);
                }
                return result;
            },
        }));
    });
}
