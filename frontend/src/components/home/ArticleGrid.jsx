import { Loader2, Search, ChevronLeft, ChevronRight } from 'lucide-react';
import ArticleCard from './ArticleCard';
import './ArticleGrid.css';

export default function ArticleGrid({
    title,
    subtitle,
    articles,
    loading,
    page,
    hasNextPage,
    onPageChange,
    searchValue,
    onSearchChange,
}) {
    return (
        <section className="articles-section">
            <div className="container">
                <div className="section-header">
                    <div className="section-header-text">
                        <h2>{title}</h2>
                        {subtitle && <p>{subtitle}</p>}
                    </div>
                </div>

                <div className="articles-search">
                    <div className="search-input-wrapper">
                        <Search size={20} className="search-icon" />
                        <input
                            type="text"
                            placeholder="Rechercher un article..."
                            value={searchValue}
                            onChange={(e) => onSearchChange?.(e.target.value)}
                        />
                    </div>
                </div>

                {loading && articles.length === 0 ? (
                    <div className="articles-loading">
                        <Loader2 size={32} className="spinner" />
                        <p>Chargement des articles...</p>
                    </div>
                ) : articles.length === 0 ? (
                    <div className="articles-empty">
                        <p>Aucun article trouvé</p>
                    </div>
                ) : (
                    <>
                        <div className="articles-grid">
                            {articles.map((article) => (
                                <ArticleCard key={article.id || article['@id']} article={article} />
                            ))}
                        </div>

                        <div className="articles-pagination">
                            <button
                                className="pagination-btn"
                                onClick={() => onPageChange?.(page - 1)}
                                disabled={page === 1 || loading}
                            >
                                <ChevronLeft size={18} />
                                Précédent
                            </button>
                            <span className="pagination-info">Page {page}</span>
                            <button
                                className="pagination-btn"
                                onClick={() => onPageChange?.(page + 1)}
                                disabled={!hasNextPage || loading}
                            >
                                Suivant
                                <ChevronRight size={18} />
                            </button>
                        </div>
                    </>
                )}
            </div>
        </section>
    );
}
