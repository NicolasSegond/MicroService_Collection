import React, { createContext, useContext, useState, useEffect } from 'react';
import Keycloak from 'keycloak-js';

const KeycloakContext = createContext();

export const useKeycloak = () => {
    const context = useContext(KeycloakContext);
    if (!context) throw new Error('useKeycloak must be used within KeycloakProvider');
    return context;
};

export const KeycloakProvider = ({ children }) => {
    const [keycloak, setKeycloak] = useState(null);
    const [initialized, setInitialized] = useState(false);
    const [authenticated, setAuthenticated] = useState(false);

    useEffect(() => {
        const kc = new Keycloak({
            url: 'http://localhost:8080',
            realm: 'collector_realms',
            clientId: 'collector_front'
        });

        kc.init({
            onLoad: 'check-sso',
            pkceMethod: 'S256',
            checkLoginIframe: false
        }).then((auth) => {
            setKeycloak(kc);
            setAuthenticated(auth);
            setInitialized(true);

            if (auth) {
                const interval = setInterval(() => {
                    kc.updateToken(70).then((refreshed) => {
                        if (refreshed) console.log('Token refreshed');
                    }).catch(() => console.error('Failed to refresh token'));
                }, 60000);

                return () => clearInterval(interval);
            }
        }).catch((err) => {
            console.error('Keycloak init error:', err);
            setInitialized(true);
        });
    }, []);

    const login = () => keycloak?.login();
    const logout = () => keycloak?.logout();

    return (
        <KeycloakContext.Provider value={{ keycloak, initialized, authenticated, login, logout }}>
            {children}
        </KeycloakContext.Provider>
    );
};
