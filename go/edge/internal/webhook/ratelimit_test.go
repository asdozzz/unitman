package webhook

import (
	"testing"
	"time"
)

func TestRateLimiterFirstRequestPasses(t *testing.T) {
	limiter := NewRateLimiter(1, nil)

	if !limiter.Allow("project|gitlab") {
		t.Fatal("first request should pass")
	}
}

func TestRateLimiterRefillsOverTime(t *testing.T) {
	now := time.Unix(1_700_000_000, 0)
	limiter := NewRateLimiter(1, func() time.Time { return now })

	if !limiter.Allow("project|gitlab") {
		t.Fatal("first request should pass")
	}

	if limiter.Allow("project|gitlab") {
		t.Fatal("second immediate request should be limited")
	}

	now = now.Add(time.Second)

	if !limiter.Allow("project|gitlab") {
		t.Fatal("request after refill should pass")
	}
}

func TestRateLimiterDisabled(t *testing.T) {
	limiter := NewRateLimiter(0, nil)

	for index := 0; index < 100; index++ {
		if !limiter.Allow("project|gitlab") {
			t.Fatalf("request %d should pass when the limiter is disabled", index)
		}
	}
}

func TestRateLimiterCleansUpIdleBuckets(t *testing.T) {
	now := time.Unix(1_700_000_000, 0)
	limiter := NewRateLimiter(10, func() time.Time { return now })

	if !limiter.Allow("old|gitlab") {
		t.Fatal("first request should pass")
	}

	now = now.Add(defaultBucketIdleTTL + time.Minute)

	if !limiter.Allow("new|gitlab") {
		t.Fatal("request after idle window should pass")
	}

	if got := limiter.size(); got != 1 {
		t.Fatalf("tracked buckets = %d, want 1 (stale bucket must be dropped)", got)
	}
}
