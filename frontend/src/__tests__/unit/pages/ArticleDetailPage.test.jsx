import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import ArticleDetailPage from '../../../pages/ArticleDetailPage.jsx';
import * as KeycloakContext from '../../../KeycloakProvider.jsx';

vi.mock('../../../KeycloakProvider', () => ({
    useKeycloak: vi.fn()
}));

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    };
});

global.fetch = vi.fn();

const mockArticle = {
    id: 1,
    title: 'Nike Air Max 90',
    description: 'Sneakers en excellent état, portées quelques fois seulement.',
    price: 150,
    mainPhotoUrl: '/uploads/nike.jpg',
    createdAt: '2024-01-15T10:30:00Z',
    owner: { fullName: 'Jean Dupont' }
};

const renderWithRouter = (productId = '1') => {
    return render(
        <MemoryRouter initialEntries={[`/product/${productId}`]}>
            <Routes>
                <Route path="/product/:productId" element={<ArticleDetailPage />} />
            </Routes>
        </MemoryRouter>
    );
};

describe('ArticleDetailPage', () => {
    beforeEach(() => {
        vi.clearAllMocks();

        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { authenticated: false, login: vi.fn() },
            initialized: true
        });
    });

    it('affiche le loader pendant le chargement', () => {
        global.fetch.mockImplementation(() => new Promise(() => {}));

        renderWithRouter();

        expect(screen.getByText(/Chargement de l'article/i)).toBeInTheDocument();
    });

    it('affiche les détails de l\'article après chargement', async () => {
        global.fetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockArticle
        });

        renderWithRouter();

        await waitFor(() => {
            expect(screen.getByText('Nike Air Max 90')).toBeInTheDocument();
        });

        expect(screen.getByText('150 €')).toBeInTheDocument();
        expect(screen.getByText(/Sneakers en excellent état/i)).toBeInTheDocument();
        expect(screen.getByText('Jean Dupont')).toBeInTheDocument();
        expect(screen.getByText(/Publié le/i)).toBeInTheDocument();
    });

    it('affiche un message d\'erreur si l\'article n\'existe pas', async () => {
        global.fetch.mockResolvedValueOnce({
            ok: false,
            status: 404
        });

        renderWithRouter('999');

        await waitFor(() => {
            expect(screen.getByText('Oups !')).toBeInTheDocument();
            expect(screen.getByText('Article non trouvé')).toBeInTheDocument();
        });

        expect(screen.getByText('Retour à l\'accueil')).toBeInTheDocument();
    });

    it('affiche un message d\'erreur générique en cas d\'erreur serveur', async () => {
        global.fetch.mockResolvedValueOnce({
            ok: false,
            status: 500
        });

        renderWithRouter();

        await waitFor(() => {
            expect(screen.getByText('Erreur lors du chargement')).toBeInTheDocument();
        });
    });

    it('permet de revenir en arrière', async () => {
        global.fetch.mockResolvedValueOnce({
            ok: true,
            json: async () => mockArticle
        });

        const user = userEvent.setup();
        renderWithRouter();

        await waitFor(() => {
            expect(screen.getByText('Nike Air Max 90')).toBeInTheDocument();
        });

        const backBtn = screen.getByText('Retour');
        await user.click(backBtn);

        expect(mockNavigate).toHaveBeenCalledWith(-1);
    });

    it('gère l\'article sans description', async () => {
        const articleWithoutDescription = { ...mockArticle, description: null };
        global.fetch.mockResolvedValueOnce({
            ok: true,
            json: async () => articleWithoutDescription
        });

        renderWithRouter();

        await waitFor(() => {
            expect(screen.getByText('Nike Air Max 90')).toBeInTheDocument();
        });

        expect(screen.queryByText('Description')).not.toBeInTheDocument();
    });
});
