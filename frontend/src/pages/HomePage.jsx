import React, {useEffect, useState} from "react";
import {useKeycloak} from "../KeycloakProvider.jsx";

const HomePage = () => {
    const {keycloak, initialized, authenticated, login} = useKeycloak();
    const [message, setMessage] = useState(null);

    useEffect(() => {
        if (!initialized) return;
        if (!authenticated) {
            login();
            return;
        }

        const token = keycloak?.token;
        if (!token) return;

        fetch(import.meta.env.VITE_API_URL + "/api/test/hello-world", {
            headers: {
                Authorization: `Bearer ${token}`,
            },
        })
            .then((res) => res.text())
            .then((data) => setMessage(data))
            .catch((error) => {
                console.error("Erreur lors de la récupération du message:", error);
                setMessage("Erreur lors de la récupération du message.");
            });
    }, [initialized, authenticated, keycloak, login]);

    return (
        <div
            className="marketplace"
            style={{
                padding: "4rem",
                textAlign: "center",
                display: "flex",
                flexDirection: "column",
                justifyContent: "center",
                alignItems: "center",
                height: "80vh"
            }}
        >
            {message ? (
                <h1 style={{fontSize: "3rem", fontWeight: "700"}}>{message}</h1>
            ) : (
                <p style={{fontSize: "1.5rem"}}>Chargement du message...</p>
            )}
        </div>
    );
};

export default HomePage;
