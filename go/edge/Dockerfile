# syntax=docker/dockerfile:1

FROM golang:1.22-alpine AS build

WORKDIR /src

COPY go.mod go.sum ./
RUN go mod download

COPY . .

RUN CGO_ENABLED=0 GOOS=linux go build \
    -trimpath \
    -ldflags="-s -w" \
    -o /out/edge \
    ./cmd/edge

FROM alpine:3.20

RUN addgroup -S edge \
    && adduser -S -G edge -H edge \
    && apk add --no-cache ca-certificates wget

COPY --from=build /out/edge /usr/local/bin/edge

USER edge

# 8081 progress, 8082 media, 8083 telemetry, 8084 webhook.
EXPOSE 8081 8082 8083 8084

ENV EDGE_LISTEN_ADDR=0.0.0.0:8081

# Each subcommand serves /healthz on its own port, so any of the four passing
# means the container is alive.
HEALTHCHECK --interval=30s --timeout=3s --start-period=5s --retries=3 \
    CMD wget -q -O /dev/null http://127.0.0.1:8081/healthz \
    || wget -q -O /dev/null http://127.0.0.1:8082/healthz \
    || wget -q -O /dev/null http://127.0.0.1:8083/healthz \
    || wget -q -O /dev/null http://127.0.0.1:8084/healthz \
    || exit 1

ENTRYPOINT ["/usr/local/bin/edge"]
CMD ["progress"]
