import { vi } from 'vitest';

export const createKeycloakMock = () => {
    const MockKeycloak = vi.fn(function(config) {
        this.init = vi.fn().mockReturnValue(Promise.resolve(false));
        this.login = vi.fn().mockReturnValue(Promise.resolve(undefined));
        this.logout = vi.fn().mockReturnValue(Promise.resolve(undefined));
        this.register = vi.fn().mockReturnValue(Promise.resolve(undefined));
        this.accountManagement = vi.fn().mockReturnValue(Promise.resolve(undefined));
        this.updateToken = vi.fn().mockReturnValue(Promise.resolve(false));
        this.clearToken = vi.fn();
        this.hasRealmRole = vi.fn().mockReturnValue(false);
        this.hasResourceRole = vi.fn().mockReturnValue(false);
        this.loadUserProfile = vi.fn().mockReturnValue(Promise.resolve({}));
        this.loadUserInfo = vi.fn().mockReturnValue(Promise.resolve({}));
        this.isTokenExpired = vi.fn().mockReturnValue(false);
        this.createLoginUrl = vi.fn().mockReturnValue('');
        this.createLogoutUrl = vi.fn().mockReturnValue('');
        this.createRegisterUrl = vi.fn().mockReturnValue('');
        this.createAccountUrl = vi.fn().mockReturnValue('');

        this.authenticated = false;
        this.token = null;
        this.tokenParsed = null;
        this.subject = null;
        this.idToken = null;
        this.idTokenParsed = null;
        this.realmAccess = null;
        this.resourceAccess = null;
        this.refreshToken = null;
        this.refreshTokenParsed = null;
        this.timeSkew = 0;
        this.responseMode = null;
        this.responseType = null;
        this.flow = null;
        this.authServerUrl = config?.url || null;
        this.realm = config?.realm || null;
        this.clientId = config?.clientId || null;
        this.clientSecret = null;
    });

    return MockKeycloak;
};