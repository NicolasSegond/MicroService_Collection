import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, waitFor } from '@testing-library/react';
import { createKeycloakMock } from '../../__mocks__/keycloak-js.js';

vi.mock('keycloak-js', () => ({
    default: createKeycloakMock()
}));

// Importer après avoir déclaré le mock
import { KeycloakProvider } from '../../KeycloakProvider';
import Keycloak from 'keycloak-js';

const TestComponent = () => {
    return <div data-testid="test-component">Test Component</div>;
};

describe('KeycloakProvider', () => {
    beforeEach(() => {
        vi.clearAllMocks();
    });

    it('devrait initialiser Keycloak avec les bonnes configurations', async () => {
        render(
            <KeycloakProvider>
                <TestComponent />
            </KeycloakProvider>
        );

        await waitFor(() => {
            expect(Keycloak).toHaveBeenCalledTimes(1);
        });

        expect(Keycloak).toHaveBeenCalledWith({
            url: import.meta.env.VITE_KEYCLOAK_URL || 'http://localhost:8080',
            realm: import.meta.env.VITE_KEYCLOAK_REALM || 'collector_realms',
            clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID || 'collector_front'
        });

        const keycloakInstance = Keycloak.mock.results[0].value;

        expect(keycloakInstance.init).toHaveBeenCalledWith({
            onLoad: 'check-sso',
            pkceMethod: 'S256',
            checkLoginIframe: false,
        });

        expect(screen.getByTestId('test-component')).toBeInTheDocument();
    });
});