import React, { useEffect, useState } from "react";
import { useKeycloak } from "../KeycloakProvider.jsx";
import { Search, Heart, ChevronLeft, ChevronRight, ShoppingBag, Star } from "lucide-react";
import "./HomePage.css";

const HomePage = () => {
    const { keycloak, initialized, authenticated, login } = useKeycloak();
    const [featuredArticles, setFeaturedArticles] = useState([]);
    const [gridArticles, setGridArticles] = useState([]);
    const [loadingGrid, setLoadingGrid] = useState(true);
    const [page, setPage] = useState(1);
    const [searchTerm, setSearchTerm] = useState("");
    const [hasNextPage, setHasNextPage] = useState(false);
    const [currentSlide, setCurrentSlide] = useState(0);

    const getImageUrl = (url) => {
        if (!url) return 'https://placehold.co/600x400?text=No+Image';

        if (url.startsWith('http')) return url;

        return `${import.meta.env.VITE_API_URL}${url}`;
    };

    // 1. Charger les articles "A la une" (Carousel)
    useEffect(() => {
        if (!initialized) return;

        const fetchFeatured = async () => {
            try {
                // Route publique, pas besoin de token
                const response = await fetch(`${import.meta.env.VITE_API_URL}/api/articles?page=1`, {
                    headers: { "Accept": "application/ld+json" },
                });

                const data = await response.json();
                const allItems = data['member'] || [];

                // On prend juste les 5 premiers pour le slider
                setFeaturedArticles(allItems.slice(0, 5));
            } catch (err) {
                console.error("Erreur chargement slider:", err);
            }
        };
        fetchFeatured();
    }, [initialized, authenticated, keycloak, login]);


    // 2. Charger la grille d'articles (Grid)
    useEffect(() => {
        // CORRECTION : On autorise le chargement même si non connecté
        if (!initialized) return;

        const fetchGrid = async () => {
            setLoadingGrid(true);
            try {
                let url = `${import.meta.env.VITE_API_URL}/api/articles?page=${page}`;
                if (searchTerm) {
                    url += `&title=${encodeURIComponent(searchTerm)}`;
                }

                const response = await fetch(url, {
                    headers: { "Accept": "application/ld+json" },
                });
                const data = await response.json();

                setGridArticles(data['member'] || []);
                setHasNextPage(!!(data['view'] && data['view']['next']));
            } catch (err) {
                console.error("Erreur chargement grille:", err);
            } finally {
                setLoadingGrid(false);
            }
        };

        const timer = setTimeout(() => fetchGrid(), 300);
        return () => clearTimeout(timer);

    }, [page, searchTerm, initialized, authenticated, keycloak]);

    const nextSlide = () => {
        if (featuredArticles.length === 0) return;
        setCurrentSlide(curr => curr === featuredArticles.length - 1 ? 0 : curr + 1);
    };

    const prevSlide = () => {
        if (featuredArticles.length === 0) return;
        setCurrentSlide(curr => curr === 0 ? featuredArticles.length - 1 : curr - 1);
    };

    const handleSearch = (e) => {
        setSearchTerm(e.target.value);
        setPage(1);
    };

    const formatPrice = (price) => {
        return new Intl.NumberFormat('fr-FR', {
            style: 'currency',
            currency: 'EUR',
            maximumFractionDigits: 0
        }).format(price);
    };

    if (!initialized) return <div className="loader-screen">Chargement...</div>;

    return (
        <div className="marketplace-app">

            {/* --- CAROUSEL --- */}
            {featuredArticles.length > 0 ? (
                <div className="carousel-container">
                    <div
                        className="carousel-track"
                        style={{ transform: `translateX(-${currentSlide * 100}%)` }}
                    >
                        {featuredArticles.map((article) => (
                            <div key={article.id} className="carousel-slide">
                                <div
                                    className="slide-bg-blur"
                                    style={{ backgroundImage: `url(${getImageUrl(article.mainPhotoUrl)})` }}
                                ></div>

                                <div className="slide-content-wrapper">
                                    <div className="slide-info">
                                        <span className="badge-featured"><Star size={12} fill="white" /> Coup de cœur</span>
                                        <h1 className="slide-title">{article.title}</h1>
                                        <div className="slide-meta">
                                            <span className="slide-price">{formatPrice(article.price)}</span>
                                            <span className="slide-seller">par {article.owner?.fullName || "Vendeur"}</span>
                                        </div>
                                        <p className="slide-description">
                                            {article.description
                                                ? (article.description.length > 120 ? article.description.substring(0, 120) + "..." : article.description)
                                                : "Aucune description"}
                                        </p>
                                        <button className="btn-primary-lg">
                                            <ShoppingBag size={18} /> Voir l'article
                                        </button>
                                    </div>
                                    <div className="slide-visual">
                                        <img src={getImageUrl(article.mainPhotoUrl)} alt={article.title} />
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>

                    <button className="nav-arrow prev" onClick={prevSlide}><ChevronLeft /></button>
                    <button className="nav-arrow next" onClick={nextSlide}><ChevronRight /></button>

                    <div className="dots-container">
                        {featuredArticles.map((_, idx) => (
                            <button
                                key={idx}
                                className={`dot ${currentSlide === idx ? "active" : ""}`}
                                onClick={() => setCurrentSlide(idx)}
                            />
                        ))}
                    </div>
                </div>
            ) : (
                <div className="carousel-placeholder">
                    <p>Aucun article à la une pour le moment.</p>
                </div>
            )}

            <div className="toolbar-sticky">
                <div className="search-bar">
                    <Search className="icon-search" size={18} />
                    <input
                        type="text"
                        placeholder="Rechercher des sneakers, cartes, montres..."
                        value={searchTerm}
                        onChange={handleSearch}
                    />
                </div>
            </div>

            {/* --- GRILLE --- */}
            <main className="main-grid-container">
                {loadingGrid ? (
                    <div className="grid-loader">Chargement des pépites...</div>
                ) : gridArticles.length > 0 ? (
                    <>
                        <div className="articles-grid">
                            {gridArticles.map((article) => (
                                <div key={article.id} className="article-card">
                                    <div className="card-thumb">
                                        <img src={getImageUrl(article.mainPhotoUrl)} alt={article.title} loading="lazy" />
                                        <button className="btn-fav"><Heart size={16} /></button>
                                        <span className="price-badge-mobile">{formatPrice(article.price)}</span>
                                    </div>
                                    <div className="card-content">
                                        <div className="card-header">
                                            <h3>{article.title}</h3>
                                            <span className="price-desktop">{formatPrice(article.price)}</span>
                                        </div>
                                        <div className="card-footer">
                                            <span className="seller-name">{article.owner?.fullName || "Membre"}</span>
                                            <span className="post-date">Récemment</span>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {/* Pagination */}
                        <div className="pagination-wrapper">
                            <button
                                className="btn-page"
                                onClick={() => setPage(p => p - 1)}
                                disabled={page === 1}
                            >
                                <ChevronLeft size={16} /> Précédent
                            </button>
                            <span className="page-indicator">Page {page}</span>
                            <button
                                className="btn-page"
                                onClick={() => setPage(p => p + 1)}
                                disabled={!hasNextPage}
                            >
                                Suivant <ChevronRight size={16} />
                            </button>
                        </div>
                    </>
                ) : (
                    <div className="empty-state">
                        <p>Aucun résultat trouvé pour "{searchTerm}"</p>
                    </div>
                )}
            </main>
        </div>
    );
};

export default HomePage;