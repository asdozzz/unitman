package redisstore

import (
	"context"
	"errors"
	"time"

	"github.com/redis/go-redis/v9"
)

// Options are the Redis connection settings taken from the environment.
type Options struct {
	Addr     string
	Username string
	Password string
	DB       int
}

// Store implements progress.KV on top of go-redis.
type Store struct {
	client *redis.Client
}

// New creates a Redis-backed KV store. The connection is lazy; call Ping to
// fail fast during startup if desired.
func New(options Options) *Store {
	return &Store{
		client: redis.NewClient(&redis.Options{
			Addr:     options.Addr,
			Username: options.Username,
			Password: options.Password,
			DB:       options.DB,
		}),
	}
}

// Ping checks the Redis connection.
func (s *Store) Ping(ctx context.Context) error {
	return s.client.Ping(ctx).Err()
}

// Get returns the raw value and whether the key exists. A missing key is not
// an error, matching RoadRunner KV's Get contract used by the PHP side.
func (s *Store) Get(ctx context.Context, key string) (string, bool, error) {
	value, err := s.client.Get(ctx, key).Result()
	if err != nil {
		if errors.Is(err, redis.Nil) {
			return "", false, nil
		}

		return "", false, err
	}

	return value, true, nil
}

// Set stores value with the given TTL.
func (s *Store) Set(ctx context.Context, key, value string, ttl time.Duration) error {
	return s.client.Set(ctx, key, value, ttl).Err()
}

// Exists reports whether the key is present.
func (s *Store) Exists(ctx context.Context, key string) (bool, error) {
	found, err := s.client.Exists(ctx, key).Result()
	if err != nil {
		return false, err
	}

	return found > 0, nil
}

// Close releases the connection pool.
func (s *Store) Close() error {
	return s.client.Close()
}
