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

global.fetch = vi.fn();

const mockArticles = [
    { id: 1, title: 'Article Test', price: 100, mainPhotoUrl: '/img.jpg', owner: { fullName: 'Test User' } }
];

const routes = [
    {
        path: '/',
        element: <Root />,
        children: [
            { path: '/', element: <HomePage /> },
            { path: '/sell', element: <CreateArticlePage /> },
            { path: '/categories', element: <div>Categories Page</div> }
        ]
    }
];

describe('App Routing Integration', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.fetch.mockResolvedValue({
            json: async () => ({ member: mockArticles })
        });
    });

    it('navigates from homepage to sell page when authenticated', async () => {
        const user = userEvent.setup();

        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {
                authenticated: true,
                token: 'fake-token',
                tokenParsed: { preferred_username: 'TestUser' },
                login: vi.fn(),
                logout: vi.fn()
            },
            initialized: true
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Article Test')).toBeInTheDocument();
        });

        const sellButton = screen.getByRole('link', { name: /vendre/i });
        await user.click(sellButton);

        await waitFor(() => {
            expect(screen.getByText('Créer une annonce')).toBeInTheDocument();
        });
    });

    it('shows homepage with header and articles', async () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { login: vi.fn() },
            initialized: true,
            authenticated: false
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        expect(screen.getByText('Collector')).toBeInTheDocument();
        expect(screen.getByText('Accueil')).toBeInTheDocument();

        await waitFor(() => {
            expect(screen.getByText('Article Test')).toBeInTheDocument();
        });
    });

    it('navigates to categories page', async () => {
        const user = userEvent.setup();

        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { login: vi.fn() },
            initialized: true,
            authenticated: false
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Article Test')).toBeInTheDocument();
        });

        const categoriesLink = screen.getAllByText('Explorer')[0];
        await user.click(categoriesLink);

        await waitFor(() => {
            expect(screen.getByText('Categories Page')).toBeInTheDocument();
        });
    });

    it('redirects unauthenticated user from sell page to homepage', async () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { authenticated: false },
            initialized: true
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/sell'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(router.state.location.pathname).toBe('/');
        });
    });
});
