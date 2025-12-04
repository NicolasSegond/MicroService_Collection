import React from 'react';
import { createBrowserRouter, RouterProvider } from 'react-router-dom';
import Root from './pages/Root/Root.jsx';
import HomePage from './pages/HomePage.jsx';
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
                    path: '/categories',
                    element: <div className="page-container"><p>Page à propos en construction...</p></div>
                },
                {
                    path: '/categories/:categoryId',
                    element: <div className="page-container"><p>Page à propos en construction...</p></div>
                },
                {
                    path: '/product/:productId',
                    element: <div className="page-container"><p>Page à propos en construction...</p></div>
                },
            ]
        }
    ]);

    return (
        <RouterProvider router={router} />
    );
}

export default App;