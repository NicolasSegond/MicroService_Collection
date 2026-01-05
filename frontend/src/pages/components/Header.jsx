import React, { useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { useKeycloak } from '../../KeycloakProvider.jsx';
import { ShoppingBag, User, Menu, X, LogOut, Settings, PlusCircle } from 'lucide-react';
import './Header.css';

const Header = () => {
    const { keycloak, initialized } = useKeycloak();
    const [mobileMenuOpen, setMobileMenuOpen] = useState(false);
    const [userMenuOpen, setUserMenuOpen] = useState(false);
    const navigate = useNavigate();

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
                        <ShoppingBag size={32} />
                        <span>Collector</span>
                    </Link>
                </div>

                <nav className="desktop-nav">
                    <Link to="/">Accueil</Link>
                    <Link to="/categories">Catégories</Link>
                    <Link to="/featured">Produits vedettes</Link>
                </nav>

                <div className="header-right">
                    {isLoggedIn && (
                        <Link to="/sell" className="btn-sell" style={{
                            display: 'flex',
                            alignItems: 'center',
                            gap: '0.5rem',
                            background: '#000',
                            color: 'white',
                            padding: '0.6rem 1rem',
                            borderRadius: '50px',
                            textDecoration: 'none',
                            fontWeight: '600',
                            fontSize: '0.9rem',
                            marginRight: '1rem',
                            transition: 'transform 0.2s'
                        }}>
                            <PlusCircle size={18} />
                            <span className="desktop-only">Vendre</span>
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
                            <User size={20} />
                            <span>Se connecter</span>
                        </button>
                    )}
                </div>
            </div>

            {mobileMenuOpen && (
                <div className="mobile-menu">
                    <nav>
                        <Link to="/" onClick={() => setMobileMenuOpen(false)}>Accueil</Link>
                        <Link to="/categories" onClick={() => setMobileMenuOpen(false)}>Catégories</Link>
                        {isLoggedIn && (
                            <Link to="/sell" onClick={() => setMobileMenuOpen(false)} style={{fontWeight: 'bold'}}>
                                + Vendre un article
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