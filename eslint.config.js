import js from '@eslint/js';
import reactHooks from 'eslint-plugin-react-hooks';
import reactRefresh from 'eslint-plugin-react-refresh';
import tseslint from 'typescript-eslint';

export default tseslint.config(
    { ignores: ['vendor/**', 'node_modules/**', 'public/**', 'bootstrap/**', 'storage/**'] },
    js.configs.recommended,
    ...tseslint.configs.recommended,
    reactHooks.configs.flat['recommended-latest'],
    reactRefresh.configs.vite,
    {
        files: ['resources/js/**/*.{ts,tsx}'],
        rules: {
            '@typescript-eslint/no-explicit-any': 'error',
        },
    },
);
