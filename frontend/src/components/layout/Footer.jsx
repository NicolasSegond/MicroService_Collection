import { Link } from 'react-router-dom';
import './Footer.css';

export default function Footer() {
    return (
        <footer className="footer">
            <div className="container">
                <div className="footer-content">
                    <p className="footer-copyright">
                        © 2024 Collector
                    </p>
                    <nav className="footer-links">
                        <Link to="/about">À propos</Link>
                        <Link to="/terms">CGU</Link>
                        <Link to="/privacy">Confidentialité</Link>
                    </nav>
                </div>
            </div>
        </footer>
    );
}
