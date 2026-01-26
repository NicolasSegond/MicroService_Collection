import { Link } from 'react-router-dom';
import { Heart } from 'lucide-react';
import './ArticleCard.css';

export default function ArticleCard({ article }) {
    const {
        id,
        title,
        price,
        mainPhotoUrl,
        owner,
    } = article;

    const articleId = id || article['@id']?.split('/').pop() || article['@id'];
    const sellerName = owner?.fullName || 'Vendeur';
    const sellerInitial = sellerName.charAt(0).toUpperCase();

    const getImageUrl = (url) => {
        if (!url) return null;
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

    const handleSaveClick = (e) => {
        e.preventDefault();
        e.stopPropagation();
    };

    const imageUrl = getImageUrl(mainPhotoUrl);

    return (
        <Link to={`/product/${articleId}`} className="article-card-link">
            <article className="article-card">
                <div className="article-card-image">
                    {imageUrl ? (
                        <img src={imageUrl} alt={title} loading="lazy" />
                    ) : (
                        <div className="article-card-placeholder">
                            <span>Photo</span>
                        </div>
                    )}
                    <button
                        className="article-card-save"
                        aria-label="Sauvegarder"
                        onClick={handleSaveClick}
                    >
                        <Heart size={18} />
                    </button>
                </div>

                <div className="article-card-body">
                    <p className="article-card-price">{formatPrice(price)}</p>
                    <h3 className="article-card-title">{title}</h3>

                    <div className="article-card-seller">
                        <div className="seller-avatar">
                            {sellerInitial}
                        </div>
                        <span className="seller-name">{sellerName}</span>
                    </div>
                </div>
            </article>
        </Link>
    );
}
