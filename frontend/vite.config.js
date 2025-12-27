import { defineConfig } from 'vite'
import react from '@vitejs/plugin-react'

export default defineConfig({
    plugins: [react()],
    server: {
        host: '0.0.0.0',
        port: 3000,
        strictPort: true,
        watch: {
            usePolling: true,
        },
    },
    test: {
        globals: true,
        environment: 'jsdom',
        setupFiles: './src/__tests__/setup.js',
        css: true,
        coverage: {
            provider: 'v8',
            reporter: ['text', 'lcov'],
            include: ['src/**/*.{js,jsx,ts,tsx}'],
            exclude: [
                'src/main.jsx',
                'src/index.css',
                'src/pages/Root/Root.jsx',
                'node_modules/**',
                'src/__tests__/**',
                'src/__mocks__/**',
            ],
        },
    },
})
