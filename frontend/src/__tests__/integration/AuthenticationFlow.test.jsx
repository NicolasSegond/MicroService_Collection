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
    { id: 1, title: 'Test Article', price: 50, mainPhotoUrl: '/test.jpg', owner: { fullName: 'Seller' } }
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

describe('Authentication Flow Integration', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        global.fetch.mockResolvedValue({
            json: async () => ({ member: mockArticles })
        });
    });

    it('shows login button and hides sell button when not authenticated', async () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { login: vi.fn() },
            initialized: true,
            authenticated: false
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        expect(screen.getByText('Connexion')).toBeInTheDocument();
        expect(screen.queryByRole('link', { name: /vendre/i })).not.toBeInTheDocument();
    });

    it('shows user menu and sell button when authenticated', async () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {
                authenticated: true,
                token: 'fake-token',
                tokenParsed: { preferred_username: 'JohnDoe' },
                login: vi.fn(),
                logout: vi.fn()
            },
            initialized: true
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        expect(screen.getByText('JohnDoe')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: /vendre/i })).toBeInTheDocument();
        expect(screen.queryByText('Connexion')).not.toBeInTheDocument();
    });

    it('calls login when clicking login button', async () => {
        const user = userEvent.setup();
        const mockLogin = vi.fn();

        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { login: mockLogin },
            initialized: true,
            authenticated: false
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        const loginBtn = screen.getByText('Connexion');
        await user.click(loginBtn);

        expect(mockLogin).toHaveBeenCalled();
    });

    it('opens user dropdown and calls logout', async () => {
        const user = userEvent.setup();
        const mockLogout = vi.fn();

        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {
                authenticated: true,
                token: 'fake-token',
                tokenParsed: { preferred_username: 'TestUser' },
                logout: mockLogout
            },
            initialized: true
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        const userMenuBtn = screen.getByLabelText('User menu');
        await user.click(userMenuBtn);

        expect(screen.getByText('Mon profil')).toBeInTheDocument();
        expect(screen.getByText('Déconnexion')).toBeInTheDocument();

        await user.click(screen.getByText('Déconnexion'));
        expect(mockLogout).toHaveBeenCalled();
    });

    it('allows authenticated user to access sell page directly', async () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {
                authenticated: true,
                token: 'fake-token',
                tokenParsed: { preferred_username: 'Seller' }
            },
            initialized: true
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/sell'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Créer une annonce')).toBeInTheDocument();
        });
    });

    it('shows loading state before keycloak initialization', () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: null,
            initialized: false,
            authenticated: false
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        expect(screen.getByText('Chargement...')).toBeInTheDocument();
    });
});
