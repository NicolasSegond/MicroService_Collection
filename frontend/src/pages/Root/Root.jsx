import React from 'react';
import { Outlet } from 'react-router-dom';
import Footer from "../../components/layout/Footer.jsx";
import Header from "../../components/layout/Header.jsx";

const Root = () => {
    return (
        <div className="app-container">
            <Header />
            <main className="main-content">
                <Outlet />
            </main>
            <Footer />
        </div>
    );
};

export default Root;