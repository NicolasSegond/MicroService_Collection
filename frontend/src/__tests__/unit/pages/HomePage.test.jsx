import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import HomePage from '../../../pages/HomePage';
import * as KeycloakContext from '../../../KeycloakProvider';
vi.mock('../../../KeycloakProvider', () => ({
    useKeycloak: vi.fn()
}));

global.fetch = vi.fn();

const mockArticles = [
    {
        id: 1,
        title: 'Article 1',
        price: 100,
        mainPhotoUrl: '/img1.jpg',
        owner: { fullName: 'Bob' }
    },
    {
        id: 2,
        title: 'Article 2',
        price: 200,
        mainPhotoUrl: '/img2.jpg',
        owner: { fullName: 'Alice' }
    }
];

describe('HomePage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
        // Default mock keycloak
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {}, initialized: true, authenticated: false
        });
    });

    it('affiche le loader tant que non initialisé', () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({ initialized: false });
        render(<HomePage />);
        expect(screen.getByText('Chargement...')).toBeInTheDocument();
    });

    it('charge et affiche les articles', async () => {
        global.fetch
            .mockResolvedValueOnce({
                json: async () => ({ member: mockArticles }) // Featured
            })
            .mockResolvedValueOnce({
                json: async () => ({ member: mockArticles, view: { next: '/api?page=2' } }) // Grid
            });

        render(<MemoryRouter><HomePage /></MemoryRouter>);

        await waitFor(() => {
            expect(screen.getByText('Article 1')).toBeInTheDocument();
            expect(screen.getByText('Article 2')).toBeInTheDocument();
        });

        expect(screen.getAllByText('Article 1').length).toBeGreaterThanOrEqual(1);
    });

    it('gère la recherche', async () => {
        global.fetch.mockResolvedValue({
            json: async () => ({ member: [] })
        });

        render(<MemoryRouter><HomePage /></MemoryRouter>);

        const searchInput = screen.getByPlaceholderText(/Rechercher/i);
        fireEvent.change(searchInput, { target: { value: 'Rolex' } });

        await waitFor(() => {
            expect(global.fetch).toHaveBeenCalledWith(
                expect.stringContaining('title=Rolex'),
                expect.anything()
            );
        }, { timeout: 1000 });
    });

    it('affiche un message si aucun résultat', async () => {
        global.fetch.mockResolvedValue({
            json: async () => ({ member: [] })
        });

        render(<MemoryRouter><HomePage /></MemoryRouter>);

        await waitFor(() => {
            expect(screen.getByText(/Aucun résultat trouvé/i)).toBeInTheDocument();
        });
    });

    it('gère la pagination', async () => {
        global.fetch
            .mockResolvedValueOnce({ json: async () => ({ member: [] }) })
            .mockResolvedValueOnce({
                json: async () => ({ member: mockArticles, view: { next: 'exists' } })
            })
            .mockResolvedValueOnce({
                json: async () => ({ member: [] })
            });

        render(<MemoryRouter><HomePage /></MemoryRouter>);

        await waitFor(() => expect(screen.getByText('Page 1')).toBeInTheDocument());

        const nextBtn = screen.getByText(/Suivant/i);
        expect(nextBtn).not.toBeDisabled();

        fireEvent.click(nextBtn);

        await waitFor(() => {
            expect(screen.getByText('Page 2')).toBeInTheDocument();
        });
    });
});