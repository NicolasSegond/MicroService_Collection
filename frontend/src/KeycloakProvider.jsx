import React, { createContext, useContext, useState, useEffect, useCallback } from 'react';
import Keycloak from 'keycloak-js';

const KeycloakContext = createContext();

// eslint-disable-next-line react-refresh/only-export-components
export const useKeycloak = () => {
    const context = useContext(KeycloakContext);
    /* v8 ignore next */
    if (!context) throw new Error('useKeycloak must be used within KeycloakProvider');
    return context;
};

export const KeycloakProvider = ({ children }) => {
    const [keycloak, setKeycloak] = useState(null);
    const [initialized, setInitialized] = useState(false);
    const [authenticated, setAuthenticated] = useState(false);

    useEffect(() => {
        const kc = new Keycloak({
            url: import.meta.env.VITE_KEYCLOAK_URL || 'http://localhost:8080',
            realm: import.meta.env.VITE_KEYCLOAK_REALM || 'collector_realms',
            clientId: import.meta.env.VITE_KEYCLOAK_CLIENT_ID || 'collector_front'
        });

        let interval;

        kc.init({
            onLoad: 'check-sso',
            pkceMethod: 'S256',
            checkLoginIframe: false,
        })
            .then((auth) => {
                setKeycloak(kc);
                setAuthenticated(auth);
                setInitialized(true);

                /* v8 ignore start */
                if (auth) {
                    interval = setInterval(() => {
                        kc.updateToken(70)
                            .then((refreshed) => {
                                if (refreshed) console.log('Token refreshed');
                            })
                            .catch(() => console.error('Failed to refresh token'));
                    }, 60000);
                }
                /* v8 ignore stop */
            })
            .catch((err) => {
                /* v8 ignore next 2 */
                console.error('Keycloak init error:', err);
                setInitialized(true);
            });

        /* v8 ignore start */
        // Always return a cleanup function
        return () => {
            if (interval) clearInterval(interval);
        };
        /* v8 ignore stop */
    }, []);

    const login = useCallback(() => {
        return keycloak?.login();
    }, [keycloak]);

    const logout = useCallback(() => {
        return keycloak?.logout();
    }, [keycloak]);

    return (
        <KeycloakContext.Provider
            value={{ keycloak, initialized, authenticated, login, logout }}
        >
            {children}
        </KeycloakContext.Provider>
    );
};

export default KeycloakProvider;

