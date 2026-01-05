import { render, screen, fireEvent } from '@testing-library/react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { MemoryRouter } from 'react-router-dom';
import Header from '../../../pages/components/Header';
import * as KeycloakContext from '../../../KeycloakProvider';

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

describe('Header', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('affiche le bouton "Se connecter" quand l\'utilisateur est déconnecté', () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: { login: vi.fn() },
            initialized: true,
            authenticated: false
        });

        render(
            <MemoryRouter>
                <Header />
            </MemoryRouter>
        );

        expect(screen.getByText('Se connecter')).toBeInTheDocument();
        expect(screen.queryByText('Vendre')).not.toBeInTheDocument();
    });

    it('affiche le menu utilisateur et le bouton "Vendre" quand connecté', () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {
                authenticated: true,
                tokenParsed: { preferred_username: 'TestUser' },
                logout: vi.fn()
            },
            initialized: true,
            authenticated: true
        });

        render(
            <MemoryRouter>
                <Header />
            </MemoryRouter>
        );

        expect(screen.getByText('Vendre')).toBeInTheDocument();
        expect(screen.getByText('TestUser')).toBeInTheDocument();
    });

    it('ouvre le menu dropdown au clic sur l\'avatar', () => {
        const mockLogout = vi.fn();
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {
                authenticated: true,
                tokenParsed: { preferred_username: 'User' },
                logout: mockLogout
            },
            initialized: true,
            authenticated: true
        });

        render(<MemoryRouter><Header /></MemoryRouter>);

        const userBtn = screen.getByLabelText('User menu');
        fireEvent.click(userBtn);

        expect(screen.getByText('Mon profil')).toBeInTheDocument();
        expect(screen.getByText('Déconnexion')).toBeInTheDocument();

        fireEvent.click(screen.getByText('Déconnexion'));
        expect(mockLogout).toHaveBeenCalled();
    });

    it('gère le menu mobile', () => {
        vi.spyOn(KeycloakContext, 'useKeycloak').mockReturnValue({
            keycloak: {}, initialized: true, authenticated: false
        });

        render(<MemoryRouter><Header /></MemoryRouter>);

        const mobileBtn = screen.getByLabelText('Toggle mobile menu');
        fireEvent.click(mobileBtn);

        expect(document.querySelector('.mobile-menu')).toBeInTheDocument();
        expect(screen.getAllByText('Accueil').length).toBeGreaterThan(1);
    });
});