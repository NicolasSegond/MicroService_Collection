import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { createMemoryRouter, RouterProvider } from 'react-router-dom';
import Root from '../../pages/Root/Root.jsx';
import HomePage from '../../pages/HomePage.jsx';
import ArticleDetailPage from '../../pages/ArticleDetailPage.jsx';
import * as KeycloakContext from '../../KeycloakProvider.jsx';

vi.mock('../../KeycloakProvider', () => ({
    useKeycloak: vi.fn()
}));

global.fetch = vi.fn();

const mockArticles = [
    { id: 1, title: 'Nike Air Max', price: 150, mainPhotoUrl: '/nike.jpg', owner: { fullName: 'Seller1' } },
    { id: 2, title: 'Adidas Yeezy', price: 300, mainPhotoUrl: '/yeezy.jpg', owner: { fullName: 'Seller2' } }
];

const mockArticleDetail = {
    id: 1,
    title: 'Nike Air Max',
    description: 'Sneakers en parfait état',
    price: 150,
    mainPhotoUrl: '/nike.jpg',
    createdAt: '2024-01-10T12:00:00Z',
    owner: { fullName: 'Seller1' }
};

const routes = [
    {
        path: '/',
        element: <Root />,
        children: [
            { path: '/', element: <HomePage /> },
            { path: '/product/:productId', element: <ArticleDetailPage /> }
        ]
    }
];

describe('Article Detail Flow Integration', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { authenticated: false, login: vi.fn() },
            initialized: true
        });
    });

    it('navigates from homepage to article detail page when clicking an article', async () => {
        const user = userEvent.setup();

        global.fetch.mockImplementation((url) => {
            if (url.includes('/api/articles/1')) {
                return Promise.resolve({
                    ok: true,
                    json: async () => mockArticleDetail
                });
            }
            return Promise.resolve({
                json: async () => ({ member: mockArticles })
            });
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/'] });
        render(<RouterProvider router={router} />);

        // Wait for article grid to finish loading
        await waitFor(() => {
            expect(screen.queryByText('Chargement des articles...')).not.toBeInTheDocument();
        }, { timeout: 2000 });

        // Find and click on the first article card link
        const articleLinks = document.querySelectorAll('.article-card-link');
        expect(articleLinks.length).toBeGreaterThan(0);

        await user.click(articleLinks[0]);

        // Wait for article detail page to load
        await waitFor(() => {
            expect(screen.getByText('Sneakers en parfait état')).toBeInTheDocument();
        });

        // Verify we're on the detail page
        expect(screen.getByText('150 €')).toBeInTheDocument();
        expect(screen.getByText('Seller1')).toBeInTheDocument();
        expect(screen.getByText('Contacter le vendeur')).toBeInTheDocument();
    });

    it('shows article details with all information', async () => {
        global.fetch.mockResolvedValue({
            ok: true,
            json: async () => mockArticleDetail
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/product/1'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Nike Air Max')).toBeInTheDocument();
        });

        // Check all sections are present
        expect(screen.getByText('Description')).toBeInTheDocument();
        expect(screen.getByText('Sneakers en parfait état')).toBeInTheDocument();
        expect(screen.getByText('Partager')).toBeInTheDocument();
    });

    it('handles 404 error gracefully', async () => {
        global.fetch.mockResolvedValue({
            ok: false,
            status: 404
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/product/999'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Article non trouvé')).toBeInTheDocument();
        });

        expect(screen.getByText('Retour à l\'accueil')).toBeInTheDocument();
    });

    it('allows returning to homepage from article detail', async () => {
        const user = userEvent.setup();

        global.fetch.mockImplementation((url) => {
            if (url.includes('/api/articles/1')) {
                return Promise.resolve({
                    ok: true,
                    json: async () => mockArticleDetail
                });
            }
            return Promise.resolve({
                json: async () => ({ member: mockArticles })
            });
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/', '/product/1'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Sneakers en parfait état')).toBeInTheDocument();
        });

        // Click back button
        const backBtn = screen.getByText('Retour');
        await user.click(backBtn);

        // Should navigate back
        await waitFor(() => {
            expect(router.state.location.pathname).toBe('/');
        });
    });

    it('prompts login when contacting seller while not authenticated', async () => {
        const mockLogin = vi.fn();
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { authenticated: false, login: mockLogin },
            initialized: true
        });

        const user = userEvent.setup();

        global.fetch.mockResolvedValue({
            ok: true,
            json: async () => mockArticleDetail
        });

        const router = createMemoryRouter(routes, { initialEntries: ['/product/1'] });
        render(<RouterProvider router={router} />);

        await waitFor(() => {
            expect(screen.getByText('Nike Air Max')).toBeInTheDocument();
        });

        const contactBtn = screen.getByText('Contacter le vendeur');
        await user.click(contactBtn);

        expect(mockLogin).toHaveBeenCalled();
    });
});
