FROM kong:3.6

USER root
ARG LUAROCKS_VERSION=3.12.1

# Build tools + LuaJIT
RUN apt-get update \
 && DEBIAN_FRONTEND=noninteractive apt-get install -y --no-install-recommends \
      git build-essential pkg-config ca-certificates curl unzip \
      libreadline-dev libssl-dev \
      luajit libluajit-5.1-dev \
 && update-ca-certificates

# Install LuaRocks (global)
RUN curl -fsSL https://luarocks.org/releases/luarocks-${LUAROCKS_VERSION}.tar.gz -o /tmp/luarocks.tar.gz \
 && tar -C /tmp -xzf /tmp/luarocks.tar.gz \
 && cd /tmp/luarocks-${LUAROCKS_VERSION} \
 && ./configure \
      --with-lua-include=/usr/include/luajit-2.1 \
      --lua-suffix=jit \
      --lua-version=5.1 \
      --with-lua-interpreter=luajit \
 && make && make install \
 && rm -rf /tmp/luarocks*

# Runtime dependencies
RUN luarocks install lua-resty-openidc \
 && luarocks install lua-resty-session \
 && luarocks install lua-resty-http \
 && luarocks install lua-cjson

# ✅ Installer le plugin kong-oidc depuis GitHub
RUN git clone https://github.com/armeldemarsac92/kong-oidc-keycloak-plugin.git /tmp/kong-oidc \
 && cd /tmp/kong-oidc \
 && luarocks --tree=/usr/local make kong-oidc-*.rockspec \
 && rm -rf /tmp/kong-oidc

# Cleanup
RUN apt-get purge -y git build-essential unzip && apt-get autoremove -y \
 && rm -rf /var/lib/apt/lists/*

# Configuration des plugins et des chemins Lua
ENV KONG_PLUGINS="bundled,oidc"
ENV KONG_LUA_PACKAGE_PATH="/home/kong/.luarocks/share/lua/5.1/?.lua;/home/kong/.luarocks/share/lua/5.1/?/init.lua;/usr/local/share/lua/5.1/?.lua;/usr/local/share/lua/5.1/?/init.lua;/usr/local/openresty/lualib/?.lua;/usr/local/openresty/lualib/?/init.lua;;"
ENV KONG_LUA_PACKAGE_CPATH="/home/kong/.luarocks/lib/lua/5.1/?.so;/usr/local/lib/lua/5.1/?.so;/usr/local/openresty/lualib/?.so;;"

USER kong