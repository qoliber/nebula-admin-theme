import { defineConfig } from 'vitest/config';

export default defineConfig({
    test: {
        globals: true,
        environment: 'happy-dom',
        include: ['ts/__tests__/**/*.test.ts'],
        coverage: {
            provider: 'v8',
            reporter: ['text', 'html'],
            include: ['ts/**/*.ts'],
            exclude: [
                'ts/__tests__/**',
                'ts/types.ts',
                'ts/nebula-core.ts',
                'ts/nebula-grid.ts',
                'ts/nebula-form.ts',
                'ts/pages/**',
            ],
        },
    },
});
