import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createMemoryRouter, RouterProvider } from 'react-router-dom';
import Root from '../../pages/Root/Root.jsx';
import HomePage from '../../pages/HomePage.jsx';
import CreateArticlePage from '../../pages/CreateArticlePage.jsx';
import * as KeycloakContext from '../../KeycloakProvider.jsx';

vi.mock('../../KeycloakProvider', () => ({
    useKeycloak: vi.fn()
}));

const mockArticles = [
    { id: 1, title: 'Existing Article', price: 100, mainPhotoUrl: '/img.jpg', owner: { fullName: 'User' } }
];

const routes = [
    {
        path: '/',
        element: <Root />,
        children: [
            { path: '/', element: <HomePage /> },
            { path: '/sell', element: <CreateArticlePage /> }
        ]
    }
];

describe('Article Creation Flow Integration', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        global.fetch = vi.fn();

        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {
                authenticated: true,
                token: 'test-jwt-token',
                tokenParsed: { preferred_username: 'Creator' },
            },
            initialized: true
        });
    });

    it('completes full article creation flow', async () => {
        const user = userEvent.setup();

        global.fetch.mockImplementation((url) => {
            if (url.includes('/api/articles') && !url.includes('POST')) {
                return Promise.resolve({ ok: true, json: async () => ({ member: mockArticles }) });
            }
            if (url.includes('/api/media/upload')) {
                return Promise.resolve({ ok: true, json: async () => ({ url: '/uploads/image.jpg' }) });
            }
            if (url.includes('/api/articles') && url.includes('POST')) {
                return Promise.resolve({ ok: true, json: async () => ({ id: 123 }) });
            }
            return Promise.resolve({ ok: true, json: async () => ({ member: [] }) });
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        const sellLink = await screen.findByRole('link', { name: /vendre/i });
        await user.click(sellLink);

        const titleInput = await screen.findByLabelText(/Titre/i);
        await user.type(titleInput, 'Jordan 1 Chicago');
        await user.type(screen.getByLabelText(/Prix/i), '250');

        const file = new File(['img'], 'sneaker.png', { type: 'image/png' });
        const fileInput = document.querySelector('input[type="file"]');
        await user.upload(fileInput, file);

        const submitBtn = screen.getByRole('button', { name: /Publier l'annonce/i });
        await user.click(submitBtn);

        expect(await screen.findByText(/Article publié !/i)).toBeInTheDocument();

        await waitFor(() => {
            expect(router.state.location.pathname).toBe('/');
        }, { timeout: 3000 });
    });

    it('disables submit button while submitting', async () => {
        const user = userEvent.setup();

        let resolveUpload;
        const uploadPromise = new Promise(resolve => { resolveUpload = resolve; });

        global.fetch.mockImplementation((url) => {
            if (url.includes('/api/media/upload')) return uploadPromise;
            return Promise.resolve({ ok: true, json: async () => ({}) });
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/sell'] });
        render(<RouterProvider router={router} />);

        await user.type(screen.getByLabelText(/Titre/i), 'Test');
        await user.type(screen.getByLabelText(/Prix/i), '50');

        const file = new File(['img'], 'test.png', { type: 'image/png' });
        await user.upload(document.querySelector('input[type="file"]'), file);

        const submitBtn = screen.getByRole('button', { name: /Publier l'annonce/i });
        await user.click(submitBtn);

        expect(submitBtn).toBeDisabled();
        expect(screen.getByText(/Publication.../i)).toBeInTheDocument();

        resolveUpload({ ok: true, json: async () => ({ url: '/img.jpg' }) });
    });
});