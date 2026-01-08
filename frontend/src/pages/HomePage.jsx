import { useEffect, useState } from "react";
import { useKeycloak } from "../KeycloakProvider.jsx";
import HeroBanner from "../components/home/HeroBanner";
import ArticleGrid from "../components/home/ArticleGrid";
import "./HomePage.css";

const HomePage = () => {
    const { initialized } = useKeycloak();
    const [featuredArticles, setFeaturedArticles] = useState([]);
    const [articles, setArticles] = useState([]);
    const [loading, setLoading] = useState(true);
    const [page, setPage] = useState(1);
    const [searchTerm, setSearchTerm] = useState("");
    const [hasNextPage, setHasNextPage] = useState(false);

    useEffect(() => {
        if (!initialized) return;

        const fetchFeatured = async () => {
            try {
                const response = await fetch(`${import.meta.env.VITE_API_URL}/api/articles?page=1`, {
                    headers: { "Accept": "application/ld+json" },
                });
                const data = await response.json();
                const allItems = data['member'] || [];
                setFeaturedArticles(allItems.slice(0, 3));
            } catch (err) {
                console.error("Erreur chargement featured:", err);
            }
        };
        fetchFeatured();
    }, [initialized]);

    useEffect(() => {
        if (!initialized) return;

        const fetchArticles = async () => {
            setLoading(true);
            try {
                let url = `${import.meta.env.VITE_API_URL}/api/articles?page=${page}`;
                if (searchTerm) {
                    url += `&title=${encodeURIComponent(searchTerm)}`;
                }

                const response = await fetch(url, {
                    headers: { "Accept": "application/ld+json" },
                });
                const data = await response.json();

                setArticles(data['member'] || []);
                setHasNextPage(!!(data['view'] && data['view']['next']));
            } catch (err) {
                console.error("Erreur chargement articles:", err);
            } finally {
                setLoading(false);
            }
        };

        const timer = setTimeout(() => fetchArticles(), 300);
        return () => clearTimeout(timer);

    }, [page, searchTerm, initialized]);

    const handleSearchChange = (value) => {
        setSearchTerm(value);
        setPage(1);
    };

    const handlePageChange = (newPage) => {
        setPage(newPage);
    };

    if (!initialized) {
        return <div className="page-loading">Chargement...</div>;
    }

    return (
        <div className="home-page">
            <HeroBanner featuredArticles={featuredArticles} />

            <ArticleGrid
                title="Découvrez nos articles"
                subtitle="Les dernières trouvailles de notre communauté"
                articles={articles}
                loading={loading}
                page={page}
                hasNextPage={hasNextPage}
                onPageChange={handlePageChange}
                searchValue={searchTerm}
                onSearchChange={handleSearchChange}
            />
        </div>
    );
};

export default HomePage;
