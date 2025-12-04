import React from 'react';
import ReactDOM from 'react-dom/client';
import App from './App';
import KeycloakProvider from './KeycloakProvider.jsx';
import './index.css';

ReactDOM.createRoot(document.getElementById('root')).render(
    <KeycloakProvider>
        <React.StrictMode>
            <App />
        </React.StrictMode>
    </KeycloakProvider>
);

