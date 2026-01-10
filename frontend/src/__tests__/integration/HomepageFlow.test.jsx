import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createMemoryRouter, RouterProvider } from 'react-router-dom';
import Root from '../../pages/Root/Root.jsx';
import HomePage from '../../pages/HomePage.jsx';
import * as KeycloakContext from '../../KeycloakProvider.jsx';

vi.mock('../../KeycloakProvider', () => ({
    useKeycloak: vi.fn()
}));

global.fetch = vi.fn();

const mockArticlesPage1 = [
    { id: 1, title: 'Nike Air Max', price: 150, mainPhotoUrl: '/nike.jpg', owner: { fullName: 'Seller1' } },
    { id: 2, title: 'Adidas Yeezy', price: 300, mainPhotoUrl: '/yeezy.jpg', owner: { fullName: 'Seller2' } }
];

const mockArticlesPage2 = [
    { id: 3, title: 'Jordan 4', price: 250, mainPhotoUrl: '/jordan.jpg', owner: { fullName: 'Seller3' } }
];

const mockSearchResults = [
    { id: 1, title: 'Nike Air Max', price: 150, mainPhotoUrl: '/nike.jpg', owner: { fullName: 'Seller1' } }
];

const routes = [
    {
        path: '/',
        element: <Root />,
        children: [
            { path: '/', element: <HomePage /> }
        ]
    }
];

describe('Homepage Flow Integration', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { login: vi.fn() },
            initialized: true,
            authenticated: false
        });
    });

    it('loads and displays articles with carousel and grid', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ member: mockArticlesPage1, view: { next: '/api?page=2' } }) });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Nike Air Max')).toBeInTheDocument();
            expect(screen.getByText('Adidas Yeezy')).toBeInTheDocument();
        }, { timeout: 2000 });

        expect(screen.getByText('150 €')).toBeInTheDocument();
        expect(screen.getByText('300 €')).toBeInTheDocument();
    });

    it('handles search and filters articles', async () => {
        const user = userEvent.setup();

        global.fetch.mockImplementation((url) => {
            if (url.includes('title=Nike')) {
                return Promise.resolve({ json: async () => ({ member: mockSearchResults }) });
            }
            return Promise.resolve({ json: async () => ({ member: mockArticlesPage1 }) });
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Nike Air Max')).toBeInTheDocument();
        }, { timeout: 2000 });

        const searchInput = screen.getByPlaceholderText(/Rechercher/i);
        await user.type(searchInput, 'Nike');

        await waitFor(() => {
            expect(global.fetch).toHaveBeenCalledWith(
                expect.stringContaining('title=Nike'),
                expect.anything()
            );
        }, { timeout: 1000 });
    });

    it('handles pagination through multiple pages', async () => {
        const user = userEvent.setup();

        // Mock returns hasNextPage for page 1
        global.fetch.mockImplementation((url) => {
            if (url.includes('page=2')) {
                return Promise.resolve({ json: async () => ({ member: mockArticlesPage2, view: {} }) });
            }
            return Promise.resolve({ json: async () => ({ member: mockArticlesPage1, view: { next: '/api?page=2' } }) });
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        // Wait for articles to load
        await waitFor(() => {
            expect(screen.getByText('Nike Air Max')).toBeInTheDocument();
        }, { timeout: 2000 });

        await waitFor(() => {
            expect(screen.getByText('Page 1')).toBeInTheDocument();
        });

        const nextBtn = screen.getByText(/Suivant/i);
        expect(nextBtn).not.toBeDisabled();

        await user.click(nextBtn);

        await waitFor(() => {
            expect(screen.getByText('Page 2')).toBeInTheDocument();
        });

        await waitFor(() => {
            expect(screen.getByText('Jordan 4')).toBeInTheDocument();
        });
    });

    it('disables previous button on first page', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ member: mockArticlesPage1, view: { next: '/api?page=2' } }) });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        // Wait for articles to load
        await waitFor(() => {
            expect(screen.getByText('Nike Air Max')).toBeInTheDocument();
        }, { timeout: 2000 });

        await waitFor(() => {
            expect(screen.getByText('Page 1')).toBeInTheDocument();
        });

        const prevBtn = screen.getByText(/Précédent/i);
        expect(prevBtn).toBeDisabled();
    });

    it('shows empty state when no results found', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ member: [] }) });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        // Wait for loading text to disappear first (indicates fetch completed)
        await waitFor(() => {
            expect(screen.queryByText(/Chargement des articles/i)).not.toBeInTheDocument();
        }, { timeout: 2000 });

        // Then check for empty state
        expect(screen.getByText(/Aucun article trouvé/i)).toBeInTheDocument();
    });

    it('navigates carousel slides', async () => {
        const user = userEvent.setup();

        global.fetch.mockResolvedValue({ json: async () => ({ member: mockArticlesPage1 }) });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Nike Air Max')).toBeInTheDocument();
        }, { timeout: 2000 });

        const dots = screen.getAllByRole('button').filter(btn =>
            btn.className.includes('dot')
        );

        if (dots.length > 1) {
            await user.click(dots[1]);
        }
    });

    it('shows sell button only when authenticated', async () => {
        global.fetch.mockResolvedValue({ json: async () => ({ member: mockArticlesPage1 }) });

        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {
                authenticated: true,
                token: 'token',
                tokenParsed: { preferred_username: 'User' },
                logout: vi.fn()
            },
            initialized: true
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByRole('link', { name: /vendre/i })).toBeInTheDocument();
        }, { timeout: 2000 });
    });
});
