import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import CreateArticlePage from '../../../pages/CreateArticlePage';
import * as KeycloakContext from '../../../KeycloakProvider';

const mockNavigate = vi.fn();
vi.mock('react-router-dom', async () => {
    const actual = await vi.importActual('react-router-dom');
    return {
        ...actual,
        useNavigate: () => mockNavigate,
    };
});

vi.mock('../../../KeycloakProvider', () => ({
    useKeycloak: vi.fn()
}));

let global = {};

global.fetch = vi.fn();

describe('CreateArticlePage', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('redirige vers l\'accueil si l\'utilisateur n\'est pas connecté', () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { authenticated: false },
            initialized: true
        });

        render(<CreateArticlePage />);
        expect(mockNavigate).toHaveBeenCalledWith('/');
    });

    it('affiche le formulaire si connecté', () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { authenticated: true, token: 'fake-token' },
            initialized: true
        });

        render(
            <MemoryRouter>
                <CreateArticlePage />
            </MemoryRouter>
        );

        expect(screen.getByText('Vendre un article')).toBeInTheDocument();
        expect(screen.getByLabelText(/Titre de l'annonce/i)).toBeInTheDocument();
    });

    it('soumet le formulaire avec succès', async () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { authenticated: true, token: 'fake-token' },
            initialized: true
        });

        global.fetch
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ url: '/uploads/image.jpg' })
            })
            .mockResolvedValueOnce({
                ok: true,
                json: async () => ({ id: 1 })
            });

        render(<MemoryRouter><CreateArticlePage /></MemoryRouter>);

        fireEvent.change(screen.getByLabelText(/Titre/i), { target: { value: 'Nike Air' } });
        fireEvent.change(screen.getByLabelText(/Prix/i), { target: { value: '150' } });
        fireEvent.change(screen.getByLabelText(/Description/i), { target: { value: 'Superbe paire' } });

        const file = new File(['(⌐□_□)'], 'chucknorris.png', { type: 'image/png' });
        const fileInput = document.getElementById('fileInput');
        fireEvent.change(fileInput, { target: { files: [file] } });

        const submitBtn = screen.getByText("Publier l'annonce");
        fireEvent.click(submitBtn);

        expect(submitBtn).toBeDisabled();

        await waitFor(() => {
            expect(global.fetch).toHaveBeenCalledTimes(2);
            expect(mockNavigate).toHaveBeenCalledWith('/');
        });
    });

    it('gère les erreurs de soumission', async () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { authenticated: true, token: 'fake-token' },
            initialized: true
        });

        global.fetch.mockResolvedValueOnce({
            ok: false,
            status: 500
        });

        render(<MemoryRouter><CreateArticlePage /></MemoryRouter>);

        fireEvent.change(screen.getByLabelText(/Titre/i), { target: { value: 'Test' } });
        fireEvent.change(screen.getByLabelText(/Prix/i), { target: { value: '10' } });

        fireEvent.click(screen.getByText("Publier l'annonce"));

        await waitFor(() => {
            expect(screen.getByText(/Erreur lors de la création de l'article/i)).toBeInTheDocument();
        });
    });
});