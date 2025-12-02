import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useKeycloak } from '../../KeycloakProvider.jsx';
import { ShoppingBag, User, Menu, X, LogOut, Settings } from 'lucide-react';
import './Header.css';

const Header = () => {
    const { keycloak, initialized } = useKeycloak();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const navigate = useNavigate();

    // Fake profile photo for now (only if logged in)
    const userProfilePic = keycloak?.tokenParsed?.picture ||
        (keycloak?.tokenParsed?.preferred_username
            ? `https://ui-avatars.com/api/?name=${keycloak.tokenParsed.preferred_username}&size=200`
            : 'https://ui-avatars.com/api/?name=User&size=200');

    const handleLogin = () => {
        keycloak.login();
    };

    const handleLogout = () => {
        keycloak.logout();
    };

    const handleProfile = () => {
        // Redirect to your custom profile page
        navigate('/profile');
        setUserMenuOpen(false);
    };

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
                        <ShoppingBag size={32} />
                        <span>Collector</span>
                    </Link>
                </div>

                {/* Menu de navigation desktop */}
                <nav className="desktop-nav">
                    <Link to="/">Accueil</Link>
                    <Link to="/categories">Catégories</Link>
                    <Link to="/featured">Produits vedettes</Link>
                </nav>

                <div className="header-right">
                    {initialized && keycloak?.authenticated ? (
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
                            <User size={20} />
                            <span>Se connecter</span>
                        </button>
                    )}
                </div>
            </div>

            {/* Mobile Menu */}
            {mobileMenuOpen && (
                <div className="mobile-menu">
                    <nav>
                        <Link to="/" onClick={() => setMobileMenuOpen(false)}>
                            Accueil
                        </Link>
                        <Link to="/categories" onClick={() => setMobileMenuOpen(false)}>
                            Catégories
                        </Link>
                        <Link to="/featured" onClick={() => setMobileMenuOpen(false)}>
                            Produits vedettes
                        </Link>
                        <Link to="/about" onClick={() => setMobileMenuOpen(false)}>
                            À propos
                        </Link>
                    </nav>
                </div>
            )}
        </header>
    );
};

export default Header;