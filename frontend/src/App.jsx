import React from 'react';
import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import Root from './pages/Root/Root.jsx';
import HomePage from './pages/HomePage.jsx';
import CreateArticlePage from './pages/CreateArticlePage.jsx';
import ArticleDetailPage from './pages/ArticleDetailPage.jsx';
import './App.css';

function App() {
    const router = createBrowserRouter([
        {
            path: '/',
            element: <Root />,
            children: [
                {
                    path: '/',
                    element: <HomePage />
                },
                {
                    path: '/sell',
                    element: <CreateArticlePage />
                },
                {
                    path: '/product/:productId',
                    element: <ArticleDetailPage />
                },
                {
                    path: '/categories',
                    element: <div className="page-container"><p>Page en construction...</p></div>
                },
                {
                    path: '/categories/:categoryId',
                    element: <div className="page-container"><p>Page en construction...</p></div>
                },
            ]
        }
    ]);

    return (
        <RouterProvider router={router} />
    );
}

export default App;