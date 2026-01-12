import { useState, useEffect } from 'react';
import { Sparkles, ChevronLeft, ChevronRight } from 'lucide-react';
import './HeroBanner.css';

export default function HeroBanner({ featuredArticles = [] }) {
    const [currentSlide, setCurrentSlide] = useState(0);

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

    const nextSlide = () => {
        setCurrentSlide((prev) =>
            prev === featuredArticles.length - 1 ? 0 : prev + 1
        );
    };

    const prevSlide = () => {
        setCurrentSlide((prev) =>
            prev === 0 ? featuredArticles.length - 1 : prev - 1
        );
    };

    useEffect(() => {
        if (featuredArticles.length <= 1) return;

        const interval = setInterval(() => {
            setCurrentSlide((prev) =>
                prev === featuredArticles.length - 1 ? 0 : prev + 1
            );
        }, 5000);

        return () => clearInterval(interval);
    }, [featuredArticles.length]);

    return (
        <section className="hero">
            <div className="hero-background">
                <div className="hero-shape hero-shape-1" />
                <div className="hero-shape hero-shape-2" />
            </div>

            <div className="hero-container">
                <div className="hero-text">
                    <div className="hero-badge">
                        <Sparkles size={14} />
                        <span>Marketplace communautaire</span>
                    </div>

                    <h1 className="hero-title">
                        Trouvez des<br />
                        <span className="hero-title-accent">Pièces Uniques</span>
                    </h1>

                    <p className="hero-description">
                        Sneakers rares, cartes de collection, montres vintage.
                        Achetez et vendez avec passion.
                    </p>

                    <div className="hero-tags">
                        <span className="hero-tag">Sneakers</span>
                        <span className="hero-tag">Montres</span>
                        <span className="hero-tag">Cartes</span>
                        <span className="hero-tag">Vintage</span>
                    </div>
                </div>

                {featuredArticles.length > 0 && (
                    <div className="hero-slider">
                        <div className="slider-container">
                            <div
                                className="slider-track"
                                style={{ transform: `translateX(-${currentSlide * 100}%)` }}
                            >
                                {featuredArticles.map((article) => (
                                    <div
                                        key={article.id || article['@id']}
                                        className="slider-slide"
                                    >
                                        <img
                                            src={getImageUrl(article.mainPhotoUrl)}
                                            alt={article.title}
                                        />
                                        <div className="slider-overlay">
                                            <span className="slider-price">
                                                {formatPrice(article.price)}
                                            </span>
                                            <span className="slider-title">{article.title}</span>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {featuredArticles.length > 1 && (
                            <>
                                <button
                                    className="slider-btn slider-btn-prev"
                                    onClick={prevSlide}
                                    aria-label="Slide précédent"
                                >
                                    <ChevronLeft size={24} />
                                </button>
                                <button
                                    className="slider-btn slider-btn-next"
                                    onClick={nextSlide}
                                    aria-label="Slide suivant"
                                >
                                    <ChevronRight size={24} />
                                </button>

                                <div className="slider-dots">
                                    {featuredArticles.map((_, index) => (
                                        <button
                                            key={index}
                                            className={`slider-dot ${index === currentSlide ? 'active' : ''}`}
                                            onClick={() => setCurrentSlide(index)}
                                            aria-label={`Aller au slide ${index + 1}`}
                                        />
                                    ))}
                                </div>
                            </>
                        )}
                    </div>
                )}
            </div>
        </section>
    );
}
