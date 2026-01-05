import React, { useState, useEffect } from 'react';
import { useNavigate } from 'react-router-dom';
import { useKeycloak } from '../KeycloakProvider.jsx';
import { Loader, Type, DollarSign, FileText, Camera } from 'lucide-react';

import ImageDropzone from './components/ui/ImageDropzone';
import FormInput from './components/ui/FormInput';
import FormTextarea from './components/ui/FormTextarea';
import './CreateArticlePage.css';

const CreateArticlePage = () => {
    const { keycloak, initialized } = useKeycloak();
    const navigate = useNavigate();
    const [loading, setLoading] = useState(false);
    const [error, setError] = useState('');

    const [formData, setFormData] = useState({
        title: '',
        description: '',
        price: ''
    });
    const [imageFile, setImageFile] = useState(null);
    const [imagePreview, setImagePreview] = useState(null);

    useEffect(() => {
        if (initialized && !keycloak?.authenticated) {
            navigate('/');
        }
    }, [initialized, keycloak, navigate]);

    if (!initialized) return <div className="page-loader">Chargement...</div>;
    if (!keycloak?.authenticated) return null;

    const handleInputChange = (e) => {
        const { name, value } = e.target;
        setFormData(prev => ({ ...prev, [name]: value }));
    };

    const handleImageSelect = (e) => {
        const file = e.target.files[0];
        if (file) {
            setImageFile(file);
            const reader = new FileReader();
            reader.onloadend = () => setImagePreview(reader.result);
            reader.readAsDataURL(file);
        }
    };

    const handleRemoveImage = () => {
        setImageFile(null);
        setImagePreview(null);
    };

    const handleSubmit = async (e) => {
        e.preventDefault();
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

            navigate('/');

        } catch (err) {
            console.error(err);
            setError(err.message || "Une erreur est survenue");
        } finally {
            setLoading(false);
        }
    };

    return (
        <div className="create-page-wrapper">
            <div className="create-header">
                <h1>Vendre un article</h1>
                <p>Transformez vos objets de collection en argent.</p>
            </div>

            <div className="create-card">
                <form onSubmit={handleSubmit} className="create-form-layout">

                    {/* Colonne Gauche : Image */}
                    <div className="form-column left-column">
                        <ImageDropzone
                            label="Photo principale"
                            icon={Camera}
                            placeholder="Glissez une photo ici"
                            subtext="JPG, PNG • Max 5 Mo"
                            imagePreview={imagePreview}
                            onFileSelect={handleImageSelect}
                            onRemove={handleRemoveImage}
                        />
                    </div>

                    {/* Colonne Droite : Formulaire */}
                    <div className="form-column right-column">
                        {error && <div className="error-banner">{error}</div>}

                        <FormInput
                            label="Titre de l'annonce"
                            icon={Type}
                            type="text"
                            name="title"
                            placeholder="Ex: Jordan 1 Retro High Chicago"
                            value={formData.title}
                            onChange={handleInputChange}
                            required
                            disabled={loading}
                        />

                        <FormInput
                            label="Prix (€)"
                            icon={DollarSign}
                            type="number"
                            name="price"
                            placeholder="0.00"
                            value={formData.price}
                            onChange={handleInputChange}
                            required
                            min="0"
                            step="0.01"
                            disabled={loading}
                        />

                        <FormTextarea
                            label="Description"
                            icon={FileText}
                            name="description"
                            rows="6"
                            placeholder="Détails sur l'état, l'année, la boîte d'origine..."
                            value={formData.description}
                            onChange={handleInputChange}
                            disabled={loading}
                        />

                        <div className="form-actions">
                            <button type="button" className="btn-cancel" onClick={() => navigate('/')}>
                                Annuler
                            </button>
                            <button type="submit" className="btn-submit" disabled={loading}>
                                {loading ? (
                                    <span style={{display: 'flex', alignItems: 'center', gap: '8px'}}>
                                        <Loader className="spin" size={20}/> Publication...
                                    </span>
                                ) : "Publier l'annonce"}
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    );
};

export default CreateArticlePage;