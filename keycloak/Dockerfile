# Keycloak avec le provider Kafka Event Listener
FROM quay.io/keycloak/keycloak:23.0

# Copier le provider Kafka
COPY providers/ /opt/keycloak/providers/

# Copier le realm pour l'import
COPY realm-export.json /opt/keycloak/data/import/realm-export.json
