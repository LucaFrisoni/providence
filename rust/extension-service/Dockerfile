# Servicio Rust único de la extensión
FROM rust:1.98-slim-bookworm AS build
WORKDIR /src
COPY Cargo.toml Cargo.lock ./
COPY src ./src
RUN cargo build --release --locked

FROM debian:bookworm-slim
COPY --from=build /src/target/release/extension-service /usr/local/bin/extension-service
ENV EXTENSION_SERVICE_ADDR=0.0.0.0:8000
EXPOSE 8000
USER nobody
CMD ["extension-service"]
