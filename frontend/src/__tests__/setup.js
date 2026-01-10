import { expect, afterEach, beforeAll, vi } from 'vitest';
import { cleanup } from '@testing-library/react';
import * as matchers from '@testing-library/jest-dom/matchers';

expect.extend(matchers);

beforeAll(() => {
    const originalWarn = console.warn;
    const originalError = console.error;

    console.warn = (...args) => {
        if (args[0]?.includes?.('React Router Future Flag Warning')) return;
        originalWarn(...args);
    };

    console.error = (...args) => {
        if (args[0]?.includes?.('not wrapped in act')) return;
        originalError(...args);
    };
});

afterEach(() => {
    cleanup();
});

Object.defineProperty(window, 'matchMedia', {
    writable: true,
    value: vi.fn().mockImplementation(query => ({
        matches: false,
        media: query,
        onchange: null,
        addListener: vi.fn(),
        removeListener: vi.fn(),
        addEventListener: vi.fn(),
        removeEventListener: vi.fn(),
        dispatchEvent: vi.fn(),
    })),
});

