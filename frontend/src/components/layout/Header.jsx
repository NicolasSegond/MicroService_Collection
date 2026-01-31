import { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useKeycloak } from '../../KeycloakProvider.jsx';
import { Store, User, Menu, X, LogOut, Settings, Plus, Search } from 'lucide-react';
import './Header.css';

const Header = () => {
    const { keycloak, initialized } = useKeycloak();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const navigate = useNavigate();

    const userProfilePic = keycloak?.tokenParsed?.picture ||
        (keycloak?.tokenParsed?.preferred_username
            ? `https://ui-avatars.com/api/?name=${keycloak.tokenParsed.preferred_username}&background=E07A5F&color=fff&size=200`
            : 'https://ui-avatars.com/api/?name=User&background=E07A5F&color=fff&size=200');

    const handleLogin = () => {
        keycloak.login();
    };

    const handleLogout = () => {
        // Logout silencieux via iframe (évite la redirection vers Keycloak)
        const logoutUrl = `${keycloak.authServerUrl}/realms/${keycloak.realm}/protocol/openid-connect/logout?id_token_hint=${keycloak.idToken}`;

        const iframe = document.createElement('iframe');
        iframe.style.display = 'none';
        iframe.src = logoutUrl;
        document.body.appendChild(iframe);

        // Nettoyer le token local et rediriger après un court délai
        setTimeout(() => {
            document.body.removeChild(iframe);
            keycloak.clearToken();
            navigate('/');
            window.location.reload();
        }, 500);

        setUserMenuOpen(false);
    };

    const handleProfile = () => {
        navigate('/profile');
        setUserMenuOpen(false);
    };

    const isLoggedIn = initialized && keycloak?.authenticated;

    return (
        <header className="header">
            <div className="header-container">
                <div className="header-left">
                    <button
                        className="mobile-menu-btn"
                        onClick={() => setMobileMenuOpen(!mobileMenuOpen)}
                        aria-label="Toggle mobile menu"
                    >
                        {mobileMenuOpen ? <X size={24} /> : <Menu size={24} />}
                    </button>
                    <Link to="/" className="logo">
                        <div className="logo-icon">
                            <Store size={24} />
                        </div>
                        <span className="logo-text">Collector</span>
                    </Link>
                </div>

                <nav className="desktop-nav">
                    <Link to="/">Accueil</Link>
                    <Link to="/categories">Explorer</Link>
                    <Link to="/about">À propos</Link>
                </nav>

                <div className="header-right">
                    {isLoggedIn && (
                        <Link to="/sell" className="btn-sell">
                            <Plus size={18} />
                            <span>Vendre</span>
                        </Link>
                    )}

                    {isLoggedIn ? (
                        <div className="user-menu-container">
                            <button
                                className="user-profile-btn"
                                onClick={() => setUserMenuOpen(!userMenuOpen)}
                                aria-label="User menu"
                            >
                                <img
                                    src={userProfilePic}
                                    alt="Profile"
                                    className="user-avatar"
                                />
                                <span className="username">
                                    {keycloak?.tokenParsed?.preferred_username || 'Utilisateur'}
                                </span>
                            </button>

                            {userMenuOpen && (
                                <div className="user-dropdown">
                                    <button onClick={handleProfile}>
                                        <Settings size={18} />
                                        <span>Mon profil</span>
                                    </button>
                                    <button onClick={handleLogout}>
                                        <LogOut size={18} />
                                        <span>Déconnexion</span>
                                    </button>
                                </div>
                            )}
                        </div>
                    ) : (
                        <button className="login-btn" onClick={handleLogin}>
                            <User size={18} />
                            <span>Connexion</span>
                        </button>
                    )}
                </div>
            </div>

            {mobileMenuOpen && (
                <div className="mobile-menu" role="navigation" aria-label="Menu mobile">
                    <nav>
                        <Link to="/" onClick={() => setMobileMenuOpen(false)}>Accueil</Link>
                        <Link to="/categories" onClick={() => setMobileMenuOpen(false)}>Explorer</Link>
                        {isLoggedIn && (
                            <Link to="/sell" onClick={() => setMobileMenuOpen(false)} className="mobile-sell-link">
                                <Plus size={18} />
                                Vendre un article
                            </Link>
                        )}
                        <Link to="/about" onClick={() => setMobileMenuOpen(false)}>À propos</Link>
                    </nav>
                </div>
            )}
        </header>
    );
};

export default Header;
