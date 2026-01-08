import { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useKeycloak } from '../KeycloakProvider.jsx';
import {
    Loader2,
    ImagePlus,
    X,
    Tag,
    Euro,
    FileText,
    Sparkles,
    ArrowLeft,
    CheckCircle2
} from 'lucide-react';
import './CreateArticlePage.css';

const CreateArticlePage = () => {
    const { keycloak, initialized } = useKeycloak();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');
    const [success, setSuccess] = useState(false);

    const [formData, setFormData] = useState({
        title: '',
        description: '',
        price: ''
    });
    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);
    const [dragActive, setDragActive] = useState(false);

    useEffect(() => {
        if (initialized && !keycloak?.authenticated) {
            navigate('/');
        }
    }, [initialized, keycloak, navigate]);

    if (!initialized) {
        return (
            <div className="create-loading">
                <Loader2 size={32} className="spinner" />
                <p>Chargement...</p>
            </div>
        );
    }

    if (!keycloak?.authenticated) return null;

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
        setError('');
    };

    const handleImageSelect = (e) => {
        const file = e.target.files?.[0];
        processFile(file);
    };

    const processFile = (file) => {
        if (file && file.type.startsWith('image/')) {
            setImageFile(file);
            const reader = new FileReader();
            reader.onloadend = () => setImagePreview(reader.result);
            reader.readAsDataURL(file);
        }
    };

    const handleDrag = (e) => {
        e.preventDefault();
        e.stopPropagation();
        if (e.type === 'dragenter' || e.type === 'dragover') {
            setDragActive(true);
        } else if (e.type === 'dragleave') {
            setDragActive(false);
        }
    };

    const handleDrop = (e) => {
        e.preventDefault();
        e.stopPropagation();
        setDragActive(false);
        const file = e.dataTransfer.files?.[0];
        processFile(file);
    };

    const handleRemoveImage = () => {
        setImageFile(null);
        setImagePreview(null);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();

        if (!formData.title.trim()) {
            setError('Le titre est requis');
            return;
        }
        if (!formData.price || parseFloat(formData.price) <= 0) {
            setError('Le prix doit être supérieur à 0');
            return;
        }

        setLoading(true);
        setError('');

        try {
            let photoUrl = '';

            if (imageFile) {
                const uploadData = new FormData();
                uploadData.append('file', imageFile);

                const uploadResponse = await fetch(`${import.meta.env.VITE_API_URL}/api/media/upload`, {
                    method: 'POST',
                    headers: { Authorization: `Bearer ${keycloak.token}` },
                    body: uploadData
                });

                if (!uploadResponse.ok) throw new Error("Erreur lors de l'upload de l'image");

                const uploadResult = await uploadResponse.json();
                photoUrl = uploadResult.url;
            }

            const articleData = {
                title: formData.title,
                description: formData.description,
                price: parseFloat(formData.price),
                mainPhotoUrl: photoUrl || 'https://placehold.co/600x400?text=No+Image',
                status: 'PUBLISHED'
            };

            const response = await fetch(`${import.meta.env.VITE_API_URL}/api/articles`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/ld+json',
                    Authorization: `Bearer ${keycloak.token}`,
                },
                body: JSON.stringify(articleData)
            });

            if (!response.ok) throw new Error("Erreur lors de la création de l'article");

            setSuccess(true);
            setTimeout(() => navigate('/'), 1500);

        } catch (err) {
            console.error(err);
            setError(err.message || "Une erreur est survenue");
        } finally {
            setLoading(false);
        }
    };

    if (success) {
        return (
            <div className="create-success">
                <div className="success-content">
                    <div className="success-icon">
                        <CheckCircle2 size={48} />
                    </div>
                    <h2>Article publié !</h2>
                    <p>Votre article est maintenant visible par tous.</p>
                </div>
            </div>
        );
    }

    return (
        <div className="create-page">
            <div className="create-container">
                <button className="back-btn" onClick={() => navigate('/')}>
                    <ArrowLeft size={20} />
                    Retour
                </button>

                <div className="create-header">
                    <div className="create-header-icon">
                        <Sparkles size={24} />
                    </div>
                    <h1>Créer une annonce</h1>
                    <p>Vendez vos trésors à notre communauté de passionnés</p>
                </div>

                <form onSubmit={handleSubmit} className="create-form">
                    {error && (
                        <div className="error-message">
                            <span>{error}</span>
                        </div>
                    )}

                    {/* Image Upload */}
                    <div className="form-section">
                        <label className="form-label">
                            <ImagePlus size={18} />
                            Photo de l'article
                        </label>

                        <div
                            className={`image-dropzone ${dragActive ? 'drag-active' : ''} ${imagePreview ? 'has-image' : ''}`}
                            onDragEnter={handleDrag}
                            onDragLeave={handleDrag}
                            onDragOver={handleDrag}
                            onDrop={handleDrop}
                        >
                            {imagePreview ? (
                                <div className="image-preview">
                                    <img src={imagePreview} alt="Aperçu" />
                                    <button
                                        type="button"
                                        className="remove-image-btn"
                                        onClick={handleRemoveImage}
                                    >
                                        <X size={18} />
                                    </button>
                                </div>
                            ) : (
                                <label className="dropzone-content">
                                    <input
                                        type="file"
                                        accept="image/*"
                                        onChange={handleImageSelect}
                                        hidden
                                    />
                                    <div className="dropzone-icon">
                                        <ImagePlus size={32} />
                                    </div>
                                    <span className="dropzone-text">
                                        Glissez une image ici
                                    </span>
                                    <span className="dropzone-subtext">
                                        ou cliquez pour sélectionner
                                    </span>
                                    <span className="dropzone-hint">
                                        JPG, PNG • Max 5 Mo
                                    </span>
                                </label>
                            )}
                        </div>
                    </div>

                    {/* Title */}
                    <div className="form-section">
                        <label className="form-label" htmlFor="title">
                            <Tag size={18} />
                            Titre de l'annonce
                        </label>
                        <input
                            id="title"
                            type="text"
                            name="title"
                            className="form-input"
                            placeholder="Ex: Jordan 1 Retro High Chicago"
                            value={formData.title}
                            onChange={handleInputChange}
                            disabled={loading}
                            maxLength={100}
                        />
                        <span className="input-hint">{formData.title.length}/100 caractères</span>
                    </div>

                    {/* Price */}
                    <div className="form-section">
                        <label className="form-label" htmlFor="price">
                            <Euro size={18} />
                            Prix
                        </label>
                        <div className="price-input-wrapper">
                            <input
                                id="price"
                                type="number"
                                name="price"
                                className="form-input price-input"
                                placeholder="0"
                                value={formData.price}
                                onChange={handleInputChange}
                                disabled={loading}
                                min="0"
                                step="0.01"
                            />
                            <span className="price-suffix">€</span>
                        </div>
                    </div>

                    {/* Description */}
                    <div className="form-section">
                        <label className="form-label" htmlFor="description">
                            <FileText size={18} />
                            Description
                            <span className="label-optional">(optionnel)</span>
                        </label>
                        <textarea
                            id="description"
                            name="description"
                            className="form-textarea"
                            placeholder="Décrivez l'état, l'année, les accessoires inclus..."
                            value={formData.description}
                            onChange={handleInputChange}
                            disabled={loading}
                            rows={5}
                            maxLength={2000}
                        />
                        <span className="input-hint">{formData.description.length}/2000 caractères</span>
                    </div>

                    {/* Actions */}
                    <div className="form-actions">
                        <button
                            type="button"
                            className="btn-secondary"
                            onClick={() => navigate('/')}
                            disabled={loading}
                        >
                            Annuler
                        </button>
                        <button
                            type="submit"
                            className="btn-primary"
                            disabled={loading}
                        >
                            {loading ? (
                                <>
                                    <Loader2 size={20} className="spinner" />
                                    Publication...
                                </>
                            ) : (
                                <>
                                    <Sparkles size={20} />
                                    Publier l'annonce
                                </>
                            )}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default CreateArticlePage;
