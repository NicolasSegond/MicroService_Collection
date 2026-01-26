import { useState, useEffect } from 'react';
import { useParams, useNavigate, Link } from 'react-router-dom';
import { useKeycloak } from '../KeycloakProvider.jsx';
import {
    Loader2,
    ArrowLeft,
    Share2,
    MapPin,
    Calendar,
    MessageCircle
} from 'lucide-react';
import './ArticleDetailPage.css';

const ArticleDetailPage = () => {
    const { productId } = useParams();
    const navigate = useNavigate();
    const { keycloak, initialized } = useKeycloak();

    const [article, setArticle] = useState(null);
    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const getImageUrl = (url) => {
        if (!url) return 'https://placehold.co/600x400?text=No+Image';
        if (url.startsWith('http')) return url;
        return `${import.meta.env.VITE_API_URL}${url}`;
    };

    const formatPrice = (price) => {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            minimumFractionDigits: 0,
            maximumFractionDigits: 0,
        }).format(price);
    };

    const formatDate = (dateString) => {
        if (!dateString) return 'Date inconnue';
        return new Intl.DateTimeFormat('fr-FR', {
            day: 'numeric',
            month: 'long',
            year: 'numeric'
        }).format(new Date(dateString));
    };

    useEffect(() => {
        if (!initialized) return;

        const fetchArticle = async () => {
            setLoading(true);
            setError(null);

            try {
                const response = await fetch(
                    `${import.meta.env.VITE_API_URL}/api/articles/${productId}`,
                    {
                        headers: { 'Accept': 'application/ld+json' }
                    }
                );

                if (!response.ok) {
                    if (response.status === 404) {
                        throw new Error('Article non trouvé');
                    }
                    throw new Error('Erreur lors du chargement');
                }

                const data = await response.json();
                setArticle(data);
            } catch (err) {
                console.error('Erreur:', err);
                setError(err.message);
            } finally {
                setLoading(false);
            }
        };

        fetchArticle();
    }, [productId, initialized]);

    const handleShare = async () => {
        if (navigator.share) {
            try {
                await navigator.share({
                    title: article?.title,
                    text: `Découvrez ${article?.title} sur Collector`,
                    url: window.location.href
                });
            } catch (err) {
                console.error('Erreur de partage:', err);
            }
        } else {
            navigator.clipboard.writeText(window.location.href);
        }
    };

    const handleContact = () => {
        if (!keycloak?.authenticated) {
            keycloak?.login();
            return;
        }
        // TODO: Implement messaging
        alert('Fonctionnalité de messagerie à venir');
    };

    if (!initialized || loading) {
        return (
            <div className="detail-loading">
                <Loader2 size={32} className="spinner" />
                <p>Chargement de l'article...</p>
            </div>
        );
    }

    if (error) {
        return (
            <div className="detail-error">
                <h2>Oups !</h2>
                <p>{error}</p>
                <button className="btn-primary" onClick={() => navigate('/')}>
                    Retour à l'accueil
                </button>
            </div>
        );
    }

    if (!article) return null;

    const sellerName = article.owner?.fullName || 'Vendeur';
    const sellerInitial = sellerName.charAt(0).toUpperCase();

    return (
        <div className="detail-page">
            <div className="detail-container">
                <button className="back-btn" onClick={() => navigate(-1)}>
                    <ArrowLeft size={20} />
                    Retour
                </button>

                <div className="detail-content">
                    <div className="detail-image-section">
                        <div className="detail-image">
                            <img
                                src={getImageUrl(article.mainPhotoUrl)}
                                alt={article.title}
                            />
                        </div>
                        <div className="detail-image-actions">
                            <button className="action-btn" onClick={handleShare}>
                                <Share2 size={20} />
                                Partager
                            </button>
                        </div>
                    </div>

                    <div className="detail-info-section">
                        <div className="detail-header">
                            <h1 className="detail-title">{article.title}</h1>
                            <p className="detail-price">{formatPrice(article.price)}</p>
                            {article.shippingCost > 0 && (
                                <p style={{ fontSize: '0.9rem', color: '#666', marginTop: '-10px' }}>
                                    + {formatPrice(article.shippingCost)} de frais de port
                                </p>
                            )}
                        </div>

                        {article.description && (
                            <div className="detail-description">
                                <h2>Description</h2>
                                <p>{article.description}</p>
                            </div>
                        )}

                        <div className="detail-meta">
                            {article.createdAt && (
                                <div className="meta-item">
                                    <Calendar size={16} />
                                    <span>Publié le {formatDate(article.createdAt)}</span>
                                </div>
                            )}
                            {article.location && (
                                <div className="meta-item">
                                    <MapPin size={16} />
                                    <span>{article.location}</span>
                                </div>
                            )}
                        </div>

                        <div className="seller-card">
                            <div className="seller-card-header">
                                <div className="seller-avatar-lg">
                                    {sellerInitial}
                                </div>
                                <div className="seller-info">
                                    <h3>{sellerName}</h3>
                                </div>
                            </div>

                            <button className="btn-contact" onClick={handleContact}>
                                <MessageCircle size={20} />
                                Contacter le vendeur
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    );
};

export default ArticleDetailPage;
